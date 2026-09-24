<?php

/* CSRF protection for the login/signup popup forms.
   figurify_csrf_token()  -> gumagawa/kumukuha ng token, isave sa session.
   figurify_verify_csrf() -> tinitingnan kung tugma ang isinumiteng token.
   Kailangan naka-start na ang session bago gamitin ang mga ito. */

if (!function_exists("figurify_csrf_token")) {

    function figurify_csrf_token(): string
    {
        if (empty($_SESSION["csrf_token"])) {
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
        }

        return $_SESSION["csrf_token"];
    }
}

if (!function_exists("figurify_verify_csrf")) {

    function figurify_verify_csrf(?string $submittedToken): bool
    {
        if (empty($_SESSION["csrf_token"]) || empty($submittedToken)) {
            return false;
        }

        return hash_equals($_SESSION["csrf_token"], $submittedToken);
    }
}
