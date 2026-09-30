<?php
// config/mailer.php — SMTP transport for email notifications.
//
// Configuration comes from environment variables only (never the repository):
//   SMTP_HOST, SMTP_PORT (587), SMTP_SECURE (tls|ssl|none, default tls), SMTP_USER, SMTP_PASS,
//   MAIL_FROM, MAIL_FROM_NAME ("MiniMines Helpdesk"), APP_URL (https://rmt.m-mines.in),
//   MAIL_ENABLED (default on when SMTP_HOST is set; 0/false/off/no turns it off).
// Like config/session_token.php, a value missing from the environment can be supplied through a
// row in the app_secrets table named after the variable in lower case (smtp_host, smtp_pass, ...).
//
// Sending must never break or noticeably slow an API call: callers hand work to mail_defer(),
// which runs it after the JSON response has been flushed to the client, and every step is
// wrapped so a mail failure is only ever logged (error_log + the email_log table).

use PHPMailer\PHPMailer\PHPMailer;

const MAIL_SETTING_KEYS = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_SECURE', 'SMTP_USER', 'SMTP_PASS',
                           'MAIL_FROM', 'MAIL_FROM_NAME', 'APP_URL', 'MAIL_ENABLED'];
const MAIL_SMTP_TIMEOUT = 10;     // seconds for each SMTP connect / command
const MAIL_TIME_BUDGET  = 40;     // seconds for one request's whole batch of messages

// Effective mail settings. Cached for the life of the request.
function mail_config(?PDO $db = null): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $vals = [];
    foreach (MAIL_SETTING_KEYS as $key) {
        $v = getenv($key);
        $vals[$key] = $v === false ? '' : trim($v);
    }

    $needsFallback = $vals['SMTP_HOST'] === '' || ($vals['SMTP_USER'] !== '' && $vals['SMTP_PASS'] === '');
    if ($db !== null && $needsFallback) {
        try {
            $in = implode(',', array_fill(0, count(MAIL_SETTING_KEYS), '?'));
            $stmt = $db->prepare("SELECT name, value FROM app_secrets WHERE name IN ($in)");
            $stmt->execute(array_map('strtolower', MAIL_SETTING_KEYS));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $key = strtoupper($row['name']);
                if (isset($vals[$key]) && $vals[$key] === '') $vals[$key] = trim((string)$row['value']);
            }
        } catch (\Throwable $e) {
            // app_secrets may not exist yet; nothing to fall back to.
        }
    }

    $secure = strtolower($vals['SMTP_SECURE'] ?: 'tls');
    if (!in_array($secure, ['tls', 'ssl', 'none'], true)) $secure = 'tls';
    $port = (int)$vals['SMTP_PORT'];
    if ($port <= 0) $port = $secure === 'ssl' ? 465 : 587;

    $appUrl = rtrim($vals['APP_URL'] !== '' ? $vals['APP_URL'] : 'https://rmt.m-mines.in', '/');

    $from = $vals['MAIL_FROM'];
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = filter_var($vals['SMTP_USER'], FILTER_VALIDATE_EMAIL)
            ? $vals['SMTP_USER']
            : 'noreply@' . (parse_url($appUrl, PHP_URL_HOST) ?: 'localhost');
    }

    $off = in_array(strtolower($vals['MAIL_ENABLED']), ['0', 'false', 'off', 'no'], true);

    return $cfg = [
        'enabled'   => $vals['SMTP_HOST'] !== '' && !$off,
        'host'      => $vals['SMTP_HOST'],
        'port'      => $port,
        'secure'    => $secure,
        'user'      => $vals['SMTP_USER'],
        'pass'      => $vals['SMTP_PASS'],
        'from'      => $from,
        'from_name' => $vals['MAIL_FROM_NAME'] !== '' ? $vals['MAIL_FROM_NAME'] : 'MiniMines Helpdesk',
        'app_url'   => $appUrl,
    ];
}

// Splits a comma/semicolon/whitespace separated list of addresses.
// Returns ['valid' => [...lower-cased, de-duplicated...], 'invalid' => [...]].
function mail_parse_address_list(?string $raw): array {
    $valid = [];
    $invalid = [];
    foreach (preg_split('/[,;\s]+/', (string)$raw, -1, PREG_SPLIT_NO_EMPTY) as $part) {
        if (strlen($part) <= 254 && filter_var($part, FILTER_VALIDATE_EMAIL)) {
            $valid[strtolower($part)] = true;
        } else {
            $invalid[] = $part;
        }
    }
    return ['valid' => array_keys($valid), 'invalid' => $invalid];
}

// Idempotent migration: departments.notification_emails (comma-separated extra recipients).
function mail_ensure_department_column(PDO $db): void {
    static $done = false;
    if ($done) return;
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'departments'
                              AND COLUMN_NAME = 'notification_emails'");
        if ((int)$stmt->fetchColumn() === 0) {
            try {
                $db->exec("ALTER TABLE departments ADD COLUMN notification_emails TEXT NULL");
            } catch (PDOException $e) {
                // A concurrent request added it first (duplicate column, 1060).
                if (($e->errorInfo[1] ?? 0) != 1060) throw $e;
            }
        }
        $done = true;
    } catch (\Throwable $e) {
        error_log("mail_ensure_department_column: " . $e->getMessage());
    }
}

function mail_ensure_log_table(PDO $db): void {
    static $done = false;
    if ($done) return;
    $db->exec("CREATE TABLE IF NOT EXISTS email_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NULL,
        recipient VARCHAR(255) NOT NULL,
        event VARCHAR(40) NOT NULL,
        status VARCHAR(16) NOT NULL,
        error TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email_log_ticket (ticket_id),
        INDEX idx_email_log_created (created_at)
    )");
    $done = true;
}

function mail_log(PDO $db, ?int $ticketId, string $recipient, string $event, string $status, ?string $error = null): void {
    try {
        $stmt = $db->prepare("INSERT INTO email_log (ticket_id, recipient, event, status, error)
                              VALUES (:t, :r, :e, :s, :err)");
        $stmt->execute([
            ':t' => $ticketId, ':r' => substr($recipient, 0, 255), ':e' => substr($event, 0, 40),
            ':s' => $status, ':err' => $error === null ? null : substr($error, 0, 1000),
        ]);
    } catch (\Throwable $e) {
        error_log("email_log write failed: " . $e->getMessage());
    }
    if ($status !== 'sent') {
        error_log("MiniHelp mail $status ($event to $recipient): " . ($error ?? ''));
    }
}

function mail_load_phpmailer(): void {
    $dir = __DIR__ . '/../vendor/phpmailer/phpmailer/src/';
    require_once $dir . 'Exception.php';
    require_once $dir . 'PHPMailer.php';
    require_once $dir . 'SMTP.php';
}

// Sends a batch of messages over one SMTP connection. Each message:
//   ['to', 'to_name', 'subject', 'html', 'text', 'event', 'ticket_id']
// Never throws. Every attempt is recorded in email_log.
function mail_send_batch(PDO $db, array $messages): void {
    $cfg = mail_config($db);
    if (!$cfg['enabled'] || !$messages) return;

    try {
        mail_ensure_log_table($db);
        mail_load_phpmailer();
    } catch (\Throwable $e) {
        error_log("MiniHelp mail setup failed: " . $e->getMessage());
        return;
    }

    $started = microtime(true);
    $mail = null;
    $dead = null; // set once the SMTP server is unreachable, so the rest fail fast instead of each waiting out a timeout
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->Port = $cfg['port'];
        $mail->Timeout = MAIL_SMTP_TIMEOUT;
        $mail->SMTPKeepAlive = true;
        $mail->CharSet = 'UTF-8';
        $mail->SMTPDebug = 0;
        if ($cfg['secure'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($cfg['secure'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }
        if ($cfg['user'] !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $cfg['user'];
            $mail->Password = $cfg['pass'];
        }
        $mail->setFrom($cfg['from'], $cfg['from_name']);
    } catch (\Throwable $e) {
        $dead = 'Mailer configuration error: ' . $e->getMessage();
    }

    foreach ($messages as $m) {
        $ticketId = isset($m['ticket_id']) ? (int)$m['ticket_id'] : null;
        $to = (string)$m['to'];
        $event = (string)$m['event'];

        if ($dead !== null) {
            mail_log($db, $ticketId, $to, $event, 'failed', $dead);
            continue;
        }
        if (microtime(true) - $started > MAIL_TIME_BUDGET) {
            mail_log($db, $ticketId, $to, $event, 'skipped', 'Time budget for this request exceeded');
            continue;
        }

        try {
            $mail->clearAllRecipients();
            $mail->addAddress($to, (string)($m['to_name'] ?? ''));
            $mail->Subject = (string)$m['subject'];
            $mail->isHTML(true);
            $mail->Body = (string)$m['html'];
            $mail->AltBody = (string)$m['text'];
            $mail->send();
            mail_log($db, $ticketId, $to, $event, 'sent');
        } catch (\Throwable $e) {
            $err = ($mail->ErrorInfo ?? '') !== '' ? $mail->ErrorInfo : $e->getMessage();
            mail_log($db, $ticketId, $to, $event, 'failed', $err);
            try {
                if (!$mail->getSMTPInstance()->connected() && preg_match('/connect|authenticate/i', $err)) {
                    $dead = $err;
                }
            } catch (\Throwable $ignored) {
            }
        }
    }

    try {
        if ($mail !== null) $mail->smtpClose();
    } catch (\Throwable $e) {
    }
}

// ---- Deferred execution (after the response has been sent) ----

final class MailDeferred {
    public static array $jobs = [];
    public static bool $registered = false;
}

// Queues $job to run after the response is flushed. When mail is not configured this does
// nothing at all (and logs a single line per container so the reason is discoverable).
function mail_defer(PDO $db, callable $job): void {
    $cfg = mail_config($db);
    if (!$cfg['enabled']) {
        $marker = sys_get_temp_dir() . '/minihelp_mail_disabled_logged';
        if (!@file_exists($marker)) {
            @file_put_contents($marker, '1');
            error_log('MiniHelp: email notifications are off (SMTP_HOST is not set, or MAIL_ENABLED is off).');
        }
        return;
    }
    MailDeferred::$jobs[] = $job;
    if (!MailDeferred::$registered) {
        MailDeferred::$registered = true;
        ob_start(); // hold the JSON response so it can be sent with an exact Content-Length
        register_shutdown_function('mail_run_deferred');
    }
}

// Ends the client's wait: sends everything buffered so far with Content-Length and
// Connection: close, then keeps the PHP process alive for the mail work.
function mail_finish_response(): void {
    if (PHP_SAPI === 'cli') return;
    if (function_exists('fastcgi_finish_request')) {
        while (ob_get_level() > 0) @ob_end_flush();
        fastcgi_finish_request();
        return;
    }
    $out = '';
    while (ob_get_level() > 0) {
        $out = (string)ob_get_clean() . $out;
    }
    if (!headers_sent()) {
        header('Content-Length: ' . strlen($out));
        header('Connection: close');
    }
    echo $out;
    flush();
}

function mail_run_deferred(): void {
    $jobs = MailDeferred::$jobs;
    MailDeferred::$jobs = [];
    try {
        mail_finish_response();
    } catch (\Throwable $e) {
        error_log('MiniHelp mail: could not finish response early: ' . $e->getMessage());
    }
    @ignore_user_abort(true);
    @set_time_limit(MAIL_TIME_BUDGET + 20);
    foreach ($jobs as $job) {
        try {
            $job();
        } catch (\Throwable $e) {
            error_log('MiniHelp mail job failed: ' . $e->getMessage());
        }
    }
}
