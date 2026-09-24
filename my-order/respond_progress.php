<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/notification_helper.php";


/* must be logged in (customer) */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../Login/Login.php");
    exit();
}

$user_id = $_SESSION["user_id"];


/* read posted fields */

$update_id     = isset($_POST["update_id"]) ? (int) $_POST["update_id"] : 0;
$order_id      = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$response      = isset($_POST["response"]) ? strtolower(trim($_POST["response"])) : "";
$revision_note = trim($_POST["revision_note"] ?? "");

$allowedResponses = ["approved", "revision"];


/* make sure this update belongs sa customer's order at wala pang sagot (avoid double-submit overwrite) */

if ($update_id > 0 && in_array($response, $allowedResponses, true)) {

    if ($response === "revision" && $revision_note === "") {

        // walang laman ang revision note — huwag i-save,
        // babalik na lang sa page, ipapakita ulit ang modal.

    } else {

        $stmt = $conn->prepare(
            "SELECT pu.update_id
             FROM order_progress_updates pu
             JOIN orders o ON o.order_id = pu.order_id
             WHERE pu.update_id = ?
               AND o.user_id = ?
               AND pu.customer_response = 'pending'
             LIMIT 1"
        );

        $stmt->bind_param("ii", $update_id, $user_id);
        $stmt->execute();

        $found = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($found) {

            if ($response === "approved") {

                $updateStmt = $conn->prepare(
                    "UPDATE order_progress_updates
                     SET customer_response = 'approved',
                         revision_note = NULL,
                         responded_at = NOW()
                     WHERE update_id = ?"
                );

                $updateStmt->bind_param("i", $update_id);

            } else {

                $updateStmt = $conn->prepare(
                    "UPDATE order_progress_updates
                     SET customer_response = 'revision',
                         revision_note = ?,
                         responded_at = NOW()
                     WHERE update_id = ?"
                );

                $updateStmt->bind_param("si", $revision_note, $update_id);

            }

            $updateStmt->execute();
            $updateStmt->close();


            /* notify owner + staff — sumagot ang customer sa progress update */

            if ($response === "approved") {

                $staffMessage = "Customer approved the progress update for order #" . $order_id . ".";

            } else {

                $staffMessage = "Customer requested a revision for order #" . $order_id . ".";

            }

            figurify_notify_staff($conn, $order_id, $staffMessage);

        }

    }

}

$conn->close();


/* back to my orders — diretso sa order na sinagutan (my-orders.php?order_id=...) */

$redirect = "my-orders.php";

if ($order_id > 0) {
    $redirect .= "?order_id=" . $order_id;
}

header("Location: " . $redirect);
exit();
