<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/mailer_helper.php";
require_once __DIR__ . "/../helpers/notification_helper.php";


require_once __DIR__ . "/../helpers/access_helper.php";

figurify_require_role("staff");

$figurifyRole = "staff";

// same as owner/send_progress_update.php, role check + $figurifyRole
// (used for the sent_by_role column) come from the entry file

$order_id      = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$staff_message = trim($_POST["staff_message"] ?? "");
$is_final      = isset($_POST["is_final"]) && $_POST["is_final"] === "1";

$errorMsg = "";


if ($order_id <= 0) {
    $errorMsg = "Invalid order.";
}


// Validate the image
if ($errorMsg === "") {

    if (!isset($_FILES["progress_image"]) || $_FILES["progress_image"]["error"] !== UPLOAD_ERR_OK) {

        $errorMsg = "Please choose a photo to send.";

    } else {

        $ext = strtolower(
            pathinfo($_FILES["progress_image"]["name"], PATHINFO_EXTENSION)
        );

        $allowedExt = ["jpg", "jpeg", "png", "gif", "webp"];

        if (!in_array($ext, $allowedExt, true)) {
            $errorMsg = "Only JPG, PNG, GIF, or WEBP images are allowed.";
        }

    }

}


// Make sure the order exists + get the customer
$customer = null;

if ($errorMsg === "") {

    $stmt = $conn->prepare(
        "SELECT o.order_id, u.user_id, u.full_name, u.email
         FROM orders o
         JOIN users u ON u.user_id = o.user_id
         WHERE o.order_id = ?
         LIMIT 1"
    );

    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    $customer = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$customer) {
        $errorMsg = "Order not found.";
    }

}


// block once final photo was already sent, backend backup for the
// "Send Update" button being hidden in the UI

if ($errorMsg === "") {

    $finalCheckStmt = $conn->prepare(
        "SELECT update_id
         FROM order_progress_updates
         WHERE order_id = ? AND is_final = 1
         LIMIT 1"
    );

    $finalCheckStmt->bind_param("i", $order_id);
    $finalCheckStmt->execute();

    $alreadyFinal = $finalCheckStmt->get_result()->fetch_assoc();
    $finalCheckStmt->close();

    if ($alreadyFinal) {
        $errorMsg = "The final photo was already sent for this order. Waiting for Ship Out.";
    }

}


// block new update while latest one is still "pending" (customer
// hasn't responded) or "revision" (need Accept/Decline first).
// backend check too, not just $canSendUpdate in the UI

if ($errorMsg === "") {

    $latestCheckStmt = $conn->prepare(
        "SELECT customer_response
         FROM order_progress_updates
         WHERE order_id = ?
         ORDER BY created_at DESC
         LIMIT 1"
    );

    $latestCheckStmt->bind_param("i", $order_id);
    $latestCheckStmt->execute();

    $latestRow = $latestCheckStmt->get_result()->fetch_assoc();
    $latestCheckStmt->close();

    $latestResponse = $latestRow["customer_response"] ?? null;

    if ($latestResponse === "pending") {
        $errorMsg = "Waiting for the customer to respond to the current update first.";
    } elseif ($latestResponse === "revision") {
        $errorMsg = "Please Accept or Decline the pending revision request first.";
    }

}


// Save the uploaded image
if ($errorMsg === "") {

    $uploadDir = __DIR__ . "/../uploads/orders/" . $order_id . "/progress/";

    if (!is_dir($uploadDir)) {

        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            $errorMsg = "Could not create upload folder.";
        }

    }

}

if ($errorMsg === "") {

    $ext = strtolower(
        pathinfo($_FILES["progress_image"]["name"], PATHINFO_EXTENSION)
    );

    $safeName = "progress_" . $order_id . "_" . time() . "_" . random_int(1000, 9999) . "." . $ext;
    $destPath = $uploadDir . $safeName;

    if (!move_uploaded_file($_FILES["progress_image"]["tmp_name"], $destPath)) {

        $errorMsg = "Failed to save the uploaded photo.";

    } else {

        $relativePath = "uploads/orders/" . $order_id . "/progress/" . $safeName;


        // final photo auto-sets customer_response to "approved",
        // that's what triggers the Ship Out button
        $initialResponse = $is_final ? "approved" : "pending";

        $insertStmt = $conn->prepare(
            "INSERT INTO order_progress_updates
                (order_id, sent_by_user_id, sent_by_role, image_path, staff_message, customer_response, is_final)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $staffUserId = (int) $_SESSION["user_id"];
        $isFinalInt  = $is_final ? 1 : 0;

        $insertStmt->bind_param(
            "iissssi",
            $order_id,
            $staffUserId,
            $figurifyRole,
            $relativePath,
            $staff_message,
            $initialResponse,
            $isFinalInt
        );

        $insertStmt->execute();
        $insertStmt->close();


        // final photo moves order to "to_ship" so it shows on the
        // "To Ship" tab
        if ($is_final) {

            $shipStmt = $conn->prepare(
                "UPDATE orders
                 SET status = 'to_ship'
                 WHERE order_id = ?"
            );

            if ($shipStmt !== false) {
                $shipStmt->bind_param("i", $order_id);
                $shipStmt->execute();
                $shipStmt->close();
            }

            // notify other owner/staff to refresh their Active Booking/Shipments page
            figurify_notify_staff(
                $conn,
                $order_id,
                "Order #" . $order_id . " is ready to ship.",
                (int) $_SESSION["user_id"]
            );

        }


        // In-system notification

        $notifyMessage = $is_final
            ? ("📷 Staff sent the final product photo for order #"
                . $order_id . ". Your order is ready — please check "
                . "My Orders.")
            : ("📷 Staff sent a progress update for order #"
                . $order_id . ". Tap to view and let us know if it's good "
                . "or needs revision.");

        $customerId = (int) $customer["user_id"];

        figurify_notify_user($conn, $customerId, $order_id, $notifyMessage, 0, null, false);


        // email notification too, sakto kung hindi naka-log in ang customer

        $emailHeading = $is_final ? "Your Order Is Ready" : "Progress Update";
        $emailIntro   = $is_final
            ? "Here's the final photo of your finished order:"
            : "Our team just sent you a photo update about your order:";
        $emailOutro   = $is_final
            ? "<p>Your order is complete and will be shipped out soon. You can check the status anytime in <strong>My Orders</strong>.</p>"
            : "<p>Please log in to your Clay and Stuff account and check <strong>My Orders</strong>
                to tell us if you're happy with it, or if you'd like a revision.</p>";

        $emailBody = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:20px;'>
                <h2 style='color:#86365f;'>" . $emailHeading . " — Order #" . $order_id . "</h2>
                <p>Hello <strong>" . htmlspecialchars($customer["full_name"]) . "</strong>,</p>
                <p>" . $emailIntro . "</p>
                <img src='cid:progressimg' alt='Photo of your order #" . $order_id . " progress' style='width:100%;max-width:480px;border-radius:12px;border:1px solid #eee;margin:12px 0;'>
                " . ($staff_message !== "" ? "<p style='background:#fff5f8;padding:12px;border-radius:10px;'>" . nl2br(htmlspecialchars($staff_message)) . "</p>" : "") . "
                " . $emailOutro . "
                <br>
                <p>Thank you,<br><strong>Clay and Stuff Team</strong></p>
            </div>
        ";

        $altBody = $is_final
            ? ("Your order #" . $order_id . " is ready! Please log in to My Orders to view the final photo.")
            : ("Progress update for order #" . $order_id
                . ". Please log in to My Orders to view the photo and respond.");

        figurify_send_mail(
            $customer["email"],
            $customer["full_name"],
            ($is_final ? "Your Order Is Ready — Order #" : "Progress Update for Order #") . $order_id . " — Clay and Stuff",
            $emailBody,
            $altBody,
            [
                ["path" => $destPath, "cid" => "progressimg"],
            ]
        );

    }

}

$conn->close();


// Back to the active booking page

$redirect = "active-booking.php?order_id=" . $order_id;

if ($errorMsg !== "") {
    $redirect .= "&progress_error=" . urlencode($errorMsg);
}

header("Location: " . $redirect);
exit();
