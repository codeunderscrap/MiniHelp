<?php
// tests/email_templates_test.php — CLI-only smoke test for the email templates and address parsing.
// Renders every event with fake data and prints the results; exits non-zero on a failed check.
//   php tests/email_templates_test.php            (prints subjects + plain text)
//   php tests/email_templates_test.php --html     (also prints the HTML bodies)
// Needs no database and no SMTP server, and never sends anything.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../server/config/mail_templates.php';
require_once __DIR__ . '/../server/config/mailer.php';

$failures = 0;
function check(string $name, bool $ok): void {
    global $failures;
    echo ($ok ? '  ok   ' : '  FAIL ') . $name . "\n";
    if (!$ok) $failures++;
}

$ticket = [
    'id' => 123,
    'ticket_number' => 'MM-20260930-4821',
    'title' => 'Laptop <script>alert(1)</script> won\'t boot & "hangs"',
    'department' => 'Information Technology',
    'priority' => 'high',
    'status' => 'assigned',
    'old_status' => 'open',
    'requester' => 'Asha <b>Rao</b>',
    'assignee' => 'Ravi Kumar',
    'actor' => 'Ravi Kumar',
    'description' => "Screen stays black after the logo.\nTried charging overnight. " . str_repeat('More detail. ', 80),
    'custom_fields' => [
        ['label' => 'Device Type', 'value' => 'Laptop'],
        ['label' => 'Asset tag <x>', 'value' => '0'],
        ['label' => 'Empty answer', 'value' => '   '],
    ],
    'comment' => "Please try holding the power button for 30 seconds.\n[Attachment: photo.jpg]",
    'url' => 'https://rmt.m-mines.in/tickets/123',
];

$events = ['ticket_created', 'ticket_received', 'ticket_assigned', 'status_changed', 'comment_added'];
$showHtml = in_array('--html', $argv ?? [], true);

foreach ($events as $event) {
    echo "\n=== $event ===\n";
    $r = mailtpl_render($event, $ticket);
    echo "Subject: {$r['subject']}\n\n{$r['text']}\n";
    if ($showHtml) echo "--- HTML ---\n{$r['html']}\n";

    check("$event: subject starts with the ticket number", str_starts_with($r['subject'], '[MM-20260930-4821] '));
    check("$event: no raw <script> in HTML", stripos($r['html'], '<script') === false);
    check("$event: no raw <b> from user content in HTML", strpos($r['html'], '<b>Rao') === false);
    check("$event: title is escaped in HTML", strpos($r['html'], '&lt;script&gt;') !== false);
    check("$event: View ticket link present", strpos($r['html'], 'href="https://rmt.m-mines.in/tickets/123"') !== false
        && strpos($r['text'], 'View ticket: https://rmt.m-mines.in/tickets/123') !== false);
    check("$event: brand colour used", strpos($r['html'], '#005D7F') !== false);
    check("$event: plain text has no HTML tags from the template", strpos($r['text'], '<html') === false);
}

$created = mailtpl_render('ticket_created', $ticket);
check('created: non-empty custom answers shown (including "0")', strpos($created['text'], 'Device Type: Laptop') !== false && strpos($created['text'], 'Asset tag <x>: 0') !== false);
check('created: empty custom answers omitted', strpos($created['text'], 'Empty answer') === false);
check('created: description is truncated', strpos($created['text'], '...') !== false);
$status = mailtpl_render('status_changed', $ticket);
check('status: subject names the new status', $status['subject'] === "[MM-20260930-4821] Status changed to Assigned: Laptop <script>alert(1)</script> won't boot & \"hangs\"");
check('status: no custom answers', strpos($status['text'], 'Device Type') === false);
$newline = mailtpl_render('comment_added', ['ticket_number' => "MM-1\r\nBcc: x@y.z", 'title' => "a\r\nb"] + $ticket);
check('subject has no line breaks', strpos($newline['subject'], "\n") === false && strpos($newline['subject'], "\r") === false);

echo "\n=== mail_parse_address_list ===\n";
$p = mail_parse_address_list("IT@Example.com, lead@example.com;  bad-address  it@example.com\nsecond@example.org");
check('valid addresses lower-cased and de-duplicated', $p['valid'] === ['it@example.com', 'lead@example.com', 'second@example.org']);
check('invalid addresses reported', $p['invalid'] === ['bad-address']);
check('empty input', mail_parse_address_list(null) === ['valid' => [], 'invalid' => []]);

echo "\n" . ($failures === 0 ? "All checks passed.\n" : "$failures check(s) FAILED.\n");
exit($failures === 0 ? 0 : 1);
