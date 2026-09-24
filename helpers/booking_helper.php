<?php

/* Booked-dates helper — ginagamit ng lahat ng calendar (owner, staff,
   commission). Isang date "booked" lang kapag status "processing" na
   pataas — "pending" ay hindi pa naka-block sa ibang customer. */

const FIGURIFY_BOOKED_CALENDAR_STATUSES = [
    "processing",
    "to_ship",
    "shipped",
    "completed"
];


/* Rush: 7-day lead, max 2/araw, max 10/buwan. Non-rush: last day ng
   buwan lang bookable, 7-day lead, max 15/date. "processing" pataas
   lang ang bilang. */

const FIGURIFY_RUSH_MIN_LEAD_DAYS   = 7;
const FIGURIFY_RUSH_DAILY_CAP       = 2;
const FIGURIFY_RUSH_MONTHLY_CAP     = 10;

const FIGURIFY_NONRUSH_MIN_LEAD_DAYS = 7;
const FIGURIFY_NONRUSH_DATE_CAP      = 15;


/* Rush fee per figure, base sa product type (Chibi/Funko Pop/
   Hirono pareho ang product names): Head Only Keychain = ₱200,
   lahat ng iba = ₱500. Sinusuma per figure sa order, hindi flat
   per-order na lang.

   IMPORTANT: dapat magkatugma sa mapping sa
   Commission/commission.js (getRushFeeForProductType()). */

const FIGURIFY_RUSH_FEE_HEAD_ONLY     = 200;
const FIGURIFY_RUSH_FEE_DEFAULT       = 500;

function figurify_get_rush_fee_for_product_type(?string $productType): int
{
    if ($productType === null) {
        return 0;
    }

    $normalized = strtolower(trim($productType));

    if ($normalized === "") {
        return 0;
    }

    if (strpos($normalized, "head only") !== false) {
        return FIGURIFY_RUSH_FEE_HEAD_ONLY;
    }

    // ibang product types -> default rush fee
    return FIGURIFY_RUSH_FEE_DEFAULT;
}

// sinusuma ang rush fee ng bawat figure sa order

function figurify_calculate_total_rush_fee(array $figures): int
{
    $total = 0;

    foreach ($figures as $figure) {

        $productType = $figure["product"] ?? "";

        $total += figurify_get_rush_fee_for_product_type($productType);

    }

    return $total;
}


// bilang ng order per date para sa isang order_type (rush/nonrush),
// "processing" status pataas lang — basehan ng pagtukoy kung puno na

function figurify_get_order_type_day_counts(mysqli $conn, string $orderType): array
{
    $bookedStatuses = FIGURIFY_BOOKED_CALENDAR_STATUSES;

    $placeholders = implode(
        ",",
        array_fill(0, count($bookedStatuses), "?")
    );

    $stmt = $conn->prepare(
        "SELECT booking_date, COUNT(*) AS cnt
         FROM orders
         WHERE order_type = ?
           AND LOWER(status) IN ($placeholders)
           AND booking_date IS NOT NULL
         GROUP BY booking_date"
    );

    $types  = "s" . str_repeat("s", count($bookedStatuses));
    $params = array_merge([$orderType], $bookedStatuses);

    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $result = $stmt->get_result();

    $counts = [];

    while ($row = $result->fetch_assoc()) {

        if (empty($row["booking_date"])) {
            continue;
        }

        $dateKey = date("Y-m-d", strtotime($row["booking_date"]));
        $counts[$dateKey] = (int) $row["cnt"];

    }

    $stmt->close();

    return $counts;
}

function figurify_get_rush_day_counts(mysqli $conn): array
{
    return figurify_get_order_type_day_counts($conn, "rush");
}

function figurify_get_nonrush_date_counts(mysqli $conn): array
{
    return figurify_get_order_type_day_counts($conn, "nonrush");
}


// rush availability check sa server side (huwag lang umasa sa JS)

function figurify_check_rush_date(mysqli $conn, string $dateStr): array
{
    $bookingDate = DateTime::createFromFormat("Y-m-d", $dateStr);

    if (!$bookingDate) {
        return ["ok" => false, "reason" => "Invalid booking date."];
    }

    $today = new DateTime("today");

    $minDate = (clone $today)->modify("+" . FIGURIFY_RUSH_MIN_LEAD_DAYS . " day");

    if ($bookingDate < $minDate) {
        return [
            "ok"     => false,
            "reason" => "Rush orders must be booked at least " .
                        FIGURIFY_RUSH_MIN_LEAD_DAYS . " days from today."
        ];
    }

    $dayCounts = figurify_get_rush_day_counts($conn);

    /* Monthly cap */
    $monthKey    = $bookingDate->format("Y-m");
    $monthTotal  = 0;

    foreach ($dayCounts as $d => $c) {
        if (strpos($d, $monthKey) === 0) {
            $monthTotal += $c;
        }
    }

    if ($monthTotal >= FIGURIFY_RUSH_MONTHLY_CAP) {
        return [
            "ok"     => false,
            "reason" => "Rush order slots for that month are already full (" .
                        FIGURIFY_RUSH_MONTHLY_CAP . "/month)."
        ];
    }

    /* Daily cap */
    $dayCount = $dayCounts[$dateStr] ?? 0;

    if ($dayCount >= FIGURIFY_RUSH_DAILY_CAP) {
        return [
            "ok"     => false,
            "reason" => "That date already has the maximum of " .
                        FIGURIFY_RUSH_DAILY_CAP . " rush orders."
        ];
    }

    return ["ok" => true, "reason" => null];
}


// non-rush availability check sa server side

function figurify_check_nonrush_date(mysqli $conn, string $dateStr): array
{
    $bookingDate = DateTime::createFromFormat("Y-m-d", $dateStr);

    if (!$bookingDate) {
        return ["ok" => false, "reason" => "Invalid booking date."];
    }

    $lastDay = (int) $bookingDate->format("t");

    if ((int) $bookingDate->format("j") !== $lastDay) {
        return [
            "ok"     => false,
            "reason" => "Non-rush orders may only be booked on the last day of a month."
        ];
    }

    $today  = new DateTime("today");
    $cutoff = (clone $bookingDate)->modify("-" . FIGURIFY_NONRUSH_MIN_LEAD_DAYS . " day");

    if ($today > $cutoff) {
        return [
            "ok"     => false,
            "reason" => "Too late to book this month's non-rush date. " .
                        "Please choose next month's last day instead."
        ];
    }

    $counts = figurify_get_nonrush_date_counts($conn);
    $count  = $counts[$dateStr] ?? 0;

    if ($count >= FIGURIFY_NONRUSH_DATE_CAP) {
        return [
            "ok"     => false,
            "reason" => "That date is already fully booked (" .
                        FIGURIFY_NONRUSH_DATE_CAP . " orders). " .
                        "Please choose next month's last day instead."
        ];
    }

    return ["ok" => true, "reason" => null];
}

function figurify_get_booked_dates(mysqli $conn): array
{
    $bookedStatuses = FIGURIFY_BOOKED_CALENDAR_STATUSES;

    $placeholders = implode(
        ",",
        array_fill(0, count($bookedStatuses), "?")
    );

    $stmt = $conn->prepare(
        "SELECT DISTINCT booking_date
         FROM orders
         WHERE LOWER(status) IN ($placeholders)
           AND booking_date IS NOT NULL"
    );

    $types = str_repeat("s", count($bookedStatuses));
    $stmt->bind_param($types, ...$bookedStatuses);
    $stmt->execute();

    $result = $stmt->get_result();

    $dates = [];

    while ($row = $result->fetch_assoc()) {

        if (empty($row["booking_date"])) {
            continue;
        }

        $dates[] = date("Y-m-d", strtotime($row["booking_date"]));
    }

    $stmt->close();

    return array_values(array_unique($dates));
}


// bookings grouped by date, para sa calendar pages — pag na-click
// ang isang booked na araw, ito ang listahan ng order(s) doon

function figurify_get_bookings_by_date(mysqli $conn): array
{
    $bookedStatuses = FIGURIFY_BOOKED_CALENDAR_STATUSES;

    $placeholders = implode(
        ",",
        array_fill(0, count($bookedStatuses), "?")
    );

    $stmt = $conn->prepare(
        "SELECT o.order_id, o.order_type, o.order_method, o.booking_date, o.status,
                u.full_name, u.email
         FROM orders o
         JOIN users u ON u.user_id = o.user_id
         WHERE LOWER(o.status) IN ($placeholders)
           AND o.booking_date IS NOT NULL
         ORDER BY o.booking_date ASC, o.created_at ASC"
    );

    $types = str_repeat("s", count($bookedStatuses));
    $stmt->bind_param($types, ...$bookedStatuses);
    $stmt->execute();

    $result = $stmt->get_result();

    $byDate = [];

    while ($row = $result->fetch_assoc()) {

        if (empty($row["booking_date"])) {
            continue;
        }

        $dateKey = date("Y-m-d", strtotime($row["booking_date"]));

        if (!isset($byDate[$dateKey])) {
            $byDate[$dateKey] = [];
        }

        $byDate[$dateKey][] = [
            "order_id"     => (int) $row["order_id"],
            "order_type"   => $row["order_type"],
            "order_method" => $row["order_method"],
            "status"       => $row["status"],
            "full_name"    => $row["full_name"],
            "email"        => $row["email"]
        ];
    }

    $stmt->close();

    return $byDate;
}


// orders na "to_ship" na status at malapit/nasa booking_date na —
// walang hiwalay na shipping_date column, booking_date ang basehan.
// default 2 days ($withinDays) bago i-flag bilang "upcoming"

function figurify_get_upcoming_shipments(mysqli $conn, int $withinDays = 2): array
{
    $shipments = [];

    $stmt = $conn->prepare(
        "SELECT o.order_id, o.order_type, o.booking_date, o.total_amount,
                u.full_name
         FROM orders o
         JOIN users u ON u.user_id = o.user_id
         WHERE LOWER(o.status) = 'to_ship'
           AND o.booking_date IS NOT NULL
           AND o.booking_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
         ORDER BY o.booking_date ASC"
    );

    $stmt->bind_param("i", $withinDays);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $shipments[] = $row;
    }

    $stmt->close();

    return $shipments;
}


// lahat ng shipments (to_ship + shipped), para sa "View All
// Shipments" page — hindi limitado sa $withinDays gaya ng function
// sa itaas. $onlyStatus para hiwalayin sa To Ship / Shipped tab

function figurify_get_all_shipments(mysqli $conn, ?string $onlyStatus = null): array
{
    $shipments = [];

    $statusFilter = "LOWER(o.status) IN ('to_ship', 'shipped')";
    $params       = [];
    $types        = "";

    if ($onlyStatus !== null && in_array($onlyStatus, ["to_ship", "shipped"], true)) {
        $statusFilter = "LOWER(o.status) = ?";
        $params[]     = $onlyStatus;
        $types       .= "s";
    }

    try {

        $sql = "SELECT o.order_id, o.order_type, o.order_method, o.status, o.booking_date,
                       o.shipped_at, o.courier, o.tracking_number, o.total_amount,
                       u.full_name, u.email
                FROM orders o
                JOIN users u ON u.user_id = o.user_id
                WHERE {$statusFilter}
                ORDER BY o.booking_date ASC";

        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            throw new \mysqli_sql_exception("prepare() returned false");
        }

        if ($types !== "") {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $shipments[] = $row;
        }

        $stmt->close();

    } catch (\Throwable $e) {

        // fallback kung wala pang shipped_at column (di pa na-migrate)

        $sql = "SELECT o.order_id, o.order_type, o.order_method, o.status, o.booking_date,
                       o.courier, o.tracking_number, o.total_amount,
                       u.full_name, u.email
                FROM orders o
                JOIN users u ON u.user_id = o.user_id
                WHERE {$statusFilter}
                ORDER BY o.booking_date ASC";

        $stmt = $conn->prepare($sql);

        if ($types !== "") {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $row["shipped_at"] = null;
            $shipments[] = $row;
        }

        $stmt->close();

    }

    return $shipments;
}
