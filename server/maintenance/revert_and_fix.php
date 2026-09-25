<?php
require_once '../config/db.php';
$database = new Database();
$db = $database->getConnection();

try {
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // 1. Recreate form_fields correctly
    $db->exec("DROP TABLE IF EXISTS form_fields");
    $db->exec("
        CREATE TABLE form_fields (
            id INT AUTO_INCREMENT PRIMARY KEY,
            department_id INT NOT NULL,
            field_label VARCHAR(255) NOT NULL,
            field_type VARCHAR(50) DEFAULT 'text',
            is_required TINYINT(1) DEFAULT 0,
            options TEXT,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
        )
    ");

    // 2. Fetch Departments
    $stmt = $db->query("SELECT id, code FROM departments");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $deptMap = [];
    foreach ($departments as $dept) {
        $deptMap[$dept['code']] = $dept['id'];
    }

    // 3. Fix Categories back to Broad Categories
    $db->exec("DELETE FROM ticket_categories");
    $db->exec("ALTER TABLE ticket_categories AUTO_INCREMENT = 1");
    
    $broadCategories = [
        'IT' => ['Software', 'Hardware', 'Network', 'Access/Permissions', 'Other'],
        'HR' => ['Payroll', 'Leave', 'Policy', 'Onboarding', 'Other'],
        'SALES' => ['CRM', 'Leads', 'Approvals', 'Incentives', 'Other'],
        'LOG' => ['Vendors', 'Inventory', 'Tracking', 'Procurement', 'Other']
    ];

    $insertCat = $db->prepare("INSERT INTO ticket_categories (department_id, name) VALUES (?, ?)");
    foreach ($broadCategories as $deptCode => $cats) {
        if (isset($deptMap[$deptCode])) {
            $did = $deptMap[$deptCode];
            foreach ($cats as $c) {
                $insertCat->execute([$did, $c]);
            }
        }
    }

    // 4. Seed Form Fields with Specific Dropdown Questions!
    $seedFields = [
        'IT' => [
            [
                'label' => 'Select Specific Issue', 
                'type' => 'dropdown', 
                'required' => 1, 
                'options' => json_encode(['Hardware Failure (Laptop/Desktop)', 'Software Installation', 'Network & Internet', 'Email & Login Problems', 'Other'])
            ],
            ['label' => 'Operating System (OS)', 'type' => 'text', 'required' => 0, 'options' => null],
            ['label' => 'Device Asset Tag', 'type' => 'text', 'required' => 0, 'options' => null]
        ],
        'HR' => [
            [
                'label' => 'HR Query Type',
                'type' => 'dropdown',
                'required' => 1,
                'options' => json_encode(['Salary/Payroll', 'Leave/Attendance', 'Company Policy', 'Resignation/Exit', 'Other'])
            ],
            ['label' => 'Employee ID', 'type' => 'text', 'required' => 1, 'options' => null]
        ],
        'SALES' => [
            [
                'label' => 'Sales Request Type',
                'type' => 'dropdown',
                'required' => 1,
                'options' => json_encode(['CRM Access', 'Lead Reassignment', 'Target Discrepancy', 'Client Meeting Approval', 'Other'])
            ],
            ['label' => 'Client Name (if applicable)', 'type' => 'text', 'required' => 0, 'options' => null]
        ],
        'LOG' => [
            [
                'label' => 'Logistics Issue',
                'type' => 'dropdown',
                'required' => 1,
                'options' => json_encode(['Vendor Payment', 'Stock Shortage', 'Delivery Delay', 'New Equipment Request', 'Other'])
            ],
            ['label' => 'PO / Order Number', 'type' => 'text', 'required' => 0, 'options' => null]
        ]
    ];

    $insertField = $db->prepare("INSERT INTO form_fields (department_id, field_label, field_type, is_required, options) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($seedFields as $deptCode => $fields) {
        if (isset($deptMap[$deptCode])) {
            $did = $deptMap[$deptCode];
            foreach ($fields as $field) {
                $insertField->execute([$did, $field['label'], $field['type'], $field['required'], $field['options']]);
            }
        }
    }

    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo json_encode(["success" => true, "message" => "Completely restored the correct layout: Broad Categories + Dropdown Custom Questions"]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
