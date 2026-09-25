<?php
require_once '../config/db.php';
$db = (new Database())->getConnection();
$stmt = $db->query('SELECT * FROM ticket_custom_values LIMIT 20');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
