<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/mailer_helper.php";
require_once __DIR__ . "/../helpers/notification_helper.php";
require_once __DIR__ . "/../helpers/activity_log_helper.php";


require_once __DIR__ . "/../helpers/access_helper.php";

figurify_require_role("admin");

// Shared logic ng owner/ at staff/accept_revision.php, role check na ang gumawa sa access_helper.

// "update_id" = ang progress update row na hiningian ng "Revision" ng customer.
// Accept lang ito, wala pang bagong photo — sa "Send Update" pa lalabas ang binago.

$order_id  = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$update_id = isset($_POST["update_id"]) ? (int) $_POST["update_id"] : 0;

$errorMsg = "";

if ($order_id <= 0 || $update_id <= 0) {
    $errorMsg = "Invalid order.";
}


// Siguraduhing naka-"revision" pa talaga itong update para sa order na ito

$customer = null;

if ($errorMsg === "") {

    $stmt = $conn->prepare(
        "SELECT pu.update_id, o.order_id, u.user_id, u.full_name, u.email
         FROM order_progress_updates pu
         JOIN orders o ON o.order_id = pu.order_id
         JOIN users u ON u.user_id = o.user_id
         WHERE pu.update_id = ?
           AND pu.order_id = ?
           AND pu.customer_response = 'revision'
         LIMIT 1"
    );

    $stmt->bind_param("ii", $update_id, $order_id);
    $stmt->execute();

    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$customer) {
        $errorMsg = "This revision request was not found (baka nasagot na o na-update na ulit ang order).";
    }

}


// Mark as "in progress" (tinanggap na, ginagawa na)

if ($errorMsg === "") {

    $updateStmt = $conn->prepare(
        "UPDATE order_progress_updates
         SET customer_response = 'in_progress',
             responded_at = NOW()
         WHERE update_id = ?"
    );

    $updateStmt->bind_param("i", $update_id);
    $updateStmt->execute();
    $updateStmt->close();

    figurify_log_activity(
        $conn,
        "revision_accepted",
        "Accepted revision request for order #" . $order_id,
        $order_id
    );


    // In-system notification

    $notifyMessage = "Good news! We've accepted your revision request for "
        . "order #" . $order_id . " and we're working on it now.";

    $customerId = (int) $customer["user_id"];

    figurify_notify_user($conn, $customerId, $order_id, $notifyMessage, 0, null, false);


    // Email notification

    $emailBody = "
        <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:20px;'>
            <h2 style='color:#86365f;'>Revision Accepted — Order #" . $order_id . "</h2>
            <p>Hello <strong>" . htmlspecialchars($customer["full_name"]) . "</strong>,</p>
            <p>Good news! We've accepted your revision request and we're working on it now.
            We'll send you another photo update once it's done.</p>
            <br>
            <p>Thank you,<br><strong>Clay and Stuff Team</strong></p>
        </div>
    ";

    $altBody = "We've accepted your revision request for order #" . $order_id
        . " and we're working on it now.";

    figurify_send_mail(
        $customer["email"],
        $customer["full_name"],
        "Revision Accepted — Order #" . $order_id . " — Clay and Stuff",
        $emailBody,
        $altBody
    );

}

$conn->close();


// Back to active booking page

$redirect = "active-booking.php?order_id=" . $order_id;

if ($errorMsg !== "") {
    $redirect .= "&progress_error=" . urlencode($errorMsg);
}

header("Location: " . $redirect);
exit();
