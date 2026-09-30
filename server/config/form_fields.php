<?php
// config/form_fields.php
// Shared helpers for the per-department custom questions (form_fields):
//  - ensure_form_fields_schema(): idempotent request-time migration
//  - form_field_visibility(): the conditional-visibility rule (mirrored in client/src/lib/fieldVisibility.ts)

const FORM_FIELD_TYPES = ['text', 'textarea', 'dropdown', 'date', 'number'];

/**
 * Idempotent, request-time migration (same pattern as sla.php). Safe to call on every request:
 * once migrated it costs one information_schema lookup. Never throws.
 * Returns true when the conditional-question columns exist (callers fall back to legacy behaviour otherwise).
 */
function ensure_form_fields_schema(PDO $db): bool {
    static $result = null;
    if ($result !== null) return $result;
    try {
        $read = function () use ($db): array {
            $cols = [];
            $stmt = $db->query("SELECT COLUMN_NAME AS c, DATA_TYPE AS t FROM information_schema.COLUMNS
                                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_fields'");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cols[strtolower($row['c'])] = strtolower($row['t']);
            }
            return $cols;
        };
        $needed = ['help_text', 'sort_order', 'show_if_field_id', 'show_if_value'];
        $cols = $read();
        if (!$cols) return $result = false; // table does not exist (nothing to migrate)

        $run = function (string $sql) use ($db) {
            try { $db->exec($sql); }
            catch (PDOException $e) { error_log('form_fields migration: ' . $e->getMessage()); }
        };

        // field_type: ENUM('text','textarea','dropdown') -> VARCHAR(20), keeping existing values.
        if (($cols['field_type'] ?? '') === 'enum') {
            $run("UPDATE form_fields SET field_type = 'text' WHERE field_type IS NULL");
            $run("ALTER TABLE form_fields MODIFY COLUMN field_type VARCHAR(20) NOT NULL DEFAULT 'text'");
        }
        if (!isset($cols['help_text']))        $run("ALTER TABLE form_fields ADD COLUMN help_text TEXT NULL");
        if (!isset($cols['sort_order']))       $run("ALTER TABLE form_fields ADD COLUMN sort_order INT NOT NULL DEFAULT 0");
        if (!isset($cols['show_if_field_id'])) $run("ALTER TABLE form_fields ADD COLUMN show_if_field_id INT NULL");
        if (!isset($cols['show_if_value']))    $run("ALTER TABLE form_fields ADD COLUMN show_if_value VARCHAR(500) NULL");

        // Self-referencing FK so deleting a parent question clears the condition on its children.
        // Best effort: if it cannot be created (engine/type mismatch) fields.php does the same cleanup itself.
        $fk = $db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_fields'
                            AND CONSTRAINT_NAME = 'fk_form_fields_show_if' AND CONSTRAINT_TYPE = 'FOREIGN KEY'")->fetchColumn();
        if ((int)$fk === 0) {
            $cols = $read();
            if (isset($cols['show_if_field_id'])) {
                // Drop orphaned references first, otherwise the ALTER would fail.
                $run("UPDATE form_fields c LEFT JOIN form_fields p ON c.show_if_field_id = p.id
                      SET c.show_if_field_id = NULL, c.show_if_value = NULL
                      WHERE c.show_if_field_id IS NOT NULL AND p.id IS NULL");
                $run("ALTER TABLE form_fields ADD CONSTRAINT fk_form_fields_show_if
                      FOREIGN KEY (show_if_field_id) REFERENCES form_fields(id) ON DELETE SET NULL");
            }
        }

        $cols = $read();
        foreach ($needed as $n) if (!isset($cols[$n])) return $result = false;
        return $result = true;
    } catch (Throwable $e) {
        error_log('form_fields migration failed: ' . $e->getMessage());
        return $result = false;
    }
}

/** Split a stored show_if_value ("Travel Booking|Both") into trimmed, non-empty accepted values. */
function form_field_accepted_values($raw): array {
    if ($raw === null) return [];
    $out = [];
    foreach (explode('|', (string)$raw) as $part) {
        $part = trim($part);
        if ($part !== '') $out[] = $part;
    }
    return $out;
}

/**
 * Visibility rule. A field is VISIBLE iff
 *   show_if_field_id is NULL, OR
 *   (its parent is visible AND the parent's answer is one of the accepted values: show_if_value
 *    split on '|', trimmed, case-sensitive exact match).
 * The answer is trimmed; a missing answer counts as ''. Cycles or a missing parent make the field hidden.
 *
 * @param array $fields  rows with at least id, show_if_field_id, show_if_value
 * @param array $values  answers keyed by field id (string|int|null)
 * @return array<int,bool> field id => visible
 */
function form_field_visibility(array $fields, array $values): array {
    $byId = [];
    foreach ($fields as $f) $byId[(int)$f['id']] = $f;

    $memo = [];
    $resolve = function (int $id, array $path) use (&$resolve, &$memo, $byId, $values): bool {
        if (isset($memo[$id])) return $memo[$id];
        if (isset($path[$id])) return false; // cycle
        $f = $byId[$id];
        $parentId = $f['show_if_field_id'] ?? null;
        if ($parentId === null || $parentId === '' ) {
            return $memo[$id] = true;
        }
        $parentId = (int)$parentId;
        if (!isset($byId[$parentId])) return $memo[$id] = false;
        $path[$id] = true;
        if (!$resolve($parentId, $path)) return $memo[$id] = false;
        $answer = $values[$parentId] ?? $values[(string)$parentId] ?? '';
        $answer = is_scalar($answer) ? trim((string)$answer) : '';
        return $memo[$id] = in_array($answer, form_field_accepted_values($f['show_if_value'] ?? null), true);
    };

    $result = [];
    foreach ($byId as $id => $_) $result[$id] = $resolve($id, []);
    return $result;
}
