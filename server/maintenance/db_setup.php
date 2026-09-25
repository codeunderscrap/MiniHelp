<?php
require_once '../config/db.php';

$database = new Database();
$pdo = $database->getConnection();

header('Content-Type: application/json');

try {
    $setup_sql = file_get_contents('../setup.sql');
    $pdo->exec($setup_sql);

    $update_push_sql = file_get_contents('../update_push.sql');
    $pdo->exec($update_push_sql);

    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) NULL;");
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }

    echo json_encode(['success' => true, 'message' => 'Database tables and seed data created successfully!']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
