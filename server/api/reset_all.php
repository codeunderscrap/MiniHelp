<?php
// api/reset_all.php
require_once '../config/cors.php';
setup_cors();

include_once '../config/db.php';
$database = new Database();
$db = $database->getConnection();

try {
    // 1. Delete all ticket-related data (ignore if tables don't exist yet)
    $tablesToEmpty = [
        "ticket_activity_log",
        "ticket_attachments",
        "ticket_comments",
        "notifications",
        "tickets",
        "ticket_categories",
        "ticket_custom_fields"
    ];

    foreach ($tablesToEmpty as $table) {
        try {
            $db->exec("DELETE FROM $table");
        } catch (PDOException $e) {}
    }
    
    // 3. Clear users' department_id BEFORE deleting departments
    try {
        $db->exec("UPDATE users SET department_id = NULL");
    } catch (PDOException $e) {}

    // Now delete departments
    try {
        $db->exec("DELETE FROM departments");
    } catch (PDOException $e) {}
    
    // 6. Re-seed default departments (Excluding Finance)
    $depts = [
        ['IT Support', 'IT', 'Computers, Access, Networks'],
        ['Human Resources', 'HR', 'Payroll, Leaves, Onboarding'],
        ['Facilities', 'FAC', 'Building, Maintenance, Supplies']
    ];
    
    $stmt = $db->prepare("INSERT INTO departments (name, code, description) VALUES (?, ?, ?)");
    foreach ($depts as $dept) {
        $stmt->execute($dept);
    }
    
    // Also re-seed basic categories for IT and HR just to be helpful
    $itId = $db->query("SELECT id FROM departments WHERE code = 'IT'")->fetchColumn();
    if ($itId) {
        $db->exec("INSERT INTO ticket_categories (department_id, name) VALUES ($itId, 'Software')");
        $db->exec("INSERT INTO ticket_categories (department_id, name) VALUES ($itId, 'Hardware')");
        $db->exec("INSERT INTO ticket_categories (department_id, name) VALUES ($itId, 'Network')");
    }
    
    $hrId = $db->query("SELECT id FROM departments WHERE code = 'HR'")->fetchColumn();
    if ($hrId) {
        $db->exec("INSERT INTO ticket_categories (department_id, name) VALUES ($hrId, 'Payroll')");
        $db->exec("INSERT INTO ticket_categories (department_id, name) VALUES ($hrId, 'Leaves')");
    }

    echo json_encode([
        "success" => true, 
        "message" => "Database successfully wiped and reset. All tickets deleted. Departments recreated (without Finance)."
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false, 
        "error" => "Reset failed: " . $e->getMessage()
    ]);
}
?>
