<?php

/* Named session cookie per portal (figurify_customer_sess / _staff_sess /
   _owner_sess) instead of PHP's default shared PHPSESSID — para hindi
   nagkaka-conflict ang login kapag Customer at Staff/Owner magkasabay
   naka-open sa magkaibang tabs ng parehong browser.

   Gamitin: figurify_start_session() sa taas ng bawat page (kapalit ng
   session_start()). Auto-detect ang portal base sa folder; explicit
   portal param lang kailangan sa files na shared across portals
   (hal. Login/logout.php). */

if (!function_exists("figurify_session_portal")) {

    function figurify_session_portal(): string
    {
        /* Explicit override via ?portal= (used by logout.php links) */
        if (!empty($_GET["portal"])) {
            $requested = strtolower($_GET["portal"]);

            if (in_array($requested, ["customer", "staff", "owner"], true)) {
                return $requested;
            }
        }

        /* Detect from the current file's folder */
        $path = str_replace(
            "\\",
            "/",
            $_SERVER["SCRIPT_FILENAME"] ?? $_SERVER["SCRIPT_NAME"] ?? ""
        );

        if (strpos($path, "/staff/") !== false) {
            return "staff";
        }

        if (strpos($path, "/owner/") !== false) {
            return "owner";
        }

        /* Fallback for shared pages (like logout.php): check the referer */
        $referer = $_SERVER["HTTP_REFERER"] ?? "";

        if (strpos($referer, "/staff/") !== false) {
            return "staff";
        }

        if (strpos($referer, "/owner/") !== false) {
            return "owner";
        }

        return "customer";
    }
}


if (!function_exists("figurify_start_session")) {

    function figurify_start_session(?string $portal = null): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $portal = $portal ?? figurify_session_portal();

        $sessionNames = [
            "customer" => "figurify_customer_sess",
            "staff"    => "figurify_staff_sess",
            "owner"    => "figurify_owner_sess",
        ];

        session_name($sessionNames[$portal] ?? $sessionNames["customer"]);

        /* Session cookie hardening: httponly (hindi mababasa ng JS),
           samesite=Lax (extra proteksyon laban sa CSRF), at secure
           na naka-auto-detect (naka-on lang kapag totoong HTTPS,
           para gumana pa rin sa local/XAMPP na plain HTTP). */
        $isHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
            || (($_SERVER["SERVER_PORT"] ?? "") == 443)
            || ((strtolower($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "")) === "https");

        session_set_cookie_params([
            "lifetime" => 0,
            "path"     => "/",
            "domain"   => "",
            "secure"   => $isHttps,
            "httponly" => true,
            "samesite" => "Lax",
        ]);

        session_start();
    }
}
