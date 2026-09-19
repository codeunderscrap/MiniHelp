<?php
require_once '../config/cors.php';
setup_cors();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['sound']) && $_FILES['sound']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/sounds/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileInfo = pathinfo($_FILES['sound']['name']);
        $ext = strtolower($fileInfo['extension']);
        $allowed = ['mp3', 'wav', 'ogg'];
        
        if (!in_array($ext, $allowed)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Invalid file format. Only MP3, WAV, OGG allowed."]);
            exit;
        }

        // To avoid saving thousands of files, just overwrite if it's named simply, or use a timestamp
        $fileName = 'sound_' . time() . '_' . rand(100,999) . '.' . $ext;
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['sound']['tmp_name'], $targetPath)) {
            // Return the direct path. In production, this might need to be served via a proxy if not accessible directly.
            // Since it's in the api folder, maybe we serve it? 
            // Wait, we can convert it to Base64 to survive Docker rebuilds, just like avatars!
            // Yes! Sound files are small (usually 10-100KB for short notifications).
            // Let's do Base64 for Docker persistence.
            $tmpName = $targetPath;
            $mime = mime_content_type($tmpName);
            if ($mime === false) $mime = 'audio/mpeg'; // fallback
            
            $fileData = file_get_contents($tmpName);
            $base64 = 'data:' . $mime . ';base64,' . base64_encode($fileData);
            
            // Clean up the temp file
            @unlink($targetPath);
            
            echo json_encode(["success" => true, "sound_url" => $base64]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Failed to move uploaded file"]);
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
