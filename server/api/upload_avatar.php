<?php
// api/upload_avatar.php
require_once '../config/cors.php';
setup_cors();

include_once '../config/db.php';
$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : null;
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "User ID is required"]);
        exit;
    }
    
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $fileInfo = pathinfo($_FILES['avatar']['name']);
        $ext = strtolower($fileInfo['extension']);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($ext, $allowed)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Invalid file format"]);
            exit;
        }

        // Convert to Base64 to survive Docker ephemeral file system wipes
        $tmpName = $_FILES['avatar']['tmp_name'];
        $mime = mime_content_type($tmpName);
        $fileData = file_get_contents($tmpName);
        
        // Optional: We can use GD to resize it, but base64 encoding it as-is works.
        // Assuming user avatars aren't massive.
        $base64 = 'data:' . $mime . ';base64,' . base64_encode($fileData);
        
        // Update DB with the base64 string directly
        try {
            // Ensure the column is large enough to hold Base64 strings
            $db->exec("ALTER TABLE users MODIFY avatar_url LONGTEXT");
            
            $stmt = $db->prepare("UPDATE users SET avatar_url = :av WHERE id = :id");
            $stmt->execute([":av" => $base64, ":id" => $user_id]);
            
            echo json_encode(["success" => true, "avatar_url" => $base64]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "No file uploaded or upload error"]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
}
?>
