<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/mailer_helper.php";
require_once __DIR__ . "/../helpers/notification_helper.php";
require_once __DIR__ . "/../helpers/activity_log_helper.php";


require_once __DIR__ . "/../helpers/access_helper.php";

figurify_require_role("admin");

// Shared logic ng owner/ at staff/decline_revision.php, role check na ang gumawa sa access_helper.

// "update_id" = ang progress update row na hiningian ng revision.
// "decline_reason" = dropdown ng common na dahilan (parehong pattern
// ng Quotation/Verification decline modal) — "staff_reply" pa rin ang
// column sa DB, pero ngayon galing sa dropdown + "Other" na textarea
// ang laman nito, hindi na basta free-text lang.

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$update_id = isset($_POST["update_id"]) ? (int) $_POST["update_id"] : 0;

$declineReasonLabels = [
    "unable_to_replicate" => "Unable to Match the Requested Change",
    "requires_additional_cost" => "Requires Additional Cost",
    "beyond_material_capability" => "Beyond Our Material/Process Capability",
    "would_delay_completion" => "Would Significantly Delay Completion",
];

$declineReasonKey = isset($_POST["decline_reason"]) ? trim($_POST["decline_reason"]) : "";

if ($declineReasonKey === "other") {

    // Custom na dahilan mula sa textarea — i-trim at limitahan ang haba
    $declineReasonOther = isset($_POST["decline_reason_other"]) ? trim($_POST["decline_reason_other"]) : "";
    $declineReasonOther = mb_substr($declineReasonOther, 0, 190);

    $staff_reply = ($declineReasonOther !== "") ? $declineReasonOther : "";

} else {

    $staff_reply = $declineReasonLabels[$declineReasonKey] ?? "";

}

$errorMsg = "";

if ($order_id <= 0 || $update_id <= 0) {
    $errorMsg = "Invalid order.";
}

if ($errorMsg === "" && $staff_reply === "") {
    $errorMsg = "Please pick a reason before declining a revision request.";
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


// Mark as "declined" + save the reason

if ($errorMsg === "") {

    $updateStmt = $conn->prepare(
        "UPDATE order_progress_updates
         SET customer_response = 'declined',
             staff_reply = ?,
             responded_at = NOW()
         WHERE update_id = ?"
    );

    $updateStmt->bind_param("si", $staff_reply, $update_id);
    $updateStmt->execute();
    $updateStmt->close();

    figurify_log_activity(
        $conn,
        "revision_declined",
        "Declined revision request for order #" . $order_id . " — reason: " . $staff_reply,
        $order_id
    );


    // In-system notification

    $notifyMessage = "We're unable to do the requested revision for order #"
        . $order_id . ". Reason: " . $staff_reply;

    $customerId = (int) $customer["user_id"];

    figurify_notify_user($conn, $customerId, $order_id, $notifyMessage, 0, null, false);


    // Email notification

    $emailBody = "
        <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:20px;'>
            <h2 style='color:#86365f;'>About Your Revision Request — Order #" . $order_id . "</h2>
            <p>Hello <strong>" . htmlspecialchars($customer["full_name"]) . "</strong>,</p>
            <p>We're sorry, but we're unable to make the requested revision for your order:</p>
            <p style='background:#fff5f8;padding:12px;border-radius:10px;'>" . nl2br(htmlspecialchars($staff_reply)) . "</p>
            <p>Please log in to your Clay and Stuff account and check <strong>My Orders</strong>
            if you have any questions about this.</p>
            <br>
            <p>Thank you,<br><strong>Clay and Stuff Team</strong></p>
        </div>
    ";

    $altBody = "We're unable to make the requested revision for order #"
        . $order_id . ". Reason: " . $staff_reply;

    figurify_send_mail(
        $customer["email"],
        $customer["full_name"],
        "About Your Revision Request — Order #" . $order_id . " — Clay and Stuff",
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
