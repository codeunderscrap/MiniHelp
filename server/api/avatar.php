<?php
// api/avatar.php
require_once '../config/cors.php';
setup_cors();

include_once '../config/db.php';

$id = isset($_GET['id']) ? $_GET['id'] : null;

if ($id) {
    // Find the latest avatar for this user
    $files = glob('uploads/avatars/avatar_' . $id . '_*.*');
    
    if ($files && count($files) > 0) {
        // Sort to get the most recent (highest timestamp)
        rsort($files);
        $filepath = $files[0];
        
        if (file_exists($filepath)) {
            $mime = mime_content_type($filepath);
            header("Content-Type: $mime");
            header("Content-Length: " . filesize($filepath));
            readfile($filepath);
            exit;
        }
    }
}

// Default fallback avatar if not found
header("Content-Type: image/svg+xml");
echo '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="#888" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/></svg>';
?>
