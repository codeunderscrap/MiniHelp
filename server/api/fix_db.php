<?php
require_once '../config/db.php';
\ = (new Database())->getConnection();
\->query("DELETE FROM system_settings WHERE setting_key = 'rules'");
echo 'Fixed rules in DB';
?>
