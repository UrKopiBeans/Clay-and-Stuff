<?php

/* Cron script: bumubuo ng .sql backup (structure + data) sa
   Admin/backups/ (protektado ng .htaccess). Pure PHP/mysqli, walang
   shell_exec/mysqldump, gumagana kahit saan.

   Schedule via cron: 0 3 * * * /usr/bin/php /path/Admin/backup_database.php
   (Windows/XAMPP: Task Scheduler.) I-backup din paminsan-minsan ang
   laman ng Admin/backups/ sa ibang lugar. */

// CLI/cron lang, hindi puwede sa browser
if (php_sapi_name() !== "cli") {
    http_response_code(403);
    die("This script is meant to run via cron / Task Scheduler only, not from a browser.");
}

require_once __DIR__ . "/database.php";

$backupDir = __DIR__ . "/backups";

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// .htaccess guard, para hindi ma-access ang mga backup file kahit
// direktang ma-guess ang filename (may customer emails/passwords ito)
$htaccessPath = $backupDir . "/.htaccess";

if (!file_exists($htaccessPath)) {
    file_put_contents($htaccessPath, "Deny from all\n");
}

$timestamp   = date("Y-m-d_His");
$backupFile  = $backupDir . "/figurify_backup_" . $timestamp . ".sql";
$handle      = fopen($backupFile, "w");

if ($handle === false) {
    die("Unable to create backup file at " . $backupFile . PHP_EOL);
}

fwrite($handle, "-- Figurify database backup — " . date("Y-m-d H:i:s") . "\n");
fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

$tablesResult = $conn->query("SHOW TABLES");
$tables       = [];

while ($row = $tablesResult->fetch_row()) {
    $tables[] = $row[0];
}

foreach ($tables as $table) {

    // structure
    $createResult = $conn->query("SHOW CREATE TABLE `" . $table . "`");
    $createRow    = $createResult->fetch_row();

    fwrite($handle, "DROP TABLE IF EXISTS `" . $table . "`;\n");
    fwrite($handle, $createRow[1] . ";\n\n");

    // data, streamed row-by-row para hindi masira ang memory sa
    // malalaking tables
    $dataResult = $conn->query("SELECT * FROM `" . $table . "`");

    if ($dataResult->num_rows > 0) {

        $columnCount = $dataResult->field_count;

        while ($row = $dataResult->fetch_row()) {

            $values = [];

            foreach ($row as $value) {

                if ($value === null) {
                    $values[] = "NULL";
                } else {
                    $values[] = "'" . $conn->real_escape_string($value) . "'";
                }

            }

            fwrite(
                $handle,
                "INSERT INTO `" . $table . "` VALUES (" . implode(", ", $values) . ");\n"
            );

        }

        fwrite($handle, "\n");

    }

}

fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($handle);

$conn->close();

// linisin ang mga backup na mahigit 30 araw na para hindi mapuno
// ang disk ng server sa paglipas ng panahon
$maxAgeSeconds = 30 * 24 * 60 * 60;
$now           = time();

foreach (glob($backupDir . "/figurify_backup_*.sql") as $oldFile) {

    if (($now - filemtime($oldFile)) > $maxAgeSeconds) {
        unlink($oldFile);
    }

}

echo "Backup saved: " . $backupFile . PHP_EOL;
