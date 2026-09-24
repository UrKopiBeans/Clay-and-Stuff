<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/expire_helper.php";
require_once __DIR__ . "/../helpers/notification_helper.php";
require_once __DIR__ . "/../helpers/activity_log_helper.php";


require_once __DIR__ . "/../helpers/access_helper.php";

figurify_require_role("staff");

// same as owner/send_quotation.php, just different role check

figurify_expire_old_quotes($conn);

// quoted_price = [figure_id => price], per-figure input from quotation.php

$order_id          = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$postedFigurePrices = isset($_POST["quoted_price"]) && is_array($_POST["quoted_price"])
    ? $_POST["quoted_price"]
    : [];


if ($order_id > 0 && !empty($postedFigurePrices)) {

    // Find who owns this order
    $ownerStmt = $conn->prepare(
        "SELECT user_id, status, order_type, rush_fee
         FROM orders
         WHERE order_id = ?"
    );

    $ownerStmt->bind_param("i", $order_id);
    $ownerStmt->execute();

    $ownerResult = $ownerStmt->get_result();
    $ownerRow    = $ownerResult->fetch_assoc();

    $ownerStmt->close();

    if ($ownerRow) {

        $customer_id   = (int) $ownerRow["user_id"];
        $currentStatus = strtolower($ownerRow["status"]);


        // only allow quoting while still "pending", locked once quoted
        // na para hindi ma-overwrite yung existing quotation
        if ($currentStatus === "pending") {

            // only figure_ids that actually belong to this order
            $figureStmt = $conn->prepare(
                "SELECT figure_id
                 FROM order_figures
                 WHERE order_id = ?"
            );

            $figureStmt->bind_param("i", $order_id);
            $figureStmt->execute();

            $figureResult = $figureStmt->get_result();

            $validFigureIds = [];

            while ($figRow = $figureResult->fetch_assoc()) {
                $validFigureIds[] = (int) $figRow["figure_id"];
            }

            $figureStmt->close();


            // save quoted price per figure then sum for order total,
            // computed server-side, not trusting client total
            $figuresTotal = 0.0;

            $quoteUpdateStmt = $conn->prepare(
                "UPDATE order_figures
                 SET quoted_price = ?
                 WHERE figure_id = ? AND order_id = ?"
            );

            foreach ($validFigureIds as $figureId) {

                if (!isset($postedFigurePrices[$figureId])) {
                    continue;
                }

                $figurePrice = (float) $postedFigurePrices[$figureId];

                if ($figurePrice < 0) {
                    $figurePrice = 0.0;
                }

                $quoteUpdateStmt->bind_param("dii", $figurePrice, $figureId, $order_id);
                $quoteUpdateStmt->execute();

                $figuresTotal += $figurePrice;

            }

            $quoteUpdateStmt->close();

            $rushFee = ($ownerRow["order_type"] === "rush")
                ? (float) $ownerRow["rush_fee"]
                : 0.0;

            $total_price = $figuresTotal + $rushFee;

            $quoteExpiresAt = date("Y-m-d H:i:s", strtotime("+2 days"));

            $updateStmt = $conn->prepare(
                "UPDATE orders
                 SET total_amount = ?, status = 'quoted', quote_expires_at = ?
                 WHERE order_id = ?"
            );

            $updateStmt->bind_param("dsi", $total_price, $quoteExpiresAt, $order_id);
            $updateStmt->execute();
            $updateStmt->close();


            // notify customer, 2-day deadline to Continue/Cancel in My Orders
            $message = "Your order #" . $order_id . " has a quotation! "
                . "Total price: ₱" . number_format($total_price, 2)
                . ". Please Continue or Cancel it on your My Orders "
                . "page within 2 days, or it will be cancelled automatically.";

            figurify_notify_user($conn, $customer_id, $order_id, $message);

            figurify_log_activity(
                $conn,
                "quotation_sent",
                "Sent quotation for order #" . $order_id . " — total: ₱" . number_format($total_price, 2),
                $order_id
            );

            // notify other staff/owner so their quotation.php auto-refreshes
            figurify_notify_staff(
                $conn,
                $order_id,
                "Order #" . $order_id . " was quoted.",
                (int) $_SESSION["user_id"]
            );
        }
    }

}

$conn->close();


// back to quotation.php, quoted tab

header("Location: quotation.php?order_id=" . $order_id . "&qfilter=quoted&sent=1");
exit();

?>