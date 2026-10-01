<?php
require_once '../config/cors.php';
setup_cors();
require_once '../config/db.php';
require_once '../config/auth_middleware.php';
require_once '../config/form_fields.php';

$method = $_SERVER['REQUEST_METHOD'];
$database = new Database();
$db = $database->getConnection();
$me = require_auth($db);
if ($method !== 'GET') require_manager($me);

function fields_fail(int $status, string $error): void {
    http_response_code($status);
    echo json_encode(["success" => false, "error" => $error]);
    exit();
}

/** Normalise a DB row for the client (ints as ints; options stays a JSON string). */
function shape_field(array $r): array {
    $r['id'] = (int)$r['id'];
    $r['department_id'] = (int)$r['department_id'];
    $r['is_required'] = (int)$r['is_required'];
    $r['sort_order'] = (int)($r['sort_order'] ?? 0);
    $r['show_if_field_id'] = isset($r['show_if_field_id']) ? (int)$r['show_if_field_id'] : null;
    return $r;
}

/** Options may arrive as an array, a JSON-array string, or a comma separated string. Returns a JSON string or null. */
function normalize_options($raw): ?string {
    if ($raw === null || $raw === '') return null;
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : explode(',', $raw);
    }
    if (!is_array($raw)) fields_fail(400, "options must be an array of strings");
    $clean = [];
    foreach ($raw as $o) {
        if (!is_scalar($o)) fields_fail(400, "options must be an array of strings");
        $o = trim((string)$o);
        if ($o === '') continue;
        if (strpos($o, '|') !== false) {
            fields_fail(400, "Dropdown options cannot contain the '|' character (it separates values in conditions): " . $o);
        }
        $clean[] = $o;
    }
    $clean = array_values(array_unique($clean));
    return $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE) : null;
}

/** Fails with a 400 unless the proposed parent is valid for $selfId (null on create) in $deptId and creates no cycle. */
function validate_parent(PDO $db, int $deptId, ?int $selfId, int $parentId): void {
    if ($selfId !== null && $parentId === $selfId) fields_fail(400, "A question cannot depend on itself");
    $seen = [];
    $cur = $parentId;
    $first = true;
    while ($cur !== null) {
        if (isset($seen[$cur])) fields_fail(400, "That condition would create a loop");
        $seen[$cur] = true;
        $s = $db->prepare("SELECT department_id, show_if_field_id FROM form_fields WHERE id = :id");
        $s->execute([':id' => $cur]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) fields_fail(400, $first ? "The 'show only when' question does not exist" : "Broken condition chain");
        if ($first && (int)$row['department_id'] !== $deptId) {
            fields_fail(400, "The 'show only when' question must belong to the same department");
        }
        $first = false;
        $cur = $row['show_if_field_id'] !== null ? (int)$row['show_if_field_id'] : null;
        if ($selfId !== null && $cur === $selfId) fields_fail(400, "That condition would create a loop");
    }
}

/**
 * Merge the request body over $base (the existing row, or defaults on create) and validate.
 * Only keys present in the body override $base, so older clients that omit the new keys
 * never wipe help text / ordering / conditions. Returns the complete set of column values to write.
 */
function build_field(PDO $db, object $data, array $base, ?int $selfId): array {
    $has = function (string $k) use ($data) { return property_exists($data, $k); };
    $f = $base;

    if ($has('category_id')) $f['category_id'] = $data->category_id === '' ? null : (int)$data->category_id;

    if ($has('field_label')) $f['field_label'] = trim((string)$data->field_label);
    if ($f['field_label'] === '') fields_fail(400, "field_label is required");
    if (mb_strlen($f['field_label']) > 255) fields_fail(400, "field_label is too long (max 255)");

    if ($has('field_type')) $f['field_type'] = (string)$data->field_type;
    if (!in_array($f['field_type'], FORM_FIELD_TYPES, true)) {
        fields_fail(400, "field_type must be one of: " . implode(', ', FORM_FIELD_TYPES));
    }

    if ($has('is_required')) $f['is_required'] = filter_var($data->is_required, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

    if ($has('options')) $f['options'] = normalize_options($data->options);
    if ($f['field_type'] !== 'dropdown') {
        // Options only mean something for dropdowns.
        if ($has('options') || $has('field_type')) $f['options'] = null;
    } elseif ($f['options'] === null) {
        fields_fail(400, "A dropdown question needs at least one option");
    }

    if ($has('help_text')) {
        $h = $data->help_text;
        $f['help_text'] = ($h === null || trim((string)$h) === '') ? null : (string)$h;
    }

    if ($has('sort_order')) {
        if (!is_numeric($data->sort_order)) fields_fail(400, "sort_order must be a number");
        $f['sort_order'] = (int)$data->sort_order;
    }

    if ($has('show_if_field_id')) {
        $p = $data->show_if_field_id;
        $f['show_if_field_id'] = ($p === null || $p === '' || $p === 0 || $p === '0') ? null : (int)$p;
        if ($f['show_if_field_id'] === null) $f['show_if_value'] = null;
    }
    if ($has('show_if_value')) {
        $v = $data->show_if_value;
        $f['show_if_value'] = ($v === null) ? null : implode('|', form_field_accepted_values($v));
        if ($f['show_if_value'] === '') $f['show_if_value'] = null;
    }
    if ($f['show_if_field_id'] !== null) {
        if ($f['show_if_value'] === null) fields_fail(400, "show_if_value is required when show_if_field_id is set");
        if (mb_strlen($f['show_if_value']) > 500) fields_fail(400, "show_if_value is too long (max 500)");
        validate_parent($db, (int)$f['department_id'], $selfId, (int)$f['show_if_field_id']);
    } else {
        $f['show_if_value'] = null;
    }
    return $f;
}

$schemaOk = ensure_form_fields_schema($db);
// Reads keep working (legacy ordering) if the migration could not run; writes need the new columns.
if (!$schemaOk && $method !== 'GET') {
    fields_fail(500, "Could not migrate the form_fields table (see server log)");
}

if ($method === 'GET') {
    $dept_id = isset($_GET['department_id']) ? $_GET['department_id'] : null;
    if ($dept_id) {
        try {
            $stmt = $db->prepare("SELECT * FROM form_fields WHERE department_id = :did ORDER BY " . ($schemaOk ? "sort_order, id" : "id"));
            $stmt->bindParam(":did", $dept_id);
            $stmt->execute();
            echo json_encode(["success" => true, "data" => array_map('shape_field', $stmt->fetchAll(PDO::FETCH_ASSOC))]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "department_id is required"]);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    if(is_object($data) && !empty($data->department_id) && !empty($data->field_label) && !empty($data->field_type)) {
        try {
            $deptId = (int)$data->department_id;
            $d = $db->prepare("SELECT id FROM departments WHERE id = :id");
            $d->execute([':id' => $deptId]);
            if (!$d->fetchColumn()) fields_fail(400, "Unknown department_id");

            $f = build_field($db, $data, [
                'department_id' => $deptId, 'category_id' => null, 'field_label' => '', 'field_type' => 'text', 'is_required' => 0,
                'options' => null, 'help_text' => null, 'sort_order' => null,
                'show_if_field_id' => null, 'show_if_value' => null,
            ], null);

            if ($f['sort_order'] === null) { // append after the existing questions
                $m = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM form_fields WHERE department_id = :did");
                $m->execute([':did' => $deptId]);
                $f['sort_order'] = (int)$m->fetchColumn();
            }

            $stmt = $db->prepare("INSERT INTO form_fields (department_id, category_id, field_label, field_type, is_required, options, help_text, sort_order, show_if_field_id, show_if_value)
                                  VALUES (:did, :cat, :label, :type, :req, :opt, :help, :sort, :pid, :pval)");
            $stmt->execute([
                ':did' => $deptId, ':cat' => $f['category_id'], ':label' => $f['field_label'], ':type' => $f['field_type'], ':req' => $f['is_required'],
                ':opt' => $f['options'], ':help' => $f['help_text'], ':sort' => $f['sort_order'],
                ':pid' => $f['show_if_field_id'], ':pval' => $f['show_if_value'],
            ]);
            echo json_encode(["success" => true, "message" => "Field added", "id" => (int)$db->lastInsertId()]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Incomplete data"]);
    }
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents("php://input"));
    if (is_object($data) && property_exists($data, 'reorder')) {
        // Reorder: { "reorder": [id, id, ...] } - all ids must belong to one department; sort_order becomes 1..n
        $ids = is_array($data->reorder) ? array_values(array_unique(array_map('intval', $data->reorder))) : [];
        if (!$ids) fields_fail(400, "reorder must be a non-empty array of field ids");
        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $s = $db->prepare("SELECT id, department_id FROM form_fields WHERE id IN ($in)");
            $s->execute($ids);
            $rows = $s->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) !== count($ids)) fields_fail(400, "Unknown field id in reorder");
            $depts = array_unique(array_map(function ($r) { return (int)$r['department_id']; }, $rows));
            if (count($depts) !== 1) fields_fail(400, "reorder ids must all belong to the same department");
            $db->beginTransaction();
            $u = $db->prepare("UPDATE form_fields SET sort_order = :s WHERE id = :id");
            foreach ($ids as $i => $fid) $u->execute([':s' => $i + 1, ':id' => $fid]);
            $db->commit();
            echo json_encode(["success" => true, "message" => "Order updated"]);
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } elseif (is_object($data) && !empty($data->id)) {
        try {
            $id = (int)$data->id;
            $s = $db->prepare("SELECT * FROM form_fields WHERE id = :id");
            $s->execute([':id' => $id]);
            $existing = $s->fetch(PDO::FETCH_ASSOC);
            if (!$existing) fields_fail(404, "Field not found");
            $existing['show_if_field_id'] = $existing['show_if_field_id'] !== null ? (int)$existing['show_if_field_id'] : null;

            $f = build_field($db, $data, $existing, $id);
            $stmt = $db->prepare("UPDATE form_fields SET category_id = :cat, field_label = :label, field_type = :type, is_required = :req, options = :opt,
                                  help_text = :help, sort_order = :sort, show_if_field_id = :pid, show_if_value = :pval WHERE id = :id");
            $stmt->execute([
                ':cat' => $f['category_id'], ':label' => $f['field_label'], ':type' => $f['field_type'], ':req' => $f['is_required'],
                ':opt' => $f['options'], ':help' => $f['help_text'], ':sort' => (int)$f['sort_order'],
                ':pid' => $f['show_if_field_id'], ':pval' => $f['show_if_value'], ':id' => $id,
            ]);
            echo json_encode(["success" => true, "message" => "Field updated"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Missing ID"]);
    }
} elseif ($method === 'DELETE') {
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    if ($id) {
        try {
            // Children lose their condition (the FK does this too when it exists).
            $db->prepare("UPDATE form_fields SET show_if_field_id = NULL, show_if_value = NULL WHERE show_if_field_id = :id")->execute([':id' => $id]);
            $stmt = $db->prepare("DELETE FROM form_fields WHERE id = :id");
            $stmt->execute([':id' => $id]);
            echo json_encode(["success" => true, "message" => "Field deleted"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Missing ID"]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
}
?>


