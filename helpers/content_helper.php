<?php

/* Site content helper — ginagamit ng owner/content-management.php
   (save edits) at Home/Home.php (display). Simpleng key -> value
   table (site_content). May safety net: gagawa ng table kung wala
   pa, kung sakaling di pa na-run ang updated na figurify_db.sql. */

function figurify_ensure_content_table(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS site_content (
            content_key    VARCHAR(60) NOT NULL PRIMARY KEY,
            content_value  TEXT NOT NULL,
            updated_by     INT NULL,
            updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                           ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    $ensured = true;
}


// kunin lahat ng content bilang array (content_key => content_value)
function figurify_get_all_content(mysqli $conn): array
{
    figurify_ensure_content_table($conn);

    $content = [];

    $result = $conn->query("SELECT content_key, content_value FROM site_content");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $content[$row["content_key"]] = $row["content_value"];
        }
    }

    return $content;
}


// value ng isang key, o $default kung wala pang na-save
function figurify_content(array $content, string $key, string $default): string
{
    $value = $content[$key] ?? "";

    return $value !== "" ? $value : $default;
}


// insert o update ng isang content key
function figurify_save_content(mysqli $conn, string $key, string $value, ?int $updatedBy = null): bool
{
    figurify_ensure_content_table($conn);

    $stmt = $conn->prepare("
        INSERT INTO site_content (content_key, content_value, updated_by)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            content_value = VALUES(content_value),
            updated_by = VALUES(updated_by)
    ");

    $stmt->bind_param("ssi", $key, $value, $updatedBy);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}


// resolve img src — pwede filename lang from /Image folder o buong
// URL (hal. Unsplash), fallback kung walang laman
function figurify_resolve_image_src(string $value, string $fallback): string
{
    $value = trim($value);

    if ($value === "") {
        return $fallback;
    }

    if (preg_match("#^(https?:)?//#i", $value)) {
        return $value;
    }

    return "../Image/" . $value;
}


// i-clean ang Image/{category}/ folder — tanggalin ang mga file na wala nang gamit sa DB.
// safety net ito, tumatakbo tuwing Save; may grace period para di matanggal ang bagong upload lang.
function figurify_sync_category_images(string $category, array $referencedValues): void
{
    $allowedCategories = ["carousel", "collection"];

    if (!in_array($category, $allowedCategories, true)) {
        return;
    }

    $dir = __DIR__ . "/../Image/" . $category;

    if (!is_dir($dir)) {
        return;
    }

    // listahan ng filenames na kasalukuyang ginagamit (base sa DB) — huwag tanggalin ang mga ito
    $keepBasenames = [];
    $prefix = $category . "/";

    foreach ($referencedValues as $value) {

        $value = trim((string) $value);

        if ($value === "" || preg_match("#^(https?:)?//#i", $value)) {
            continue;
        }

        $value = str_replace("\\", "/", $value);

        if (strpos($value, $prefix) === 0) {
            $keepBasenames[basename($value)] = true;
        }
    }

    $entries = @scandir($dir);

    if ($entries === false) {
        return;
    }

    $gracePeriod = 30; // segundo
    $now = time();

    // dito na tinatanggal — laktawan ang nasa listahan sa itaas at ang bagong-bago pa lang
    foreach ($entries as $entry) {

        if ($entry === "." || $entry === "..") {
            continue;
        }

        $path = $dir . "/" . $entry;

        if (!is_file($path) || isset($keepBasenames[$entry])) {
            continue;
        }

        $mtime = @filemtime($path);

        if ($mtime !== false && ($now - $mtime) < $gracePeriod) {
            continue; // baka kakabago lang i-upload, huwag pang galawin
        }

        @unlink($path);
    }
}


// listahan ng images sa /Image folder para sa picker sa content-management.php
function figurify_list_home_images(): array
{
    $imageDir = __DIR__ . "/../Image";
    $files = [];

    foreach (scandir($imageDir) as $file) {
        if (preg_match("/\.(jpg|jpeg|png|webp)$/i", $file)) {
            $files[] = $file;
        }
    }

    sort($files);

    return $files;
}
