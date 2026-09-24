<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/notification_helper.php";
require_once __DIR__ . "/../helpers/activity_log_helper.php";


require_once __DIR__ . "/../helpers/access_helper.php";

figurify_require_role("admin");

$figurifyRole = "owner";

/* Shared logic ng owner/ at staff/verify_order.php ($figurifyRole ang
   nagtatakda). Sinasadya: owner verify -> diretso "processing"; staff
   verify -> "for approval" muna, hihintay pa sa owner.

   "source" values:
   - to_verify           : first payment (downpayment) verification, sa Verification page
   - for_approval        : owner-only, first payment na na-verify na ng staff, hihintay ng final sign-off
   - for_balance_approval: owner-only — dumederetso dito ang balance payment mula sa customer,
                            walang staff-verification step, For Approval page lang */

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$action   = isset($_POST["action"]) ? strtolower(trim($_POST["action"])) : "";

$allowedActions = ["approve", "reject"];

if ($figurifyRole === "owner") {

    $source = isset($_POST["source"]) ? strtolower(trim($_POST["source"])) : "to_verify";

    $allowedSources = ["to_verify", "for_approval", "for_balance_approval"];

    if (!in_array($source, $allowedSources, true)) {
        $source = "to_verify";
    }

} else {

    /* walang for-approval page ang staff — laging galing sa
       verification.php ang request (to_verify lang) */
    $source = isset($_POST["source"]) ? strtolower(trim($_POST["source"])) : "to_verify";

    $allowedSources = ["to_verify"];

    if (!in_array($source, $allowedSources, true)) {
        $source = "to_verify";
    }

}

/* ang "for approval" status sa DB ay may SPACE (hindi
   underscore) — para tumugma sa mga bilang na ginagamit
   na ng owner/staff dashboard ("FOR APPROVAL" stat card) */
$sourceStatusInDb = "to_verify";

if ($source === "for_approval") {
    $sourceStatusInDb = "for approval";
} elseif ($source === "for_balance_approval") {
    $sourceStatusInDb = "for_balance_approval";
}


// Required ang decline reason bago ma-confirm ang "reject". Invalid/tampered reason = hindi ituloy.

$declineReasonLabels = [
    "incomplete_order_details" => "Incomplete Order Details",
    "insufficient_down_payment" => "Insufficient Down Payment",
    "no_down_payment_received" => "No Down Payment Received",
    "insufficient_balance_payment" => "Insufficient Balance Payment",
    "no_balance_payment_received" => "No Balance Payment Received",
    "unable_to_fulfill" => "Unable to Fulfill the Order",
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

if ($action === "reject" && $declineReasonLabel === null) {
    $action = ""; // walang valid na dahilan, huwag ituloy
}

if ($order_id > 0 && in_array($action, $allowedActions, true)) {

    // Hanapin ang may-ari ng order, siguraduhing nasa expected status pa (para hindi ma-double-process)

    $ownerStmt = $conn->prepare(
        "SELECT user_id, status
         FROM orders
         WHERE order_id = ?"
    );

    $ownerStmt->bind_param("i", $order_id);
    $ownerStmt->execute();

    $ownerRow = $ownerStmt->get_result()->fetch_assoc();
    $ownerStmt->close();

    if ($ownerRow && strtolower($ownerRow["status"]) === $sourceStatusInDb) {

        $customer_id = (int) $ownerRow["user_id"];

        $isBalanceSource = ($source === "for_balance_approval");

        if ($action === "approve") {

            if ($isBalanceSource) {

                /* dito lang naaabot ang balance approval — owner-only
                   ang For Approval page, kaya diretso na sa "to_ship" */
                $newStatus = "to_ship";

                $message = "Good news! We've verified your balance payment for order #"
                    . $order_id . ". Your order is now ready to ship.";

            } elseif ($figurifyRole === "owner") {

                $newStatus = "processing";

                $message = "Good news! We've verified your payment for order #"
                    . $order_id . ". Your order is now being processed.";

            } else {

                /* hindi na direktang "processing" — dadaan muna
                   ito sa OWNER (For Approval page) para sa huling
                   approval bago tuluyang i-proseso ang order */
                $newStatus = "for approval";

                $message = "Good news! We've verified your payment for order #"
                    . $order_id . ". It is now waiting for the owner's "
                    . "final approval before it moves to processing.";

            }

        } elseif ($isBalanceSource) {

            /* hindi na cancelled ang buong order dito — malayo na ito sa
               proseso, ibabalik na lang sa "awaiting_balance" para
               makapag-resubmit ulit ng proof of payment si customer */
            $newStatus = "awaiting_balance";

            $message = "We couldn't verify your balance payment for order #" . $order_id
                . ". Reason: " . $declineReasonLabel . ". "
                . "Please pay via the QR code on your My Orders page and upload proof of payment again.";

        } else {

            $newStatus = "cancelled";

            $message = "Unfortunately, order #" . $order_id . " has been "
                . "cancelled. Reason: " . $declineReasonLabel . ". "
                . "Please contact us if you believe this is a mistake.";
        }

        $updateStmt = null;

        if ($action === "reject") {

            try {

                // Subukan i-save ang decline_reason (baka hindi pa na-migrate ang column) —
                // huwag na lang isama kaysa bumagsak ang buong action.
                $updateStmt = $conn->prepare(
                    "UPDATE orders
                     SET status = ?, decline_reason = ?
                     WHERE order_id = ? AND status = ?"
                );

                if ($updateStmt === false) {
                    throw new \mysqli_sql_exception("prepare() returned false");
                }

                $updateStmt->bind_param(
                    "ssis",
                    $newStatus,
                    $declineReasonLabel,
                    $order_id,
                    $sourceStatusInDb
                );

            } catch (\Throwable $e) {
                $updateStmt = null;
            }

        }

        if ($updateStmt === null) {

            $updateStmt = $conn->prepare(
                "UPDATE orders
                 SET status = ?
                 WHERE order_id = ? AND status = ?"
            );

            $updateStmt->bind_param("sis", $newStatus, $order_id, $sourceStatusInDb);

        }

        $updateStmt->execute();
        $updateStmt->close();

        figurify_log_activity(
            $conn,
            ($action === "approve") ? "order_verified" : "order_rejected",
            (($action === "approve") ? "Verified" : "Rejected") . " order #" . $order_id
                . (($action === "reject") ? (" — reason: " . $declineReasonLabel) : ""),
            $order_id
        );


        // Notify customer
        figurify_notify_user($conn, $customer_id, $order_id, $message);

        // I-notify din ang ibang Owner/Staff (hindi kasama ang nag-verify/nag-reject mismo)

        $actorLabel = ($figurifyRole === "owner") ? "the owner" : "staff";

        $peerMessage = ($action === "approve")
            ? ("Order #" . $order_id . " was already verified by " . $actorLabel . ".")
            : ("Order #" . $order_id . " was already rejected/cancelled by " . $actorLabel . ".");

        figurify_notify_staff(
            $conn,
            $order_id,
            $peerMessage,
            (int) $_SESSION["user_id"]
        );
    }

}

$conn->close();


// Back to where the request came from

if ($figurifyRole === "owner") {
    $redirectPage = in_array($source, ["for_approval", "for_balance_approval"], true)
        ? "for-approval.php"
        : "verification.php";
} else {
    $redirectPage = "verification.php";
}

header("Location: " . $redirectPage . "?order_id=" . $order_id);
exit();
