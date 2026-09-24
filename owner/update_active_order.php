<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/notification_helper.php";
require_once __DIR__ . "/../helpers/review_helper.php";
require_once __DIR__ . "/../helpers/activity_log_helper.php";


require_once __DIR__ . "/../helpers/access_helper.php";

figurify_require_role("admin");

// Shared logic ng owner/ at staff/update_active_order.php, role check na ang gumawa sa access_helper.

/* "action": request_balance -> "awaiting_balance" (notify, tanong ang
   natitirang balance sa customer bago pa man ma-ship out — dating
   "mark_to_ship" ito na diretso sa "to_ship", pinalitan na para
   dumaan muna sa balance payment sub-flow, tingnan ang
   helpers/order_status_helper.php); ship_out -> "shipped" (notify);
   complete_order -> "completed" (notify); update_status -> manual,
   walang notification.

   Ang pag-abot sa "to_ship" mismo ay galing na sa balance approval
   chain (verify_order.php, source=balance) — hindi na dito. */

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$action   = isset($_POST["action"]) ? strtolower(trim($_POST["action"])) : "";

$allowedStatuses = ["processing", "awaiting_balance", "to_ship", "shipped", "completed", "cancelled"];

$newStatus      = "";
$notifyCustomer = false;
$shipOutError   = "";

$trackingNumber   = trim($_POST["tracking_number"] ?? "");
$shippingFeeRaw   = trim($_POST["shipping_fee"] ?? "");
$balanceAmountRaw = trim($_POST["balance_amount"] ?? "");

if ($action === "request_balance") {

    $newStatus      = "awaiting_balance";
    $notifyCustomer = true;

    if ($balanceAmountRaw === "" || !is_numeric($balanceAmountRaw) || (float) $balanceAmountRaw <= 0) {

        $shipOutError = "Please enter a valid balance amount.";
        $newStatus    = "";

    }

} elseif ($action === "ship_out") {

    $newStatus      = "shipped";
    $notifyCustomer = true;

    if ($trackingNumber === "" || $shippingFeeRaw === "") {

        $shipOutError = "Please fill up Tracking Number and Shipping Fee.";
        $newStatus    = "";

    } elseif (!is_numeric($shippingFeeRaw)) {

        $shipOutError = "Shipping Fee must be a number.";
        $newStatus    = "";

    }

} elseif ($action === "complete_order") {

    $newStatus      = "completed";
    $notifyCustomer = true;

} elseif ($action === "update_status") {

    $requestedStatus = isset($_POST["status"]) ? strtolower(trim($_POST["status"])) : "";

    if (in_array($requestedStatus, $allowedStatuses, true)) {
        $newStatus = $requestedStatus;
    }

}


if ($order_id > 0 && $newStatus !== "") {

    $ownerStmt = $conn->prepare(
        "SELECT user_id, status, booking_date
         FROM orders
         WHERE order_id = ?"
    );

    $ownerStmt->bind_param("i", $order_id);
    $ownerStmt->execute();

    $ownerRow = $ownerStmt->get_result()->fetch_assoc();
    $ownerStmt->close();


    // Server-side guard: kailangang "to_ship" na muna bago ma-ship out. Walang booking-date lock,
    // pwede na anumang oras basta "to_ship" na.

    if ($ownerRow && $action === "ship_out") {

        $currentStatus = strtolower(trim((string) $ownerRow["status"]));

        if ($currentStatus !== "to_ship") {

            $shipOutError = "This order isn't ready to ship yet.";
            $newStatus    = "";

        }

    }

    // Server-side guard: kailangang "shipped" na muna bago maging "completed"

    if ($ownerRow && $action === "complete_order") {

        $currentStatus = strtolower(trim((string) $ownerRow["status"]));

        if ($currentStatus !== "shipped") {

            $shipOutError = "This order hasn't been shipped out yet.";
            $newStatus    = "";

        }

    }

    // Server-side guard: kailangang aktibo pa ang order AT approved/accepted na ang latest response
    // bago payagang humingi ng balance

    if ($ownerRow && $action === "request_balance") {

        $currentStatus = strtolower(trim((string) $ownerRow["status"]));

        if (in_array($currentStatus, ["awaiting_balance", "to_verify_balance", "for_balance_approval", "to_ship", "shipped", "completed", "cancelled"], true)) {

            $shipOutError = "This order is no longer in progress.";
            $newStatus    = "";

        } else {

            $latestStmt = $conn->prepare(
                "SELECT customer_response
                 FROM order_progress_updates
                 WHERE order_id = ?
                 ORDER BY created_at DESC
                 LIMIT 1"
            );

            $latestStmt->bind_param("i", $order_id);
            $latestStmt->execute();

            $latestRow = $latestStmt->get_result()->fetch_assoc();
            $latestStmt->close();

            $latestResponse = $latestRow["customer_response"] ?? null;

            if (!in_array($latestResponse, ["approved", "in_progress"], true)) {

                $shipOutError = "This order isn't marked good/approved yet.";
                $newStatus    = "";

            }

        }

    }

    if ($ownerRow && $newStatus !== "") {

        if ($action === "ship_out") {

            // Subukan i-save kasama ang tracking/fee columns — baka wala pa sila sa DB
            // (hindi pa na-run ang add_shipping_columns.sql), kaya try/catch, fallback sa status-only update.
            // (Wala nang balance_to_pay dito — na-collect at na-verify na yun mas maaga,
            // sa "request_balance" -> balance payment sub-flow.)
            try {

                // shipped_at = NOW() dito rin para exact ang timestamp ng pag-Ship Out
                $updateStmt = $conn->prepare(
                    "UPDATE orders
                     SET status = ?, tracking_number = ?, shipping_fee = ?, shipped_at = NOW()
                     WHERE order_id = ?"
                );

                if ($updateStmt === false) {
                    throw new \mysqli_sql_exception("prepare() returned false");
                }

                $shippingFee = (float) $shippingFeeRaw;

                $updateStmt->bind_param(
                    "ssdi",
                    $newStatus,
                    $trackingNumber,
                    $shippingFee,
                    $order_id
                );
                $updateStmt->execute();
                $updateStmt->close();

            } catch (\Throwable $e) {

                $updateStmt = $conn->prepare(
                    "UPDATE orders
                     SET status = ?
                     WHERE order_id = ?"
                );

                $updateStmt->bind_param("si", $newStatus, $order_id);
                $updateStmt->execute();
                $updateStmt->close();

            }

        } elseif ($action === "request_balance") {

            // Isave ang hinihinging balance amount kasabay ng status change —
            // ito ang makikita ng customer sa My Orders (QR + proof upload page).
            $updateStmt = $conn->prepare(
                "UPDATE orders
                 SET status = ?, balance_to_pay = ?
                 WHERE order_id = ?"
            );

            $balanceAmount = (float) $balanceAmountRaw;

            $updateStmt->bind_param("sdi", $newStatus, $balanceAmount, $order_id);
            $updateStmt->execute();
            $updateStmt->close();

        } else {

            $updateStmt = $conn->prepare(
                "UPDATE orders
                 SET status = ?
                 WHERE order_id = ?"
            );

            $updateStmt->bind_param("si", $newStatus, $order_id);
            $updateStmt->execute();
            $updateStmt->close();

        }


        figurify_log_activity(
            $conn,
            "order_status_updated",
            "Order #" . $order_id . " status changed to \"" . $newStatus . "\""
                . (($action === "ship_out" && $trackingNumber !== "") ? (" (tracking: " . $trackingNumber . ")") : ""),
            $order_id
        );


        // I-notify din ang ibang Owner/Staff (hindi kasama ang gumawa mismo ng aksyon)
        $peerActionLabel = ($action === "complete_order")
            ? "marked as completed"
            : (($action === "ship_out")
                ? "shipped out"
                : (($action === "request_balance") ? "sent a balance payment request" : "updated"));

        figurify_notify_staff(
            $conn,
            $order_id,
            "Order #" . $order_id . " was " . $peerActionLabel . ".",
            (int) $_SESSION["user_id"]
        );


        // Notify customer — "Ship Out" at "Complete" lang

        if ($notifyCustomer) {

            $customer_id = (int) $ownerRow["user_id"];

            if ($action === "complete_order") {

                $message = "Your order #" . $order_id . " has been "
                    . "completed! Thank you for choosing Figurify.";

                // Completed — email may "Leave a Review" button, review_token bilang secret para gumana
                // kahit walang login. Isang review lang bawat order.

                $reviewToken = figurify_ensure_review_token($conn, $order_id);
                $reviewUrl   = figurify_review_url($order_id, $reviewToken);

                // In-system notification lang muna, custom email na may review button gagawin sa ibaba
                figurify_notify_user($conn, $customer_id, $order_id, $message, 0, null, false);

                $custStmt = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
                $custStmt->bind_param("i", $customer_id);
                $custStmt->execute();
                $customerInfo = $custStmt->get_result()->fetch_assoc();
                $custStmt->close();

                if ($customerInfo && !empty($customerInfo["email"])) {

                    $safeName = htmlspecialchars($customerInfo["full_name"]);
                    $safeUrl  = htmlspecialchars($reviewUrl, ENT_QUOTES, "UTF-8");

                    $emailBody = "
                        <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:20px;'>
                            <h2 style='color:#86365f;'>Clay and Stuff</h2>
                            <p>Hello <strong>" . $safeName . "</strong>,</p>
                            <p>" . htmlspecialchars($message) . "</p>
                            <p>We'd love to hear what you think! Let us know your rating, your thoughts, and (optionally) a photo of your figure.</p>
                            <p style='text-align:center;margin:26px 0;'>
                                <a href='" . $safeUrl . "'
                                   style='background:linear-gradient(135deg,#f2699b,#e0447f);color:#ffffff;
                                          padding:12px 26px;border-radius:14px;text-decoration:none;
                                          font-weight:bold;display:inline-block;'>
                                    Leave a Review
                                </a>
                            </p>
                            <p style='font-size:13px;color:#777;'>
                                You can also leave your review anytime from your My Orders page.
                            </p>
                            <p>Thank you,<br><strong>Clay and Stuff Team</strong></p>
                        </div>
                    ";

                    figurify_send_mail(
                        $customerInfo["email"],
                        $customerInfo["full_name"],
                        "Order #" . $order_id . " Completed — Leave a Review! — Clay and Stuff",
                        $emailBody,
                        $message . " Leave a review here: " . $reviewUrl
                    );

                }

            } elseif ($action === "request_balance") {

                $balanceAmount = (float) $balanceAmountRaw;

                $message = "A remaining balance of ₱" . number_format($balanceAmount, 2)
                    . " is due for order #" . $order_id . " before we can ship it out. "
                    . "Please pay via the QR code on your My Orders page and upload proof of payment.";

                figurify_notify_user($conn, $customer_id, $order_id, $message);

            } else {

                $message = "Your order #" . $order_id . " has been shipped "
                    . "out! Thank you for choosing Figurify.";

                figurify_notify_user($conn, $customer_id, $order_id, $message);

            }

        }

    }

}

$conn->close();


// Saan babalik pagkatapos i-submit — "redirect_to" field (galing Shipments o Active Booking)

$redirectTo = isset($_POST["redirect_to"]) ? strtolower(trim((string) $_POST["redirect_to"])) : "active-booking";

if ($redirectTo === "shipments") {
    $redirect = "shipments.php?order_id=" . $order_id;
} else {
    $redirect = "active-booking.php?order_id=" . $order_id;
}

if ($shipOutError !== "") {
    $redirect .= "&progress_error=" . urlencode($shipOutError);
}

header("Location: " . $redirect);
exit();

?>
