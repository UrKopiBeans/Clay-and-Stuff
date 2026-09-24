<?php

// AJAX endpoint ng content-management.php para sa pag-upload ng image/video. Nag-return ng JSON (filename/path)
// para i-set ng JS ang hidden input at live preview nang walang page reload.

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

header("Content-Type: application/json");

function figurify_upload_fail(string $message): void
{
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => $message]);
    exit();
}

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "You are not allowed to upload here."]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    figurify_upload_fail("Invalid request method.");
}

$kind = $_POST["kind"] ?? "image";

if (!in_array($kind, ["image", "video"], true)) {
    figurify_upload_fail("Unknown file type.");
}

// sub-folder ng /Image (carousel o collection), para hiwalay sa system images. Kung wala/hindi kilala, Image/ root pa rin.
$category = $_POST["category"] ?? "";
$allowedCategories = ["carousel", "collection"];

if (!in_array($category, $allowedCategories, true)) {
    $category = "";
}

if (!isset($_FILES["file"]) || $_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
    $uploadErr = $_FILES["file"]["error"] ?? UPLOAD_ERR_NO_FILE;

    $message = ($uploadErr === UPLOAD_ERR_INI_SIZE || $uploadErr === UPLOAD_ERR_FORM_SIZE)
        ? "File is too large."
        : "No file received.";

    figurify_upload_fail($message);
}

$tmpPath = $_FILES["file"]["tmp_name"];
$originalName = $_FILES["file"]["name"];
$size = $_FILES["file"]["size"];

$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if ($kind === "image") {

    $allowedExt = ["jpg", "jpeg", "png", "webp", "gif"];
    $maxSize = 8 * 1024 * 1024; // 8MB
    $targetDir = __DIR__ . "/../Image" . ($category !== "" ? "/" . $category : "");

} else {

    $allowedExt = ["mp4", "webm", "mov"];
    $maxSize = 60 * 1024 * 1024; // 60MB
    $targetDir = __DIR__ . "/../Image/videos";

}

if (!in_array($extension, $allowedExt, true)) {
    figurify_upload_fail("Unsupported file type (" . implode(", ", $allowedExt) . " only).");
}

if ($size > $maxSize) {
    figurify_upload_fail("File is too large (max " . round($maxSize / 1024 / 1024) . "MB).");
}

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0775, true);
}

if (!is_writable($targetDir)) {
    figurify_upload_fail("Could not save the file — no write permission on the server folder.");
}

/* Sanitize + gumawa ng natatanging filename para hindi
   masira ang existing na files kapag pareho ang pangalan. */
$baseName = pathinfo($originalName, PATHINFO_FILENAME);
$safeBase = preg_replace("/[^A-Za-z0-9_\-]+/", "_", $baseName);
$safeBase = trim($safeBase, "_");

if ($safeBase === "") {
    $safeBase = $kind;
}

$uniqueSuffix = date("Ymd_His") . "_" . substr(bin2hex(random_bytes(3)), 0, 6);
$finalName = $safeBase . "_" . $uniqueSuffix . "." . $extension;

$destination = $targetDir . "/" . $finalName;

if (!move_uploaded_file($tmpPath, $destination)) {
    figurify_upload_fail("Failed to save the file on the server.");
}

// tanggalin ang dating file kapag REPLACE (may old_value), para di mag-ipon ng duplicate sa /Image
$imageRootDir = __DIR__ . "/../Image";
$oldValue = trim((string) ($_POST["old_value"] ?? ""));

if (
    $oldValue !== "" &&
    !preg_match("#^(https?:)?//#i", $oldValue) &&
    strpos($oldValue, "..") === false
) {
    $oldSegments = array_values(array_filter(
        explode("/", str_replace("\\", "/", $oldValue)),
        fn($seg) => $seg !== ""
    ));

    // Sanitize: max 2 levels lang (category/filename o filename lang), basename bawat piraso.
    $oldSegments = array_map("basename", array_slice($oldSegments, -2));
    $oldRelative = implode("/", $oldSegments);
    $oldPath = $imageRootDir . "/" . $oldRelative;

    $newRelative = ($category !== "" ? $category . "/" : "") . $finalName;

    if ($oldRelative !== "" && $oldRelative !== $newRelative && is_file($oldPath)) {
        @unlink($oldPath);
    }
}

if ($kind === "image") {

    $value = ($category !== "" ? $category . "/" : "") . $finalName;

    echo json_encode([
        "ok" => true,
        "value" => $value,
        "src" => "../Image/" . $value,
    ]);

} else {

    $relativeValue = "../Image/videos/" . $finalName;

    echo json_encode([
        "ok" => true,
        "value" => $relativeValue,
        "src" => $relativeValue,
    ]);

}
