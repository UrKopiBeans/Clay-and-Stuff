<?php

/* Hinahanap dito ang "orphaned" upload files (larawan ng orders na
   na-delete na sa DB pero naiwan pa rin sa server). Default: DRY RUN
   lang (listahan, walang tanggal). Idagdag ang "?confirm=1" sa URL
   para talagang tanggalin ang mga nakalista. */

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/database.php";
require_once __DIR__ . "/../helpers/access_helper.php";

// Admin lang na naka-login ang puwedeng magpatakbo nito (may DELETE ito).
figurify_require_role("admin");

$confirm = isset($_GET["confirm"]) && $_GET["confirm"] === "1";

$uploadsRoot   = __DIR__ . "/../uploads";
$ordersDir     = $uploadsRoot . "/orders";
$paymentsDir   = $uploadsRoot . "/payment_proofs";

$orphanedOrderFolders = [];
$orphanedPaymentFiles = [];
$errors               = [];


/* =========================================================
   1. GET ALL EXISTING ORDER IDs FROM THE DATABASE
========================================================= */

$existingOrderIds = [];

$result = $conn->query("SELECT order_id FROM orders");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $existingOrderIds[(int) $row["order_id"]] = true;
    }

} else {

    $errors[] = "Could not read orders table: " . $conn->error;

}


/* =========================================================
   2. SCAN uploads/orders/{order_id}/ FOLDERS
========================================================= */

if (is_dir($ordersDir)) {

    $entries = scandir($ordersDir);

    foreach ($entries as $entry) {

        if ($entry === "." || $entry === "..") {
            continue;
        }

        $fullPath = $ordersDir . "/" . $entry;

        if (!is_dir($fullPath)) {
            continue;
        }

        /* Ang folder name ay dapat purong numero (yung
           order_id) — kung hindi, laktawan na lang, huwag
           galawin (baka may ibang purpose ito). */

        if (!ctype_digit($entry)) {
            continue;
        }

        $folderOrderId = (int) $entry;

        if (!isset($existingOrderIds[$folderOrderId])) {

            $orphanedOrderFolders[] = [
                "order_id" => $folderOrderId,
                "path"     => $fullPath
            ];

        }

    }

}


/* =========================================================
   3. SCAN uploads/payment_proofs/ FILES
      Filename format: payment_{order_id}_{time}_{hash}.ext
========================================================= */

if (is_dir($paymentsDir)) {

    $entries = scandir($paymentsDir);

    foreach ($entries as $entry) {

        if ($entry === "." || $entry === "..") {
            continue;
        }

        $fullPath = $paymentsDir . "/" . $entry;

        if (!is_file($fullPath)) {
            continue;
        }

        /* payment_{order_id}_... -> kunin yung order_id part */

        if (preg_match('/^payment_(\d+)_/', $entry, $matches)) {

            $fileOrderId = (int) $matches[1];

            if (!isset($existingOrderIds[$fileOrderId])) {

                $orphanedPaymentFiles[] = [
                    "order_id" => $fileOrderId,
                    "path"     => $fullPath
                ];

            }

        }

    }

}


/* =========================================================
   HELPER: RECURSIVELY DELETE A FOLDER AND ITS CONTENTS
========================================================= */

function figurify_delete_folder(string $dir): bool
{
    if (!is_dir($dir)) {
        return false;
    }

    $items = scandir($dir);

    foreach ($items as $item) {

        if ($item === "." || $item === "..") {
            continue;
        }

        $path = $dir . "/" . $item;

        if (is_dir($path)) {
            figurify_delete_folder($path);
        } else {
            @unlink($path);
        }

    }

    return @rmdir($dir);
}


/* =========================================================
   4. IF CONFIRMED, ACTUALLY DELETE NOW
========================================================= */

$deletedFolders = [];
$deletedFiles   = [];
$deleteErrors   = [];

if ($confirm) {

    foreach ($orphanedOrderFolders as $folder) {

        if (figurify_delete_folder($folder["path"])) {
            $deletedFolders[] = $folder;
        } else {
            $deleteErrors[] = "Could not delete folder: " . $folder["path"];
        }

    }

    foreach ($orphanedPaymentFiles as $file) {

        if (@unlink($file["path"])) {
            $deletedFiles[] = $file;
        } else {
            $deleteErrors[] = "Could not delete file: " . $file["path"];
        }

    }

}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="../Image/logoclay.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cleanup Orphaned Uploads</title>
<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 800px;
        margin: 40px auto;
        padding: 0 20px;
        color: #222;
    }
    h1 { font-size: 22px; }
    h2 { font-size: 17px; margin-top: 30px; }
    .box {
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
        background: #fafafa;
    }
    .warn {
        background: #fff8e1;
        border-color: #ffca28;
    }
    .ok {
        background: #e8f5e9;
        border-color: #66bb6a;
    }
    .error {
        background: #ffebee;
        border-color: #ef5350;
    }
    ul { margin: 8px 0; padding-left: 20px; }
    code {
        background: #eee;
        padding: 2px 6px;
        border-radius: 4px;
    }
    a.confirm-btn {
        display: inline-block;
        margin-top: 10px;
        padding: 10px 18px;
        background: #d32f2f;
        color: #fff;
        text-decoration: none;
        border-radius: 6px;
        font-weight: bold;
    }
</style>
</head>
<body>

<h1>Cleanup Orphaned Uploads</h1>

<?php if (!$confirm): ?>

    <div class="box warn">
        <strong>DRY RUN lang ito — wala pang natatanggal.</strong><br>
        Ito lang ang listahan ng mga orphaned na files/folder na
        makikita ko. Suriin mo muna, tapos kung tama, i-click yung
        button sa ibaba para talagang tanggalin sila.
    </div>

<?php else: ?>

    <div class="box ok">
        <strong>Tapos na ang cleanup.</strong> Tingnan sa ibaba kung
        ano ang natanggal.
    </div>

<?php endif; ?>


<h2>Order folders (uploads/orders/) — <?php echo count($orphanedOrderFolders); ?> orphaned</h2>

<?php if (empty($orphanedOrderFolders)): ?>
    <p>Walang nahanap na orphaned order folder. 🎉</p>
<?php else: ?>
    <ul>
        <?php foreach ($orphanedOrderFolders as $folder): ?>
            <li>
                Order #<?php echo $folder["order_id"]; ?>
                — <code><?php echo htmlspecialchars($folder["path"]); ?></code>
                <?php if ($confirm): ?>
                    <?php
                        $wasDeleted = in_array($folder, $deletedFolders, true);
                    ?>
                    — <?php echo $wasDeleted ? "✅ deleted" : "❌ failed to delete"; ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>


<h2>Payment proof files (uploads/payment_proofs/) — <?php echo count($orphanedPaymentFiles); ?> orphaned</h2>

<?php if (empty($orphanedPaymentFiles)): ?>
    <p>Walang nahanap na orphaned payment proof file. 🎉</p>
<?php else: ?>
    <ul>
        <?php foreach ($orphanedPaymentFiles as $file): ?>
            <li>
                Order #<?php echo $file["order_id"]; ?>
                — <code><?php echo htmlspecialchars($file["path"]); ?></code>
                <?php if ($confirm): ?>
                    <?php
                        $wasDeleted = in_array($file, $deletedFiles, true);
                    ?>
                    — <?php echo $wasDeleted ? "✅ deleted" : "❌ failed to delete"; ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>


<?php if (!empty($errors)): ?>
    <h2>Errors</h2>
    <div class="box error">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>


<?php if (!empty($deleteErrors)): ?>
    <h2>Delete Errors</h2>
    <div class="box error">
        <ul>
            <?php foreach ($deleteErrors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>


<?php if (!$confirm && (!empty($orphanedOrderFolders) || !empty($orphanedPaymentFiles))): ?>

    <a class="confirm-btn" href="?confirm=1">
        Yes, delete all of the above now
    </a>

<?php endif; ?>

</body>
</html>
