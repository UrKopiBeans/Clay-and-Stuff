<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/notification_helper.php";


/* =====================================================
   STAFF ACCESS PROTECTION
===================================================== */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../Login/Login.php");
    exit();
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "staff") {
    header("Location: ../Home/Home.php");
    exit();
}


/* =====================================================
   ONLY THESE STATUSES ARE ALLOWED
   (kailangan tumugma sa dropdown sa allbookings.php — "pending",
   "quoted", at "awaiting_payment" ay automatic lang / hindi
   dapat pinipili ng staff nang manu-mano, kaya wala sila
   dito. "to_verify" ang dating nawawala sa list na ito kaya
   hindi na-save yung pagpili ng staff niyan sa dropdown.)
===================================================== */

$allowedStatuses = ["to_verify", "processing", "completed", "cancelled"];


/* =====================================================
   READ POSTED FIELDS
===================================================== */

$order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$status   = isset($_POST["status"]) ? strtolower(trim($_POST["status"])) : "";


/* =====================================================
   VALIDATE THEN UPDATE
===================================================== */

if ($order_id > 0 && in_array($status, $allowedStatuses, true)) {

    $stmt = $conn->prepare(
        "UPDATE orders
         SET status = ?
         WHERE order_id = ?"
    );

    $stmt->bind_param("si", $status, $order_id);
    $stmt->execute();
    $stmt->close();

    /* BAGO: i-notify ang ibang Owner/Staff (hindi kasama ang
       gumawa mismo ng pagbabago) — para awtomatikong ma-refresh
       ang kanilang mga page (allbookings.php, verification.php,
       completed.php, cancelled.php, atbp.) kapag may order na
       lumipat ng status habang bukas ang page nila. Tingnan ang
       malaking paliwanag sa helpers/notification_helper.php. */

    figurify_notify_staff(
        $conn,
        $order_id,
        "Order #" . $order_id . " status was changed to \"" . $status . "\".",
        (int) $_SESSION["user_id"]
    );

}

$conn->close();


/* =====================================================
   BALIK SA ORDERS PAGE, NAKA-SELECT PA RIN ANG PARENG
   ORDER NA KAKA-UPDATE LANG
===================================================== */

header("Location: allbookings.php?order_id=" . $order_id);
exit();

?>