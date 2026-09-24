<?php

require_once "owner-header.php";
require_once __DIR__ . "/../helpers/content_helper.php";

// Content Management — pag-eedit ng owner ng text/image sa Home page (hero, about, featured, footer)

$content = figurify_get_all_content($conn);

$saved = isset($_GET["saved"]);


// Reusable image field — click preview para magbukas ng file dialog, auto-upload (AJAX) papunta /Image.
// Ginagamit ng Hero slides (3) at Featured Products (4).
function figurify_cm_image_field(string $name, string $label, array $content, string $fallback, string $ratioClass = "cm-ratio-square", string $sizeHint = ""): void
{
    $value = figurify_content($content, $name, "");
    $previewSrc = figurify_resolve_image_src($value, $fallback);
    ?>
    <label class="content-field content-field-full">
        <?php echo e($label); ?>
        <div class="content-media-field">
            <div
                class="content-media-preview content-media-upload <?php echo e($ratioClass); ?>"
                onclick="this.querySelector('input[type=file]').click()"
            >
                <img src="<?php echo e($previewSrc); ?>" alt="<?php echo e($label); ?> preview">
                <div class="content-media-upload-overlay">
                    <span>📷 Click to change</span>
                </div>
                <input
                    type="file"
                    accept="image/*"
                    hidden
                    onchange="figurifyUploadMedia(this, 'image')"
                >
            </div>
            <input type="hidden" name="<?php echo e($name); ?>" value="<?php echo e($value); ?>">
            <?php if ($sizeHint !== ""): ?>
                <p class="content-media-hint"><?php echo e($sizeHint); ?></p>
            <?php endif; ?>
            <p class="content-media-status"></p>
        </div>
    </label>
    <?php
}

// Same idea pero para sa VIDEO field (about_video), upload papunta /Image/videos
function figurify_cm_video_field(string $name, string $label, array $content, string $fallback, string $labelClass = "content-field content-field-full"): void
{
    $value = figurify_content($content, $name, "");
    $previewSrc = $value !== "" ? $value : $fallback;
    ?>
    <label class="<?php echo e($labelClass); ?>">
        <?php echo e($label); ?>
        <div class="content-media-field">
            <div
                class="content-media-preview content-media-upload cm-ratio-video"
                onclick="this.querySelector('input[type=file]').click()"
            >
                <video src="<?php echo e($previewSrc); ?>" muted loop autoplay playsinline></video>
                <div class="content-media-upload-overlay">
                    <span>🎬 Click to change</span>
                </div>
                <input
                    type="file"
                    accept="video/*"
                    hidden
                    onchange="figurifyUploadMedia(this, 'video')"
                >
            </div>
            <input type="hidden" name="<?php echo e($name); ?>" value="<?php echo e($value); ?>">
            <p class="content-media-status"></p>
        </div>
    </label>
    <?php
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

<title>Figurify — Content Management</title>

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


/* CONTENT MANAGEMENT — form styling */

.content-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.content-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.content-subgroup {
    border: 1px dashed #e6dbe0;
    border-radius: 12px;

    padding: 12px;

    display: flex;
    flex-direction: column;
    gap: 10px;
}

.content-subgroup-title {
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
    color: #a4476d;
}

.content-field {
    display: flex;
    flex-direction: column;
    gap: 6px;

    font-size: 12px;
    font-weight: 700;
    color: #3c3035;
}

.content-field input,
.content-field select,
.content-field textarea {
    font-family: inherit;
    font-size: 13px;
    font-weight: 500;

    padding: 10px 12px;

    border: 1px solid #e6dbe0;
    border-radius: 10px;

    background: #fffbfc;
    color: #29252a;

    resize: vertical;
}

.content-field input:focus,
.content-field select:focus,
.content-field textarea:focus {
    outline: none;
    border-color: #ff7aa2;
}

.content-form-actions {
    display: flex;
    justify-content: flex-end;
}

.content-save-btn {
    border: none;
    border-radius: 12px;

    background: #ff7aa2;
    color: white;

    font-size: 13px;
    font-weight: 800;

    padding: 12px 24px;

    box-shadow: 3px 3px 0 rgba(41,37,42,.12);
}

.content-save-btn:hover {
    background: #a4476d;
}


/* Content Management redesign — single-column form, hiwalay-hiwalay na .card bawat section */


/* Section header: icon + title + short description */

.content-section-header {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 4px;
}

.content-section-icon {
    font-size: 20px;
    line-height: 1;
}

.content-section-header p {
    color: #9a8c94;
    font-size: 11px;
    margin-top: 3px;
}


/* 2-column grid for short fields (eyebrow, button text, etc) */

.content-field-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

@media (max-width: 700px) {
    .content-field-grid {
        grid-template-columns: 1fr;
    }
}

.content-field-full {
    grid-column: 1 / -1;
}


/* Media field (image/video) — malaking preview (same aspect-ratio ng Home page) + input sa ibaba */

.content-media-field {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.content-media-preview {
    width: 100%;
    max-width: 220px;

    border-radius: 14px;
    overflow: hidden;

    border: 1px solid var(--gray-light);
    background: var(--background);

    box-shadow: 0 4px 12px rgba(71,45,55,.06);
}

.content-media-preview img,
.content-media-preview video {
    width: 100%;
    height: 100%;

    object-fit: cover;
    display: block;
}

/* Aspect ratios — kopya mula sa Home/Home.css para eksaktong tugma ang proporsyon */

/* Wide/banner ratio para sa Hero Carousel, larawan ang buong background ng slide */
.cm-ratio-hero {
    aspect-ratio: 8 / 3;
}

.cm-ratio-square {
    aspect-ratio: 1 / 1;
}

.cm-ratio-video {
    aspect-ratio: 1 / 1.02;
}


/* Click-to-upload — buong preview box ang button, i-click para magbukas ng file dialog */

.content-media-upload {
    position: relative;
    cursor: pointer;

    transition: transform .15s ease, box-shadow .15s ease;
}

.content-media-upload:hover {
    box-shadow: 0 6px 18px rgba(71,45,55,.14);
    transform: translateY(-2px);
}

.content-media-upload-overlay {
    position: absolute;
    inset: 0;

    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;

    padding: 10px;

    background: rgba(41,37,42,.55);
    color: white;

    font-size: 12px;
    font-weight: 800;

    opacity: 0;
    transition: opacity .15s ease;
}

.content-media-upload:hover .content-media-upload-overlay {
    opacity: 1;
}

.content-media-hint {
    font-size: 10.5px;
    font-weight: 600;
    color: #9a8c94;
}

.content-media-status {
    font-size: 11px;
    font-weight: 700;
    color: var(--green, #65a77e);
    min-height: 14px;
}

.content-media-status.error {
    color: var(--red, #d96b78);
}


/* Side-by-side groups (Hero slides, Products, Features) so they don't stack too tall */

.content-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    align-items: start;
}

.content-grid-4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    align-items: start;
}

@media (max-width: 1000px) {
    .content-grid-3,
    .content-grid-4 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .content-grid-3,
    .content-grid-4 {
        grid-template-columns: 1fr;
    }
}

/* About Us: video on the left, fields on the right */

.content-about-layout {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 20px;
    align-items: start;
}

/* bigger preview here than the default, fills the 380px column */
.content-about-layout .content-media-preview {
    max-width: 100%;
}

.content-about-fields {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

@media (max-width: 700px) {
    .content-about-layout {
        grid-template-columns: 1fr;
    }
}


/* CONTENT MANAGEMENT — TAB SWITCHER (Home / Collection) */

.cm-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}

.cm-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;

    padding: 10px 18px;

    background: white;
    border: 1px solid var(--gray-light);
    border-radius: 12px;

    font-size: 12px;
    font-weight: 800;
    color: var(--gray);

    transition: .15s;
}

.cm-tab:hover {
    background: var(--pink-soft);
}

.cm-tab.active {
    background: var(--pink);
    color: var(--black);
    border-color: var(--pink);
}


</style>

</head>


<body>

<div class="app">

<?php require_once __DIR__ . "/owner-sidebar.php"; ?>


<main class="main">


<header class="topbar">

    <div class="welcome">

        <h2>
            Content Management
        </h2>

        <p>
            Edit the text and images that appear on the
            customer's Home page — hero, about, featured
            products, commission section, and footer/contact.
        </p>

    </div>



        <div class="top-actions">

            <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>

        </div>

    </header>


    <div class="cm-tabs">
        <a href="content-management.php" class="cm-tab active">🏠 Home Page</a>
        <a href="collection-management.php" class="cm-tab">🎨 Collection</a>
    </div>


<?php if ($saved): ?>

    <?php
        $savedToastMessage = "Changes saved. You will see this on the Home page now.";
        include __DIR__ . "/../Shared/saved-toast.php";
    ?>

<?php endif; ?>


<form
    method="POST"
    action="save_content.php"
    class="content-form"
>

    <!-- HERO -->

    <section class="card content-section" id="section-hero">

        <div class="content-section-header">
            <div class="content-section-icon">🖼️</div>
            <div>
                <h3>Hero Carousel</h3>
                <p>3 slides that rotate at the top of the Home page.</p>
            </div>
        </div>

        <div class="content-grid-3">

            <div class="content-subgroup">
                <div class="content-subgroup-title">Slide 1</div>

                <?php figurify_cm_image_field("hero_image", "Image (full background of the slide)", $content, "https://images.unsplash.com/photo-1534447677768-be436bb09401?auto=format&fit=crop&w=800&q=80", "cm-ratio-hero", "Recommended size: 1600×600px or larger, wide/landscape image."); ?>

                <label class="content-field">
                    Eyebrow text
                    <input type="text" name="hero_eyebrow" value="<?php echo e(figurify_content($content, "hero_eyebrow", "Tiny keepsakes for big feelings")); ?>">
                </label>

                <label class="content-field">
                    Button text
                    <input type="text" name="hero_link_text" value="<?php echo e(figurify_content($content, "hero_link_text", "Shop the collection")); ?>">
                </label>

                <label class="content-field">
                    Main heading (| = new line)
                    <textarea name="hero_heading" rows="2"><?php echo e(figurify_content($content, "hero_heading", "Turning memories|into mini masterpieces")); ?></textarea>
                </label>

                <label class="content-field">
                    Tagline
                    <textarea name="hero_tagline" rows="2"><?php echo e(figurify_content($content, "hero_tagline", "Hand-sculpted polymer clay pieces made from your favorite moments — no two are ever exactly alike.")); ?></textarea>
                </label>

                <label class="content-field">
                    Stamp badge (| = new line)
                    <input type="text" name="hero_stamp" value="<?php echo e(figurify_content($content, "hero_stamp", "100%|handmade")); ?>">
                </label>
            </div>

            <div class="content-subgroup">
                <div class="content-subgroup-title">Slide 2</div>

                <?php figurify_cm_image_field("slide2_image", "Image (full background of the slide)", $content, "https://images.unsplash.com/photo-1513519245088-0e12902e5a38?auto=format&fit=crop&w=800&q=80", "cm-ratio-hero", "Recommended size: 1600×600px or larger, wide/landscape image."); ?>

                <label class="content-field">
                    Eyebrow text
                    <input type="text" name="slide2_eyebrow" value="<?php echo e(figurify_content($content, "slide2_eyebrow", "Customized just for you")); ?>">
                </label>

                <label class="content-field">
                    Button text
                    <input type="text" name="slide2_link_text" value="<?php echo e(figurify_content($content, "slide2_link_text", "Commission a piece")); ?>">
                </label>

                <label class="content-field">
                    Heading
                    <input type="text" name="slide2_heading" value="<?php echo e(figurify_content($content, "slide2_heading", "Handcrafted couple figures made with love")); ?>">
                </label>

                <label class="content-field">
                    Tagline
                    <textarea name="slide2_tagline" rows="2"><?php echo e(figurify_content($content, "slide2_tagline", "Send a photo, tell us your story, and we'll shape it into a little keepsake you can hold onto forever.")); ?></textarea>
                </label>

                <label class="content-field">
                    Stamp badge
                    <input type="text" name="slide2_stamp" value="<?php echo e(figurify_content($content, "slide2_stamp", "Made|for two")); ?>">
                </label>
            </div>

            <div class="content-subgroup">
                <div class="content-subgroup-title">Slide 3</div>

                <?php figurify_cm_image_field("slide3_image", "Image (full background of the slide)", $content, "https://images.unsplash.com/photo-1579783902614-a3fb3927b675?auto=format&fit=crop&w=800&q=80", "cm-ratio-hero", "Recommended size: 1600×600px or larger, wide/landscape image."); ?>

                <label class="content-field">
                    Eyebrow text
                    <input type="text" name="slide3_eyebrow" value="<?php echo e(figurify_content($content, "slide3_eyebrow", "Special events & weddings")); ?>">
                </label>

                <label class="content-field">
                    Button text
                    <input type="text" name="slide3_link_text" value="<?php echo e(figurify_content($content, "slide3_link_text", "Explore wedding sets")); ?>">
                </label>

                <label class="content-field">
                    Heading
                    <input type="text" name="slide3_heading" value="<?php echo e(figurify_content($content, "slide3_heading", "Capture your special day in cute clay style")); ?>">
                </label>

                <label class="content-field">
                    Tagline
                    <textarea name="slide3_tagline" rows="2"><?php echo e(figurify_content($content, "slide3_tagline", "From bridal parties to first dances, we sculpt the day you'll want to keep on your shelf.")); ?></textarea>
                </label>

                <label class="content-field">
                    Stamp badge
                    <input type="text" name="slide3_stamp" value="<?php echo e(figurify_content($content, "slide3_stamp", "Keep the|day")); ?>">
                </label>
            </div>

        </div>

    </section>


    <!-- ABOUT -->

    <section class="card content-section" id="section-about">

        <div class="content-section-header">
            <div class="content-section-icon">📖</div>
            <div>
                <h3>About Us</h3>
                <p>Video, text, and tags for the "About" section.</p>
            </div>
        </div>

        <div class="content-about-layout">

            <?php figurify_cm_video_field("about_video", "Video", $content, "https://assets.mixkit.co/videos/preview/mixkit-hands-working-on-pottery-42995-large.mp4", "content-field"); ?>

            <div class="content-about-fields">

                <div class="content-field-grid">
                    <label class="content-field">
                        Badge text (sa ibabaw ng video)
                        <input type="text" name="about_badge" value="<?php echo e(figurify_content($content, "about_badge", "Clay & Stuff")); ?>">
                    </label>

                    <label class="content-field">
                        Eyebrow (small text on top)
                        <input type="text" name="about_heading" value="<?php echo e(figurify_content($content, "about_heading", "About us")); ?>">
                    </label>
                </div>

                <label class="content-field">
                    Heading
                    <input type="text" name="about_script" value="<?php echo e(figurify_content($content, "about_script", "Crafting smiles out of tiny pieces of clay")); ?>">
                </label>

                <label class="content-field">
                    Main paragraph
                    <textarea name="about_text" rows="3"><?php echo e(figurify_content($content, "about_text", "Naniniwala kami na ang mga pinakamagandang alaala ay nabubuhay sa maliliit na detalye. Ang bawat obra ay buong-pusong hinuhubog para maging natatanging regalo para sa iyo at sa iyong mga mahal sa buhay.")); ?></textarea>
                </label>

                <label class="content-field">
                    Sign-off line
                    <textarea name="about_sign" rows="2"><?php echo e(figurify_content($content, "about_sign", "Mula sa mga custom couple figures hanggang sa mga espesyal na tagpo sa buhay, ginagawa naming sining ang iyong mga kwento.")); ?></textarea>
                </label>

            </div>

        </div>

    </section>


    <!-- FEATURED PRODUCTS -->

    <section class="card content-section" id="section-featured">

        <div class="content-section-header">
            <div class="content-section-icon">🛍️</div>
            <div>
                <h3>Featured Orders</h3>
                <p>Heading, and the 4 product cards on the Home page.</p>
            </div>
        </div>

        <div class="content-field-grid">
            <label class="content-field">
                Section heading
                <input type="text" name="featured_heading" value="<?php echo e(figurify_content($content, "featured_heading", "Featured orders")); ?>">
            </label>

            <label class="content-field">
                Section subtitle
                <input type="text" name="featured_subtitle" value="<?php echo e(figurify_content($content, "featured_subtitle", "Our most-loved pieces, shaped one at a time")); ?>">
            </label>
        </div>

        <?php
        $defaultProducts = [
            1 => ["image" => "Hirono-Couple.jpg", "title" => "Custom Couple Figurine", "price" => "₱850"],
            2 => ["image" => "Hirono.jpg", "title" => "Hirono Inspired", "price" => "₱650"],
            3 => ["image" => "ch-fbs.jpg", "title" => "Wedding", "price" => "₱1,200"],
            4 => ["image" => "ch-ho.jpg", "title" => "Custom Keychains", "price" => "₱150 each"],
        ];
        ?>

        <div class="content-grid-4">

        <?php foreach ($defaultProducts as $i => $default): ?>

            <div class="content-subgroup">

                <div class="content-subgroup-title">Product <?php echo $i; ?></div>

                <?php figurify_cm_image_field("product{$i}_image", "Photo", $content, "../Image/" . $default["image"], "cm-ratio-square"); ?>

                <label class="content-field">
                    Title
                    <input type="text" name="product<?php echo $i; ?>_title" value="<?php echo e(figurify_content($content, "product{$i}_title", $default["title"])); ?>">
                </label>

                <label class="content-field">
                    Price
                    <input type="text" name="product<?php echo $i; ?>_price" value="<?php echo e(figurify_content($content, "product{$i}_price", $default["price"])); ?>">
                </label>

                <label class="content-field">
                    Caption (optional)
                    <input type="text" name="product<?php echo $i; ?>_caption" value="<?php echo e(figurify_content($content, "product{$i}_caption", "")); ?>">
                </label>

            </div>

        <?php endforeach; ?>

        </div>

        <label class="content-field content-field-full">
            Note below Featured Orders
            <input type="text" name="featured_note" value="<?php echo e(figurify_content($content, "featured_note", "We can make it exactly how you imagine it — from the pose to the little details.")); ?>">
        </label>

    </section>


    <!-- FEATURES BANNER -->

    <section class="card content-section" id="section-features">

        <div class="content-section-header">
            <div class="content-section-icon">✨</div>
            <div>
                <h3>Features Banner</h3>
                <p>The 4 small "Handmade / Customize / Secured / Friendly" banners.</p>
            </div>
        </div>

        <div class="content-grid-4">

            <?php
            $defaultFeatures = [
                1 => ["title" => "Handmade", "subtitle" => "with love"],
                2 => ["title" => "Customize", "subtitle" => "your order"],
                3 => ["title" => "Secured", "subtitle" => "packaging"],
                4 => ["title" => "Friendly", "subtitle" => "service"],
            ];
            ?>

            <?php foreach ($defaultFeatures as $i => $default): ?>
                <div class="content-subgroup">
                    <div class="content-subgroup-title">Item <?php echo $i; ?></div>
                    <label class="content-field">
                        Title
                        <input type="text" name="feature<?php echo $i; ?>_title" value="<?php echo e(figurify_content($content, "feature{$i}_title", $default["title"])); ?>">
                    </label>
                    <label class="content-field">
                        Subtitle
                        <input type="text" name="feature<?php echo $i; ?>_subtitle" value="<?php echo e(figurify_content($content, "feature{$i}_subtitle", $default["subtitle"])); ?>">
                    </label>
                </div>
            <?php endforeach; ?>

        </div>

    </section>


    <!-- REVIEWS -->

    <section class="card content-section" id="section-reviews">

        <div class="content-section-header">
            <div class="content-section-icon">💬</div>
            <div>
                <h3>Reviews</h3>
                <p>Only the heading can be edited here — the reviews themselves come from real customer feedback.</p>
            </div>
        </div>

        <div class="content-field-grid">
            <label class="content-field">
                Section heading
                <input type="text" name="reviews_heading" value="<?php echo e(figurify_content($content, "reviews_heading", "What our customers say")); ?>">
            </label>

            <label class="content-field">
                Section subtitle
                <input type="text" name="reviews_subtitle" value="<?php echo e(figurify_content($content, "reviews_subtitle", "Loved by our amazing collectors & gift-givers")); ?>">
            </label>
        </div>

    </section>


    <!-- COMMISSION CTA -->

    <section class="card content-section" id="section-commission">

        <div class="content-section-header">
            <div class="content-section-icon">🎨</div>
            <div>
                <h3>Commission CTA</h3>
                <p>The final "call to action" section before the footer.</p>
            </div>
        </div>

        <div class="content-field-grid">
            <label class="content-field">
                Eyebrow text
                <input type="text" name="commission_eyebrow" value="<?php echo e(figurify_content($content, "commission_eyebrow", "Let's make something personal")); ?>">
            </label>

            <label class="content-field">
                Heading
                <input type="text" name="commission_heading" value="<?php echo e(figurify_content($content, "commission_heading", "Commission a figure")); ?>">
            </label>
        </div>

        <label class="content-field content-field-full">
            Description
            <textarea name="commission_text" rows="2"><?php echo e(figurify_content($content, "commission_text", "Tell us your idea and we'll bring it to life in clay.")); ?></textarea>
        </label>

    </section>


    <!-- FOOTER / CONTACT -->

    <section class="card content-section" id="section-footer">

        <div class="content-section-header">
            <div class="content-section-icon">📍</div>
            <div>
                <h3>Footer &amp; Contact</h3>
                <p>Information shown in the footer of every page.</p>
            </div>
        </div>

        <label class="content-field content-field-full">
            Tagline below the "Clay and Stuff" logo
            <input type="text" name="footer_tagline" value="<?php echo e(figurify_content($content, "footer_tagline", "Made with little bits of joy.")); ?>">
        </label>

        <div class="content-field-grid">
            <label class="content-field">
                Address
                <input type="text" name="contact_address" value="<?php echo e(figurify_content($content, "contact_address", "Brgy. Palingon, Calamba City, Laguna")); ?>">
            </label>

            <label class="content-field">
                Phone number
                <input type="text" name="contact_phone" value="<?php echo e(figurify_content($content, "contact_phone", "+63 910 281 4331")); ?>">
            </label>

            <label class="content-field">
                Email
                <input type="text" name="contact_email" value="<?php echo e(figurify_content($content, "contact_email", "clayandstuff@gmail.com")); ?>">
            </label>
        </div>

    </section>


    <div class="content-form-actions">
        <button
            type="submit"
            class="content-save-btn"
        >
            Save Changes
        </button>
    </div>

</form>


<script>
// Media upload (larawan/video) — auto-upload (AJAX) papunta upload_content_media.php, then set hidden
// input value (isu-submit kasama ng form) AT update live preview.
function figurifyUploadMedia(fileInput, kind) {

    const file = fileInput.files && fileInput.files[0];

    if (!file) {
        return;
    }

    const field = fileInput.closest(".content-media-field");
    const hiddenInput = field.querySelector('input[type="hidden"]');
    const status = field.querySelector(".content-media-status");
    const previewImg = field.querySelector("img");
    const previewVideo = field.querySelector("video");

    if (status) {
        status.textContent = "Uploading...";
        status.classList.remove("error");
    }

    const formData = new FormData();
    formData.append("file", file);
    formData.append("kind", kind);
    formData.append("category", "carousel");

    // Ipadala ang dati/kasalukuyang value para matanggal ng server yung
    // lumang file — kung hindi, naiiwan ito sa /Image (parang "duplicate").
    if (hiddenInput && hiddenInput.value) {
        formData.append("old_value", hiddenInput.value);
    }

    fetch("upload_content_media.php", {
        method: "POST",
        body: formData
    })
        .then(response => response.json())
        .then(data => {

            if (!data.ok) {
                if (status) {
                    status.textContent = data.error || "Failed to upload file.";
                    status.classList.add("error");
                }
                return;
            }

            if (hiddenInput) {
                hiddenInput.value = data.value;
            }

            if (previewImg) {
                previewImg.src = data.src;
            }

            if (previewVideo) {
                previewVideo.src = data.src;
                previewVideo.load();
            }

            if (status) {
                status.textContent = "✔ Uploaded. Click \"Save Changes\" to apply.";
            }

        })
        .catch(() => {
            if (status) {
                status.textContent = "Upload error. Please try again.";
                status.classList.add("error");
            }
        });

    // I-reset para pwede ulit pumili ng parehong file sa susunod.
    fileInput.value = "";
}
</script>


</main>

</div>

<script src="../Shared/dashboard.js"></script>
<script>
/* FIGURIFY — OWNER DASHBOARD JS */


// DATE & TIME — nasa Shared/dashboard.js na ang updateDateTime(), shared sa Owner AT Staff


/* SEARCH ORDERS */

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


/* DASHBOARD BUTTONS */

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


/* QUICK ACTIONS */

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


/* DASHBOARD CALENDAR */

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


/* formatDate() is in Shared/dashboard.js, shared by Owner and Staff */


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

<?php include __DIR__ . "/../Shared/section-scroll.php"; ?>

</body>

</html>
