<?php

require_once __DIR__ . "/../helpers/session_helper.php";

/* Start whichever portal's session this logout link belongs to
   (?portal=customer|staff|owner, passed by the logout link) so we
   clear the correct cookie instead of always the customer one. */
figurify_start_session();

/* Clear all session variables */
$_SESSION = array();

/* Delete session cookie */
if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/* Destroy session */
session_destroy();

/* Staff/Owner go back to the login page; Customer goes to the
   public Home page */
$portal = $_GET["portal"] ?? "customer";

if ($portal === "staff" || $portal === "owner") {
    header("Location: ../Login/Login.php");
} else {
    header("Location: ../Home/Home.php");
}

exit();

?>