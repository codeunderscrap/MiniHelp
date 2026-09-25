<?php
require_once '../config/db.php';
$database = new Database();
$db = $database->getConnection();

try {
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $db->exec("DROP TABLE IF EXISTS form_fields");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    $db->exec("
        CREATE TABLE form_fields (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            field_label VARCHAR(255) NOT NULL,
            field_type VARCHAR(50) DEFAULT 'text',
            is_required TINYINT(1) DEFAULT 0,
            options TEXT,
            FOREIGN KEY (category_id) REFERENCES ticket_categories(id) ON DELETE CASCADE
        )
    ");
    
    // Seed fields directly to Categories!
    $stmt = $db->query("SELECT id, name FROM ticket_categories");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $catMap = [];
    foreach ($categories as $cat) {
        $catMap[$cat['name']] = $cat['id'];
    }

    $seedFields = [
        'Hardware Failure (Laptop/Desktop)' => [
            ['label' => 'Operating System (OS)', 'type' => 'text', 'required' => 1],
            ['label' => 'Device Asset Tag / Serial Number', 'type' => 'text', 'required' => 1]
        ],
        'Software Installation & Access' => [
            ['label' => 'Software Name', 'type' => 'text', 'required' => 1],
            ['label' => 'Current Version (if known)', 'type' => 'text', 'required' => 0]
        ],
        'Leave & Attendance Issues' => [
            ['label' => 'Employee ID', 'type' => 'text', 'required' => 1],
            ['label' => 'Dates of Leave (From - To)', 'type' => 'text', 'required' => 1]
        ],
        'CRM Access & Lead Assignment' => [
            ['label' => 'CRM Module/Section', 'type' => 'text', 'required' => 1]
        ],
        'Delivery Tracking & Issues' => [
            ['label' => 'Order / PO Number', 'type' => 'text', 'required' => 1],
            ['label' => 'Logistics Partner Name', 'type' => 'text', 'required' => 0]
        ]
    ];

    $insertStmt = $db->prepare("INSERT INTO form_fields (category_id, field_label, field_type, is_required) VALUES (?, ?, ?, ?)");
    
    foreach ($seedFields as $catName => $fields) {
        if (isset($catMap[$catName])) {
            $catId = $catMap[$catName];
            foreach ($fields as $field) {
                $insertStmt->execute([$catId, $field['label'], $field['type'], $field['required']]);
            }
        }
    }

    echo json_encode(["success" => true, "message" => "form_fields table migrated and seeded to use category_id"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
