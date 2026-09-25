<?php
require_once '../config/db.php';
$db = (new Database())->getConnection();
$stmt = $db->query('SHOW TABLES');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
