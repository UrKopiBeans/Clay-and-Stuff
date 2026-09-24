<?php

require_once "owner-header.php";
require_once __DIR__ . "/../helpers/activity_log_helper.php";


/* MESSAGE */

$success = "";
$error = "";


/* CREATE USER */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["action"])
    && $_POST["action"] === "create_user"
) {

    $full_name = trim($_POST["full_name"] ?? "");
    $email     = trim($_POST["email"] ?? "");
    $password  = $_POST["password"] ?? "";
    $role      = strtolower(trim($_POST["role"] ?? "customer"));

    $allowedRoles = [
        "customer",
        "staff",
        "admin"
    ];

    if (!in_array($role, $allowedRoles, true)) {
        $error = "Invalid account role.";
    }


    elseif (
        $full_name === ""
        || $email === ""
        || $password === ""
    ) {

        $error = "Please complete all required fields.";

    }


    elseif (strlen($password) < 6) {

        $error = "Password must contain at least 6 characters.";

    }


    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    }


    else {

        $check = $conn->prepare(
            "SELECT user_id
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $check->bind_param(
            "s",
            $email
        );

        $check->execute();

        $check->store_result();


        if ($check->num_rows > 0) {

            $error = "An account with this email already exists.";

            $check->close();

        } else {

            $check->close();


            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            $stmt = $conn->prepare(
                "INSERT INTO users
                (
                    full_name,
                    email,
                    password,
                    role
                )
                VALUES (?, ?, ?, ?)"
            );


            $stmt->bind_param(
                "ssss",
                $full_name,
                $email,
                $hashedPassword,
                $role
            );


            if ($stmt->execute()) {

                $success =
                    "Account created successfully.";

                figurify_log_activity(
                    $conn,
                    "user_created",
                    "Created a new \"" . $role . "\" account: " . $full_name . " (" . $email . ")"
                );

            } else {

                $error =
                    "Unable to create account.";

            }


            $stmt->close();
        }
    }
}


/* UPDATE ROLE */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["action"])
    && $_POST["action"] === "update_role"
) {

    $user_id = (int)($_POST["user_id"] ?? 0);
    $role    = strtolower(trim($_POST["role"] ?? ""));


    $allowedRoles = [
        "customer",
        "staff",
        "admin"
    ];


    if ($user_id <= 0) {

        $error = "Invalid user.";

    }

    elseif (!in_array($role, $allowedRoles, true)) {

        $error = "Invalid role.";

    }

    elseif ($user_id === (int)$_SESSION["user_id"]) {

        $error =
            "You cannot change your own owner role.";

    }

    else {

        $targetStmt = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
        $targetStmt->bind_param("i", $user_id);
        $targetStmt->execute();
        $targetUser = $targetStmt->get_result()->fetch_assoc();
        $targetStmt->close();

        $stmt = $conn->prepare(
            "UPDATE users
             SET role = ?
             WHERE user_id = ?"
        );

        $stmt->bind_param(
            "si",
            $role,
            $user_id
        );


        if ($stmt->execute()) {

            $success =
                "User role updated successfully.";

            figurify_log_activity(
                $conn,
                "user_role_updated",
                "Changed " . ($targetUser["full_name"] ?? ("user #" . $user_id)) . "'s role to \"" . $role . "\""
            );

        } else {

            $error =
                "Unable to update user role.";

        }


        $stmt->close();
    }
}


/* DELETE USER */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["action"])
    && $_POST["action"] === "delete_user"
) {

    $user_id = (int)($_POST["user_id"] ?? 0);


    if ($user_id <= 0) {

        $error = "Invalid user.";

    }

    elseif ($user_id === (int)$_SESSION["user_id"]) {

        $error =
            "You cannot delete your own account.";

    }

    else {

        $targetStmt = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
        $targetStmt->bind_param("i", $user_id);
        $targetStmt->execute();
        $targetUser = $targetStmt->get_result()->fetch_assoc();
        $targetStmt->close();

        $stmt = $conn->prepare(
            "DELETE FROM users
             WHERE user_id = ?"
        );

        $stmt->bind_param(
            "i",
            $user_id
        );


        if ($stmt->execute()) {

            $success =
                "User account deleted successfully.";

            figurify_log_activity(
                $conn,
                "user_deleted",
                "Deleted account: " . ($targetUser["full_name"] ?? ("user #" . $user_id))
                    . (!empty($targetUser["email"]) ? (" (" . $targetUser["email"] . ")") : "")
            );

        } else {

            $error =
                "Unable to delete user.";

        }


        $stmt->close();
    }
}


/* GET USERS */

$users = [];


// "last_seen" ay optional column (Online/Offline feature) — subukan muna, fallback sa query na wala nito
// kung hindi pa na-migrate ang DB.

try {

    $result = $conn->query(
        "SELECT
            user_id,
            full_name,
            email,
            role,
            last_seen
         FROM users
         ORDER BY user_id DESC"
    );

} catch (\Throwable $e) {

    $result = $conn->query(
        "SELECT
            user_id,
            full_name,
            email,
            role
         FROM users
         ORDER BY user_id DESC"
    );

}


if ($result) {

    while ($row = $result->fetch_assoc()) {

        $row["last_seen"] = $row["last_seen"] ?? null;

        $users[] = $row;

    }
}


// Group users — Admin muna, Staff (iisang "Team" container), hiwalay na container ang Customers.
// Walang "owner" role sa DB, "Admin" ang sumasagisag sa owner account.

$adminUsers    = [];
$staffUsers    = [];
$customerUsers = [];

foreach ($users as $u) {

    if ($u["role"] === "admin") {
        $adminUsers[] = $u;
    } elseif ($u["role"] === "staff") {
        $staffUsers[] = $u;
    } else {
        $customerUsers[] = $u;
    }

}

$teamUsers = array_merge($adminUsers, $staffUsers);


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

<title>
    Figurify — User Management
</title>

<style>
/* Shared dashboard styles (layout, sidebar, cards, tables, status colors) common sa owner AT staff.
   owner.css/staff.css ay nag-@import na lang dito, role-specific styles na lang ang natitira sa kanila. */

/* FIGURIFY — OWNER DASHBOARD (Pastel / Cute / Modern / Clean) */

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

/* APP */

.app {
    min-height: 100vh;
}

/* SIDEBAR */

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

/* MAIN */

.main {
    margin-left: 230px;

    width: calc(100% - 230px);

    padding: 22px 22px 38px 14px;
}

/* TOPBAR */

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

/* DATE TIME */

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

/* TOP ACTIONS */

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

/* CARDS */

.card {
    background: white;

    border: 1px solid var(--gray-light);

    border-radius: 18px;

    padding: 17px;

    box-shadow:
        0 5px 16px rgba(71,45,55,.04);
}

/* STATISTICS */

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
        repeat(3,1fr);

    align-items: start;

    gap: 14px;
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

/* DASHBOARD LAYOUT — left: stats + calendar + recent bookings, right: tall shipment card */

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

    align-items: start;
}

/* DASHBOARD GRID */

.dashboard-grid {
    display: grid;

    grid-template-columns:
        1fr 1.2fr;

    gap: 18px;

    align-items: start;
}

/* CARD HEADER */

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

/* ORDERS */

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

/* STATUS */

.status {
    display: inline-block;

    padding: 5px 8px;

    border-radius: 99px;

    font-size: 9px;

    font-weight: 900;

    white-space: nowrap;
}

/* Status colors — pareho sa Staff Dashboard/Calendar/My Orders, galing Admin/order_status_helper.php */

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

/* CALENDAR CONTROLS */

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

/* CALENDAR */

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
    height: 30px;

    border-radius: 9px;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 10px;

    transition: .2s;
}

.day:hover {
    background: var(--pink-soft);
    cursor: pointer;
}

.day.empty {
    background: transparent;
}

.day.today {
    background: var(--pink);

    color: var(--black);

    font-weight: 900;
}

.day.booked {
    background: var(--pink-light);

    color: #a4476d;

    font-weight: 800;
}

.day.selected {
    outline: 2px solid var(--pink);
    outline-offset: 1px;
}

/* Dashboard calendar is read-only preview (unlike full calendar page), kaya disabled ang hover/pointer */
#calendarDays .day {
    pointer-events: none;
}

#calendarDays .day:hover {
    background: transparent;
    cursor: default;
}

/* CALENDAR LEGEND */

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

.today-dot {
    background: var(--pink);
}

.booked-dot {
    background: var(--pink-light);

    border: 1px solid #e8b9ca;
}

/* SHIPMENTS */

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

/* OVERVIEW */

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

    white-space: nowrap;
}

/* OFFLINE — kapareho ng .online-badge pero gray/muted, "Xh/Xd ago" text (see figurify_presence_status()) */
.offline-badge {
    color: var(--gray);

    background: var(--background);

    padding: 5px 9px;

    border-radius: 99px;

    font-size: 9px;

    font-weight: 900;

    white-space: nowrap;
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

/* QUICK ACTIONS */

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

/* EMPTY */

.empty-state {
    padding: 30px 10px;

    text-align: center;

    color: #a0959b;

    font-size: 11px;
}

/* MODAL */

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

/* RESPONSIVE */

@media (max-width: 1100px) {

    .stats {
        grid-template-columns: 1fr;
    }

    .dashboard-layout {
        grid-template-columns: 1fr;
    }

    .stats-left {
        grid-template-columns:
            repeat(3,1fr);
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

/* INNER PAGE */

.page-description {
    color: var(--gray);
    font-size: 11px;
    margin-top: 4px;
}

/* BOOKING TABLE */

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

/* Completed orders table (completed.php) — hiwalay sa .booking-row, 6 columns ito (may Customer/Courier) */

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

/* FULL CALENDAR */

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

/* RESPONSIVE INNER PAGES */

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


/* User Management styles — moved here from the old shared owner.css, dito lang ito ginagamit */

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


/* USER MANAGEMENT */

.user-page {
    max-width: 1400px;
}


/* HEADER */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 22px;
}

.page-header h2 {
    font-size: 26px;
}

.page-header p {
    color: #81777d;
    font-size: 12px;
    margin-top: 5px;
}


/* CREATE BUTTON */

.create-user-btn {
    border: none;

    background: #ff7aa2;
    color: #29252a;

    padding: 12px 18px;

    border-radius: 12px;

    font-weight: 800;
    font-size: 11px;

    cursor: pointer;

    transition: .2s;
}

.create-user-btn:hover {
    transform: translateY(-2px);
}


/* ALERT */

.alert {
    padding: 13px 15px;

    border-radius: 12px;

    margin-bottom: 15px;

    font-size: 11px;
    font-weight: 700;
}

.alert-success {
    background: #e1f3e7;
    color: #397a57;
}

.alert-error {
    background: #ffe4e8;
    color: #b34e5c;
}


/* Search + Create Account row (dati nasa page-header lang ang button) */

.user-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 15px;
}

.user-actions-row {
    display: flex;
    justify-content: flex-end;
    align-items: center;

    margin-bottom: 18px;
}

.user-search {
    width: 220px;

    border: 1px solid #eee7ea;

    border-radius: 12px;

    padding: 9px 12px;

    outline: none;

    font-size: 11px;
}

.user-search:focus {
    border-color: #ff7aa2;
}


/* Grouped containers — "Team" (Admin+Staff) at "Customers" hiwalay na .card, may sariling search box */

.user-group-card {
    margin-bottom: 20px;
}

.user-group-card:last-child {
    margin-bottom: 0;
}

.user-group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;

    margin-bottom: 14px;
}

.user-group-header h3 {
    font-size: 15px;
    font-weight: 800;
}

.user-group-header p {
    color: #9a8c94;
    font-size: 11px;
    margin-top: 3px;
}


/* table-layout: fixed + parehong % width sa Team at Customers table para pantay ang column sizes */

.user-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.user-table th:nth-child(1), .user-table td:nth-child(1) { width: 26%; }
.user-table th:nth-child(2), .user-table td:nth-child(2) { width: 26%; }
.user-table th:nth-child(3), .user-table td:nth-child(3) { width: 14%; }
.user-table th:nth-child(4), .user-table td:nth-child(4) { width: 18%; }
.user-table th:nth-child(5), .user-table td:nth-child(5) { width: 16%; }

.user-table th {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-table td:nth-child(1),
.user-table td:nth-child(2) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-table th {
    text-align: left;

    color: #9a8c94;

    font-size: 9px;

    text-transform: uppercase;

    padding: 12px;

    border-bottom: 1px solid #eee7ea;
}

.user-table td {
    padding: 13px 12px;

    border-bottom: 1px solid #f1eaed;

    font-size: 11px;
}


/* USER */

.user-info {
    display: flex;
    align-items: center;
    gap: 9px;
}

/* ROLE */

.role {
    display: inline-block;

    padding: 5px 9px;

    border-radius: 99px;

    font-size: 9px;

    font-weight: 900;
}

.role-admin {
    background: #ffe4ed;
    color: #a4476d;
}

.role-staff {
    background: #e8edff;
    color: #536fc5;
}

.role-customer {
    background: #e1f3e7;
    color: #3b8b62;
}


/* ACTIONS */

.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.action-btn {
    border: none;

    padding: 7px 9px;

    border-radius: 8px;

    font-size: 9px;

    font-weight: 800;

    cursor: pointer;
}

.edit-btn {
    background: #e8edff;
    color: #536fc5;
}

.delete-btn {
    background: #ffe4e8;
    color: #b34e5c;
}


/* MODAL */

.user-modal {
    position: fixed;

    inset: 0;

    background: rgba(35,25,30,.42);

    display: none;

    align-items: center;
    justify-content: center;

    z-index: 999;
}

.user-modal.show {
    display: flex;
}

.user-modal-box {
    width: min(450px, 92%);

    background: white;

    border-radius: 20px;

    padding: 24px;
}

.user-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 20px;
}

.user-modal-header h3 {
    font-size: 18px;
}

.close-user-modal {
    border: none;

    width: 30px;
    height: 30px;

    border-radius: 9px;

    background: #ffe4ed;

    font-size: 18px;

    cursor: pointer;
}


/* FORM */

.form-group {
    margin-bottom: 13px;
}

.form-group label {
    display: block;

    font-size: 10px;

    font-weight: 800;

    margin-bottom: 5px;
}

.form-group input,
.form-group select {
    width: 100%;

    border: 1px solid #eee7ea;

    border-radius: 10px;

    padding: 11px;

    outline: none;

    font-size: 11px;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #ff7aa2;
}

.submit-user {
    width: 100%;

    border: none;

    background: #ff7aa2;

    padding: 12px;

    border-radius: 11px;

    font-weight: 900;

    cursor: pointer;

    margin-top: 5px;
}


/* ROLE FORM */

.role-form {
    display: flex;
    align-items: center;
    gap: 6px;
}

.role-form select {
    border: 1px solid #eee7ea;

    padding: 6px;

    border-radius: 8px;

    font-size: 9px;
}

.save-role {
    border: none;

    background: #e1f3e7;

    color: #397a57;

    padding: 6px 8px;

    border-radius: 7px;

    font-size: 9px;

    font-weight: 800;

    cursor: pointer;
}


@media(max-width:800px) {

    .user-toolbar,
    .user-actions-row,
    .user-group-header {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }

    .user-search {
        width: 100%;
    }

    .user-table {
        min-width: 700px;
    }

    .user-page {
        overflow-x: auto;
    }

}

</style>

</head>


<body>


<div class="app">


<!-- SIDEBAR (shared across every owner page) -->

<?php require_once __DIR__ . "/owner-sidebar.php"; ?>


<!-- MAIN -->

<main class="main">

<div class="user-page">


    <!-- HEADER -->

    <div class="page-header">

        <div>

            <h2>
                User Management
            </h2>

            <p>
                Create and manage Figurify user accounts.
            </p>

        </div>


        <div class="top-actions">

            <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>

        </div>

    </div>


    <!-- ALERT -->

    <?php if ($success !== ""): ?>

        <div class="alert alert-success">

            ✓ <?php echo e($success); ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="alert alert-error">

            ! <?php echo e($error); ?>

        </div>

    <?php endif; ?>


    <!-- Create Account row — search box naman nasa loob ng bawat group container (Team, Customers) -->

    <div class="user-actions-row">

        <button
            type="button"
            class="create-user-btn"
            id="openCreateUser"
        >

            ＋ Create Account

        </button>

    </div>


    <!-- Users — dalawang hiwalay na container: "Team" (Admin+Staff) at "Customers", parehong gumagamit
         ng iisang renderer function sa ibaba -->

    <?php
    // Gumagawa lang ng <tr> ang function na ito — tatawagin dalawang beses (Team, Customers)
    function figurify_render_user_rows(array $userList, string $emptyMessage): void
    {
        if (empty($userList)) {
            ?>
            <tr>
                <td colspan="5" style="text-align:center;padding:35px;">
                    <?php echo e($emptyMessage); ?>
                </td>
            </tr>
            <?php
            return;
        }

        foreach ($userList as $user):

            $roleClass = "role-" . e($user["role"]);

            $presence = figurify_presence_status($user["last_seen"] ?? null);
            ?>

            <tr
                class="user-row"
                data-search="<?php
                    echo e(
                        $user["full_name"]
                        . " "
                        . $user["email"]
                        . " "
                        . $user["role"]
                    );
                ?>"
            >

                <td>
                    <div class="user-info">
                        <strong>
                            <?php echo e($user["full_name"]); ?>
                        </strong>
                    </div>
                </td>


                <td>
                    <?php echo e($user["email"]); ?>
                </td>


                <td>
                    <span class="role <?php echo $roleClass; ?>">
                        <?php echo e(ucfirst($user["role"])); ?>
                    </span>
                </td>


                <td>
                    <span class="<?php echo $presence["online"] ? "online-badge" : "offline-badge"; ?>">
                        <?php echo e($presence["label"]); ?>
                    </span>
                </td>


                <td>

                    <div class="actions">

                        <?php if ((int) $user["user_id"] !== (int) $_SESSION["user_id"]): ?>

                            <button
                                type="button"
                                class="action-btn edit-btn"
                                onclick="editRole(
                                    <?php echo (int) $user["user_id"]; ?>,
                                    '<?php echo e($user["full_name"]); ?>',
                                    '<?php echo e($user["role"]); ?>'
                                )"
                            >
                                Edit Role
                            </button>


                            <form
                                method="POST"
                                onsubmit="return confirm('Delete this account?');"
                            >

                                <input type="hidden" name="action" value="delete_user">

                                <input
                                    type="hidden"
                                    name="user_id"
                                    value="<?php echo (int) $user["user_id"]; ?>"
                                >

                                <button type="submit" class="action-btn delete-btn">
                                    Delete
                                </button>

                            </form>

                        <?php else: ?>

                            <span style="font-size:9px;color:#9a8c94;">
                                Current Account
                            </span>

                        <?php endif; ?>

                    </div>

                </td>

            </tr>

        <?php endforeach;
    }
    ?>


    <!-- TEAM: Admin (pinaka-taas) tapos Staff -->

    <section class="card user-group-card">

        <div class="user-group-header">
            <div>
                <h3>Team</h3>
                <p>Admin &amp; Staff accounts.</p>
            </div>

            <input
                type="text"
                id="teamSearch"
                class="user-search"
                placeholder="Search name, email, role..."
                oninput="figurifyFilterUserGroup('teamTable', this.value)"
            >
        </div>

        <div style="overflow-x:auto;">

        <table class="user-table" id="teamTable">

            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php figurify_render_user_rows($teamUsers, "No admin/staff accounts found."); ?>
            </tbody>

        </table>

        </div>

    </section>


    <!-- CUSTOMERS -->

    <section class="card user-group-card">

        <div class="user-group-header">
            <div>
                <h3>Customers</h3>
                <p>Customer accounts.</p>
            </div>

            <input
                type="text"
                id="customerSearch"
                class="user-search"
                placeholder="Search name, email, role..."
                oninput="figurifyFilterUserGroup('customerTable', this.value)"
            >
        </div>

        <div style="overflow-x:auto;">

        <table class="user-table" id="customerTable">

            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php figurify_render_user_rows($customerUsers, "No customer accounts found."); ?>
            </tbody>

        </table>

        </div>

    </section>

</div>

</main>

</div>


<!-- CREATE USER MODAL -->

<div
    class="user-modal"
    id="createUserModal"
>

    <div class="user-modal-box">

        <div class="user-modal-header">

            <h3>
                Create Account
            </h3>

            <button
                type="button"
                class="close-user-modal"
                id="closeCreateUser"
            >
                ×
            </button>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="create_user"
            >


            <div class="form-group">

                <label>
                    FULL NAME
                </label>

                <input
                    type="text"
                    name="full_name"
                    placeholder="Enter full name"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    EMAIL
                </label>

                <input
                    type="email"
                    name="email"
                    placeholder="Enter email"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    PASSWORD
                </label>

                <input
                    type="password"
                    name="password"
                    placeholder="Minimum 6 characters"
                    minlength="6"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    ACCOUNT ROLE
                </label>

                <select
                    name="role"
                    required
                >

                    <option value="customer">
                        Customer
                    </option>

                    <option value="staff">
                        Staff
                    </option>

                    <option value="admin">
                        Admin / Owner
                    </option>

                </select>

            </div>


            <button
                type="submit"
                class="submit-user"
            >

                Create Account

            </button>

        </form>

    </div>

</div>


<!-- EDIT ROLE MODAL -->

<div
    class="user-modal"
    id="roleModal"
>

    <div class="user-modal-box">

        <div class="user-modal-header">

            <h3>
                Change User Role
            </h3>

            <button
                type="button"
                class="close-user-modal"
                onclick="closeRoleModal()"
            >
                ×
            </button>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="update_role"
            >

            <input
                type="hidden"
                name="user_id"
                id="editUserId"
            >


            <div class="form-group">

                <label>
                    USER
                </label>

                <input
                    type="text"
                    id="editUserName"
                    readonly
                >

            </div>


            <div class="form-group">

                <label>
                    NEW ROLE
                </label>

                <select
                    name="role"
                    id="editUserRole"
                >

                    <option value="customer">
                        Customer
                    </option>

                    <option value="staff">
                        Staff
                    </option>

                    <option value="admin">
                        Admin / Owner
                    </option>

                </select>

            </div>


            <button
                type="submit"
                class="submit-user"
            >

                Save Role

            </button>

        </form>

    </div>

</div>


<script>

/* CREATE MODAL */

const createModal =
    document.getElementById(
        "createUserModal"
    );

const openCreate =
    document.getElementById(
        "openCreateUser"
    );

const closeCreate =
    document.getElementById(
        "closeCreateUser"
    );


openCreate.addEventListener(
    "click",
    function() {

        createModal.classList.add(
            "show"
        );

    }
);


closeCreate.addEventListener(
    "click",
    function() {

        createModal.classList.remove(
            "show"
        );

    }
);


/* EDIT ROLE */

const roleModal =
    document.getElementById(
        "roleModal"
    );


function editRole(
    userId,
    userName,
    role
) {

    document.getElementById(
        "editUserId"
    ).value = userId;


    document.getElementById(
        "editUserName"
    ).value = userName;


    document.getElementById(
        "editUserRole"
    ).value = role;


    roleModal.classList.add(
        "show"
    );
}


function closeRoleModal()
{
    roleModal.classList.remove(
        "show"
    );
}


// Search users — hiwalay na search box ang Team at Customers container, sarili-sariling filter

function figurifyFilterUserGroup(tableId, rawValue) {

    const search = rawValue.toLowerCase().trim();
    const table = document.getElementById(tableId);

    if (!table) {
        return;
    }

    table.querySelectorAll(".user-row").forEach(row => {

        const text = (row.dataset.search || "").toLowerCase();

        row.style.display = text.includes(search) ? "" : "none";

    });

}


/* CLOSE MODAL OUTSIDE */

window.addEventListener(
    "click",
    function(event) {

        if (
            event.target === createModal
        ) {

            createModal.classList.remove(
                "show"
            );

        }


        if (
            event.target === roleModal
        ) {

            roleModal.classList.remove(
                "show"
            );

        }

    }
);


/* ESCAPE */

document.addEventListener(
    "keydown",
    function(event) {

        if (
            event.key === "Escape"
        ) {

            createModal.classList.remove(
                "show"
            );

            roleModal.classList.remove(
                "show"
            );

        }

    }
);

</script>


</body>

</html>