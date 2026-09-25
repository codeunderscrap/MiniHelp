<?php
require_once '../config/db.php';
$db = clone (new Database())->getConnection();
$db->query("DELETE FROM system_settings WHERE setting_key = 'rules'");
echo 'Fixed rules in DB';
?>
