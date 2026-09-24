<?php

/* Optional cron: pages like my-orders.php/notifications.php already
   call figurify_expire_old_quotes() on every load ("lazy expiration"),
   so this is only needed to expire quotes with no visitor at all.
   Schedule hourly: 0 * * * * /usr/bin/php /path/Admin/check_expired_orders.php */

// CLI/cron lang, hindi puwede sa browser (hindi session-based dahil
// sisirain nito ang cron job na nakadokumento sa itaas).
if (php_sapi_name() !== "cli") {
    http_response_code(403);
    die("This script is meant to run via cron / Task Scheduler only, not from a browser.");
}

require_once __DIR__ . "/database.php";
require_once __DIR__ . "/../helpers/expire_helper.php";

figurify_expire_old_quotes($conn);

$conn->close();

echo "Done checking for expired quotations." . PHP_EOL;
