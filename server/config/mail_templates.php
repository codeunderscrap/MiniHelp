<?php
// config/mail_templates.php — HTML + plain-text bodies for ticket emails. Pure functions: no
// database, no environment, so tests/email_templates_test.php can render them with fake data.
//
// mailtpl_render($event, $t) returns ['subject' => ..., 'html' => ..., 'text' => ...].
// $event is one of: ticket_created, ticket_received, ticket_assigned, status_changed, comment_added.
// $t keys: ticket_number, title, department, priority, status, requester, assignee (may be ''),
//          description, custom_fields (list of ['label' => .., 'value' => ..]), url,
//          actor (who did it), old_status, comment.

const MAILTPL_BRAND = '#005D7F';

function mailtpl_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mailtpl_label(?string $status): string {
    $s = str_replace('_', ' ', (string)$status);
    return $s === '' ? '' : strtoupper(substr($s, 0, 1)) . substr($s, 1);
}

function mailtpl_one_line(string $s): string {
    return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
}

function mailtpl_excerpt(string $s, int $max = 400): string {
    $s = trim(str_replace(["\r\n", "\r"], "\n", $s));
    $len = function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
    if ($len <= $max) return $s;
    $cut = function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
    return rtrim($cut) . '...';
}

function mailtpl_subject(string $event, array $t): string {
    $n = '[' . mailtpl_one_line((string)($t['ticket_number'] ?? '')) . '] ';
    $title = mailtpl_one_line((string)($t['title'] ?? ''));
    switch ($event) {
        case 'ticket_created':  return $n . 'New ticket: ' . $title;
        case 'ticket_received': return $n . 'Ticket received: ' . $title;
        case 'ticket_assigned': return $n . 'Assigned to you: ' . $title;
        case 'status_changed':  return $n . 'Status changed to ' . mailtpl_label($t['status'] ?? '') . ': ' . $title;
        case 'comment_added':   return $n . 'New comment: ' . $title;
    }
    return $n . $title;
}

// [heading, intro sentence] for the event.
function mailtpl_copy(string $event, array $t): array {
    $actor = trim((string)($t['actor'] ?? ''));
    $dept = (string)($t['department'] ?? '');
    switch ($event) {
        case 'ticket_created':
            return ['New ticket in ' . $dept,
                    trim((string)($t['requester'] ?? '')) !== '' ? $t['requester'] . ' raised a new ticket.' : 'A new ticket was raised.'];
        case 'ticket_received':
            return ["We've received your ticket",
                    'Thanks' . (trim((string)($t['requester'] ?? '')) !== '' ? ', ' . $t['requester'] : '')
                    . '. Your request has been logged and the ' . $dept . ' team has been notified.'];
        case 'ticket_assigned':
            return ['A ticket was assigned to you',
                    $actor !== '' ? $actor . ' assigned this ticket to you.' : 'This ticket has been assigned to you.'];
        case 'status_changed':
            $from = mailtpl_label($t['old_status'] ?? '');
            $to = mailtpl_label($t['status'] ?? '');
            $change = ($from !== '' ? ' from ' . $from : '') . ' to ' . $to . '.';
            return ['Ticket status changed',
                    $actor !== '' ? $actor . ' changed the status' . $change : 'The status was changed' . $change];
        case 'comment_added':
            return ['New comment', ($actor !== '' ? $actor : 'Someone') . ' commented on this ticket.'];
    }
    return ['Ticket update', ''];
}

function mailtpl_render(string $event, array $t): array {
    [$heading, $intro] = mailtpl_copy($event, $t);
    $showDescription = in_array($event, ['ticket_created', 'ticket_received', 'ticket_assigned'], true);
    $showAnswers = in_array($event, ['ticket_created', 'ticket_received'], true);

    $rows = [
        ['Ticket', (string)($t['ticket_number'] ?? '')],
        ['Title', (string)($t['title'] ?? '')],
        ['Department', (string)($t['department'] ?? '')],
        ['Priority', mailtpl_label($t['priority'] ?? '')],
        ['Status', mailtpl_label($t['status'] ?? '')],
        ['Requester', (string)($t['requester'] ?? '')],
    ];
    if (trim((string)($t['assignee'] ?? '')) !== '') $rows[] = ['Assigned to', (string)$t['assignee']];

    $description = $showDescription ? mailtpl_excerpt((string)($t['description'] ?? '')) : '';
    $comment = $event === 'comment_added' ? mailtpl_excerpt((string)($t['comment'] ?? ''), 800) : '';

    $answers = [];
    if ($showAnswers) {
        foreach (($t['custom_fields'] ?? []) as $f) {
            $value = trim((string)($f['value'] ?? ''));
            if ($value !== '') $answers[] = [(string)($f['label'] ?? ''), mailtpl_excerpt($value, 300)];
        }
    }
    $url = (string)($t['url'] ?? '');

    // ---- plain text ----
    $text = $heading . "\n" . str_repeat('=', min(60, strlen($heading))) . "\n" . $intro . "\n\n";
    foreach ($rows as [$k, $v]) $text .= str_pad($k . ':', 13) . mailtpl_one_line($v) . "\n";
    if ($description !== '') $text .= "\nDescription:\n" . $description . "\n";
    if ($comment !== '') $text .= "\nComment:\n" . $comment . "\n";
    if ($answers) {
        $text .= "\nAnswers:\n";
        foreach ($answers as [$k, $v]) $text .= '- ' . mailtpl_one_line($k) . ': ' . $v . "\n";
    }
    if ($url !== '') $text .= "\nView ticket: " . $url . "\n";
    $text .= "\n--\nThis is an automated message from MiniMines Helpdesk.\n";

    // ---- HTML ----
    $brand = MAILTPL_BRAND;
    $font = "Roboto, Arial, Helvetica, sans-serif";
    $td = "padding:6px 0;font-family:$font;font-size:14px;color:#1f2933;vertical-align:top;";
    $h = 'mailtpl_h';

    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . $h(mailtpl_subject($event, $t)) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f2f5f7;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f5f7;"><tr><td align="center" style="padding:24px 12px;">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #dde4e9;">'
        . '<tr><td style="background:' . $brand . ';padding:18px 24px;font-family:' . $font . ';font-size:16px;font-weight:bold;color:#ffffff;">MiniMines Helpdesk</td></tr>'
        . '<tr><td style="padding:24px;font-family:' . $font . ';">'
        . '<h1 style="margin:0 0 8px 0;font-size:20px;line-height:1.3;color:' . $brand . ';font-family:' . $font . ';">' . $h($heading) . '</h1>'
        . '<p style="margin:0 0 20px 0;font-size:14px;line-height:1.5;color:#1f2933;">' . $h($intro) . '</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e5eaee;border-bottom:1px solid #e5eaee;margin-bottom:20px;">';
    foreach ($rows as [$k, $v]) {
        $html .= '<tr><td width="110" style="' . $td . 'color:#5f6c76;">' . $h($k) . '</td><td style="' . $td . '">' . $h($v) . '</td></tr>';
    }
    $html .= '</table>';

    if ($description !== '') {
        $html .= '<p style="margin:0 0 4px 0;font-size:12px;color:#5f6c76;text-transform:uppercase;letter-spacing:0.04em;">Description</p>'
            . '<p style="margin:0 0 20px 0;font-size:14px;line-height:1.5;color:#1f2933;">' . nl2br($h($description)) . '</p>';
    }
    if ($comment !== '') {
        $html .= '<p style="margin:0 0 4px 0;font-size:12px;color:#5f6c76;text-transform:uppercase;letter-spacing:0.04em;">Comment</p>'
            . '<div style="margin:0 0 20px 0;padding:10px 14px;border-left:4px solid ' . $brand . ';background:#f2f5f7;font-size:14px;line-height:1.5;color:#1f2933;">' . nl2br($h($comment)) . '</div>';
    }
    if ($answers) {
        $html .= '<p style="margin:0 0 4px 0;font-size:12px;color:#5f6c76;text-transform:uppercase;letter-spacing:0.04em;">Details provided</p>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">';
        foreach ($answers as [$k, $v]) {
            $html .= '<tr><td width="160" style="' . $td . 'color:#5f6c76;">' . $h($k) . '</td><td style="' . $td . '">' . nl2br($h($v)) . '</td></tr>';
        }
        $html .= '</table>';
    }
    if ($url !== '') {
        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:4px 0 8px 0;"><tr>'
            . '<td bgcolor="' . $brand . '" style="background:' . $brand . ';border-radius:6px;">'
            . '<a href="' . $h($url) . '" style="display:inline-block;padding:12px 24px;font-family:' . $font . ';font-size:14px;font-weight:bold;color:#ffffff;text-decoration:none;">View ticket</a>'
            . '</td></tr></table>';
    }
    $html .= '</td></tr>'
        . '<tr><td style="padding:14px 24px;background:#f2f5f7;font-family:' . $font . ';font-size:12px;color:#5f6c76;">This is an automated message from MiniMines Helpdesk.</td></tr>'
        . '</table></td></tr></table></body></html>';

    return ['subject' => mailtpl_subject($event, $t), 'html' => $html, 'text' => $text];
}
