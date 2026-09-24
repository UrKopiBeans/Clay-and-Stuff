<?php

/* Owner/Staff notification bell endpoint — shared ng JS sa
   owner/ownerdashboard.php AT staff/staffdashboard.php.
   ?action=list (GET, default) = latest notifications + unread
   count; action=mark_read (POST) = mark all as read. Same
   "notifications" table gaya ng customer-facing notifications. */

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

header("Content-Type: application/json");


// must be logged in as admin (owner) or staff

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    !in_array($_SESSION["role"], ["admin", "staff"], true)
) {

    http_response_code(403);
    echo json_encode(["error" => "forbidden"]);
    exit();

}

require_once __DIR__ . "/../Admin/database.php";

$user_id = (int) $_SESSION["user_id"];

$action = $_POST["action"] ?? ($_GET["action"] ?? "list");


// action: mark all as read

if ($action === "mark_read") {

    $markStmt = $conn->prepare(
        "UPDATE notifications
         SET is_read = 1
         WHERE user_id = ? AND is_read = 0"
    );

    $markStmt->bind_param("i", $user_id);
    $markStmt->execute();
    $markStmt->close();

    $conn->close();

    echo json_encode(["success" => true]);
    exit();

}


// action: list (default) — latest notifications + unread count

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
