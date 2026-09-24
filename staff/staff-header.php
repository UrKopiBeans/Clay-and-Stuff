<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/order_status_helper.php";
require_once __DIR__ . "/../helpers/access_helper.php";
require_once __DIR__ . "/../helpers/dressup_helper.php";


// staff access check + staff info, same pattern as owner-header.php

[$staffName, $staffInitial] = figurify_require_role_page("staff", "Figurify Staff");


// current page (for active nav highlight)
$currentPage =
    basename($_SERVER["PHP_SELF"]);

?>
