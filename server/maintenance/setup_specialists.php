<?php
include_once '../config/db.php';
$database = new Database();
$db = $database->getConnection();

try {
    // 1. Wipe all tickets, history, comments, attachments, etc.
    $tablesToWipe = [
        'ticket_attachments',
        'ticket_history',
        'ticket_custom_values',
        'comments',
        'notifications',
        'tickets'
    ];
    
    foreach ($tablesToWipe as $table) {
        $db->exec("DELETE FROM $table");
        $db->exec("ALTER TABLE $table AUTO_INCREMENT = 1");
    }

    // 2. Wipe all users EXCEPT admin (MUST be done before deleting departments)
    $db->exec("DELETE FROM users WHERE role != 'admin'");
    
    // 3. Wipe departments (now safe since users are gone)
    $db->exec("DELETE FROM departments");
    $db->exec("ALTER TABLE departments AUTO_INCREMENT = 1");

    // 4. Create the 4 standard departments
    $depts = [
        ['name' => 'Sales & BD', 'code' => 'SALES'],
        ['name' => 'HR', 'code' => 'HR'],
        ['name' => 'IT', 'code' => 'IT'],
        ['name' => 'Logistics & Purchase', 'code' => 'LOG']
    ];

    $deptIds = [];
    $stmtDept = $db->prepare("INSERT INTO departments (name, code) VALUES (?, ?)");
    foreach ($depts as $dept) {
        $stmtDept->execute([$dept['name'], $dept['code']]);
        $deptIds[$dept['code']] = $db->lastInsertId();
    }

    // 5. Create 1 Specialist (agent) for each department
    $defaultPassword = password_hash('password123', PASSWORD_BCRYPT);
    $stmtUser = $db->prepare("INSERT INTO users (name, email, password_hash, role, department_id) VALUES (?, ?, ?, 'agent', ?)");

    $users = [
        ['name' => 'Sales Specialist', 'email' => 'sales@m-mines.in', 'dept_id' => $deptIds['SALES']],
        ['name' => 'HR Specialist', 'email' => 'hr@m-mines.in', 'dept_id' => $deptIds['HR']],
        ['name' => 'IT Specialist', 'email' => 'it@m-mines.in', 'dept_id' => $deptIds['IT']],
        ['name' => 'Logistics Specialist', 'email' => 'logistics@m-mines.in', 'dept_id' => $deptIds['LOG']]
    ];

    foreach ($users as $u) {
        $stmtUser->execute([$u['name'], $u['email'], $defaultPassword, $u['dept_id']]);
    }

    echo "<h1>Success!</h1>";
    echo "Database cleaned and 4 Departments with their Specialists created successfully. <br> Password for all specialists is <b>password123</b>";

} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "Detail: " . $e->getMessage();
}
?>
