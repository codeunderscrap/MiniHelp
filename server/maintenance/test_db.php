<?php
include_once '../config/db.php';
$database = new Database();
$db = $database->getConnection();
$stmt = $db->query("SELECT * FROM ticket_attachments ORDER BY id DESC LIMIT 5");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res);
?>
