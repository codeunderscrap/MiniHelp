# Conditional custom questions

Per-department custom questions (Settings -> Custom Questions) support more types, ordering,
help text and "show only when" conditions.

## Question properties (`form_fields`)

| Column | Meaning |
| --- | --- |
| `field_type` | `text`, `textarea`, `dropdown`, `date` (YYYY-MM-DD), `number` (>= 0) |
| `help_text` | Optional description under the label; line breaks are kept |
| `sort_order` | Questions are shown ordered by `sort_order`, then `id`. A new question is appended (max + 1) unless `sort_order` is sent |
| `show_if_field_id` | Another question of the same department, or `null` (always shown) |
| `show_if_value` | Accepted parent answers separated by `\|`, e.g. `Travel Booking\|Both` |

The four new columns are added by an idempotent request-time migration
(`ensure_form_fields_schema()` in `server/config/form_fields.php`, run by `fields.php` and `tickets.php`);
`field_type` is widened from ENUM to `VARCHAR(20)`. `server/setup.sql` has the same shape for fresh installs.

## Visibility rule (identical on client and server)

A question is **visible** iff `show_if_field_id` is null, **or** its parent question is visible **and** the
parent's answer (trimmed; missing = empty) equals one of `show_if_value` split on `|` (each trimmed,
case-sensitive exact match). It is recursive: a hidden parent hides its children. A missing parent or a cycle
hides the question.

* Client: `client/src/lib/fieldVisibility.ts`, used by `CreateTicket.tsx`. Hidden questions are not rendered, are
  never `required`, have their answer cleared, and are not submitted.
* Server: `form_field_visibility()` in `server/config/form_fields.php`, used by `POST /api/tickets.php`: required
  only when visible, answers for hidden questions are dropped, dates must be `YYYY-MM-DD`, numbers numeric.
* Test of the rule: `php server/tests/form_fields_visibility_test.php` (CLI only).

## API: `/api/fields.php` (writes need a manager role)

* `GET ?department_id=N` -> `{success, data: [ {id, department_id, field_label, field_type, is_required, options, help_text, sort_order, show_if_field_id, show_if_value}, ... ]}` ordered by `sort_order, id`.
* `POST` body:
  ```json
  {
    "department_id": 7,
    "field_label": "Primary mode of travel",
    "field_type": "dropdown",
    "is_required": true,
    "options": ["Flight", "Train", "Bus", "Car"],
    "help_text": "Line one\nLine two",
    "sort_order": 12,
    "show_if_field_id": 101,
    "show_if_value": "Travel Booking|Both"
  }
  ```
  Required: `department_id`, `field_label`, `field_type`. Optional: everything else (`is_required` default false;
  `sort_order` default = append; `show_if_*` default null). `options` may be an array, a JSON array string, or a
  comma separated string; it is required (at least one) for `dropdown` and ignored for other types; options may
  not contain `|`. Returns `{success, message, id}` (new question id, so conditions can reference it).
* `PUT` body: `{ "id": N, ...any of the POST keys except department_id }`. Keys that are omitted keep their
  current value. Send `"show_if_field_id": null` to remove a condition.
* `PUT` body `{ "reorder": [id, id, ...] }` sets `sort_order` to 1..n in that order (all ids from one department).
* `DELETE ?id=N` deletes a question; questions that depended on it become unconditional.

Validation (HTTP 400): type whitelist, parent must exist in the same department, not the question itself, no
cycles, `show_if_value` required when a parent is set.
