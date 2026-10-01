<?php
// maintenance/migrate_schema.php
require_once __DIR__ . '/../config/db.php';

$database = new Database();
$db = $database->getConnection();

// Add category to tickets
try {
    $db->exec("ALTER TABLE tickets ADD COLUMN category VARCHAR(100) NULL AFTER priority");
    echo "Added category to tickets.\n";
} catch (Exception $e) {
    echo "Category column might already exist: " . $e->getMessage() . "\n";
}

// Add category_name to form_fields
try {
    $db->exec("ALTER TABLE form_fields ADD COLUMN category_name VARCHAR(100) NULL AFTER field_type");
    echo "Added category_name to form_fields.\n";
} catch (Exception $e) {
    echo "Category_name column might already exist: " . $e->getMessage() . "\n";
}

// Automatically remove Operating System
try {
    $db->exec("DELETE FROM form_fields WHERE field_label LIKE '%Operating System%'");
    echo "Removed Operating System field.\n";
} catch (Exception $e) {
    echo "Failed to remove Operating System: " . $e->getMessage() . "\n";
}
?>