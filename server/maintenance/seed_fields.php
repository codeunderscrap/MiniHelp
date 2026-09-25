<?php
include_once '../config/db.php';
$database = new Database();
$db = $database->getConnection();

try {
    // 1. Wipe existing fields to avoid orphans/duplicates
    $db->exec("DELETE FROM form_fields");
    $db->exec("ALTER TABLE form_fields AUTO_INCREMENT = 1");

    // 2. Fetch Department IDs
    $stmt = $db->query("SELECT id, code FROM departments");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $deptMap = [];
    foreach ($departments as $dept) {
        $deptMap[$dept['code']] = $dept['id'];
    }

    // 3. Define genuine Custom Questions (Fields) for each department
    $seedFields = [
        'IT' => [
            ['label' => 'Operating System (OS)', 'type' => 'text', 'required' => 1],
            ['label' => 'Device Asset Tag / Serial Number', 'type' => 'text', 'required' => 0]
        ],
        'HR' => [
            ['label' => 'Employee ID', 'type' => 'text', 'required' => 1],
            ['label' => 'Related Month / Year', 'type' => 'text', 'required' => 0]
        ],
        'SALES' => [
            ['label' => 'Client / Company Name', 'type' => 'text', 'required' => 1],
            ['label' => 'Region / Territory', 'type' => 'text', 'required' => 0]
        ],
        'LOG' => [
            ['label' => 'Order / PO Number', 'type' => 'text', 'required' => 1],
            ['label' => 'Vendor Name', 'type' => 'text', 'required' => 0]
        ]
    ];

    // 4. Insert Custom Fields
    $insertStmt = $db->prepare("INSERT INTO form_fields (department_id, field_label, field_type, is_required) VALUES (?, ?, ?, ?)");
    
    foreach ($seedFields as $code => $fields) {
        if (isset($deptMap[$code])) {
            $deptId = $deptMap[$code];
            foreach ($fields as $field) {
                $insertStmt->execute([$deptId, $field['label'], $field['type'], $field['required']]);
            }
        }
    }

    echo json_encode(["success" => true, "message" => "Department Specific Questions (Custom Fields) restored successfully!"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>



