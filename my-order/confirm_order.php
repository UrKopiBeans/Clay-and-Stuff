<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/expire_helper.php";
require_once __DIR__ . "/../helpers/notification_helper.php";


/* must be logged in (customer) */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../Login/Login.php");
    exit();
}

$user_id = $_SESSION["user_id"];


/* clean up any quotes na na-expire na bago pa man na-click ang button */

figurify_expire_old_quotes($conn);


/* read posted fields */

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$action   = isset($_POST["action"]) ? strtolower(trim($_POST["action"])) : "";


/* make sure: ari niya ang order, at nasa tamang status pa rin — kung hindi, huwag nang ulitin.
   "cancel" ay puwede sa quoted PATI awaiting_payment (para makabalik/mag-back-out ang customer
   kahit nasa Payment & Shipping page na sila, hindi lang sa "quoted" stage). "continue" ay
   sa quoted lang, papunta ng awaiting_payment. */

if ($order_id > 0 && in_array($action, ["continue", "cancel"], true)) {

    $stmt = $conn->prepare(
        "SELECT user_id, status
         FROM orders
         WHERE order_id = ?"
    );

    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $order   = $result->fetch_assoc();

    $stmt->close();

    $ownsOrder      = $order && (int) $order["user_id"] === (int) $user_id;
    $stillQuoted    = $order && $order["status"] === "quoted";
    $stillAwaitingPayment = $order && $order["status"] === "awaiting_payment";

    if ($ownsOrder && ($stillQuoted || $stillAwaitingPayment)) {

        /* customer chose: cancel (either from "quoted" or "awaiting_payment") */

        if ($action === "cancel") {

            $updateStmt = $conn->prepare(
                "UPDATE orders
                 SET status = 'cancelled', quote_expires_at = NULL
                 WHERE order_id = ?"
            );

            $updateStmt->bind_param("i", $order_id);
            $updateStmt->execute();
            $updateStmt->close();

            $message = "You cancelled order #" . $order_id . ".";

            figurify_notify_user($conn, (int) $user_id, $order_id, $message, 1);

            figurify_notify_staff(
                $conn,
                $order_id,
                "Customer cancelled order #" . $order_id . "."
            );

            $conn->close();

            header("Location: my-orders.php?order_id=" . $order_id . "&cancelled=1");
            exit();
        }


        /* customer chose: continue -> "awaiting_payment" (walang timer, papunta na sa payment).
           "quoted" lang ito puwede — hindi na kailangang i-"continue" ulit ang awaiting_payment. */

        if ($action === "continue" && $stillQuoted) {

            $updateStmt = $conn->prepare(
                "UPDATE orders
                 SET status = 'awaiting_payment', quote_expires_at = NULL
                 WHERE order_id = ?"
            );

            $updateStmt->bind_param("i", $order_id);
            $updateStmt->execute();
            $updateStmt->close();

            figurify_notify_staff(
                $conn,
                $order_id,
                "Customer confirmed the quotation for order #" . $order_id . " and is proceeding to payment."
            );

            $conn->close();

            header("Location: payment-shipping.php?order_id=" . $order_id);
            exit();
        }
    }
}

$conn->close();

header("Location: my-orders.php");
exit();
