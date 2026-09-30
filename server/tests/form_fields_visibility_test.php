<?php
// CLI-only test for the conditional-question visibility rule:  php server/tests/form_fields_visibility_test.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(); }
require_once __DIR__ . '/../config/form_fields.php';

$fields = [
    ['id' => 1, 'show_if_field_id' => null, 'show_if_value' => null],              // Booking Type (dropdown)
    ['id' => 2, 'show_if_field_id' => 1,    'show_if_value' => 'Travel Booking|Both'], // Journey section
    ['id' => 3, 'show_if_field_id' => 2,    'show_if_value' => 'Flight'],           // Flight questions (child of 2)
    ['id' => 4, 'show_if_field_id' => 1,    'show_if_value' => ' Stay Booking | Both '], // trimmed tokens
    ['id' => 5, 'show_if_field_id' => 99,   'show_if_value' => 'x'],                // missing parent
    ['id' => 6, 'show_if_field_id' => 7,    'show_if_value' => 'a'],                // cycle 6 <-> 7
    ['id' => 7, 'show_if_field_id' => 6,    'show_if_value' => 'a'],
    ['id' => 8, 'show_if_field_id' => 1,    'show_if_value' => ''],                 // no accepted values
];

$cases = [
    'no answers' => [[], [1 => true, 2 => false, 3 => false, 4 => false, 5 => false, 6 => false, 7 => false, 8 => false]],
    'stay' => [[1 => 'Stay Booking'], [1 => true, 2 => false, 3 => false, 4 => true, 5 => false, 6 => false, 7 => false, 8 => false]],
    'travel, no mode' => [[1 => 'Travel Booking'], [1 => true, 2 => true, 3 => false, 4 => false, 5 => false, 6 => false, 7 => false, 8 => false]],
    'travel + flight' => [[1 => 'Travel Booking', 2 => 'Flight'], [1 => true, 2 => true, 3 => true, 4 => false, 5 => false, 6 => false, 7 => false, 8 => false]],
    'hidden parent hides child even with stale answer' => [[1 => 'Stay Booking', 2 => 'Flight'], [1 => true, 2 => false, 3 => false, 4 => true, 5 => false, 6 => false, 7 => false, 8 => false]],
    'both' => [[1 => 'Both', 2 => 'Flight'], [1 => true, 2 => true, 3 => true, 4 => true, 5 => false, 6 => false, 7 => false, 8 => false]],
    'case sensitive' => [[1 => 'travel booking'], [1 => true, 2 => false, 3 => false, 4 => false, 5 => false, 6 => false, 7 => false, 8 => false]],
    'string keys and padded answer' => [['1' => ' Both '], [1 => true, 2 => true, 3 => false, 4 => true, 5 => false, 6 => false, 7 => false, 8 => false]],
];

$failed = 0;
foreach ($cases as $name => [$values, $expected]) {
    $got = form_field_visibility($fields, $values);
    if ($got !== $expected) {
        $failed++;
        echo "FAIL $name\n  expected " . json_encode($expected) . "\n  got      " . json_encode($got) . "\n";
    } else {
        echo "ok   $name\n";
    }
}
exit($failed ? 1 : 0);
