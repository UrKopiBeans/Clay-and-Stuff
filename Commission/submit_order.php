<?php

/* SUBMIT ORDER — saves cart (figures) into the database */

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../helpers/notification_helper.php";

error_reporting(E_ALL);
ini_set("display_errors", "0");
ini_set("log_errors", "1");


/* buffer output kasi kailangan laging JSON lang ang response, kahit may warning or mag-die() ang database.php */

ob_start();

register_shutdown_function(function () {

    $buffered = ob_get_contents();

    if (ob_get_level() > 0) {

        ob_end_clean();

    }


    $decoded = json_decode($buffered, true);


    if (!headers_sent()) {

        header("Content-Type: application/json");

    }


    if ($decoded === null) {

        /* not valid JSON, log the real error, generic message na lang sa customer */

        if ($buffered !== "") {

            error_log("submit_order.php raw output: " . $buffered);

        }


        echo json_encode([
            "success" => false,
            "message" => "Server error while saving your order. Please check your database connection / server logs and try again."
        ]);

    } else {

        /* already valid JSON, pass as-is */

        echo $buffered;

    }

});


require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/booking_helper.php";
require_once __DIR__ . "/../helpers/rate_limit_helper.php";


/* Dress Up payload cleaner — tinatanggap lang ang mga field na kailangan para
   maipakita ulit ang 3D figure at listahan ng napili; walang HTML/script na
   naiipit (lahat string/number lang), at may size limit. */

function figurify_clean_dressup_payload($raw): ?string
{
    if (!is_array($raw)) {
        return null;
    }

    $cleanText = function ($value, int $max = 200): string {
        $text = trim((string) $value);
        return function_exists("mb_substr") ? mb_substr($text, 0, $max) : substr($text, 0, $max);
    };

    $cleanPath = function ($value) use ($cleanText): string {
        $path = $cleanText($value, 255);
        /* 3D files lang sa loob ng dressup-assets, o "placeholder:" (wala pang 3D) */
        if (strpos($path, "placeholder:") === 0) {
            return $path;
        }
        if (!preg_match('#^dressup-assets/[A-Za-z0-9 _()./+-]+\.glb$#', $path) || strpos($path, "..") !== false) {
            return "";
        }
        return $path;
    };

    $cleanColor = function ($value): string {
        $color = trim((string) $value);
        return preg_match('/^#[0-9A-Fa-f]{3,8}$/', $color) ? $color : "";
    };

    $cleanItem = function ($item) use ($cleanText, $cleanPath, $cleanColor): ?array {
        if (!is_array($item)) {
            return null;
        }
        return [
            "name"     => $cleanText($item["name"] ?? ""),
            "model"    => $cleanPath($item["model"] ?? ""),
            "color"    => $cleanColor($item["color"] ?? ""),
            "price"    => max(0, (float) ($item["price"] ?? 0)),
            "billable" => !empty($item["billable"]),
        ];
    };

    $category = $cleanText($raw["category"] ?? "", 20);

    if (!in_array($category, ["funko", "hirono", "chibi"], true)) {
        return null;
    }

    $slots = [];

    foreach ((is_array($raw["slots"] ?? null) ? $raw["slots"] : []) as $slotRow) {

        if (!is_array($slotRow) || count($slots) >= 20) {
            continue;
        }

        $item = $cleanItem($slotRow["item"] ?? null);

        if ($item === null) {
            continue;
        }

        $slots[] = [
            "slot"       => $cleanText($slotRow["slot"] ?? "", 40),
            "label"      => $cleanText($slotRow["label"] ?? "", 60),
            "item"       => $item,
            "colorName"  => $cleanText($slotRow["colorName"] ?? "", 60),
            "colorValue" => $cleanColor($slotRow["colorValue"] ?? ""),
            "isBottom"   => !empty($slotRow["isBottom"]),
        ];
    }

    $model = $cleanItem($raw["model"] ?? null);

    if ($model === null || $model["model"] === "") {
        return null;
    }

    $clean = [
        "version"     => 1,
        "category"    => $category,
        "productKey"  => $cleanText($raw["productKey"] ?? "", 40),
        "productType" => $cleanText($raw["productType"] ?? "", 60),
        "genderLabel" => $cleanText($raw["genderLabel"] ?? "", 60),
        "model"       => $model,
        "skin"        => $cleanItem($raw["skin"] ?? null),
        "slots"       => $slots,
        "size"        => $cleanText($raw["size"] ?? "", 40),
        "sizePrice"   => max(0, (float) ($raw["sizePrice"] ?? 0)),
        "nameFee"     => max(0, (float) ($raw["nameFee"] ?? 0)),
        "designTotal" => max(0, (float) ($raw["designTotal"] ?? 0)),
    ];

    $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return ($json !== false && strlen($json) <= 60000) ? $json : null;
}


/* ------------------------------------------------------------------
   DRESS UP SERVER-SIDE PRICING — huwag pagkatiwalaan ang presyong galing
   sa browser. Kinukuwenta ulit dito ang presyo ng design mula sa mismong
   presyo ng mga card sa Commission/commission.php (data-price), kasama ang
   size, name fee at box. Dapat tugma ito sa ipinakita sa customer.
------------------------------------------------------------------ */

function figurify_dressup_card_prices(): array
{
    static $prices = null;

    if ($prices !== null) {
        return $prices;
    }

    $prices = [];
    $html   = @file_get_contents(__DIR__ . "/commission.php");

    if ($html === false) {
        return $prices;
    }

    preg_match_all('/<button\b([^>]*)>/i', $html, $buttons);

    foreach ($buttons[1] as $attrText) {

        preg_match_all('/data-([a-z-]+)="([^"]*)"/i', $attrText, $pairs, PREG_SET_ORDER);

        $attrs = [];

        foreach ($pairs as $pair) {
            $attrs[strtolower($pair[1])] = html_entity_decode($pair[2], ENT_QUOTES);
        }

        if (empty($attrs["figure"]) || empty($attrs["slot"]) || empty($attrs["model"])) {
            continue;
        }

        /* "Coming soon" na card (naka-disabled) — hindi puwedeng i-order */
        if (preg_match('/\bdisabled\b/i', $attrText)) {
            continue;
        }

        $billable = ($attrs["billable"] ?? "true") !== "false";

        $prices[$attrs["figure"]][$attrs["slot"]][$attrs["model"]] =
            $billable ? (float) ($attrs["price"] ?? 0) : 0.0;
    }

    return $prices;
}


/* kapareho ng PRODUCT_SIZE_PRICES sa Commission/dressup.js */

const FIGURIFY_DRESSUP_SIZE_PRICES = [
    "funkoBoy"           => ["3 inches" => 900, "4 inches" => 1200, "5 inches" => 1500],
    "funkoGirl"          => ["3 inches" => 900, "4 inches" => 1200, "5 inches" => 1500],
    "chibiBoy"           => ["2 inches" => 500, "3 inches" => 680, "4 inches" => 900, "5 inches" => 1200],
    "chibiGirl"          => ["2 inches" => 500, "3 inches" => 680, "4 inches" => 900, "5 inches" => 1200],
    "chibiBoyKeychain"   => ["2 inches" => 500],
    "chibiGirlKeychain"  => ["2 inches" => 500],
    "hironoStandee"      => ["2 inches" => 600, "3.5 inches" => 950],
    "hironoKeychain"     => ["2 inches" => 600],
    "hironoHeadKeychain" => [],
];

/* dagdag-presyo ng bawat item kada size: 1st size 100%, 2nd 125%, 3rd 150%, 4th 175% */
const FIGURIFY_DRESSUP_SIZE_PERCENTS = [100, 125, 150, 175];

const FIGURIFY_DRESSUP_PRODUCT_CATEGORY = [
    "funkoBoy" => "funko", "funkoGirl" => "funko",
    "chibiBoy" => "chibi", "chibiGirl" => "chibi",
    "chibiBoyKeychain" => "chibi", "chibiGirlKeychain" => "chibi",
    "hironoStandee" => "hirono", "hironoKeychain" => "hirono", "hironoHeadKeychain" => "hirono",
];


/* ibinabalik ang presyong kinuwenta ng server, o isang error message (string) */

function figurify_dressup_server_price(array $figure)
{
    $design = $figure["dressup"] ?? null;

    if (!is_array($design)) {
        return "Missing Dress Up design data.";
    }

    $category   = (string) ($design["category"] ?? "");
    $productKey = (string) ($design["productKey"] ?? "");

    if ((FIGURIFY_DRESSUP_PRODUCT_CATEGORY[$productKey] ?? null) !== $category) {
        return "Unknown Dress Up product.";
    }

    $cardPrices = figurify_dressup_card_prices()[$category] ?? [];

    if (empty($cardPrices)) {
        return "Dress Up prices could not be loaded.";
    }

    /* gender/model — dapat totoong card */
    $modelPath = (string) ($design["model"]["model"] ?? "");

    if (!isset($cardPrices["model"][$modelPath])) {
        return "Unknown figure model.";
    }

    /* size — kailangan muna para sa presyo ng bawat item */
    $sizeTable = FIGURIFY_DRESSUP_SIZE_PRICES[$productKey];
    $size      = (string) ($figure["size"] ?? "");
    $sizePrice = 0.0;
    $sizePercent = 100;

    if (!empty($sizeTable)) {
        if (!isset($sizeTable[$size])) {
            return "Please choose a valid figure size.";
        }
        $sizePrice   = (float) $sizeTable[$size];
        $sizeIndex   = array_search($size, array_keys($sizeTable), true);
        $sizePercent = FIGURIFY_DRESSUP_SIZE_PERCENTS[min((int) $sizeIndex, count(FIGURIFY_DRESSUP_SIZE_PERCENTS) - 1)];
    }

    /* hair / top / bottom / shoes / hat — presyo ng card × size (naka-round sa ₱5),
       kapareho ng scaleDressUpPrice() sa Commission/dressup.js */
    $designTotal = 0.0;
    $slotPrices  = [];

    foreach ((array) ($design["slots"] ?? []) as $slotIndex => $slotRow) {

        $slot = (string) ($slotRow["slot"] ?? "");
        $path = (string) ($slotRow["item"]["model"] ?? "");

        if (!isset($cardPrices[$slot][$path])) {
            return "One of the selected items is no longer available.";
        }

        $basePrice   = (float) $cardPrices[$slot][$path];
        $scaledPrice = $basePrice > 0 ? round(($basePrice * $sizePercent) / 500) * 5 : 0.0;

        $slotPrices[$slotIndex] = (float) $scaledPrice;
        $designTotal += $scaledPrice;
    }

    /* walang Figure Name sa Dress Up, kaya walang name fee */
    $nameFee = 0.0;

    /* box / add-ons */
    $boxPrice   = 0.0;
    $boxDetails = $figure["boxDetails"] ?? null;

    if (is_array($boxDetails) && ($boxDetails["addonType"] ?? "") === "funko_box") {

        $funkoBoxPrices = ["solo" => 500, "couple" => 800];
        $boxType        = (string) ($boxDetails["boxType"] ?? "");

        if ($category !== "funko" || !isset($funkoBoxPrices[$boxType])) {
            return "Invalid box selection.";
        }

        $boxPrice = (float) $funkoBoxPrices[$boxType];

    } elseif (is_array($boxDetails) && ($boxDetails["addonType"] ?? "") === "hirono_blind_box") {

        $blindPrices = ["regular" => 150, "set" => 350];
        $extraPrices = ["tear_blind_paper" => 50, "pouch" => 50, "digital_art" => 150];
        $blindType   = (string) ($boxDetails["blindType"] ?? "");

        if ($category !== "hirono" || !isset($blindPrices[$blindType])) {
            return "Invalid blind box selection.";
        }

        $boxPrice = (float) $blindPrices[$blindType];

        foreach (array_keys((array) ($boxDetails["blindItems"] ?? [])) as $extraKey) {
            if (!isset($extraPrices[$extraKey])) {
                return "Invalid blind box extra.";
            }
            $boxPrice += $extraPrices[$extraKey];
        }
    }

    return [
        "designTotal" => $designTotal,
        "sizePrice"   => $sizePrice,
        "nameFee"     => $nameFee,
        "boxPrice"    => $boxPrice,
        "slotPrices"  => $slotPrices,
        "total"       => $designTotal + $sizePrice + $nameFee + $boxPrice,
    ];
}


/* must be logged in */

if (!isset($_SESSION["user_id"])) {

    echo json_encode([
        "success" => false,
        "message" => "Please log in first before submitting an order."
    ]);

    exit();

}

$user_id = $_SESSION["user_id"];


/* rate-limit: max 5 order submissions per 10 minutes per account,
   laban sa spam/abuse (hindi ito magiging problema sa normal na
   customer, isang order lang naman ang isinusumite kada session) */

$orderRateLimitSeconds = figurify_action_rate_limit_seconds_remaining(
    $conn,
    "commission_order",
    "user_" . $user_id,
    5,
    10
);

if ($orderRateLimitSeconds > 0) {

    echo json_encode([
        "success" => false,
        "message" => "You've submitted several orders recently. Please wait a bit before submitting another."
    ]);

    exit();

}


/* check kung existing pa rin yung user, baka luma na yung session pero na-reset na ang DB */

$userCheckStmt = $conn->prepare(
    "SELECT user_id FROM users WHERE user_id = ? LIMIT 1"
);

$userCheckStmt->bind_param("i", $user_id);
$userCheckStmt->execute();

$userExists = $userCheckStmt->get_result()->num_rows === 1;

$userCheckStmt->close();

if (!$userExists) {

    /* clear the invalid session */
    session_unset();
    session_destroy();

    echo json_encode([
        "success" => false,
        "message" => "Your session is outdated (this can happen after a database reset). Please log out and log in again, then try submitting your order."
    ]);

    exit();

}


/* read posted fields */

$order_type   = $_POST["order_type"] ?? "";
$order_method = $_POST["order_method"] ?? "reference";
$booking_date = $_POST["booking_date"] ?? "";
$figures_json = $_POST["figures"] ?? "[]";

$figures = json_decode($figures_json, true);


/* basic validation */

if (!in_array($order_type, ["rush", "nonrush"], true)) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid order type."
    ]);

    exit();

}

/* default sa "reference" kung di kilalang value */

if (!in_array($order_method, ["reference", "create_style"], true)) {

    $order_method = "reference";

}

if ($booking_date === "" || !strtotime($booking_date)) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid or missing booking date."
    ]);

    exit();

}

if (!is_array($figures) || count($figures) === 0) {

    echo json_encode([
        "success" => false,
        "message" => "Please add at least 1 figure to your order."
    ]);

    exit();

}


/* recheck booking limits server-side, di puwede umasa lang sa calendar JS */

$normalizedBookingDate = date("Y-m-d", strtotime($booking_date));

$bookingCheck = ($order_type === "rush")
    ? figurify_check_rush_date($conn, $normalizedBookingDate)
    : figurify_check_nonrush_date($conn, $normalizedBookingDate);

if (!$bookingCheck["ok"]) {

    echo json_encode([
        "success" => false,
        "message" => $bookingCheck["reason"] ?? "That booking date is no longer available."
    ]);

    exit();

}


/* Dress Up: i-verify at palitan ng presyong galing sa server ang bawat figure */

if ($order_method === "create_style") {

    foreach ($figures as $figureIndex => $dressFigure) {

        $serverPrice = is_array($dressFigure)
            ? figurify_dressup_server_price($dressFigure)
            : "Invalid figure data.";

        if (is_string($serverPrice)) {

            echo json_encode([
                "success" => false,
                "message" => $serverPrice . " Please go back to your design and try again."
            ]);

            exit();

        }

        /* dapat tugma sa presyong ipinakita sa customer */
        if (abs($serverPrice["total"] - floatval($dressFigure["total"] ?? -1)) > 0.009) {

            error_log(
                "submit_order.php dress up price mismatch: client=" .
                ($dressFigure["total"] ?? "none") . " server=" . $serverPrice["total"]
            );

            echo json_encode([
                "success" => false,
                "message" => "The price of your design has changed. Please go back to your design, review it, and try again."
            ]);

            exit();

        }

        $figures[$figureIndex]["productPrice"]  = $serverPrice["sizePrice"];
        $figures[$figureIndex]["nameFee"]       = $serverPrice["nameFee"];
        $figures[$figureIndex]["boxAddonPrice"] = $serverPrice["boxPrice"];
        $figures[$figureIndex]["total"]         = $serverPrice["total"];
        $figures[$figureIndex]["dressup"]["designTotal"] = $serverPrice["designTotal"];
        $figures[$figureIndex]["dressup"]["sizePrice"]   = $serverPrice["sizePrice"];
        $figures[$figureIndex]["dressup"]["nameFee"]     = $serverPrice["nameFee"];

        /* presyo ng bawat item sa design — galing din sa server */
        foreach ($serverPrice["slotPrices"] as $slotIndex => $slotPrice) {
            $figures[$figureIndex]["dressup"]["slots"][$slotIndex]["item"]["price"]    = $slotPrice;
            $figures[$figureIndex]["dressup"]["slots"][$slotIndex]["item"]["billable"] = $slotPrice > 0;
        }

    }

}


/* compute totals server-side, do not trust the client */

/* rush fee is per figure na base sa product type, hindi na flat ₱500 */
$rush_fee = ($order_type === "rush")
    ? figurify_calculate_total_rush_fee($figures)
    : 0;

$subtotal = 0;

foreach ($figures as $figure) {

    $subtotal += floatval($figure["total"] ?? 0);

}

$total = $subtotal + $rush_fee;


/* Dress Up (create_style) — alam na agad ang presyo (galing sa design mismo),
   kaya diretso na sa "awaiting_payment" at wala nang quotation. Ang Image
   Submission (reference) ay "pending" pa rin para ma-quote muna ng staff. */

$isDressUpOrder = ($order_method === "create_style");

$initialStatus = $isDressUpOrder ? "awaiting_payment" : "pending";

if ($isDressUpOrder && count($figures) !== 1) {

    echo json_encode([
        "success" => false,
        "message" => "A Dress Up order should contain exactly 1 figure."
    ]);

    exit();

}


/* check database connection before starting */

if (!isset($conn) || $conn->connect_error) {

    echo json_encode([
        "success" => false,
        "message" => "Could not connect to the database. Please try again later."
    ]);

    exit();

}


/* insert order + figures + images (transaction) */

/* Dress Up: siguraduhing may "dressup_data" column. Kapag hindi pa na-run ang
   Admin/add_dressup_orders.sql, kusa na itong idadagdag dito (isang beses lang).
   Ginagawa ito BAGO ang transaction dahil nag-a-auto-commit ang ALTER TABLE. */

if ($isDressUpOrder) {

    try {
        $columnCheck = $conn->query("SHOW COLUMNS FROM order_figures LIKE 'dressup_data'");
        $hasDressUpColumn = $columnCheck && $columnCheck->num_rows > 0;

        if (!$hasDressUpColumn) {
            $conn->query(
                "ALTER TABLE order_figures
                    ADD COLUMN dressup_data LONGTEXT NULL
                    COMMENT 'JSON ng Dress Up design: gender, skin, hair, top, bottom, shoes, colors, 3D model paths'"
            );
        }
    } catch (Throwable $columnError) {
        error_log("submit_order.php could not add dressup_data column: " . $columnError->getMessage());
    }

}


$conn->begin_transaction();

try {

    /* insert into orders */

    $stmt = $conn->prepare(
        "INSERT INTO orders
            (user_id, order_type, order_method, booking_date, rush_fee, subtotal, total_amount, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        throw new Exception("Prepare failed (orders): " . $conn->error);
    }

    $stmt->bind_param(
        "isssddds",
        $user_id,
        $order_type,
        $order_method,
        $booking_date,
        $rush_fee,
        $subtotal,
        $total,
        $initialStatus
    );

    $stmt->execute();

    $order_id = $stmt->insert_id;

    $stmt->close();


    /* folder for this order's images */

    $uploadDir = __DIR__ . "/../uploads/orders/" . $order_id . "/";

    if (!is_dir($uploadDir)) {

        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {

            throw new Exception("Could not create upload folder.");

        }

    }


    /* insert each figure */

    foreach ($figures as $index => $figure) {

        $style        = $figure["style"] ?? "";
        $product      = $figure["product"] ?? "";
        $size         = $figure["size"] ?? "";
        $sizeLabel    = $figure["sizeLabel"] ?? "";
        $name         = $figure["name"] ?? "";
        $notes        = $figure["notes"] ?? "";
        $productPrice = floatval($figure["productPrice"] ?? 0);
        $nameFee      = floatval($figure["nameFee"] ?? 0);
        $figureTotal  = floatval($figure["total"] ?? 0);


        /* name is optional sa frontend, style/product na lang required, placeholder name na lang kung wala */

        if ($name === "") {
            $name = $style . " Figure #" . ($index + 1);
        }

        if ($style === "" || $product === "") {

            $missing = [];

            if ($style === "")   $missing[] = "style";
            if ($product === "") $missing[] = "product type";

            throw new Exception(
                "Incomplete figure data on figure #" . ($index + 1) .
                " — missing: " . implode(", ", $missing) .
                ". (Received style='" . $style . "', product='" . $product . "')"
            );

        }


        /* box add-on fields, null by default, filled in depende sa addonType */

        $boxDetails     = $figure["boxDetails"] ?? null;
        $boxAddonPrice  = floatval($figure["boxAddonPrice"] ?? 0);

        $boxAddonType        = null;
        $funkoBoxType        = null;
        $funkoBoxName        = null;
        $funkoBoxNumber      = null;
        $funkoBoxColor       = null;
        $hironoBlindType     = null;
        $hironoBoxDesign     = null;
        $hironoBoxColor      = null;
        $hironoLetter        = null;
        $hironoNickname      = null;
        $hironoDate          = null;
        $hironoBlindItemsRaw = null;
        $hironoBlindItemsTotal = 0;

        $funkoBoxTypeVal    = is_array($boxDetails) ? trim($boxDetails["boxType"] ?? "") : "";
        $hironoBlindTypeVal = is_array($boxDetails) ? trim($boxDetails["blindType"] ?? "") : "";

        /* check muna kung may napiling boxType/blindType, boxDetails always exists sa JS kahit walang box napili */

        if (is_array($boxDetails) && ($boxDetails["addonType"] ?? "") === "funko_box" && $funkoBoxTypeVal !== "") {

            $boxAddonType   = "funko_box";
            $funkoBoxType   = $funkoBoxTypeVal;
            $funkoBoxName   = $boxDetails["boxName"] ?? "";
            $funkoBoxNumber = $boxDetails["boxNumber"] ?? "";
            $funkoBoxColor  = $boxDetails["boxColor"] ?? "";

        } elseif (is_array($boxDetails) && ($boxDetails["addonType"] ?? "") === "hirono_blind_box" && $hironoBlindTypeVal !== "") {

            $monthNames = [
                "01" => "January",   "02" => "February", "03" => "March",
                "04" => "April",     "05" => "May",       "06" => "June",
                "07" => "July",      "08" => "August",    "09" => "September",
                "10" => "October",   "11" => "November",  "12" => "December",
            ];

            $dateMonth = $boxDetails["dateMonth"] ?? "";
            $dateDay   = $boxDetails["dateDay"] ?? "";

            $boxAddonType    = "hirono_blind_box";
            $hironoBlindType = $hironoBlindTypeVal;
            $hironoBoxDesign = $boxDetails["boxDesign"] ?? "";
            $hironoBoxColor  = $boxDetails["boxColor"] ?? "";
            $hironoLetter    = $boxDetails["letter"] ?? "";
            $hironoNickname  = $boxDetails["nickname"] ?? "";

            if ($dateMonth !== "" && $dateDay !== "") {

                $monthLabel = $monthNames[$dateMonth] ?? $dateMonth;
                $hironoDate = $monthLabel . " " . ltrim($dateDay, "0");

            }

            /* optional extras, saved as JSON e.g. { "tear_blind_paper": 50, ... } */

            $blindItems = $boxDetails["blindItems"] ?? [];

            if (is_array($blindItems) && count($blindItems) > 0) {

                $cleanItems = [];

                foreach ($blindItems as $itemKey => $itemPrice) {

                    $cleanItems[(string) $itemKey] = floatval($itemPrice);
                    $hironoBlindItemsTotal += floatval($itemPrice);

                }

                $hironoBlindItemsRaw = json_encode($cleanItems);

            }


        }


        $fstmt = $conn->prepare(
            "INSERT INTO order_figures
                (order_id, figure_style, product_type, size_value, size_label,
                 figure_name, notes, product_price, name_fee, figure_total,
                 box_addon_type, box_addon_price,
                 funko_box_type, funko_box_name, funko_box_number, funko_box_color,
                 hirono_blind_type, hirono_box_design, hirono_box_color, hirono_letter,
                 hirono_nickname, hirono_date, hirono_blind_items, hirono_blind_items_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$fstmt) {
            throw new Exception("Prepare failed (order_figures): " . $conn->error);
        }

        $fstmt->bind_param(
            "issssssdddsdsssssssssssd",
            $order_id,
            $style,
            $product,
            $size,
            $sizeLabel,
            $name,
            $notes,
            $productPrice,
            $nameFee,
            $figureTotal,
            $boxAddonType,
            $boxAddonPrice,
            $funkoBoxType,
            $funkoBoxName,
            $funkoBoxNumber,
            $funkoBoxColor,
            $hironoBlindType,
            $hironoBoxDesign,
            $hironoBoxColor,
            $hironoLetter,
            $hironoNickname,
            $hironoDate,
            $hironoBlindItemsRaw,
            $hironoBlindItemsTotal
        );

        $fstmt->execute();

        $figure_id = $fstmt->insert_id;

        $fstmt->close();


        /* Dress Up design (gender, skin, hair, top, bottom, shoes, colors, 3D paths).
           Fixed na ang presyo ng Dress Up, kaya quoted_price = figure_total agad
           (para lumabas sa Order Summary gaya ng na-quote na Image Submission).
           Hiwalay na UPDATE para hindi maapektuhan ang Image Submission orders
           kung sakaling hindi pa na-run ang Admin/add_dressup_orders.sql. */

        if ($isDressUpOrder) {

            $dressupJson = figurify_clean_dressup_payload($figure["dressup"] ?? null);

            if ($dressupJson === null) {
                throw new Exception("Missing Dress Up design data on figure #" . ($index + 1) . ".");
            }

            try {
                $dstmt = $conn->prepare(
                    "UPDATE order_figures SET dressup_data = ?, quoted_price = figure_total WHERE figure_id = ?"
                );
            } catch (Throwable $prepareError) {
                $dstmt = false;
            }

            if (!$dstmt) {
                throw new Exception(
                    "The dressup_data column is missing. Please run Admin/add_dressup_orders.sql in phpMyAdmin."
                );
            }

            $dstmt->bind_param("si", $dressupJson, $figure_id);
            $dstmt->execute();
            $dstmt->close();

        }


        /* images for this figure — JS sends files under key: figure_{index}_images[] */

        $fileKey = "figure_" . $index . "_images";

        if (isset($_FILES[$fileKey])) {

            $fileCount = count($_FILES[$fileKey]["name"]);

            for ($i = 0; $i < $fileCount; $i++) {

                if ($_FILES[$fileKey]["error"][$i] !== UPLOAD_ERR_OK) {

                    continue;

                }

                $tmpPath      = $_FILES[$fileKey]["tmp_name"][$i];
                $originalName = basename($_FILES[$fileKey]["name"][$i]);
                $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                $allowedExt = ["jpg", "jpeg", "png", "gif", "webp"];

                if (!in_array($ext, $allowedExt, true)) {

                    continue;

                }

                $safeName = "fig" . $figure_id . "_img" . $i . "_" . time() . "." . $ext;
                $destPath = $uploadDir . $safeName;

                if (move_uploaded_file($tmpPath, $destPath)) {

                    $relativePath = "uploads/orders/" . $order_id . "/" . $safeName;

                    $imgStmt = $conn->prepare(
                        "INSERT INTO order_figure_images (figure_id, image_path)
                         VALUES (?, ?)"
                    );

                    $imgStmt->bind_param("is", $figure_id, $relativePath);

                    $imgStmt->execute();

                    $imgStmt->close();

                }

            }

        }


        /* box reference images, Hirono blind box only. JS sends these under figure_{index}_box_images[] */

        $boxFileKey = "figure_" . $index . "_box_images";

        if ($boxAddonType === "hirono_blind_box" && isset($_FILES[$boxFileKey])) {

            $boxFileCount = count($_FILES[$boxFileKey]["name"]);

            for ($i = 0; $i < $boxFileCount; $i++) {

                if ($_FILES[$boxFileKey]["error"][$i] !== UPLOAD_ERR_OK) {

                    continue;

                }

                $tmpPath      = $_FILES[$boxFileKey]["tmp_name"][$i];
                $originalName = basename($_FILES[$boxFileKey]["name"][$i]);
                $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                $allowedExt = ["jpg", "jpeg", "png", "gif", "webp"];

                if (!in_array($ext, $allowedExt, true)) {

                    continue;

                }

                $safeName = "fig" . $figure_id . "_box" . $i . "_" . time() . "." . $ext;
                $destPath = $uploadDir . $safeName;

                if (move_uploaded_file($tmpPath, $destPath)) {

                    $relativePath = "uploads/orders/" . $order_id . "/" . $safeName;

                    $boxImgStmt = $conn->prepare(
                        "INSERT INTO order_figure_box_images (figure_id, image_path)
                         VALUES (?, ?)"
                    );

                    $boxImgStmt->bind_param("is", $figure_id, $relativePath);

                    $boxImgStmt->execute();

                    $boxImgStmt->close();

                }

            }

        }

    }


    /* all good — commit */

    $conn->commit();

    figurify_record_action_attempt($conn, "commission_order", "user_" . $user_id);


    /* notify staff, may bagong order na dapat tingnan */

    figurify_notify_staff(
        $conn,
        (int) $order_id,
        $isDressUpOrder
            ? "New Dress Up order #" . $order_id . " was placed — waiting for the customer's downpayment."
            : "New order #" . $order_id . " was placed and needs review."
    );

    echo json_encode([
        "success"      => true,
        "order_id"     => $order_id,
        "status"       => $initialStatus,
        "payment_url"  => $isDressUpOrder
            ? "../my-order/payment-shipping.php?order_id=" . $order_id
            : null
    ]);

} catch (Throwable $e) {

    $conn->rollback();

    error_log("submit_order.php exception: " . $e->getMessage());

    /* show real error reason kapag localhost/XAMPP lang, hidden sa live server */

    $isLocalhost = in_array(
        $_SERVER["SERVER_ADDR"] ?? "",
        ["127.0.0.1", "::1"],
        true
    ) || in_array(
        $_SERVER["SERVER_NAME"] ?? "",
        ["localhost", "127.0.0.1"],
        true
    );

    $response = [
        "success" => false,
        "message" => "Something went wrong while saving your order. Please try again."
    ];

    if ($isLocalhost) {
        $response["debug_reason"] = $e->getMessage();
    }

    echo json_encode($response);

}

$conn->close();