<?php

require_once "owner-header.php";
require_once __DIR__ . "/../helpers/booking_helper.php";

$bookedDates  = figurify_get_booked_dates($conn);


/* Parehong label mapping gaya ng ginagamit na sa All Bookings
   page — "reference" -> Image Submission, "create_style" -> Dress
   Up — para pare-pareho ang display sa buong Owner/Staff side. */
function orderMethodLabel($method)
{
    $labels = [
        "reference"    => "Image Submission",
        "create_style" => "Dress Up",
    ];

    return $labels[$method] ?? "Image Submission";
}


// default dashboard data

$allBookings       = 0;
$pendingBookings   = 0;
$forApproval       = 0;
$activeOrders      = 0;
$upcomingShipments = 0;

$upcomingShipmentOrders = [];

$shipmentSummaryOrders = [];

$recentOrders = [];


/* Reusable renderer ng Shipment Summary widget — iisang grid na lang
   ng order cards. Nasa labas ng "orders table exists" check dahil
   ginagamit pa rin ito kahit wala pang orders table. */
function figurify_render_shipment_summary(array $orders, string $emptyText, int $limit = 12): void
{

    $count = count($orders);

    /* "Today" (walang oras) — para ma-compute ilang araw na lang bago mag-due. */
    $todayTs = strtotime(date("Y-m-d"));

    ?>

    <div class="shipment-summary-grid">

        <?php if ($count === 0): ?>

            <div class="shipment-bucket-empty"><?php echo e($emptyText); ?></div>

        <?php else: ?>

            <?php foreach (array_slice($orders, 0, $limit) as $order): ?>

                <?php
                // Isang badge na lang bawat card — Overdue / Shipping Tomorrow /
                // Due in Xd / Ready to Ship. Hindi na duplicate ng generic status,
                // dahil malinaw na naman na "to ship" ang lahat dito.
                $cardClass  = "shipment-bucket-item";
                $badgeClass = "shipment-bucket-item-badge-ready";
                $badgeLabel = "Ready to Ship";

                if (!empty($order["booking_date"])) {

                    $orderDateTs = strtotime($order["booking_date"]);
                    $daysUntil   = (int) floor(($orderDateTs - $todayTs) / 86400);

                    if ($daysUntil < 0) {
                        $cardClass  = "shipment-bucket-item shipment-bucket-item-overdue";
                        $badgeClass = "shipment-bucket-item-badge-overdue";
                        $badgeLabel = ($daysUntil === -1)
                            ? "1 day overdue"
                            : abs($daysUntil) . " days overdue";
                    } elseif ($daysUntil === 1) {
                        $cardClass  = "shipment-bucket-item shipment-bucket-item-duesoon";
                        $badgeClass = "shipment-bucket-item-badge-tomorrow";
                        $badgeLabel = "Shipping Tomorrow";
                    } elseif ($daysUntil >= 0 && $daysUntil <= 3) {
                        $cardClass  = "shipment-bucket-item shipment-bucket-item-duesoon";
                        $badgeClass = "shipment-bucket-item-badge-soon";
                        $badgeLabel = ($daysUntil === 0) ? "Due Today" : "Due in " . $daysUntil . "d";
                    }

                }
                ?>

                <a
                    href="shipments.php?order_id=<?php echo (int) $order["order_id"]; ?>"
                    class="<?php echo e($cardClass); ?>"
                >
                    <div class="shipment-bucket-item-top">
                        <span class="shipment-bucket-item-id">
                            #<?php echo (int) $order["order_id"]; ?>
                            <?php echo ($order["order_type"] ?? "") === "rush" ? "Rush" : "Non-Rush"; ?>
                        </span>

                        <span class="shipment-bucket-item-badge <?php echo e($badgeClass); ?>">
                            <?php echo e($badgeLabel); ?>
                        </span>
                    </div>

                    <div class="shipment-bucket-item-meta">
                        <span>
                            <?php echo e(orderMethodLabel($order["order_method"] ?? "")); ?>
                        </span>
                    </div>

                    <div class="shipment-bucket-item-meta">
                        <span><?php echo e($order["email"] ?? ""); ?></span>
                    </div>

                    <div class="shipment-bucket-item-meta">
                        <span>
                            <?php
                                echo !empty($order["courier"])
                                    ? e($order["courier"])
                                    : "No courier yet";
                            ?>
                        </span>

                        <span class="shipment-bucket-item-date">
                            <?php
                                echo !empty($order["booking_date"])
                                    ? e(date("M d, Y", strtotime($order["booking_date"])))
                                    : "—";
                            ?>
                        </span>
                    </div>
                </a>

            <?php endforeach; ?>

            <?php if ($count > $limit): ?>
                <div class="shipment-bucket-more">
                    +<?php echo ($count - $limit); ?> more
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>

    <?php
}


// check orders table

$ordersTableExists = false;

$checkTable = $conn->query("SHOW TABLES LIKE 'orders'");

if ($checkTable && $checkTable->num_rows > 0) {

    $ordersTableExists = true;


    // total bookings
    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM orders
    ");

    if ($result) {

        $row = $result->fetch_assoc();

        $allBookings = (int)($row["total"] ?? 0);
    }


    // pending bookings
    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM orders
        WHERE LOWER(TRIM(status)) = 'pending'
    ");

    if ($result) {

        $row = $result->fetch_assoc();

        $pendingBookings = (int)($row["total"] ?? 0);
    }


    // for-approval bookings (handles "forapproval", "for approval", "for_approval")

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM orders
        WHERE LOWER(REPLACE(REPLACE(TRIM(status), ' ', ''), '_', ''))
        IN ('forapproval', 'approval')
    ");

    if ($result) {

        $row = $result->fetch_assoc();

        $forApproval = (int)($row["total"] ?? 0);
    }


    // active orders (handles "paid", "processing", "on process", "active")

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM orders
        WHERE LOWER(REPLACE(TRIM(status), ' ', ''))
        IN (
            'processing',
            'onprocess',
            'active',
            'paid'
        )
    ");

    if ($result) {

        $row = $result->fetch_assoc();

        $activeOrders = (int)($row["total"] ?? 0);
    }


    // UPCOMING SHIPMENTS — "to_ship" orders na 2 days na lang o mas
    // malapit ang booking_date (kasama na rin nakaraan). See booking_helper.php.

    $upcomingShipmentOrders = figurify_get_upcoming_shipments($conn);

    $upcomingShipments = count($upcomingShipmentOrders);


    // SHIPMENT SUMMARY WIDGET — lahat ng "to_ship" orders, pinaka-
    // malapit nang due muna. Same query helper ng shipments.php.

    $shipmentSummaryOrders = figurify_get_all_shipments($conn, "to_ship");

    $shipmentSummaryToday = strtotime(date("Y-m-d"));

    // isang listahan na lang, pinaka-malapit nang due muna — dati
    // magkakahiwalay na grupo ito (Tomorrow/Within3/Ready/Overdue) na
    // paulit-ulit ang laman ng isa't isa, kaya pinagsama na lang at
    // isort base sa lapit ng booking_date
    usort($shipmentSummaryOrders, function ($a, $b) use ($shipmentSummaryToday) {
        $aDays = !empty($a["booking_date"])
            ? (int) floor((strtotime($a["booking_date"]) - $shipmentSummaryToday) / 86400)
            : PHP_INT_MAX;
        $bDays = !empty($b["booking_date"])
            ? (int) floor((strtotime($b["booking_date"]) - $shipmentSummaryToday) / 86400)
            : PHP_INT_MAX;

        return $aDays <=> $bDays;
    });


    // recent orders — see orders table columns in the schema

    $recentResult = $conn->query("
        SELECT
            order_id,
            user_id,
            order_type,
            booking_date,
            rush_fee,
            subtotal,
            total_amount,
            status,
            created_at
        FROM orders
        ORDER BY created_at DESC
        LIMIT 6
    ");

    if ($recentResult) {

        while ($row = $recentResult->fetch_assoc()) {

            $recentOrders[] = $row;
        }
    }
}


?>


<!DOCTYPE html>

<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../Image/logoclay.png">

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Figurify — Owner Dashboard</title>


<style>

/* full dashboard.css copied here (1 php = 1 css, no @import). owner.css/dashboard.css untouched, other pages still use those. */
/* shared owner/staff dashboard styles — owner.css/staff.css just @import this now */


/* owner dashboard styles */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --pink: #f78fd4;
    --pink-dark: #b23e82;
    --pink-light: #ffc4e8;
    --pink-soft: #fff0f9;

    --black: #29252a;
    --gray: #81777d;
    --gray-light: #eee7ea;

    --white: #ffffff;
    --background: #f8f4f5;

    --green: #65a77e;
    --green-light: #e1f3e7;

    --yellow: #d59b2b;
    --yellow-light: #fff1cc;

    --blue: #6689dd;
    --blue-light: #e8edff;

    --red: #d96b78;
    --red-light: #ffe4e8;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: "Segoe UI", Arial, sans-serif;
    color: var(--black);
    min-height: 100vh;

    background-color: #fffdf3;

    background-image:
        linear-gradient(rgba(214, 198, 229, .25) 1px, transparent 1px),
        linear-gradient(90deg, rgba(214, 198, 229, .25) 1px, transparent 1px);

    background-size: 25px 25px;
}

button,
input,
a {
    font-family: inherit;
}

button,
a {
    cursor: pointer;
}

a {
    text-decoration: none;
    color: inherit;
}

/* app */
.app {
    min-height: 100vh;
}

/* sidebar */
/* Sidebar styling moved to Shared/dashboard-sidebar.php (single shared component for Owner + Staff) */


.owner-mini {
    background: rgba(255,255,255,.45);

    border: 1px solid rgba(41,37,42,.12);

    padding: 10px;

    border-radius: 14px;

    display: flex;
    align-items: center;

    gap: 9px;
}

.avatar {
    width: 36px;
    height: 36px;

    border-radius: 11px;

    background: white;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 900;
}

.owner-mini strong {
    display: block;
    font-size: 12px;
    max-width: 130px;

    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.owner-mini span {
    display: block;

    font-size: 10px;

    color: #705c64;

    margin-top: 2px;
}

.logout {
    margin-top: 8px;
    color: #713447;
}

/* main */
.main {
    margin-left: 230px;

    width: calc(100% - 230px);

    padding: 22px 22px 38px 14px;
}

/* topbar */
.topbar {
    display: flex;

    align-items: flex-start;
    justify-content: space-between;

    margin-bottom: 16px;
}

.welcome-label {
    color: var(--pink-dark);

    font-size: 9px;

    font-weight: 900;

    letter-spacing: 1.7px;

    margin-bottom: 5px;
}

.welcome h2 {
    font-size: 27px;

    letter-spacing: -1px;
}

.welcome p {
    color: var(--gray);

    font-size: 12px;

    margin-top: 5px;
}

/* date time */
.datetime {
    display: flex;
    align-items: center;

    gap: 9px;

    margin-top: 10px;

    color: #8b747d;

    font-size: 11px;

    font-weight: 700;
}

.datetime-icon {
    width: 27px;
    height: 27px;

    background: var(--pink-light);

    border-radius: 9px;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 13px;
}

#currentDate {
    color: #765f68;
}

#currentTime {
    color: #a4476d;
    font-weight: 900;
}

.datetime-dot {
    color: #c7aeb8;
}

/* top actions */
.top-actions {
    display: flex;
    align-items: center;

    gap: 9px;
}

.search-box {
    width: 220px;

    background: white;

    border: 1px solid var(--gray-light);

    border-radius: 12px;

    padding: 11px 13px;

    outline: none;

    font-size: 11px;

    transition: .2s;
}

.search-box:focus {
    border-color: var(--pink);

    box-shadow:
        0 0 0 3px var(--pink-light);
}

.notification-btn {
    position: relative;

    width: 42px;
    height: 42px;

    background: white;

    border: 1px solid var(--gray-light);

    border-radius: 12px;

    font-size: 17px;

    transition: .2s;
}

.notification-btn:hover {
    background: var(--pink-soft);
}

.notification-badge {
    position: absolute;

    top: -5px;
    right: -5px;

    min-width: 17px;
    height: 17px;

    padding: 0 4px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: var(--pink);

    color: white;

    border-radius: 50%;

    border: 2px solid var(--background);

    font-size: 8px;
    font-weight: 900;
}

/* cards */
.card {
    background: white;

    border: 1px solid var(--gray-light);

    border-radius: 18px;

    padding: 17px;

    box-shadow:
        0 5px 16px rgba(71,45,55,.04);
}

/* statistics */
.stats {
    display: grid;

    grid-template-columns:
        3fr 1.1fr;

    align-items: start;

    gap: 14px;

    margin-bottom: 18px;
}

.stats-left {
    display: grid;

    grid-template-columns:
        repeat(4,1fr);

    align-items: stretch;

    gap: 14px;
}

.stats-left .stat-card {
    height: 100%;
}

.stat-card {
    position: relative;

    overflow: hidden;
}

.stat-card::after {
    content: "";

    position: absolute;

    width: 80px;
    height: 80px;

    border-radius: 50%;

    background: var(--pink-light);

    right: -28px;
    top: -30px;
}

.pending-card::after {
    background: var(--blue-light);
}

.approval-card::after {
    background: var(--yellow-light);
}

.stat-label {
    color: var(--black);

    font-size: 10px;

    font-weight: 900;
}

.stat-number {
    font-size: 30px;

    font-weight: 900;

    margin-top: 6px;
}

.stat-trend {
    margin-top: 5px;

    font-size: 10px;

    font-weight: 800;

    color: var(--green);
}

.attention {
    color: var(--yellow);
}

.shipment-stat-card {
    z-index: 1;

    display: flex;

    flex-direction: column;
}

.shipment-stat-card .stat-label {
    margin-bottom: 12px;
}

.shipment-stat-card .shipment-list {
    flex: 1;
}

/* dashboard layout — left col: stats + calendar + recent bookings, right col: shipment card */

.dashboard-layout {
    display: grid;

    grid-template-columns:
        2.3fr 1fr;

    gap: 18px;

    align-items: stretch;

    margin-bottom: 18px;
}

.dashboard-left {
    display: flex;
    flex-direction: column;

    gap: 18px;
}

.dashboard-right {
    display: flex;

    height: 100%;
}

.dashboard-right .shipment-stat-card {
    flex: 1;

    width: 100%;
    height: 100%;
}

.bottom-row {
    display: grid;

    grid-template-columns:
        1fr 1.2fr;

    gap: 18px;

    align-items: stretch;
}

/* Pinapantay ang taas ng Recent Bookings card sa Calendar card sa tabi nito. */
.bottom-row > .card {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.bottom-row > .card #orders {
    flex: 1;
    overflow-y: auto;
}

/* dashboard grid */
.dashboard-grid {
    display: grid;

    grid-template-columns:
        1fr 1.2fr;

    gap: 18px;

    align-items: start;
}

/* card header */
.card-header {
    display: flex;

    align-items: center;
    justify-content: space-between;

    margin-bottom: 15px;
}

.card-header h3 {
    font-size: 15px;
}

.view-link {
    border: none;

    background: transparent;

    color: #a4476d;

    font-size: 11px;

    font-weight: 800;
}

.view-link:hover {
    text-decoration: underline;
}

/* orders */
.order-row {
    display: grid;

    grid-template-columns:
        1.2fr .9fr .75fr .75fr;

    gap: 8px;

    align-items: center;

    padding: 13px 3px;

    border-top: 1px solid #f0e7eb;

    font-size: 11px;
}

.order-row.header {
    border-top: none;

    color: var(--black);

    font-size: 9px;

    font-weight: 800;

    text-transform: uppercase;
}

.customer {
    display: flex;

    align-items: center;

    gap: 8px;
}

.customer-icon {
    width: 31px;
    height: 31px;

    border-radius: 10px;

    background: #fbe5ed;

    display: flex;
    align-items: center;
    justify-content: center;
}

.customer strong {
    display: block;

    font-size: 11px;
}

.customer small {
    display: block;

    color: #9a8c94;

    font-size: 9px;

    margin-top: 2px;
}

/* status */
.status {
    display: inline-block;

    padding: 5px 8px;

    border-radius: 99px;

    font-size: 9px;

    font-weight: 900;

    white-space: nowrap;
}

/* status colors — same as Staff Dashboard, Calendar, and My Orders. Single source: Admin/order_status_helper.php. */

.status-pending {
    background: #FFF3C4;
    color: #29252A;
}

.status-quoted {
    background: #E1F3E7;
    color: #29252A;
}

.status-awaiting_payment {
    background: #FBE8D6;
    color: #29252A;
    white-space: normal;
    max-width: 64px;
    text-align: center;
    line-height: 1.25;
}

.status-to_verify {
    background: #E1F3E7;
    color: #29252A;
}

.status-for_approval {
    background: #E1F3E7;
    color: #29252A;
}

.status-processing {
    background: #FBE8D6;
    color: #29252A;
}

.status-awaiting_balance {
    background: #FBE8D6;
    color: #29252A;
}

.status-to_verify_balance {
    background: #E1F3E7;
    color: #29252A;
}

.status-for_balance_approval {
    background: #E1F3E7;
    color: #29252A;
}

.status-to_ship {
    background: #E8EDFF;
    color: #29252A;
}

.status-shipped {
    background: #E8EDFF;
    color: #29252A;
}

.status-completed {
    background: #E1F3E7;
    color: #29252A;
}

.status-cancelled {
    background: #FADCDC;
    color: #29252A;
}

/* calendar controls */
.calendar-controls {
    display: flex;

    align-items: center;
    justify-content: space-between;

    margin-bottom: 10px;
}

.calendar-controls button {
    width: 30px;
    height: 30px;

    display: flex;
    align-items: center;
    justify-content: center;

    border: none;

    background: var(--pink-light);

    color: var(--pink-dark);

    border-radius: 50%;

    font-size: 16px;
    line-height: 1;
    font-weight: 900;

    cursor: pointer;

    transition: .2s;
}

.calendar-controls button:hover {
    background: var(--pink);
    color: white;

    transform: scale(1.06);
}

.calendar-controls strong {
    font-size: 12px;
}

.calendar-arrow-icon {
    display: block;
}

/* calendar */
.calendar-week {
    display: grid;

    grid-template-columns:
        repeat(7,1fr);

    gap: 4px;

    margin-bottom: 3px;
}

.calendar-week div {
    text-align: center;

    color: var(--black);

    font-size: 8px;
    font-weight: 900;

    padding: 4px 0;
}

.calendar-days {
    display: grid;

    grid-template-columns:
        repeat(7,1fr);

    gap: 4px;
}

.day {
    width: 100%;
    height: auto;
    aspect-ratio: 1 / 1;

    border-radius: 9px;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 10px;

    transition: .2s;
}

.day:hover {
    background: #F1F0F4;
    cursor: pointer;
}

.day.empty {
    background: transparent;
}

.day.available {
    background: #fffbed;
}

.day.booked {
    background: #f6dede;

    color: #c98080;

    font-weight: 800;

    text-decoration: line-through;
}

.day.unavailable {
    background: #f4f0f3;

    color: #c5b9c0;
}

.day.selected {
    background: #f4a4ca;

    color: white;

    outline: 2px solid #c9689a;
    outline-offset: 1px;

    font-weight: 800;
}

/* Current day — mapusyaw na pink fill (di kasing tingkad ng
   .selected) para makilala agad "ngayon". Nasa dulo para
   di ma-override ng ibang state classes sa itaas. */
.day.today {
    background: #ffe3f1;

    color: #b6608f;

    font-weight: 900;
}

.day.selected.today {
    background: #f4a4ca;

    color: white;

    outline: 2px solid #c9689a;
    outline-offset: 1px;

    font-weight: 900;
}

/* Dashboard calendar (#calendarDays) ay read-only preview lang —
   buwan lang ang mapapalitan (arrow buttons), kaya naka-disable
   ang pointer/click sa bawat araw. */
#calendarDays .day {
    pointer-events: none;
}

#calendarDays .day:hover {
    background: inherit;
    cursor: default;
}

/* calendar legend */
.calendar-legend {
    display: flex;

    gap: 15px;

    margin-top: 13px;

    font-size: 9px;

    color: #95878e;
}

.calendar-legend span {
    display: flex;

    align-items: center;

    gap: 5px;
}

.legend-dot {
    width: 8px;
    height: 8px;

    border-radius: 50%;
}

.available-dot {
    background: #fffbed;

    border: 1px solid #e9d9a0;
}

.booked-dot {
    background: #f6dede;

    border: 1px solid #e0a6a6;
}

.unavailable-dot {
    background: #f4f0f3;

    border: 1px solid #d8cdd4;
}

.selected-dot {
    background: #f4a4ca;

    border: 1px solid #c9689a;
}

.today-dot {
    background: #ffe3f1;

    border: 1px solid #f6c8de;
}

/* shipments */
.shipment-list {
    display: grid;
    gap: 10px;
}

.shipment {
    display: flex;

    align-items: center;
    justify-content: space-between;

    background: #fff8fa;

    border: 1px solid #f3e4e9;

    padding: 10px 11px;

    border-radius: 12px;
}

.shipment strong {
    font-size: 11px;
}

.shipment small {
    display: block;

    color: #9b8f95;

    font-size: 9px;

    margin-top: 3px;
}

.shipment-icon {
    font-size: 18px;
}

.empty-shipment {
    min-height: 55px;
}

/* Shipment summary widget — isang grid na lang ng cards (isang order
   = isang container). Dati magkakahiwalay na grupo (Tomorrow/
   Within3/Ready/Overdue) na paulit-ulit ang laman ng isa't isa. */
.shipment-summary-grid {
    display: grid;
    gap: 8px;

    flex: 1;
}

.shipment-bucket-empty {
    color: #a0959b;
    font-size: 10px;
    padding: 4px 0;
}

.shipment-bucket-item {
    display: flex;
    flex-direction: column;
    gap: 4px;

    background: #fff8fa;
    border: 1px solid #f3e4e9;

    padding: 8px 10px;

    border-radius: 10px;

    text-decoration: none;
    color: inherit;

    font-size: 10.5px;
}

.shipment-bucket-item-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.shipment-bucket-item-id {
    font-weight: 800;
    color: var(--pink-dark);

    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

/* iisang badge na lang bawat card (Overdue / Shipping Tomorrow /
   Due in Xd / Ready to Ship) — kapalit ng dating status pill +
   urgency label na magkahiwalay pa noon */
.shipment-bucket-item-badge {
    font-size: 8.5px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .2px;
    padding: 3px 7px;
    border-radius: 999px;
    white-space: nowrap;
    flex-shrink: 0;
}

.shipment-bucket-item-badge-ready {
    background: var(--pink-soft);
    color: var(--pink-dark);
}

.shipment-bucket-item-badge-tomorrow,
.shipment-bucket-item-badge-soon {
    background: #FFF3E0;
    color: #9A6400;
}

.shipment-bucket-item-badge-overdue {
    background: var(--red-light, #FADCDC);
    color: var(--red, #C0392B);
}

.shipment-bucket-item-duesoon {
    background: #FFF3E0;
    border-color: #F5C97A;
}

.shipment-bucket-item-overdue {
    background: var(--red-light, #FADCDC);
    border-color: var(--red, #C0392B);
}

.shipment-bucket-item-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;

    color: var(--gray);
    font-size: 9.5px;
}

.shipment-bucket-item-meta span:first-child {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.shipment-bucket-item-date {
    color: var(--gray);
    font-size: 9.5px;
    white-space: nowrap;
}

.shipment-bucket-more {
    font-size: 9.5px;
    color: var(--gray);
    padding: 2px 2px 0;
}



/* overview */
.activity-card {
    margin-top: 18px;
}

.online-badge {
    color: var(--green);

    background: var(--green-light);

    padding: 5px 9px;

    border-radius: 99px;

    font-size: 9px;

    font-weight: 900;
}

.overview-grid {
    display: grid;

    grid-template-columns:
        repeat(4,1fr);

    gap: 10px;
}

.overview-box {
    display: flex;

    align-items: center;

    gap: 10px;

    background: var(--pink-soft);

    border: 1px solid #f4e2e8;

    border-radius: 13px;

    padding: 12px;
}

.overview-icon {
    width: 32px;
    height: 32px;

    border-radius: 10px;

    background: white;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 900;

    color: var(--pink-dark);
}

.overview-box strong {
    display: block;

    font-size: 14px;
}

.overview-box small {
    display: block;

    color: #9b8f95;

    font-size: 9px;

    margin-top: 2px;
}

/* quick actions */
.quick-actions {
    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 10px;

    margin-top: 18px;
}

.quick-btn {
    padding: 12px;

    background: var(--pink-soft);

    border: 1px dashed #d9b6c3;

    border-radius: 12px;

    font-size: 10px;

    font-weight: 800;

    transition: .2s;
}

.quick-btn:hover {
    background: var(--pink-light);

    transform: translateY(-1px);
}

/* empty */
.empty-state {
    padding: 30px 10px;

    text-align: center;

    color: #a0959b;

    font-size: 11px;
}

/* modal */
.modal-overlay {
    display: none;

    position: fixed;

    inset: 0;

    background: rgba(35,25,30,.38);

    z-index: 999;

    align-items: center;
    justify-content: center;
}

.modal-overlay.show {
    display: flex;
}

.modal {
    width: min(420px,90%);

    background: white;

    border-radius: 20px;

    padding: 24px;

    box-shadow:
        0 25px 70px rgba(0,0,0,.22);

    animation: modalIn .2s ease;
}

@keyframes modalIn {

    from {
        opacity: 0;
        transform:
            translateY(10px)
            scale(.97);
    }

    to {
        opacity: 1;
        transform:
            translateY(0)
            scale(1);
    }
}

.modal-top {
    display: flex;

    justify-content: space-between;

    align-items: center;
}

.modal h3 {
    font-size: 18px;
}

.close-modal {
    width: 30px;
    height: 30px;

    border: none;

    background: var(--pink-light);

    border-radius: 9px;

    font-size: 18px;
}

.modal p {
    color: var(--gray);

    font-size: 12px;

    line-height: 1.6;

    margin: 12px 0 18px;
}

.modal-button {
    border: none;

    background: var(--pink);

    color: var(--black);

    padding: 10px 18px;

    border-radius: 10px;

    font-weight: 800;
}

/* responsive */
@media (max-width: 1100px) {

    .stats {
        grid-template-columns: 1fr;
    }

    .dashboard-layout {
        grid-template-columns: 1fr;
    }

    .stats-left {
        grid-template-columns:
            repeat(2,1fr);
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .bottom-row {
        grid-template-columns: 1fr;
    }

    .overview-grid {
        grid-template-columns:
            repeat(2,1fr);
    }
}

@media (max-width: 800px) {

    .sidebar {
        width: 76px;
    }

    .brand {
        padding-left: 0;
        justify-content: center;
    }

    .brand h1,
    .brand small,
    .nav-btn span:last-child,
    .owner-mini > div:not(.avatar) {
        display: none;
    }

    .nav-btn {
        justify-content: center;
    }

    .main {
        margin-left: 76px;

        width: calc(100% - 76px);
    }

    .search-box {
        width: 180px;
    }
}

@media (max-width: 600px) {

    .main {
        padding: 18px;
    }

    .topbar {
        flex-direction: column;

        gap: 15px;
    }

    .top-actions {
        width: 100%;
    }

    .search-box {
        flex: 1;
    }

    .stats-left {
        grid-template-columns:
            1fr 1fr;
    }

    .order-row {
        grid-template-columns:
            1.5fr .8fr .7fr;
    }

    .order-row > div:nth-child(2) {
        display: none;
    }

    .datetime {
        flex-wrap: wrap;
    }

    .overview-grid {
        grid-template-columns: 1fr;
    }
}

/* inner page */
.page-description {
    color: var(--gray);
    font-size: 11px;
    margin-top: 4px;
}

/* booking table */
.booking-table {
    width: 100%;
    overflow-x: auto;
}

.booking-row {
    min-width: 720px;

    display: grid;

    grid-template-columns:
        .8fr
        1fr
        1fr
        .9fr
        .9fr;

    align-items: center;

    gap: 12px;

    padding: 14px 8px;

    border-top: 1px solid var(--gray-light);

    font-size: 11px;
}

.booking-header {
    border-top: none;

    color: var(--gray);

    font-size: 9px;

    font-weight: 900;

    text-transform: uppercase;
}

.booking-row strong {
    font-size: 11px;
}

/* completed orders table — 6 columns (Customer + Courier) instead of 5, same as Staff Dashboard */

.completed-row {
    min-width: 760px;

    display: grid;

    grid-template-columns:
        .7fr
        1fr
        .8fr
        .9fr
        .8fr
        .8fr;

    align-items: center;

    gap: 12px;

    padding: 14px 8px;

    border-top: 1px solid var(--gray-light);

    font-size: 11px;
}

.completed-header {
    border-top: none;

    color: var(--gray);

    font-size: 9px;

    font-weight: 900;

    text-transform: uppercase;
}

.completed-row strong {
    font-size: 11px;
}

/* full calendar */
.full-calendar-card {
    height: fit-content;
}

.calendar-page-controls {
    display: flex;

    align-items: center;

    justify-content: space-between;

    margin: 10px 0 25px;
}

.calendar-page-controls h3 {
    font-size: 18px;
}

.calendar-page-controls button {
    width: 42px;
    height: 42px;

    border: none;

    background: var(--pink-light);

    color: var(--pink-dark);

    border-radius: 12px;

    font-size: 25px;

    font-weight: 900;

    transition: .2s;
}

.calendar-page-controls button:hover {
    background: var(--pink);

    color: white;
}

.full-week,
.full-calendar-days {
    width: 100%;
}

.full-week div {
    font-size: 12px;
    font-weight: 800;
}

.full-calendar-days {
    gap: 6px;
}

.large-day {
    height: 46px;

    border: none;

    border-radius: 11px;

    font-size: 12px;
}

.large-day:hover {
    background: var(--pink-soft);
}

.large-day.today {
    background: var(--pink);
}

.large-day.booked {
    background: var(--pink-light);

    color: var(--pink-dark);
}

/* responsive inner pages */
@media (max-width: 800px) {

    .quotation-info {
        grid-template-columns: 1fr;
    }

    .full-calendar-card {
        overflow-x: auto;
    }

    .full-calendar-days,
    .full-week {
        min-width: 600px;
    }

}


/* user management */

.user-header,
.user-row {
    grid-template-columns:
        .5fr
        1fr
        1.5fr
        .8fr
        1fr;
}

.role-badge {
    display: inline-block;

    padding: 5px 9px;

    border-radius: 99px;

    background: var(--pink-light);

    color: var(--pink-dark);

    font-size: 9px;

    font-weight: 900;
}

</style>


</head>


<body>


<div class="app">


<!-- SIDEBAR (shared across every owner page) -->

<?php require_once __DIR__ . "/owner-sidebar.php"; ?>



<!-- MAIN -->

<main class="main">



    <!-- TOPBAR -->

    <header class="topbar">


        <div class="welcome">


            <h2>

                Good afternoon,
                <?php echo e($ownerName); ?> ✦

            </h2>


            <p>

                Here's what is happening with Figurify today.

            </p>



            <div class="datetime">


                <div class="datetime-icon">

                    ◷

                </div>


                <span id="currentDate">

                    Loading date...

                </span>


                <span class="datetime-dot">

                    •

                </span>


                <span id="currentTime">

                    Loading time...

                </span>


            </div>


        </div>


        <div class="top-actions">

            <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>

        </div>


    </header>



    <!-- STATISTICS -->

    <div class="dashboard-layout">


        <div class="dashboard-left">


        <div class="stats-left">


            <!-- TOTAL BOOKINGS -->

            <div class="card stat-card">


                <div class="stat-label">

                    TOTAL BOOKINGS

                </div>


                <div class="stat-number">

                    <?php echo $allBookings; ?>

                </div>


                <div class="stat-trend">

                    ● All customer orders

                </div>


            </div>



            <!-- PENDING -->

            <div class="card stat-card pending-card">


                <div class="stat-label">

                    PENDING

                </div>


                <div class="stat-number">

                    <?php echo $pendingBookings; ?>

                </div>


                <div class="stat-trend attention">

                    ● Awaiting quotation

                </div>


            </div>



            <!-- FOR APPROVAL -->

            <div class="card stat-card approval-card">


                <div class="stat-label">

                    FOR APPROVAL

                </div>


                <div class="stat-number">

                    <?php echo $forApproval; ?>

                </div>


                <div class="stat-trend attention">

                    ● Needs attention

                </div>


            </div>



            <!-- ACTIVE -->

            <div class="card stat-card">


                <div class="stat-label">

                    ACTIVE ORDERS

                </div>


                <div class="stat-number">

                    <?php echo $activeOrders; ?>

                </div>


                <div class="stat-trend">

                    ● Currently processing

                </div>


            </div>


        </div>



        <div class="bottom-row">


        <!-- CALENDAR -->

            <section class="card">


                <div class="card-header">


                    <h3 id="calendarTitle">

                        Calendar

                    </h3>


                </div>



                <div class="calendar-controls">

                    <button
                        type="button"
                        id="previousMonth"
                        class="calendar-arrow-btn"
                        aria-label="Previous month"
                    >

                        <svg class="calendar-arrow-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>

                    </button>


                    <strong id="calendarMonth">

                        Loading...

                    </strong>


                    <button
                        type="button"
                        id="nextMonth"
                        class="calendar-arrow-btn"
                        aria-label="Next month"
                    >

                        <svg class="calendar-arrow-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>

                    </button>

                </div>



                <div class="calendar-week">


                    <div>MON</div>

                    <div>TUE</div>

                    <div>WED</div>

                    <div>THU</div>

                    <div>FRI</div>

                    <div>SAT</div>

                    <div>SUN</div>


                </div>



                <div
                    class="calendar-days"
                    id="calendarDays"
                ></div>



                <div class="calendar-legend">


                    <span>

                        <i class="legend-dot available-dot"></i>

                        Available

                    </span>


                    <span>

                        <i class="legend-dot booked-dot"></i>

                        Booked

                    </span>


                    <span>

                        <i class="legend-dot unavailable-dot"></i>

                        Unavailable

                    </span>


                    <span>

                        <i class="legend-dot today-dot"></i>

                        Today

                    </span>


                </div>


            </section>


        <!-- RECENT BOOKINGS -->

        <section class="card">


            <div class="card-header">


                <h3>

                    Recent Bookings

                </h3>


                <button
                    type="button"
                    class="view-link"
                    id="viewBookings"
                >

                    View all →

                </button>


            </div>



            <!-- TABLE HEADER -->

            <div class="order-row header">


                <div>

                    Order

                </div>


                <div>

                    Type

                </div>


                <div>

                    Date

                </div>


                <div>

                    Status

                </div>


            </div>



            <div id="orders">


            <?php if (!empty($recentOrders)): ?>


                <?php foreach ($recentOrders as $order): ?>


                    <?php


                    // STATUS — from shared helper (order_status_helper.php),
                    // same kulay/label ng Staff Dashboard, Calendar, My Orders.

                    $statusClass   = figurify_status_class($order["status"] ?? "pending");
                    $displayStatus = statusLabel($order["status"] ?? "pending");



                    // booking date
                    $bookingDate = "—";


                    if (!empty($order["booking_date"])) {

                        $bookingTimestamp = strtotime(
                            $order["booking_date"]
                        );


                        if ($bookingTimestamp !== false) {

                            $bookingDate = date(
                                "M d",
                                $bookingTimestamp
                            );

                        }

                    }



                    // order type
                    $orderType = strtolower(
                        trim(
                            $order["order_type"] ?? ""
                        )
                    );


                    if ($orderType === "rush") {

                        $orderTypeDisplay = "Rush";

                    }

                    elseif ($orderType === "nonrush") {

                        $orderTypeDisplay = "Non-Rush";

                    }

                    else {

                        $orderTypeDisplay = ucwords(
                            str_replace(
                                "_",
                                " ",
                                $orderType
                            )
                        );

                    }



                    // search data
                    $searchData =
                        "FG-" .
                        ($order["order_id"] ?? "") .
                        " " .
                        ($order["order_type"] ?? "") .
                        " " .
                        ($order["status"] ?? "") .
                        " " .
                        ($order["booking_date"] ?? "");

                    ?>


                    <div
                        class="order-row searchable-order"
                        data-search="<?php echo e($searchData); ?>"
                        data-order-id="<?php echo (int) ($order["order_id"] ?? 0); ?>"
                        data-order-status="<?php echo e($order["status"] ?? "pending"); ?>"
                    >


                        <!-- CUSTOMER / ORDER -->

                        <div class="customer">


                            <div>


                                <strong>

                                    Order #<?php
                                    echo (int)$order["order_id"];
                                    ?>

                                </strong>


                            </div>


                        </div>



                        <!-- TYPE -->

                        <div>

                            <?php
                            echo e(
                                $orderTypeDisplay
                            );
                            ?>

                        </div>



                        <!-- DATE -->

                        <div>

                            <?php
                            echo e(
                                $bookingDate
                            );
                            ?>

                        </div>



                        <!-- STATUS -->

                        <div>


                            <span
                                class="
                                    status
                                    <?php
                                    echo e($statusClass);
                                    ?>
                                "
                            >

                                <?php
                                echo e(
                                    $displayStatus
                                );
                                ?>

                            </span>


                        </div>


                    </div>


                <?php endforeach; ?>


            <?php else: ?>


                <div class="empty-state">

                    No orders yet.

                </div>


            <?php endif; ?>


            </div>


        </section>



        </div>


        </div>


        <div class="dashboard-right">

        <!-- SHIPMENT SUMMARY -->

        <div class="card stat-card shipment-stat-card">

            <div class="stat-label" style="display:flex !important; align-items:center !important; justify-content:space-between !important; gap:8px !important;">
                <span>SHIPMENT SUMMARY</span>

                <a
                    href="shipments.php"
                    style="font-size:11px !important; font-weight:700 !important; color:var(--pink, #e0447f) !important; text-decoration:none !important; white-space:nowrap !important;"
                >
                    View All Shipments
                </a>
            </div>

            <?php
                figurify_render_shipment_summary(
                    $shipmentSummaryOrders,
                    "No shipments to prepare right now."
                );
            ?>

        </div>

        </div>


    </div>





</main>


</div>



<!-- MODAL -->

<div
    class="modal-overlay"
    id="modalOverlay"
>


    <div class="modal">


        <div class="modal-top">


            <h3 id="modalTitle">

                Figurify

            </h3>


            <button
                type="button"
                class="close-modal"
                id="closeModal"
            >

                ×

            </button>


        </div>



        <p id="modalMessage">

            Message

        </p>



        <button
            type="button"
            class="modal-button"
            id="modalOkay"
        >

            Okay

        </button>


    </div>


</div>



<script src="../Shared/dashboard.js"></script>
<script>
    const figurifyBookedDates = <?php echo json_encode($bookedDates); ?>;

// owner dashboard JS (updateDateTime()/formatDate() are in Shared/dashboard.js, loaded above)


// search orders
const searchInput =
    document.getElementById(
        "searchOrders"
    );


if (searchInput) {

    searchInput.addEventListener(
        "input",
        function() {

            const value =
                this.value
                    .toLowerCase()
                    .trim();


            const orders =
                document.querySelectorAll(
                    ".searchable-order"
                );


            orders.forEach(
                order => {

                    const text =
                        (
                            order.dataset.search
                            || ""
                        ).toLowerCase();


                    order.style.display =
                        text.includes(value)
                        ? "grid"
                        : "none";

                }
            );

        }
    );

}


// dashboard buttons
const viewBookings =
    document.getElementById(
        "viewBookings"
    );


viewBookings?.addEventListener(
    "click",
    function() {

        window.location.href =
            "allbookings.php";

    }
);


const viewShipments =
    document.getElementById(
        "viewShipments"
    );


viewShipments?.addEventListener(
    "click",
    function() {

        window.location.href =
            "active-booking.php";

    }
);


// quick actions
document
    .querySelectorAll(
        ".quick-btn"
    )
    .forEach(button => {

        button.addEventListener(
            "click",
            function() {

                const action =
                    this.dataset.action;


                if (
                    action ===
                    "quotation"
                ) {

                    window.location.href =
                        "quotation.php";

                }


                if (
                    action ===
                    "new-booking"
                ) {

                    window.location.href =
                        "allbookings.php";

                }

            }
        );

    });


// dashboard calendar
const calendarDays =
    document.getElementById(
        "calendarDays"
    );

const calendarMonth =
    document.getElementById(
        "calendarMonth"
    );

const previousMonth =
    document.getElementById(
        "previousMonth"
    );

const nextMonth =
    document.getElementById(
        "nextMonth"
    );


let currentCalendarDate =
    new Date();


const bookingDates =
    typeof figurifyBookedDates !== "undefined"
    ? figurifyBookedDates
    : [];


/* formatDate() ay nasa Shared/dashboard.js na rin — iisang
   pinagmumulan lang para sa Owner AT Staff calendar. */


function renderCalendar() {

    if (
        !calendarDays ||
        !calendarMonth
    ) {

        return;

    }


    const year =
        currentCalendarDate
            .getFullYear();


    const month =
        currentCalendarDate
            .getMonth();


    calendarMonth.textContent =
        currentCalendarDate.toLocaleDateString(
            "en-US",
            {
                month: "long",
                year: "numeric"
            }
        );


    // Di pwede bumalik sa nakalipas na buwan — kasalukuyang buwan
    // na lang ang pinaka-maaga.
    const now = new Date();

    const isAtCurrentMonth =
        year === now.getFullYear() &&
        month === now.getMonth();

    if (previousMonth) {

        previousMonth.disabled = isAtCurrentMonth;

        previousMonth.style.opacity =
            isAtCurrentMonth ? "0.4" : "1";

        previousMonth.style.cursor =
            isAtCurrentMonth ? "not-allowed" : "pointer";

    }


    calendarDays.innerHTML = "";


    const jsFirstDay =
        new Date(
            year,
            month,
            1
        ).getDay();


    const firstDay =
        jsFirstDay === 0
            ? 6
            : jsFirstDay - 1;


    const daysInMonth =
        new Date(
            year,
            month + 1,
            0
        ).getDate();


    for (
        let i = 0;
        i < firstDay;
        i++
    ) {

        const empty =
            document.createElement(
                "div"
            );


        empty.className =
            "day empty";


        calendarDays.appendChild(
            empty
        );

    }


    const today =
        new Date();


    for (
        let day = 1;
        day <= daysInMonth;
        day++
    ) {

        const dayElement =
            document.createElement(
                "div"
            );


        dayElement.className =
            "day";


        dayElement.textContent =
            day;


        const dateString =
            formatDate(
                year,
                month,
                day
            );

        dayElement.dataset.date = dateString;


        const isToday =
            today.getFullYear() === year &&
            today.getMonth() === month &&
            today.getDate() === day;

        if (isToday) {

            dayElement.classList.add(
                "today"
            );

        }


        const isBooked =
            bookingDates.includes(
                dateString
            );

        if (isBooked) {

            dayElement.classList.add(
                "booked"
            );

        }


        // Kapareho ng Commission Calendar: hindi na puwedeng
        // piliin ang mga araw na nakalipas na — "Unavailable"
        // ang estado nila dito.
        const cellDate =
            new Date(year, month, day);

        const todayAtMidnight =
            new Date(
                today.getFullYear(),
                today.getMonth(),
                today.getDate()
            );

        const isPast = cellDate < todayAtMidnight;

        if (isPast) {

            dayElement.classList.add(
                "unavailable"
            );

        } else if (!isBooked) {

            dayElement.classList.add(
                "available"
            );

        }

        // Read-only preview lang ang dashboard calendar — walang
        // click-to-select dito (buwan lang ang mapapalitan gamit
        // ang prev/next arrows), kaya wala nang "selected" state
        // o click listener sa bawat araw.

        calendarDays.appendChild(
            dayElement
        );

    }

}


previousMonth?.addEventListener(
    "click",
    function() {

        const now = new Date();

        const isAtCurrentMonth =
            currentCalendarDate.getFullYear() === now.getFullYear() &&
            currentCalendarDate.getMonth() === now.getMonth();

        if (isAtCurrentMonth) {
            return;
        }

        currentCalendarDate.setMonth(
            currentCalendarDate.getMonth() - 1
        );

        renderCalendar();

    }
);


nextMonth?.addEventListener(
    "click",
    function() {

        currentCalendarDate.setMonth(
            currentCalendarDate.getMonth() + 1
        );

        renderCalendar();

    }
);


renderCalendar();

</script>


</body>

</html>