<?php

// Auto-cancels "quoted" orders the customer didn't confirm within 2 days.
// Called before fetching orders/notifications. Can also run as a cron job
// via Admin/check_expired_orders.php.

require_once __DIR__ . "/notification_helper.php";

function figurify_expire_old_quotes(mysqli $conn): void
{
    // hanapin ang "quoted" orders na lampas na sa deadline

    $stmt = $conn->prepare(
        "SELECT order_id, user_id
         FROM orders
         WHERE status = 'quoted'
           AND quote_expires_at IS NOT NULL
           AND quote_expires_at < NOW()"
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $expiredOrders = [];

    while ($row = $result->fetch_assoc()) {
        $expiredOrders[] = $row;
    }

    $stmt->close();

    if (empty($expiredOrders)) {
        return;
    }


    // cancel isa-isa + notify ang customer

    $updateStmt = $conn->prepare(
        "UPDATE orders
         SET status = 'cancelled', quote_expires_at = NULL
         WHERE order_id = ?"
    );

    foreach ($expiredOrders as $expired) {

        $orderId = (int) $expired["order_id"];
        $userId  = (int) $expired["user_id"];

        $updateStmt->bind_param("i", $orderId);
        $updateStmt->execute();

        $message = "Order #" . $orderId . " was automatically cancelled "
            . "because the quotation wasn't confirmed within 2 days.";

        figurify_notify_user($conn, $userId, $orderId, $message);
    }

    $updateStmt->close();
}
