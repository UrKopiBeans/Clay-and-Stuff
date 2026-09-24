<?php

/* Customer notification polling endpoint — kaparehong pattern ng
   Notification/staff_bell.php (owner/staff), pero para sa naka-login
   na customer. Ginagamit lang para malaman kung may bagong
   notification (walang bell icon/badge dito, "list" action lang ang
   ginagamit ng polling script sa Shared/navbar.php). */

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

header("Content-Type: application/json");


// must be logged in

if (!isset($_SESSION["user_id"])) {

    http_response_code(403);
    echo json_encode(["error" => "forbidden"]);
    exit();

}

require_once __DIR__ . "/../Admin/database.php";

$user_id = (int) $_SESSION["user_id"];


// latest notifications + unread count (list lang ang kailangan dito)

$stmt = $conn->prepare(
    "SELECT notification_id, order_id, message, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 20"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$notifications = [];
$unreadCount   = 0;

while ($row = $result->fetch_assoc()) {

    $row["notification_id"] = (int) $row["notification_id"];
    $row["order_id"]        = $row["order_id"] !== null ? (int) $row["order_id"] : null;
    $row["is_read"]         = (int) $row["is_read"];

    if (!$row["is_read"]) {
        $unreadCount++;
    }

    $notifications[] = $row;

}

$stmt->close();
$conn->close();

echo json_encode([
    "notifications" => $notifications,
    "unread_count"  => $unreadCount,
]);
