<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/notification_helper.php";
require_once __DIR__ . "/../helpers/activity_log_helper.php";


require_once __DIR__ . "/../helpers/access_helper.php";

figurify_require_role("staff");

$figurifyRole = "staff";

// tinatanggihan ang order request bago pa ito ma-quote (habang "pending" pa lang)

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;

$declineReasonLabels = [
    "incomplete_order_details" => "Incomplete Order Details",
    "unable_to_fulfill" => "Unable to Fulfill the Order",
    "out_of_scope" => "Outside Our Services",
];

$declineReasonKey = isset($_POST["decline_reason"]) ? trim($_POST["decline_reason"]) : "";

if ($declineReasonKey === "other") {

    // Custom na dahilan mula sa textarea — i-trim at limitahan ang haba
    $declineReasonOther = isset($_POST["decline_reason_other"]) ? trim($_POST["decline_reason_other"]) : "";
    $declineReasonOther = mb_substr($declineReasonOther, 0, 190);

    $declineReasonLabel = ($declineReasonOther !== "") ? $declineReasonOther : null;

} else {

    $declineReasonLabel = $declineReasonLabels[$declineReasonKey] ?? null;

}

if ($order_id > 0 && $declineReasonLabel !== null) {

    $ownerStmt = $conn->prepare(
        "SELECT user_id, status
         FROM orders
         WHERE order_id = ?"
    );

    $ownerStmt->bind_param("i", $order_id);
    $ownerStmt->execute();

    $ownerRow = $ownerStmt->get_result()->fetch_assoc();
    $ownerStmt->close();

    if ($ownerRow && strtolower($ownerRow["status"]) === "pending") {

        $customer_id = (int) $ownerRow["user_id"];

        $updateStmt = null;

        try {

            // Subukan i-save ang decline_reason (baka hindi pa na-migrate ang column) —
            // huwag na lang isama kaysa bumagsak ang buong action.
            $updateStmt = $conn->prepare(
                "UPDATE orders
                 SET status = 'cancelled', decline_reason = ?
                 WHERE order_id = ? AND status = 'pending'"
            );

            if ($updateStmt === false) {
                throw new \mysqli_sql_exception("prepare() returned false");
            }

            $updateStmt->bind_param("si", $declineReasonLabel, $order_id);

        } catch (\Throwable $e) {
            $updateStmt = null;
        }

        if ($updateStmt === null) {

            $updateStmt = $conn->prepare(
                "UPDATE orders
                 SET status = 'cancelled'
                 WHERE order_id = ? AND status = 'pending'"
            );

            $updateStmt->bind_param("i", $order_id);

        }

        $updateStmt->execute();
        $updateStmt->close();

        $message = "Unfortunately, we're unable to proceed with order #" . $order_id
            . ". Reason: " . $declineReasonLabel . ". "
            . "Please contact us if you believe this is a mistake.";

        figurify_notify_user($conn, $customer_id, $order_id, $message);

        figurify_log_activity(
            $conn,
            "order_rejected",
            "Declined order #" . $order_id . " — reason: " . $declineReasonLabel,
            $order_id
        );

        // I-notify din ang ibang Owner/Staff (hindi kasama ang nag-decline mismo)

        $actorLabel = ($figurifyRole === "owner") ? "the owner" : "staff";

        figurify_notify_staff(
            $conn,
            $order_id,
            "Order #" . $order_id . " was already declined by " . $actorLabel . ".",
            (int) $_SESSION["user_id"]
        );
    }

}

$conn->close();

header("Location: quotation.php?order_id=" . $order_id);
exit();
