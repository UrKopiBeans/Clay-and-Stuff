<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/collection_helper.php";

// Owner access guard
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../Login/Login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: collection-management.php");
    exit();
}

figurify_collection_ensure_table($conn);

// Fixed groups (Chibi/Hirono/Funko Pop) — laging "normal" ang section, fixed ang category/style.
// Ang "special" group ay free-form ang style/category kasi magkakaiba-iba ang laman.

$fixedGroups = [
    "chibi"  => ["section" => "normal", "category" => "chibi",  "style" => "Chibi"],
    "hirono" => ["section" => "normal", "category" => "hirono", "style" => "Hirono"],
    "funko"  => ["section" => "normal", "category" => "funko",  "style" => "Funko Pop"],
];

$items = $_POST["items"] ?? [];

// Chibi / Hirono / Funko Pop
foreach ($fixedGroups as $groupKey => $groupInfo) {

    if (!isset($items[$groupKey]) || !is_array($items[$groupKey])) {
        continue;
    }

    foreach ($items[$groupKey] as $row) {

        $itemId = trim((string) ($row["item_id"] ?? ""));
        $remove = !empty($row["remove"]);

        if ($remove) {
            if ($itemId !== "" && ctype_digit($itemId)) {
                $stmt = $conn->prepare("DELETE FROM collection_items WHERE item_id = ?");
                $itemIdInt = (int) $itemId;
                $stmt->bind_param("i", $itemIdInt);
                $stmt->execute();
                $stmt->close();
            }
            continue;
        }

        $type = trim((string) ($row["type"] ?? ""));
        $size = trim((string) ($row["size"] ?? ""));
        $price = trim((string) ($row["price"] ?? ""));
        $image = trim((string) ($row["image"] ?? ""));
        $bestseller = !empty($row["bestseller"]) ? 1 : 0;

        /* Laktawan kung talagang walang laman (hindi na-fill-up
           na bagong row na dinagdag lang pero hindi ginamit). */
        if ($type === "" && $size === "" && $price === "" && $image === "") {
            continue;
        }

        if ($itemId !== "" && ctype_digit($itemId)) {

            $itemIdInt = (int) $itemId;

            $stmt = $conn->prepare("
                UPDATE collection_items
                SET style = ?, type = ?, size = ?, price = ?, image = ?, is_bestseller = ?
                WHERE item_id = ?
            ");
            $stmt->bind_param(
                "sssssii",
                $groupInfo["style"],
                $type,
                $size,
                $price,
                $image,
                $bestseller,
                $itemIdInt
            );
            $stmt->execute();
            $stmt->close();

        } else {

            $stmt = $conn->prepare("
                INSERT INTO collection_items
                    (section, category, style, type, size, price, image, is_bestseller, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 999)
            ");
            $stmt->bind_param(
                "sssssssi",
                $groupInfo["section"],
                $groupInfo["category"],
                $groupInfo["style"],
                $type,
                $size,
                $price,
                $image,
                $bestseller
            );
            $stmt->execute();
            $stmt->close();

        }
    }
}

// Special figures — free-form style/category
if (isset($items["special"]) && is_array($items["special"])) {

    $allowedCategories = ["funko", "custom", "special"];

    foreach ($items["special"] as $row) {

        $itemId = trim((string) ($row["item_id"] ?? ""));
        $remove = !empty($row["remove"]);

        if ($remove) {
            if ($itemId !== "" && ctype_digit($itemId)) {
                $stmt = $conn->prepare("DELETE FROM collection_items WHERE item_id = ?");
                $itemIdInt = (int) $itemId;
                $stmt->bind_param("i", $itemIdInt);
                $stmt->execute();
                $stmt->close();
            }
            continue;
        }

        $style = trim((string) ($row["style"] ?? ""));
        $category = trim((string) ($row["category"] ?? "special"));
        $type = trim((string) ($row["type"] ?? ""));
        $size = trim((string) ($row["size"] ?? ""));
        $price = trim((string) ($row["price"] ?? ""));
        $image = trim((string) ($row["image"] ?? ""));
        $bestseller = !empty($row["bestseller"]) ? 1 : 0;

        if (!in_array($category, $allowedCategories, true)) {
            $category = "special";
        }

        if ($style === "" && $type === "" && $size === "" && $price === "") {
            continue;
        }

        if ($itemId !== "" && ctype_digit($itemId)) {

            $itemIdInt = (int) $itemId;

            $stmt = $conn->prepare("
                UPDATE collection_items
                SET category = ?, style = ?, type = ?, size = ?, price = ?, image = ?, is_bestseller = ?
                WHERE item_id = ?
            ");
            $stmt->bind_param(
                "ssssssii",
                $category,
                $style,
                $type,
                $size,
                $price,
                $image,
                $bestseller,
                $itemIdInt
            );
            $stmt->execute();
            $stmt->close();

        } else {

            $section = "special";

            $stmt = $conn->prepare("
                INSERT INTO collection_items
                    (section, category, style, type, size, price, image, is_bestseller, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 999)
            ");
            $stmt->bind_param(
                "sssssssi",
                $section,
                $category,
                $style,
                $type,
                $size,
                $price,
                $image,
                $bestseller
            );
            $stmt->execute();
            $stmt->close();

        }
    }
}

// tanggalin sa Image/collection/ ang mga file na wala nang gumagamit
$freshImages = [];
$imgResult = $conn->query("SELECT image FROM collection_items");

if ($imgResult) {
    while ($imgRow = $imgResult->fetch_assoc()) {
        $freshImages[] = $imgRow["image"];
    }
}

figurify_sync_category_images("collection", $freshImages);

header("Location: collection-management.php?saved=1");
exit();
