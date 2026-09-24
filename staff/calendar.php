<?php

require_once __DIR__ . "/staff-header.php";

$figurifyRole    = "staff";
$figurifyPageDir = __DIR__;

// shared with owner/calendar.php, entry file sets $figurifyRole and $figurifyPageDir
require_once __DIR__ . "/../helpers/booking_helper.php";

$bookedDates    = figurify_get_booked_dates($conn);
$bookingsByDate = figurify_get_bookings_by_date($conn);

// same booking-limit logic as the customer-facing commission calendar
// (2/2 rush, 15/15 non-rush), not just plain booked/not-booked
$rushDayCounts     = figurify_get_rush_day_counts($conn);
$nonrushDateCounts = figurify_get_nonrush_date_counts($conn);

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

<title>Figurify — Calendar</title>

<style>
/* shared dashboard styles, owner.css/staff.css just @import this */

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

/* Dashboard layout: left = stats + calendar + recent bookings,
   right = tall shipment card */

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

/* Status colors — pareho sa Dashboard, Calendar, at My Orders,
   iisang source ang mga kulay: Admin/order_status_helper.php */

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

/* Dashboard calendar (#calendarDays) is read-only preview lang —
   pointer-events:none para hindi mukhang clickable */
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
    background: #ffe3f1;
    border: 1px solid #f6c8de;
}

.booked-dot {
    background: #f6dede;

    border: 1px solid #e0a6a6;
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

/* Completed orders table — hiwalay sa .booking-row kasi 6 columns
   ito (may Customer at Courier), 5 lang ang All Bookings */

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
    height: 100%;
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow-y: auto;
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

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;

    cursor: pointer;

    transition: .2s;
}

.calendar-page-controls button:hover {
    background: var(--pink);

    color: white;
}

.calendar-arrow-icon {
    display: block;
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
    width: 100%;
    height: auto;
    aspect-ratio: 1 / 1;

    border: 1px solid var(--gray-light);

    border-radius: 12px;

    font-size: 12px;

    cursor: pointer;

    background: var(--white);

    transition: .15s ease;
}

.large-day:hover {
    background: #ffe2f0;
}

.large-day.available {
    background: #fffbed;
}

.large-day.booked {
    background: #f6dede;

    color: #c98080;

    border-color: #e0a6a6;

    font-weight: 800;

    text-decoration: line-through;
}

/* same logic/colors as the commission calendar, unavailable once full */
.large-day.rush-full,
.large-day.nonrush-full,
.large-day.unavailable {
    background: #f4f0f3;

    color: #c5b9c0;

    font-weight: 700;

    box-shadow: inset 0 0 0 1px #d8cdd4;
}

.large-day.day-selected {
    background: #f4a4ca;

    color: white;

    border-color: #c9689a;

    box-shadow: 0 3px 0 #d986ad;

    font-weight: 800;
}

/* today's fill, needs to stay last so other states don't override it */
.large-day.today {
    background: #ffe3f1;

    color: #b6608f;

    font-weight: 800;
}

/* today + selected at the same time, just keep the selected pink */
.large-day.day-selected.today {
    background: #f4a4ca;

    color: white;

    border-color: #c9689a;

    box-shadow: 0 3px 0 #d986ad;

    font-weight: 800;
}

.large-day.empty {
    cursor: default;
    border-color: transparent;
    background: transparent;
}

.large-day.empty:hover {
    background: transparent;
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

</style>

</head>


<body>

<div class="app">

<?php require_once $figurifyPageDir . "/" . $figurifyRole . "-sidebar.php"; ?>


<main class="main">


<header class="topbar">

    <div class="welcome">

        <h2>
            Calendar
        </h2>

    </div>



        <div class="top-actions">

            <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>

        </div>

    </header>


<div class="calendar-page-layout">


    <!-- left: calendar -->

    <section class="card full-calendar-card">


        <div class="calendar-page-controls">

            <button
                type="button"
                id="fullPreviousMonth"
                class="calendar-arrow-btn"
                aria-label="Previous month"
            >
                <svg class="calendar-arrow-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>


            <h3 id="fullCalendarMonth">
                Loading...
            </h3>


            <button
                type="button"
                id="fullNextMonth"
                class="calendar-arrow-btn"
                aria-label="Next month"
            >
                <svg class="calendar-arrow-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

        </div>


        <div class="calendar-week full-week">

            <div>MON</div>
            <div>TUE</div>
            <div>WED</div>
            <div>THU</div>
            <div>FRI</div>
            <div>SAT</div>
            <div>SUN</div>

        </div>


        <div
            class="calendar-days full-calendar-days"
            id="fullCalendarDays"
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

                <i class="legend-dot selected-dot"></i>

                Selected

            </span>


            <span>

                <i class="legend-dot today-dot"></i>

                Today

            </span>

        </div>


    </section>


    <!-- right: booked orders list -->

    <section class="card" id="dayBookingsCard">

        <div class="card-header">

            <h3 id="dayBookingsTitle">
                Booked Orders
            </h3>

            <a
                href="#"
                id="clearDayFilter"
                class="clear-day-filter"
                style="display:none;"
            >
                Show all ✕
            </a>

        </div>

        <div id="dayBookingsList"></div>

    </section>



</div>


<style>

.calendar-page-layout{
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap:20px;
    align-items:stretch;
}

/* page itself doesn't scroll, fits in 100vh, only #dayBookingsList scrolls */
@media (min-width: 901px){

    html, body{
        height: 100%;
        overflow: hidden;
    }

    .app{
        height: 100vh;
    }

    .main{
        height: 100vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .calendar-page-layout{
        flex: 1 1 auto;
        min-height: 0;
    }

}

@media (max-width: 900px){

    .calendar-page-layout{
        grid-template-columns: 1fr;
    }

}

#dayBookingsCard{
    height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

#dayBookingsCard .card-header{
    align-items:center;
}

/* 3 columns x auto rows — bawat booking may sariling container/card */
#dayBookingsList{
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding-right: 4px;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    grid-auto-rows: min-content;
    align-content: start;
    gap: 12px;
}

#dayBookingsList .empty-state{
    grid-column: 1 / -1;
}

@media (max-width: 1200px){
    #dayBookingsList{ grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 900px){
    #dayBookingsList{ grid-template-columns: 1fr; }
}

.clear-day-filter{
    color:#6B7280;
    font-size:12px;
    font-weight:700;
    text-decoration:none;
    cursor:pointer;
    white-space:nowrap;
}

.clear-day-filter:hover{
    text-decoration:underline;
}

.day-booking-date{
    display:block;
    font-size:11px;
    color:#8A8690;
    font-weight:700;
    margin-top:2px;
}

.day-booking-row{
    display:flex;
    flex-direction:column;
    gap:8px;
    padding:12px;
    border-radius:12px;
    background:#F7F7F9;
    border:1px solid var(--gray-light);
    transition: background .15s ease, border-color .15s ease;
}

.day-booking-row:hover{
    background:#F1F0F4;
    border-color:#D8D4DC;
}

.day-booking-row.highlighted{
    background:#FDE6F1;
    border-color:#E896C4;
}

.day-booking-content{
    min-width:0;
}

.day-booking-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    margin-bottom:8px;
}

.day-booking-order-id{
    color:#A5446D;
    font-weight:900;
    font-size:14px;
}

/* simple mini table under the Order # heading */
.day-booking-table{
    display:flex;
    flex-direction:column;
    gap:6px;
}

.day-booking-table-row{
    display:flex;
    flex-direction:column;
    gap:2px;
    padding:5px 0;
    border-bottom:1px solid #F3E4EC;
}

.day-booking-table-row:last-child{
    border-bottom:none;
}

.day-booking-label{
    font-size:11px;
    font-weight:800;
    color:#B5789D;
    text-transform:uppercase;
    letter-spacing:.02em;
}

.day-booking-value{
    font-size:12.5px;
    font-weight:700;
    color:#5c4654;
    word-break:break-word;
}

.day.booked{
    cursor:pointer;
}

.full-calendar-card .calendar-legend{
    gap:22px;
    margin-top:18px;
    font-size:13px;
    color:#6b5c64;
    justify-content:flex-start;
}

.full-calendar-card .calendar-legend span{
    gap:8px;
}

.full-calendar-card .legend-dot{
    width:13px;
    height:13px;
}

.available-dot{
    background: #fffbed;
    border: 1px solid #e9d9a0;
}

.unavailable-dot{
    background: #f4f0f3;
    border: 1px solid #d8cdd4;
}

.selected-dot{
    background: #f4a4ca;
    border: 1px solid #c9689a;
}

</style>


</main>

</div>


<script src="../Shared/dashboard.js"></script>
<script>
/* FIGURIFY — STAFF DASHBOARD JS */


// updateDateTime() is shared, defined above already


/* NAVIGATION */

const navButtons =
    document.querySelectorAll(
        ".nav-btn[data-section]"
    );

navButtons.forEach(button => {

    button.addEventListener(
        "click",
        function(event) {

            event.preventDefault();

            navButtons.forEach(item => {

                item.classList.remove(
                    "active"
                );

            });

            document
                .querySelector(
                    ".nav-btn[href='staffdashboard.php']"
                )
                ?.classList.remove("active");

            this.classList.add("active");

            openModal(
                this.dataset.section,
                "The " +
                this.dataset.section +
                " module is ready to connect to your PHP page."
            );

        }
    );

});


/* SEARCH */

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

            orders.forEach(order => {

                const text =
                    (
                        order.dataset.search || ""
                    ).toLowerCase();

                if (
                    text.includes(value)
                ) {

                    order.style.display =
                        "grid";

                } else {

                    order.style.display =
                        "none";
                }

            });

        }
    );
}


/* MODAL */

const modalOverlay =
    document.getElementById(
        "modalOverlay"
    );

const modalTitle =
    document.getElementById(
        "modalTitle"
    );

const modalMessage =
    document.getElementById(
        "modalMessage"
    );


function openModal(
    title,
    message
) {

    if (!modalOverlay) {
        return;
    }

    if (modalTitle) {
        modalTitle.textContent =
            title;
    }

    if (modalMessage) {
        modalMessage.textContent =
            message;
    }

    modalOverlay.classList.add(
        "show"
    );
}


function closeModal() {

    if (!modalOverlay) {
        return;
    }

    modalOverlay.classList.remove(
        "show"
    );
}


document
    .getElementById("closeModal")
    ?.addEventListener(
        "click",
        closeModal
    );


document
    .getElementById("modalOkay")
    ?.addEventListener(
        "click",
        closeModal
    );


modalOverlay?.addEventListener(
    "click",
    function(event) {

        if (
            event.target === modalOverlay
        ) {

            closeModal();

        }

    }
);


/* NOTIFICATIONS */

document
    .getElementById("notificationButton")
    ?.addEventListener(
        "click",
        function() {

            openModal(
                "Notifications",
                "You currently have staff actions waiting for review."
            );

        }
    );


/* VIEW BOOKINGS */

document
    .getElementById("viewBookings")
    ?.addEventListener(
        "click",
        function() {

            openModal(
                "All Bookings",
                "This button is ready to connect to your All Bookings PHP module."
            );

        }
    );


/* VIEW SHIPMENTS */

document
    .getElementById("viewShipments")
    ?.addEventListener(
        "click",
        function() {

            window.location.href =
                "allbookings.php";

        }
    );


/* CALENDAR */

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


// real booking dates from DB, same variable name/marker as owner.js

const bookingDates =
    typeof figurifyBookedDates !== "undefined"
    ? figurifyBookedDates
    : [];


// formatDate() also shared, one source for both calendars


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

    // can't go back before the current month
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


    /*
        Monday-first calendar.
    */

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


        if (
            today.getFullYear() === year &&
            today.getMonth() === month &&
            today.getDate() === day
        ) {

            dayElement.classList.add(
                "today"
            );

        }


        if (
            bookingDates.includes(
                dateString
            )
        ) {

            dayElement.classList.add(
                "booked"
            );

        }


        dayElement.addEventListener(
            "click",
            function() {

                document
                    .querySelectorAll(
                        ".calendar-days .day"
                    )
                    .forEach(item => {

                        item.classList.remove(
                            "selected"
                        );

                    });


                this.classList.add(
                    "selected"
                );


                const readableDate =
                    new Date(
                        year,
                        month,
                        day
                    ).toLocaleDateString(
                        "en-US",
                        {
                            month: "long",
                            day: "numeric",
                            year: "numeric"
                        }
                    );


                openModal(
                    "Calendar",
                    "You selected " +
                    readableDate +
                    "."
                );

            }
        );


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


/* QUICK ACTIONS */

document
    .querySelectorAll(
        ".quick-btn"
    )
    .forEach(button => {

        button.addEventListener(
            "click",
            function() {

                if (
                    this.dataset.action ===
                    "new-booking"
                ) {

                    openModal(
                        "New Booking",
                        "This will later connect to your booking creation module."
                    );

                }


                if (
                    this.dataset.action ===
                    "quotation"
                ) {

                    openModal(
                        "Create Quotation",
                        "This will later connect to your quotation module."
                    );

                }

            }
        );

    });


/* ESCAPE KEY */

document.addEventListener(
    "keydown",
    function(event) {

        if (
            event.key === "Escape"
        ) {

            closeModal();

        }

    }
);
</script>

<script>

const fullCalendarDays =
    document.getElementById(
        "fullCalendarDays"
    );

const fullCalendarMonth =
    document.getElementById(
        "fullCalendarMonth"
    );

const fullPreviousMonth =
    document.getElementById(
        "fullPreviousMonth"
    );

const fullNextMonth =
    document.getElementById(
        "fullNextMonth"
    );


let fullCalendarDate =
    new Date();


const demoBookings = <?php echo json_encode($bookedDates); ?>;

const bookingsByDate = <?php echo json_encode($bookingsByDate); ?>;


// order IDs currently counted as "booked" (processing/to_ship/
// shipped/completed), snapshot on page load, used by the polling below
const initialBookedOrderIds = <?php

    $flatBookedOrderIds = [];

    foreach ($bookingsByDate as $dateBookings) {
        foreach ($dateBookings as $booking) {
            $flatBookedOrderIds[] = (string) $booking["order_id"];
        }
    }

    echo json_encode(array_values(array_unique($flatBookedOrderIds)));

?>;

const FIGURIFY_BOOKED_CALENDAR_STATUSES = <?php echo json_encode(FIGURIFY_BOOKED_CALENDAR_STATUSES); ?>;

// same capacity data as the commission calendar, so owner/staff also see
// which days are actually full vs just booked
const figurifyRushDayCounts     = <?php echo json_encode($rushDayCounts); ?>;
const figurifyNonrushDateCounts = <?php echo json_encode($nonrushDateCounts); ?>;

const FIGURIFY_RUSH_DAILY_CAP    = <?php echo (int) FIGURIFY_RUSH_DAILY_CAP; ?>;
const FIGURIFY_NONRUSH_DATE_CAP  = <?php echo (int) FIGURIFY_NONRUSH_DATE_CAP; ?>;

// returns capacity status for a date, same rules as commission.js
// (rush full at 2/2, non-rush full at 15/15 on the last day of month)
function getDayCapacityInfo(dateString) {

    const rushCount = figurifyRushDayCounts[dateString] || 0;

    if (rushCount >= FIGURIFY_RUSH_DAILY_CAP) {

        return {
            type: "rush-full",
            label: "Rush full (" + rushCount + "/" + FIGURIFY_RUSH_DAILY_CAP + ")"
        };

    }

    const dateObj = new Date(dateString + "T00:00:00");

    const lastDayOfMonth =
        new Date(
            dateObj.getFullYear(),
            dateObj.getMonth() + 1,
            0
        ).getDate();

    const isLastDayOfMonth =
        dateObj.getDate() === lastDayOfMonth;

    if (isLastDayOfMonth) {

        const nonrushCount =
            figurifyNonrushDateCounts[dateString] || 0;

        if (nonrushCount >= FIGURIFY_NONRUSH_DATE_CAP) {

            return {
                type: "nonrush-full",
                label: "Non-Rush full (" + nonrushCount + "/" + FIGURIFY_NONRUSH_DATE_CAP + ")"
            };

        }

    }

    return null;

}


const dayBookingsCard =
    document.getElementById(
        "dayBookingsCard"
    );

const dayBookingsTitle =
    document.getElementById(
        "dayBookingsTitle"
    );

const dayBookingsList =
    document.getElementById(
        "dayBookingsList"
    );


const clearDayFilter =
    document.getElementById(
        "clearDayFilter"
    );

let activeDayFilter = null;


function niceDateLabel(dateString) {

    return new Date(dateString + "T00:00:00")
        .toLocaleDateString(
            "en-US",
            {
                month: "long",
                day: "numeric",
                year: "numeric"
            }
        );

}


function orderTypeLabel(orderType) {

    return (orderType === "rush")
        ? "Rush"
        : "Non-Rush";

}


function orderMethodLabel(orderMethod) {

    return (orderMethod === "create_style")
        ? "Dress Up"
        : "Image Submission";

}


// same status label/color source as order_status_helper.php,
// keeps it consistent with the dashboard and My Orders
const FIGURIFY_STATUS_LABELS = <?php echo json_encode(array_map(
    static function ($s) { return $s["label"]; },
    FIGURIFY_ORDER_STATUSES
)); ?>;

function statusSlug(status) {

    return String(status || "pending")
        .toLowerCase()
        .trim()
        .replace(/[\s-]+/g, "_");

}

function statusBadgeLabel(status) {

    const slug = statusSlug(status);

    return FIGURIFY_STATUS_LABELS[slug] || String(status || "")
        .replace(/_/g, " ")
        .replace(/\b\w/g, function(c) {
            return c.toUpperCase();
        });

}


// full list of booked orders on the right, grouped by date. clicking a
// day just scrolls/highlights it instead of hiding the other dates

function renderBookingsList() {

    if (!dayBookingsList) {
        return;
    }

    let dateKeys =
        Object.keys(bookingsByDate)
            .sort();

    // scope list to the selected date only, even if empty, para
    // klaro na "wala" imbes na ipakita yung listahan ng lahat
    if (activeDayFilter) {
        dateKeys = dateKeys.filter(function(dateString) {
            return dateString === activeDayFilter;
        });
    }

    dayBookingsList.innerHTML = "";

    if (dayBookingsTitle) {

        dayBookingsTitle.textContent =
            activeDayFilter
            ? "Bookings — " + niceDateLabel(activeDayFilter)
            : "Booked Orders";

    }

    if (dateKeys.length === 0) {

        dayBookingsList.innerHTML =
            "<div class='empty-state'>" +
            (
                activeDayFilter
                ? "No bookings on this date."
                : "No booked orders yet."
            ) +
            "</div>";

        if (clearDayFilter) {
            clearDayFilter.style.display =
                activeDayFilter ? "inline-block" : "none";
        }

        return;

    }

    dateKeys.forEach(function(dateString) {

        const orders =
            bookingsByDate[dateString] ||
            [];

        orders.forEach(function(order) {

            const row =
                document.createElement("div");

            row.className =
                "day-booking-row" +
                (
                    activeDayFilter === dateString
                    ? " highlighted"
                    : ""
                );

            row.dataset.date = dateString;

            // Order # + status badge sa header, tapos mini table sa ilalim
            row.innerHTML =
                "<div class='day-booking-content'>" +
                "<div class='day-booking-head'>" +
                "<span class='day-booking-order-id'>Order #" +
                order.order_id +
                "</span>" +
                "<span class='status status-" +
                statusSlug(order.status) +
                "'>" +
                statusBadgeLabel(order.status) +
                "</span>" +
                "</div>" +
                "<div class='day-booking-table'>" +
                "<div class='day-booking-table-row'>" +
                "<span class='day-booking-label'>Customer</span>" +
                "<span class='day-booking-value'>" +
                order.full_name +
                "</span>" +
                "</div>" +
                "<div class='day-booking-table-row'>" +
                "<span class='day-booking-label'>Email</span>" +
                "<span class='day-booking-value'>" +
                (order.email || "—") +
                "</span>" +
                "</div>" +
                "<div class='day-booking-table-row'>" +
                "<span class='day-booking-label'>Order Type</span>" +
                "<span class='day-booking-value'>" +
                orderTypeLabel(order.order_type) +
                "</span>" +
                "</div>" +
                "<div class='day-booking-table-row'>" +
                "<span class='day-booking-label'>Order Method</span>" +
                "<span class='day-booking-value'>" +
                orderMethodLabel(order.order_method) +
                "</span>" +
                "</div>" +
                "<div class='day-booking-table-row'>" +
                "<span class='day-booking-label'>Booking Date</span>" +
                "<span class='day-booking-value'>" +
                niceDateLabel(dateString) +
                "</span>" +
                "</div>" +
                "</div>" +
                "</div>";

            dayBookingsList.appendChild(
                row
            );

        });

    });

    if (clearDayFilter) {

        clearDayFilter.style.display =
            activeDayFilter
            ? "inline-block"
            : "none";

    }

}


function showDayBookings(dateString) {

    // clicking the same date again deselects it (back to show-all)
    activeDayFilter =
        (activeDayFilter === dateString)
        ? null
        : dateString;

    // sync the highlight on the calendar days too, without rebuilding everything
    fullCalendarDays
        .querySelectorAll(".large-day")
        .forEach(function(dayEl) {
            dayEl.classList.remove("day-selected");
        });

    if (activeDayFilter) {

        const selectedDayEl =
            fullCalendarDays.querySelector(
                "[data-date='" + activeDayFilter + "']"
            );

        selectedDayEl?.classList.add("day-selected");

    }

    renderBookingsList();

    const firstMatch =
        dayBookingsList.querySelector(
            "[data-date='" + dateString + "']"
        );

    if (firstMatch) {

        firstMatch.scrollIntoView({
            behavior: "smooth",
            block: "nearest"
        });

    }

}


clearDayFilter?.addEventListener(
    "click",
    function(event) {

        event.preventDefault();

        activeDayFilter = null;

        document
            .querySelectorAll("#fullCalendarDays .large-day")
            .forEach(function(dayEl) {
                dayEl.classList.remove("day-selected");
            });

        renderBookingsList();

    }
);


function renderFullCalendar() {

    if (!fullCalendarDays) {
        return;
    }


    const year =
        fullCalendarDate.getFullYear();

    const month =
        fullCalendarDate.getMonth();


    fullCalendarMonth.textContent =
        fullCalendarDate.toLocaleDateString(
            "en-US",
            {
                month: "long",
                year: "numeric"
            }
        );


    // can't go back before the current month
    const now = new Date();

    const isAtCurrentMonth =
        year === now.getFullYear() &&
        month === now.getMonth();

    if (fullPreviousMonth) {

        fullPreviousMonth.disabled = isAtCurrentMonth;

        fullPreviousMonth.style.opacity =
            isAtCurrentMonth ? "0.4" : "1";

        fullPreviousMonth.style.cursor =
            isAtCurrentMonth ? "not-allowed" : "pointer";

    }


    fullCalendarDays.innerHTML = "";

    // clear day filter/highlight when switching calendar months
    activeDayFilter = null;


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
            document.createElement("div");

        empty.className =
            "day empty";

        fullCalendarDays.appendChild(
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

        const element =
            document.createElement("div");


        element.className =
            "day large-day";


        element.textContent =
            day;


        const dateString =
            year +
            "-" +
            String(month + 1)
                .padStart(2, "0") +
            "-" +
            String(day)
                .padStart(2, "0");

        element.dataset.date = dateString;


        if (
            today.getFullYear() === year &&
            today.getMonth() === month &&
            today.getDate() === day
        ) {

            element.classList.add(
                "today"
            );

        }


        const isBooked =
            demoBookings.includes(
                dateString
            );

        if (isBooked) {

            element.classList.add(
                "booked"
            );

        }


        const capacityInfo =
            getDayCapacityInfo(dateString);

        if (capacityInfo) {

            element.classList.add(
                capacityInfo.type
            );

            element.title =
                capacityInfo.label;

        }

        if (!isBooked && !capacityInfo) {

            element.classList.add(
                "available"
            );

        }

        // past dates greyed out as "unavailable" unless still booked
        const cellDate =
            new Date(year, month, day);

        const todayAtMidnight =
            new Date(
                today.getFullYear(),
                today.getMonth(),
                today.getDate()
            );

        const isPast =
            cellDate < todayAtMidnight;

        if (isPast && !isBooked) {

            element.classList.add(
                "unavailable"
            );

        }

        if (
            activeDayFilter === dateString
        ) {

            element.classList.add(
                "day-selected"
            );

        }

        // any day is clickable, past dates are locked unless booked
        const isClickable =
            !isPast || isBooked;

        if (isClickable) {

            element.addEventListener(
                "click",
                function() {

                    showDayBookings(
                        dateString
                    );

                }
            );

        } else {

            element.style.cursor =
                "not-allowed";

        }


        fullCalendarDays.appendChild(
            element
        );

    }

}


fullPreviousMonth?.addEventListener(
    "click",
    function() {

        const now = new Date();

        const isAtCurrentMonth =
            fullCalendarDate.getFullYear() === now.getFullYear() &&
            fullCalendarDate.getMonth() === now.getMonth();

        if (isAtCurrentMonth) {
            return;
        }

        fullCalendarDate.setMonth(
            fullCalendarDate.getMonth() - 1
        );

        renderFullCalendar();

    }
);


fullNextMonth?.addEventListener(
    "click",
    function() {

        fullCalendarDate.setMonth(
            fullCalendarDate.getMonth() + 1
        );

        renderFullCalendar();

    }
);


renderFullCalendar();
renderBookingsList();

</script>

</body>

</html>