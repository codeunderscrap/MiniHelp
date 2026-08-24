<?php
// api/subscribe.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../config/db.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->user_id) && !empty($data->subscription)) {
    try {
        $endpoint = $data->subscription->endpoint;
        $p256dh = $data->subscription->keys->p256dh;
        $auth = $data->subscription->keys->auth;
        $user_id = $data->user_id;

        // Check if subscription already exists for this endpoint
        $check = "SELECT id FROM push_subscriptions WHERE endpoint = :endpoint";
        $checkStmt = $db->prepare($check);
        $checkStmt->bindParam(":endpoint", $endpoint);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            echo json_encode(["success" => true, "message" => "Subscription already exists."]);
            exit;
        }

        $query = "INSERT INTO push_subscriptions SET user_id=:uid, endpoint=:endpoint, p256dh=:p256dh, auth=:auth";
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(":uid", $user_id);
        $stmt->bindParam(":endpoint", $endpoint);
        $stmt->bindParam(":p256dh", $p256dh);
        $stmt->bindParam(":auth", $auth);
        
        if($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Successfully subscribed to push notifications."]);
        } else {
            http_response_code(503);
            echo json_encode(["success" => false, "error" => "Unable to save subscription."]);
        }
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Incomplete data."]);
}
?>
