<?php

/* Shared access-guard helper para sa Owner + Staff pages.
   Dati duplicated sa bawat file ang login/role check, ngayon
   iisang lugar na lang ito para madaling i-maintain. */

if (!function_exists("e")) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
    }
}

// for action handlers with no HTML output (accept_revision.php,
// verify_order.php, send_quotation.php). redirects away if not
// logged in or wrong role.
function figurify_require_role(string $requiredRole): void
{
    if (!isset($_SESSION["user_id"])) {
        header("Location: ../Login/Login.php");
        exit();
    }

    if (!isset($_SESSION["role"]) || $_SESSION["role"] !== $requiredRole) {
        header("Location: ../Home/Home.php");
        exit();
    }
}

// for full pages with HTML output (quotation.php, calendar.php,
// active-booking.php). does the same guard, then returns the
// display name + initial for the page header/avatar.
function figurify_require_role_page(string $requiredRole, string $fallbackName): array
{
    figurify_require_role($requiredRole);

    $displayName = $_SESSION["full_name"] ?? $fallbackName;
    $initial = strtoupper(substr(trim($displayName), 0, 1));

    figurify_touch_last_seen();

    return [$displayName, $initial];
}

// updates "last_seen" whenever an owner/staff page loads. wrapped in
// try/catch since the column might not exist yet.
function figurify_touch_last_seen(): void
{
    global $conn;

    if (!isset($conn) || !isset($_SESSION["user_id"])) {
        return;
    }

    try {
        $stmt = $conn->prepare(
            "UPDATE users SET last_seen = NOW() WHERE user_id = ?"
        );

        if ($stmt) {
            $userId = (int) $_SESSION["user_id"];
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
        }
    } catch (\Throwable $e) {
        // ignore lang — baka wala pang "last_seen" column
    }
}

// for the User Management page — online/offline status and how
// long ago (min/hr/day) since last activity
function figurify_presence_status(?string $lastSeen): array
{
    if ($lastSeen === null || $lastSeen === "") {
        return [
            "online" => false,
            "label"  => "Never logged in",
        ];
    }

    $lastSeenTime = strtotime($lastSeen);

    if ($lastSeenTime === false) {
        return [
            "online" => false,
            "label"  => "Never logged in",
        ];
    }

    $secondsAgo = time() - $lastSeenTime;

    // "Online" kung may aktibidad sa loob ng huling 5 minuto.
    if ($secondsAgo <= 300) {
        return [
            "online" => true,
            "label"  => "Online now",
        ];
    }

    if ($secondsAgo < 3600) {
        $minutes = max(1, (int) floor($secondsAgo / 60));
        $label = $minutes . "m ago";
    } elseif ($secondsAgo < 86400) {
        $hours = (int) floor($secondsAgo / 3600);
        $label = $hours . "h ago";
    } else {
        $days = (int) floor($secondsAgo / 86400);
        $label = $days . "d ago";
    }

    return [
        "online" => false,
        "label"  => "Offline · " . $label,
    ];
}
