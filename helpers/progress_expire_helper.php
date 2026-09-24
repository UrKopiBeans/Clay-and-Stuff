<?php

// Auto-approves progress updates the customer never responded to within 24hrs,
// so orders don't get stuck "pending" forever. Marked "auto_approved" so it's
// clear the customer didn't actually approve it. Runs lazily, same idea as
// expire_helper.php — fires whenever active-booking.php loads.
//
// Needs an "auto_approved" column on order_progress_updates:
//   ALTER TABLE order_progress_updates
//     ADD COLUMN auto_approved TINYINT(1) NOT NULL DEFAULT 0 AFTER is_final;
// Wrapped in try/catch below in case that column isn't there yet.

require_once __DIR__ . "/notification_helper.php";

function figurify_autoapprove_stale_progress_updates(mysqli $conn): void
{
    // hanapin ang "pending" progress updates na lampas na sa 24hrs

    try {

        $stmt = $conn->prepare(
            "SELECT pu.update_id, pu.order_id, o.user_id
             FROM order_progress_updates pu
             JOIN orders o ON o.order_id = pu.order_id
             WHERE pu.customer_response = 'pending'
               AND pu.is_final = 0
               AND pu.created_at <= (NOW() - INTERVAL 24 HOUR)"
        );

        if ($stmt === false) {
            return; // wala pa yung auto_approved column / lumang schema
        }

        $stmt->execute();

        $result = $stmt->get_result();

        $staleUpdates = [];

        while ($row = $result->fetch_assoc()) {
            $staleUpdates[] = $row;
        }

        $stmt->close();

        if (empty($staleUpdates)) {
            return;
        }


        // mark isa-isa bilang approved + notify ang customer

        $updateStmt = $conn->prepare(
            "UPDATE order_progress_updates
             SET customer_response = 'approved',
                 auto_approved = 1,
                 responded_at = NOW()
             WHERE update_id = ?"
        );

        if ($updateStmt === false) {
            return;
        }

        foreach ($staleUpdates as $stale) {

            $updateId = (int) $stale["update_id"];
            $orderId  = (int) $stale["order_id"];
            $userId   = (int) $stale["user_id"];

            $updateStmt->bind_param("i", $updateId);
            $updateStmt->execute();

            $message = "We didn't hear back within 24 hours on the "
                . "latest photo update for order #" . $orderId
                . ", so we've marked it as approved and moved "
                . "forward. Let us know if that's not okay!";

            figurify_notify_user($conn, $userId, $orderId, $message);

        }

        $updateStmt->close();

    } catch (\Throwable $e) {
        // huwag hayaang bumagsak ang buong page dahil dito
        return;
    }
}
