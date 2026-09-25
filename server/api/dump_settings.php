<?php
require_once '../config/db.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SELECT * FROM system_settings");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
