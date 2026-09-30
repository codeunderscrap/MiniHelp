# Email notifications

MiniHelp emails people about ticket activity over SMTP, in addition to the existing in-app and
Web Push notifications. It is **off until `SMTP_HOST` is set**; while off, nothing changes and a
single line is written to the container log.

Mail is sent by PHPMailer (vendored in `server/vendor/phpmailer`). Sending happens *after* the JSON
response has been flushed to the browser (`Content-Length` + `Connection: close`, then the PHP
process keeps going), so a slow or broken mail server never delays or breaks an API call. SMTP
timeouts are 10 s per command and a request's whole batch is capped at 40 s. If a reverse proxy or
web server compresses or buffers responses it can defeat the early flush; mail still sends, but the
API response may then wait for it.

## Environment variables

Set these on the `backend-api` service in Coolify (they are passed through by `docker-compose.yml`).
Never commit them; this repository is public.

| Variable | Default | Meaning |
| --- | --- | --- |
| `SMTP_HOST` | (none) | SMTP server. Setting it turns email on. |
| `SMTP_PORT` | `587` (`465` when `SMTP_SECURE=ssl`) | SMTP port. |
| `SMTP_SECURE` | `tls` | `tls` = STARTTLS (587), `ssl` = implicit TLS (465), `none` = no encryption. |
| `SMTP_USER` | (none) | SMTP login. Leave empty for an IP-allowlisted relay. |
| `SMTP_PASS` | (none) | SMTP password (for Google: an App Password). |
| `MAIL_FROM` | `SMTP_USER`, else `noreply@<APP_URL host>` | From address. |
| `MAIL_FROM_NAME` | `MiniMines Helpdesk` | From display name. |
| `APP_URL` | `https://rmt.m-mines.in` | Base URL used for the "View ticket" button (`<APP_URL>/tickets/<id>`). |
| `MAIL_ENABLED` | on when `SMTP_HOST` is set | `0`, `false`, `off` or `no` switches email off without removing the SMTP settings. |

Like `MINIHELP_SESSION_SECRET`, any of these may instead be stored as a row in the `app_secrets`
table named in lower case (`smtp_host`, `smtp_pass`, ...). The environment wins.

### Google Workspace examples

Personal/app account with an App Password (2-Step Verification must be on for the account):

```
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USER=helpdesk@your-domain.com
SMTP_PASS=<16-character App Password>
MAIL_FROM=helpdesk@your-domain.com
```

Gmail only lets you send as the authenticated account or one of its "Send mail as" aliases.

Google Workspace SMTP relay (allowlisted server IP, no password):

```
SMTP_HOST=smtp-relay.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
MAIL_FROM=helpdesk@your-domain.com
```

The relay must be enabled in the Admin console (Apps > Google Workspace > Gmail > Routing > SMTP relay
service) and allow the VPS's public IP, or require SMTP authentication.

## Events and recipients

The person who performed the action is never emailed (except the requester's confirmation), addresses
are de-duplicated, and users without a valid email are skipped. Comments have no internal/private flag
in this schema, so every comment email is treated as public.

| Event | Subject | Recipients |
| --- | --- | --- |
| Ticket created | `[MM-...] New ticket: <title>` | Users in the ticket's department with role `agent`, `dept_head` or `admin`, plus the department's extra *Notification emails* |
| Ticket created | `[MM-...] Ticket received: <title>` | The requester (confirmation) |
| Assigned / reassigned (PATCH `assignee_id` changes) | `[MM-...] Assigned to you: <title>` | The new assignee |
| Status changed (PATCH `status` changes) | `[MM-...] Status changed to <Status>: <title>` | The requester, and the assignee (unless they already got the assignment email for the same change) |
| New comment | `[MM-...] New comment: <title>` | The requester and the assignee |

Tickets that are auto-assigned on creation do not send a separate "assigned" email: the assignee is a
department agent or head and receives the "New ticket" email, which shows them as assigned.
SLA breach emails are not implemented.

Ticket created emails list the non-empty custom-question answers; the body also includes ticket
number, title, department, priority, status, requester, a description excerpt and a **View ticket**
button.

### Per-department extra recipients

*Settings > Departments > Add/Edit* has a **Notification emails (comma separated)** field (for example a
shared team mailbox). It is stored in `departments.notification_emails` (added automatically, and safely
repeatable, the first time the departments API or a notification runs). Addresses are validated
server-side (max 20); writes need a department head or admin, the same as other department changes, and
the field is only returned to those roles.

## Checking delivery: `email_log`

Every attempt is recorded in the `email_log` table (created automatically):

```sql
SELECT created_at, ticket_id, recipient, event, status, error
FROM email_log ORDER BY id DESC LIMIT 50;

SELECT * FROM email_log WHERE status <> 'sent' ORDER BY id DESC;
```

`status` is `sent`, `failed` (with the SMTP error in `error`) or `skipped` (time budget exceeded).
Admins can also read it over the API: `GET /api/email_log.php?status=failed&limit=100` (optionally
`&ticket_id=N`); the response includes `"configured": true|false`.

Failures are also written to the container's PHP error log. Typical causes: wrong App Password
(`Could not authenticate`), `MAIL_FROM` not allowed for the account, or port 587 blocked by the host.

## Testing

```
php tests/email_templates_test.php          # renders every template with fake data and self-checks
php tests/email_templates_test.php --html   # also dumps the HTML bodies
```

The script refuses to run outside the CLI, needs no database or SMTP server and sends nothing.

## Notes for maintainers

* `server/api/tickets.php` and `server/api/comments.php` started with a UTF-8 byte-order mark before
  `<?php`, which made PHP send the response headers before any code ran (so `http_response_code()` and
  `Content-Type` silently did nothing, and headers could not be set for the early flush). The BOM was
  removed in the same change.
* PHPMailer is loaded directly by `config/mailer.php`, not through Composer's autoloader, so
  `composer.json`/`composer.lock` are unchanged.
