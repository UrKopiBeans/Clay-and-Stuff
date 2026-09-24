<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/notification_helper.php";
require_once __DIR__ . "/../helpers/activity_log_helper.php";


require_once __DIR__ . "/../helpers/access_helper.php";

figurify_require_role("staff");

$figurifyRole = "staff";

/* Shared logic ng owner/ at staff/verify_order.php ($figurifyRole ang
   nagtatakda). Sinasadya: owner verify -> diretso "processing"; staff
   verify -> "for approval" muna, hihintay pa sa owner.

   "source" values (staff):
   - to_verify : first payment (downpayment) verification, sa Verification page
   Wala nang balance-related source dito — dumederetso na ang balance
   payment sa owner's For Approval page, hindi na dumadaan sa staff. */

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$action   = isset($_POST["action"]) ? strtolower(trim($_POST["action"])) : "";

$allowedActions = ["approve", "reject"];

// staff has no for-approval page, laging galing sa verification.php (to_verify lang)
$source = isset($_POST["source"]) ? strtolower(trim($_POST["source"])) : "to_verify";

$allowedSources = ["to_verify"];

if (!in_array($source, $allowedSources, true)) {
    $source = "to_verify";
}

// "for approval" status has a SPACE not underscore in the DB,
// dapat match sa dashboard's "FOR APPROVAL" stat card count
$sourceStatusInDb = "to_verify";


// must pick a reason before "reject" goes through, invalid/tampered
// request just gets ignored

$declineReasonLabels = [
    "incomplete_order_details" => "Incomplete Order Details",
    "insufficient_down_payment" => "Insufficient Down Payment",
    "no_down_payment_received" => "No Down Payment Received",
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
    $action = "";
}


if ($order_id > 0 && in_array($action, $allowedActions, true)) {

    // check pa rin kung nasa expected status, avoid double-process on refresh
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

        if ($action === "approve") {

            // dadaan muna sa owner's For Approval page bago maging "processing"
            $newStatus = "for approval";

            $message = "Good news! We've verified your payment for order #"
                . $order_id . ". It is now waiting for the owner's "
                . "final approval before it moves to processing.";

        } else {

            $newStatus = "cancelled";

            $message = "Unfortunately, order #" . $order_id . " has been "
                . "cancelled. Reason: " . $declineReasonLabel . ". "
                . "Please contact us if you believe this is a mistake.";
        }

        $updateStmt = null;

        if ($action === "reject") {

            try {

                // try saving decline_reason too, in case migration
                // hasn't run yet just fall back below instead of crashing
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

        figurify_notify_user($conn, $customer_id, $order_id, $message);

        // notify other owner/staff so their page auto-refreshes too

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


// back to where the request came from

if ($figurifyRole === "owner") {
    $redirectPage = in_array($source, ["for_approval", "for_balance_approval"], true)
        ? "for-approval.php"
        : "verification.php";
} else {
    $redirectPage = "verification.php";
}

header("Location: " . $redirectPage . "?order_id=" . $order_id);
exit();
