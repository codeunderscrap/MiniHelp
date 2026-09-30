<?php
// config/notify_email.php — who gets which email, and when.
// Every notify_* function only queues work (mail_defer); recipients are resolved, rendered and
// sent after the API response has gone out, and nothing here can throw into the caller.
//
// Rules: addresses are de-duplicated, users without a usable email are skipped, and the person
// who performed the action is never emailed (except the requester's "ticket received"
// confirmation). The comments table has no internal/private flag, so every comment is treated
// as public.

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/mail_templates.php';

const NOTIFY_STAFF_ROLES = ['agent', 'dept_head', 'admin'];

// Ticket + department + people, in the shape the templates expect (plus ids and raw emails).
function notify_load_ticket(PDO $db, int $ticketId): ?array {
    mail_ensure_department_column($db);
    $stmt = $db->prepare(
        "SELECT t.id, t.ticket_number, t.title, t.description, t.status, t.priority, t.department_id,
                t.creator_id, t.assignee_id, d.name AS department, d.notification_emails,
                cu.name AS requester, cu.email AS requester_email,
                au.name AS assignee, au.email AS assignee_email
         FROM tickets t
         JOIN departments d ON d.id = t.department_id
         LEFT JOIN users cu ON cu.id = t.creator_id
         LEFT JOIN users au ON au.id = t.assignee_id
         WHERE t.id = :id");
    $stmt->execute([':id' => $ticketId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    $row['assignee'] = (string)($row['assignee'] ?? '');
    $row['requester'] = (string)($row['requester'] ?? '');
    $row['url'] = mail_config($db)['app_url'] . '/tickets/' . $row['id'];
    $row['custom_fields'] = [];
    $row['actor'] = '';
    $row['old_status'] = '';
    $row['comment'] = '';
    return $row;
}

// Non-empty custom-question answers, in question order.
function notify_custom_fields(PDO $db, int $ticketId): array {
    $stmt = $db->prepare("SELECT ff.field_label, tcv.field_value
                          FROM ticket_custom_values tcv
                          JOIN form_fields ff ON ff.id = tcv.field_id
                          WHERE tcv.ticket_id = :t ORDER BY ff.id");
    $stmt->execute([':t' => $ticketId]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $v = (string)($r['field_value'] ?? '');
        if ($v !== '' && $v[0] === '[') {            // multi-select answers are stored as a JSON list
            $decoded = json_decode($v, true);
            if (is_array($decoded)) $v = implode(', ', array_map('strval', $decoded));
        }
        if (trim($v) !== '') $out[] = ['label' => (string)$r['field_label'], 'value' => $v];
    }
    return $out;
}

function notify_user(PDO $db, int $userId): ?array {
    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Renders $event for $email and appends it to $msgs, unless the address is unusable or
// already used (tracked in $seen, keyed by lower-cased address).
function notify_add(array &$msgs, array &$seen, ?string $email, ?string $name, string $event, array $t): void {
    $email = strtolower(trim((string)$email));
    if ($email === '' || isset($seen[$email]) || !filter_var($email, FILTER_VALIDATE_EMAIL)) return;
    $seen[$email] = true;
    $r = mailtpl_render($event, $t);
    $msgs[] = [
        'to' => $email, 'to_name' => (string)$name, 'subject' => $r['subject'], 'html' => $r['html'],
        'text' => $r['text'], 'event' => $event, 'ticket_id' => (int)$t['id'],
    ];
}

function notify_run(PDO $db, callable $build): void {
    $msgs = $build();
    if ($msgs) mail_send_batch($db, $msgs);
}

// Ticket created: department staff + department extra addresses, and a confirmation to the requester.
function notify_ticket_created(PDO $db, int $ticketId, int $actorId): void {
    mail_defer($db, function () use ($db, $ticketId, $actorId) {
        notify_run($db, function () use ($db, $ticketId, $actorId) {
            $t = notify_load_ticket($db, $ticketId);
            if (!$t) return [];
            $t['custom_fields'] = notify_custom_fields($db, $ticketId);
            $actor = notify_user($db, $actorId);
            $t['actor'] = $actor['name'] ?? '';

            $msgs = [];
            $seen = [];
            // The requester's confirmation goes first; it also keeps them out of the staff mail below.
            notify_add($msgs, $seen, $t['requester_email'], $t['requester'], 'ticket_received', $t);

            $stmt = $db->prepare("SELECT id, name, email FROM users
                                  WHERE department_id = :d AND role IN ('agent', 'dept_head', 'admin') AND id <> :actor");
            $stmt->execute([':d' => $t['department_id'], ':actor' => $actorId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
                notify_add($msgs, $seen, $u['email'], $u['name'], 'ticket_created', $t);
            }
            foreach (mail_parse_address_list($t['notification_emails'])['valid'] as $extra) {
                notify_add($msgs, $seen, $extra, '', 'ticket_created', $t);
            }
            return $msgs;
        });
    });
}

// Status and/or assignee changed. $oldStatus / $oldAssigneeId are the values before the update;
// the new values are read back from the database.
function notify_ticket_updated(PDO $db, int $ticketId, int $actorId, ?string $oldStatus, ?int $oldAssigneeId): void {
    mail_defer($db, function () use ($db, $ticketId, $actorId, $oldStatus, $oldAssigneeId) {
        notify_run($db, function () use ($db, $ticketId, $actorId, $oldStatus, $oldAssigneeId) {
            $t = notify_load_ticket($db, $ticketId);
            if (!$t) return [];
            $actor = notify_user($db, $actorId);
            $t['actor'] = $actor['name'] ?? '';
            $t['old_status'] = (string)$oldStatus;

            $statusChanged = $oldStatus !== null && $oldStatus !== $t['status'];
            $assigneeChanged = $t['assignee_id'] !== null && (int)$t['assignee_id'] !== (int)$oldAssigneeId;

            $msgs = [];
            $seen = [];
            if ($actor) $seen[strtolower(trim((string)$actor['email']))] = true;

            // The new assignee gets one email; it already shows the ticket's current status.
            if ($assigneeChanged) {
                notify_add($msgs, $seen, $t['assignee_email'], $t['assignee'], 'ticket_assigned', $t);
            }
            if ($statusChanged) {
                notify_add($msgs, $seen, $t['requester_email'], $t['requester'], 'status_changed', $t);
                notify_add($msgs, $seen, $t['assignee_email'], $t['assignee'], 'status_changed', $t);
            }
            return $msgs;
        });
    });
}

// Comment added: requester and assignee, never the commenter.
function notify_comment_added(PDO $db, int $ticketId, int $actorId, string $content): void {
    mail_defer($db, function () use ($db, $ticketId, $actorId, $content) {
        notify_run($db, function () use ($db, $ticketId, $actorId, $content) {
            $t = notify_load_ticket($db, $ticketId);
            if (!$t) return [];
            $actor = notify_user($db, $actorId);
            $t['actor'] = $actor['name'] ?? '';
            // Attachments are stored in the comment as [FILE://path|name]; show just the name.
            $t['comment'] = preg_replace('/\[FILE:\/\/[^|\]]*\|([^\]]*)\]/', '[Attachment: $1]', $content) ?? $content;

            $msgs = [];
            $seen = [];
            if ($actor) $seen[strtolower(trim((string)$actor['email']))] = true;
            notify_add($msgs, $seen, $t['requester_email'], $t['requester'], 'comment_added', $t);
            notify_add($msgs, $seen, $t['assignee_email'], $t['assignee'], 'comment_added', $t);
            return $msgs;
        });
    });
}
