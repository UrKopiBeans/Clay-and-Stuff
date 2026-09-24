<?php

/* polled by my-orders.php JS para malaman kung may nagbago sa status.
   returns JSON {order_id: "status"}. included din ang latest progress
   update id + response kasi may cases na hindi nagbabago ang o.status
   pero may bagong update (staff progress check-ins). */

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "not_logged_in"]);
    exit();
}

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/expire_helper.php";

$user_id = (int) $_SESSION["user_id"];


/* i-check muna kung may expired quotations (2-day deadline) bago kunin ang statuses */

figurify_expire_old_quotes($conn);


/* kunin ang latest status ng bawat order, kasama ang latest progress update
   (update_id + customer_response) para ma-detect kahit hindi nagbago ang o.status */

$stmt = $conn->prepare(
    "SELECT o.order_id, o.status,
        (SELECT pu.update_id
         FROM order_progress_updates pu
         WHERE pu.order_id = o.order_id
         ORDER BY pu.created_at DESC, pu.update_id DESC
         LIMIT 1) AS latest_update_id,
        (SELECT pu.customer_response
         FROM order_progress_updates pu
         WHERE pu.order_id = o.order_id
         ORDER BY pu.created_at DESC, pu.update_id DESC
         LIMIT 1) AS latest_response
     FROM orders o
     WHERE o.user_id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$statuses = [];

while ($row = $result->fetch_assoc()) {

    $pollKey = $row["status"]
        . "::u" . (int) ($row["latest_update_id"] ?? 0)
        . "::" . (string) ($row["latest_response"] ?? "");

    $statuses[(string) $row["order_id"]] = $pollKey;
}

$stmt->close();
$conn->close();

echo json_encode($statuses);
