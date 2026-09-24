<?php

/* Staff sidebar, just include this file. Same as owner sidebar
   minus the owner-only items. Icons are inline SVG so walang
   need mag-load ng Font Awesome CDN. Spacing/font sizes need
   !important para match talaga sa owner sidebar. */

$sidebarCurrentPage = basename($_SERVER["PHP_SELF"]);

function sb_active(string $page, string $current): string
{
    return $page === $current ? "active" : "";
}

?>

<style>

/* :root, not .sidebar — modal/toast are siblings of .sidebar so
   they won't inherit the colors otherwise (logout button becomes
   invisible). */
:root {
    --sb-pink: #f78fd4;
    --sb-pink-dark: #b23e82;
    --sb-pink-light: #ffc4e8;
    --sb-text: #403a55;
    --sb-icon: #6e5a72;
}

.sidebar {
    width: 225px !important;
    height: 100vh !important;
    position: fixed !important;
    left: 0 !important;
    top: 0 !important;
    background: linear-gradient(180deg, #ffd6ef 0%, #ffc4e8 100%);
    border-right: 1px solid rgba(131,24,67,.15);
    box-shadow: 6px 0 22px rgba(74,48,76,.06);
    padding: 0 !important;
    display: flex !important;
    flex-direction: column !important;
    z-index: 100 !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
}

/* Force Segoe UI kahit may sariling font ang page CSS
   (my-orders.css, orders.css, etc). */
.sidebar,
.sidebar * {
    font-family: "Segoe UI", Arial, sans-serif !important;
    box-sizing: border-box !important;
}

/* brand — logo sa kaliwa, "Clay and Stuff" na text sa tabi (hindi na
   nakapatong sa ibabaw), gamit ang naka-crop na logo (logoclay-sidebar.png)
   na tinanggalan na ng transparent padding sa paligid ng laman ng image */

.sb-brand {
    flex: 0 0 68px !important;
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 10px !important;
    padding: 10px 16px !important;
    background: transparent;
}

.sb-logo-wrap {
    width: 54px !important;
    height: 44px !important;
    flex-shrink: 0;
}

.sb-logo {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}

.sb-brand-name {
    font-size: 13px !important;
    font-weight: 900 !important;
    color: var(--sb-text) !important;
    line-height: 1.15 !important;
    letter-spacing: .2px;
}

/* nav — nilakihan ang text/icons (mas madaling mabasa/makita) pero
   sinigurado na kasya pa rin lahat ng item nang walang scrollbar,
   pareho sa owner sidebar para consistent ang laki. */

.sb-nav {
    display: flex !important;
    flex-direction: column !important;
    gap: 2px !important;
    flex: 1 !important;
    min-height: 0;
    padding: 7px 8px !important;
    overflow-y: auto;
}

.sb-nav-item {
    width: 100% !important;
    min-height: 33px !important;
    border: none !important;
    background: transparent;
    color: var(--sb-text) !important;
    padding: 0 10px !important;
    border-radius: 8px !important;
    display: flex !important;
    align-items: center !important;
    gap: 9px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    line-height: 1.2 !important;
    transition: .18s ease;
    text-decoration: none !important;
    flex-shrink: 0;
}

/* hover = color/background change lang, hindi na gumagalaw ang item */
.sb-nav-item:hover {
    background: rgba(255,255,255,.7);
}

.sb-nav-item.active {
    color: var(--sb-pink-dark) !important;
    background: rgba(255,255,255,.85);
}

.sb-icon {
    width: 18px !important;
    min-width: 18px !important;
    height: 18px !important;
    display: grid !important;
    place-items: center !important;
    color: var(--sb-icon);
    transition: .18s ease;
    flex-shrink: 0;
}

.sb-icon svg {
    width: 16px !important;
    height: 16px !important;
    stroke: currentColor;
    fill: none;
    stroke-width: 2.2;
    stroke-linecap: round;
    stroke-linejoin: round;
    display: block !important;
}

.sb-nav-item:hover .sb-icon,
.sb-nav-item.active .sb-icon {
    color: var(--sb-pink);
}

/* bottom / logout */

.sb-bottom {
    flex: 0 0 46px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 6px 14px !important;
    background: transparent;
}

.sb-logout-btn {
    width: auto !important;
    max-width: 100% !important;
    height: 30px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    padding: 0 18px !important;
    border: 1px solid rgba(233,95,127,.2) !important;
    border-radius: 10px !important;
    background: rgba(255,255,255,.9);
    color: var(--sb-pink) !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    cursor: pointer;
    box-shadow: 0 3px 8px rgba(70,50,80,.04);
    transition: .2s ease;
    text-decoration: none;
}

.sb-logout-btn:hover {
    background: #ffeef2;
    border-color: var(--sb-pink);
}

/* logout modal */

.sb-modal-overlay {
    position: fixed !important;
    inset: 0 !important;
    background: rgba(61,36,48,.4) !important;
    backdrop-filter: blur(3px);
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    opacity: 0;
    pointer-events: none;
    transition: opacity .2s ease;
    z-index: 999 !important;
}

.sb-modal-overlay.active {
    opacity: 1 !important;
    pointer-events: auto !important;
}

.sb-modal-box {
    background: #fff !important;
    width: min(300px,90%);
    padding: 22px 20px !important;
    border-radius: 16px !important;
    text-align: center !important;
    box-shadow: 0 10px 25px rgba(0,0,0,.15);
    transform: scale(.9);
    transition: transform .2s ease;
    font-family: "Segoe UI", Arial, sans-serif !important;
}

.sb-modal-overlay.active .sb-modal-box {
    transform: scale(1);
}

.sb-modal-icon {
    width: 32px;
    height: 32px;
    margin: 0 auto 10px;
    color: var(--sb-pink);
}

.sb-modal-icon svg {
    width: 32px !important;
    height: 32px !important;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    display: block;
}

.sb-modal-title {
    font-size: 15px !important;
    font-weight: bold !important;
    color: var(--sb-text) !important;
    margin-bottom: 6px !important;
}

.sb-modal-desc {
    font-size: 12px !important;
    color: #777080 !important;
    margin-bottom: 18px !important;
}

.sb-modal-actions {
    display: flex !important;
    gap: 8px !important;
}

.sb-modal-btn {
    flex: 1 !important;
    height: 36px !important;
    border: 0 !important;
    border-radius: 8px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    cursor: pointer;
    transition: .15s ease;
    font-family: "Segoe UI", Arial, sans-serif !important;
}

.sb-modal-btn-cancel {
    background: #f0eaf0 !important;
    color: #514b5d !important;
}

.sb-modal-btn-cancel:hover {
    background: #e4dbe4 !important;
}

.sb-modal-btn-confirm {
    background: var(--sb-pink) !important;
    color: #fff !important;
}

.sb-modal-btn-confirm:hover {
    background: var(--sb-pink-dark) !important;
}

.sb-toast {
    position: fixed !important;
    left: 50% !important;
    bottom: 24px !important;
    transform: translate(-50%,20px);
    background: #3d2430 !important;
    color: #fff !important;
    padding: 11px 19px !important;
    border-radius: 20px !important;
    font-size: 12px !important;
    opacity: 0;
    pointer-events: none;
    transition: .22s ease;
    z-index: 1000 !important;
    font-family: "Segoe UI", Arial, sans-serif !important;
}

.sb-toast.show {
    opacity: 1;
    transform: translate(-50%,0);
}

@media (max-width: 800px) {

    .sidebar {
        width: 70px !important;
    }

    .sb-brand {
        justify-content: center !important;
        padding: 14px 8px !important;
    }

    .sb-brand-name {
        display: none !important;
    }

    .sb-nav-item span:last-child {
        display: none !important;
    }

    .sb-nav-item {
        justify-content: center !important;
    }

    .sb-logout-btn span:last-child {
        display: none !important;
    }

}

</style>


<aside class="sidebar">

    <!-- BRAND -->
    <div class="sb-brand">

        <div class="sb-logo-wrap">
            <img
                src="../Image/logoclay-sidebar.png"
                alt="Clay and Stuff"
                class="sb-logo"
            >
        </div>

        <span class="sb-brand-name">Clay and Stuff</span>

    </div>


    <!-- NAVIGATION -->
    <nav class="sb-nav">

        <a href="staffdashboard.php" class="sb-nav-item <?php echo sb_active('staffdashboard.php', $sidebarCurrentPage); ?>">
            <span class="sb-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg></span>
            <span>Dashboard</span>
        </a>

        <a href="allbookings.php" class="sb-nav-item <?php echo sb_active('allbookings.php', $sidebarCurrentPage); ?>">
            <span class="sb-icon"><svg viewBox="0 0 24 24"><path d="M9 6h11M9 12h11M9 18h11"/><path d="M3.5 5.5l1 1 2-2M3.5 11.5l1 1 2-2M3.5 17.5l1 1 2-2"/></svg></span>
            <span>All Booking</span>
        </a>

        <a href="calendar.php" class="sb-nav-item <?php echo sb_active('calendar.php', $sidebarCurrentPage); ?>">
            <span class="sb-icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4"/></svg></span>
            <span>Calendar</span>
        </a>

        <a href="quotation.php" class="sb-nav-item <?php echo sb_active('quotation.php', $sidebarCurrentPage); ?>">
            <span class="sb-icon"><svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="M9 12h6M9 16h4"/></svg></span>
            <span>Quotation</span>
        </a>

        <a href="verification.php" class="sb-nav-item <?php echo sb_active('verification.php', $sidebarCurrentPage); ?>">
            <span class="sb-icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6"/><path d="M14.5 15l2 2 4-4"/></svg></span>
            <span>Verification</span>
        </a>

        <a href="active-booking.php" class="sb-nav-item <?php echo sb_active('active-booking.php', $sidebarCurrentPage); ?>">
            <span class="sb-icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4"/><path d="M8.5 15l2 2 4-4"/></svg></span>
            <span>Active Booking</span>
        </a>

        <a href="shipments.php" class="sb-nav-item <?php echo sb_active('shipments.php', $sidebarCurrentPage); ?>">
            <span class="sb-icon"><svg viewBox="0 0 24 24"><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/></svg></span>
            <span>Shipments</span>
        </a>

        <a href="completed.php" class="sb-nav-item <?php echo sb_active('completed.php', $sidebarCurrentPage); ?>">
            <span class="sb-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v9a2 2 0 002 2h10a2 2 0 002-2V9"/><path d="M10 13h4"/></svg></span>
            <span>Completed Orders</span>
        </a>

        <a href="cancelled.php" class="sb-nav-item <?php echo sb_active('cancelled.php', $sidebarCurrentPage); ?>">
            <span class="sb-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg></span>
            <span>Cancelled / Declined</span>
        </a>

        <a href="reports.php" class="sb-nav-item <?php echo sb_active('reports.php', $sidebarCurrentPage); ?>">
            <span class="sb-icon"><svg viewBox="0 0 24 24"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/></svg></span>
            <span>Statistics</span>
        </a>

    </nav>


    <!-- BOTTOM -->
    <div class="sb-bottom">
        <button type="button" class="sb-logout-btn" id="sbLogoutBtn">
            <span class="sb-icon"><svg viewBox="0 0 24 24"><path d="M9 4H5a2 2 0 00-2 2v12a2 2 0 002 2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg></span>
            <span>Log Out</span>
        </button>
    </div>

</aside>

<!-- LOGOUT CONFIRMATION MODAL -->
<div class="sb-modal-overlay" id="sbLogoutModal">
    <div class="sb-modal-box">
        <div class="sb-modal-icon"><svg viewBox="0 0 24 24"><path d="M9 4H5a2 2 0 00-2 2v12a2 2 0 002 2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg></div>
        <div class="sb-modal-title">Log Out</div>
        <div class="sb-modal-desc">Are you sure you want to log out?</div>
        <div class="sb-modal-actions">
            <button type="button" class="sb-modal-btn sb-modal-btn-cancel" id="sbLogoutCancel">Cancel</button>
            <button type="button" class="sb-modal-btn sb-modal-btn-confirm" id="sbLogoutConfirm">Yes, Log Out</button>
        </div>
    </div>
</div>

<div class="sb-toast" id="sbToast"></div>

<script>
(function () {

    var logoutHref = "../Login/logout.php?portal=staff";

    function init() {
        var overlay    = document.getElementById("sbLogoutModal");
        var openBtn    = document.getElementById("sbLogoutBtn");
        var cancelBtn  = document.getElementById("sbLogoutCancel");
        var confirmBtn = document.getElementById("sbLogoutConfirm");
        var toast      = document.getElementById("sbToast");
        var toastTimer;

        if (!overlay || !openBtn || !toast) {
            return;
        }

        function showToast(text) {
            toast.textContent = text;
            toast.classList.add("show");
            clearTimeout(toastTimer);
            toastTimer = setTimeout(function () {
                toast.classList.remove("show");
            }, 1200);
        }

        openBtn.addEventListener("click", function () {
            overlay.classList.add("active");
        });

        if (cancelBtn) {
            cancelBtn.addEventListener("click", function () {
                overlay.classList.remove("active");
            });
        }

        overlay.addEventListener("click", function (event) {
            if (event.target === overlay) {
                overlay.classList.remove("active");
            }
        });

        if (confirmBtn) {
            confirmBtn.addEventListener("click", function () {
                overlay.classList.remove("active");
                showToast("Logging out...");
                setTimeout(function () {
                    window.location.href = logoutHref;
                }, 500);
            });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

})();
</script>
