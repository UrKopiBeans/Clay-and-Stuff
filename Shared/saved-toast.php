<?php
/*
 * "Changes saved" popup — kaparehas ng notice-toast sa Commission page
 * (yung lumalabas sa taas kapag kulang ang order).
 *
 * Gamit:
 *   $savedToastMessage = "Changes saved. You will see this on the Home page now.";
 *   include __DIR__ . "/../Shared/saved-toast.php";
 */
$savedToastMessage = $savedToastMessage ?? "Changes saved.";
?>
<style>
.cs-notice-container {
    position: fixed;
    top: 70px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 9999;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    max-width: 400px;
    width: calc(100% - 32px);
    pointer-events: none;
}

.cs-notice-toast {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px 18px;
    background: #fff;
    border-radius: 14px;
    border: 1px solid #efd6e4;
    border-left: 4px solid #7bc9a0;
    box-shadow:
        3px 4px 0 rgba(215,192,221,.35),
        0 6px 18px rgba(150,90,120,.12);
    opacity: 0;
    transform: translateY(-10px) scale(.96);
    transition: opacity .2s ease, transform .2s ease;
    pointer-events: auto;
    cursor: pointer;
}

.cs-notice-toast.cs-notice-show {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.cs-notice-toast.cs-notice-hide {
    opacity: 0;
    transform: translateY(-6px) scale(.97);
}

.cs-notice-icon {
    flex-shrink: 0;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 900;
    color: #fff;
    background: #7bc9a0;
}

.cs-notice-message {
    flex: 1;
    color: #873c63;
    font-family: Georgia, serif;
    font-size: 14px;
    line-height: 1.5;
    padding-top: 2px;
}

@media (max-width: 520px) {
    .cs-notice-container {
        max-width: none;
        width: calc(100% - 24px);
    }
}
</style>

<div class="cs-notice-container">
    <div class="cs-notice-toast" id="savedToast" role="status" aria-live="polite">
        <div class="cs-notice-icon">✓</div>
        <div class="cs-notice-message"><?= htmlspecialchars($savedToastMessage) ?></div>
    </div>
</div>

<script>
(function () {
    const toast = document.getElementById("savedToast");
    if (!toast) return;

    function hideToast() {
        toast.classList.remove("cs-notice-show");
        toast.classList.add("cs-notice-hide");
        setTimeout(function () {
            if (toast.parentNode && toast.parentNode.parentNode) {
                toast.parentNode.parentNode.removeChild(toast.parentNode);
            }
        }, 250);
    }

    requestAnimationFrame(function () {
        toast.classList.add("cs-notice-show");
    });

    // nawawala mag-isa pagkalipas ng 5 seconds, o i-click para isara agad
    setTimeout(hideToast, 5000);
    toast.addEventListener("click", hideToast);

    // tanggalin ang ?saved=1 sa URL para di na lumabas ulit kapag nag-refresh
    if (window.history && window.history.replaceState) {
        const url = new URL(window.location.href);
        url.searchParams.delete("saved");
        window.history.replaceState({}, "", url);
    }
})();
</script>
