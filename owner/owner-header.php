<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/order_status_helper.php";
require_once __DIR__ . "/../helpers/access_helper.php";
require_once __DIR__ . "/../helpers/dressup_helper.php";


// Access guard + owner name/initial, shared sa access_helper.php para sa Owner at Staff panel
[$ownerName, $ownerInitial] = figurify_require_role_page("admin", "Figurify Owner");

// Status label/class/colors helpers ginagamit sa lahat ng owner pages, galing order_status_helper.php


// Current page

$currentPage =
    basename($_SERVER["PHP_SELF"]);

?>
