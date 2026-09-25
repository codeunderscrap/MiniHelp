<?php
include_once '../config/db.php';
$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    // 1. Wipe existing categories to avoid duplicates
    $db->exec("DELETE FROM categories");
    $db->exec("ALTER TABLE categories AUTO_INCREMENT = 1");

    // 2. Fetch Department IDs
    $stmt = $db->query("SELECT id, code FROM departments");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $deptMap = [];
    foreach ($departments as $dept) {
        $deptMap[$dept['code']] = $dept['id'];
    }

    // 3. Define genuine categories for each department
    $seedData = [
        'IT' => [
            'Network & Internet Issues',
            'Hardware Failure (Laptop/Desktop)',
            'Software Installation & Access',
            'Email & Login Problems'
        ],
        'HR' => [
            'Salary & Payroll Queries',
            'Leave & Attendance Issues',
            'Company Policy Clarifications',
            'Onboarding / Offboarding'
        ],
        'SALES' => [
            'CRM Access & Lead Assignment',
            'Incentive & Target Queries',
            'Client Meeting Approvals',
            'Promotional Material Request'
        ],
        'LOG' => [
            'Vendor Payment Delay',
            'Stock / Inventory Shortage',
            'Delivery Tracking & Issues',
            'Equipment Procurement Request'
        ]
    ];

    // 4. Insert categories
    $insertStmt = $db->prepare("INSERT INTO categories (name, department_id) VALUES (?, ?)");
    
    foreach ($seedData as $code => $categories) {
        if (isset($deptMap[$code])) {
            $deptId = $deptMap[$code];
            foreach ($categories as $categoryName) {
                $insertStmt->execute([$categoryName, $deptId]);
            }
        }
    }

    $db->commit();
    echo json_encode(["success" => true, "message" => "Genuine categories populated successfully!"]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
