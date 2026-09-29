<?php
require_once 'config/db.php';
$database = new Database();
$db = $database->getConnection();
$db->exec("DELETE FROM form_fields WHERE field_label LIKE '%Operating System%' OR field_label LIKE '%Error Message%'");
echo "Deleted";
?>
