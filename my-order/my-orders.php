<?php

require_once __DIR__ . "/../helpers/session_helper.php";
require_once __DIR__ . "/../helpers/dressup_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/expire_helper.php";
require_once __DIR__ . "/../helpers/order_status_helper.php";
require_once __DIR__ . "/../helpers/review_helper.php";


/* statusLabel() and figurify_status_class() are in order_status_helper.php,
   shared across Owner/Staff Dashboard, Calendar, and this page */


/* Friendly text para sa order method: "reference" = Image Submission, "create_style" = Dress Up */

function orderMethodLabel($method)
{
    $labels = [
        "reference"    => "Image Submission",
        "create_style" => "Dress Up",
    ];

    return $labels[$method] ?? "Image Submission";
}


/* friendly text for the Hirono box design */

function hironoBoxDesignLabel($design)
{
    $labels = [
        "checkered"   => "Checkered",
        "hirono_peek" => "Hirono Peek",
    ];

    return $labels[$design] ?? $design;
}


/* price helper: presyo sa "Product Type" row kung walang size, sa "Size" row kung may size
   (kapareho ng Commission form's Order Summary) */

function figureHasSize($fig)
{
    return !empty($fig["size_label"]);
}


/* Hirono base blind box price (walang extras) — box_addon_price ay kabuuan na
   ng box + extras, kaya ibawas ang extras total */

function hironoBaseBoxPrice($fig)
{
    return (float) $fig["box_addon_price"] - (float) $fig["hirono_blind_items_total"];
}


/* reference images — parehong larawan gaya ng Commission form, para magkatugma */

function figureStyleImage($style)
{
    $map = [
        "Chibi"     => "chibi.jpg",
        "Funko Pop" => "funko.jpg",
        "Hirono"    => "hirono.jpg",
    ];

    return $map[$style] ?? null;
}

function productTypeImage($style, $product)
{
    $map = [
        "Chibi" => [
            "Full Body Standee"  => "ch-fbs.jpg",
            "Full Body Keychain" => "ch-fbk.jpg",
            "Half Body Keychain" => "ch-hbk.jpg",
            "Head Only Keychain" => "ch-ho.jpg",
        ],
        "Funko Pop" => [
            "Full Body Standee"  => "funko-fbs.jpg",
            "Full Body Keychain" => "funko-fbk.jpg",
            "Half Body Keychain" => "funko-hbk.jpg",
            "Head Only Keychain" => "funko-ho.jpg",
        ],
        "Hirono" => [
            "Full Body Standee"  => "hirono-fbs.jpg",
            "Full Body Keychain" => "hirono-fbk.jpg",
            "Half Body Keychain" => "hirono-hbk.jpg",
            "Head Only Keychain" => "hirono-ho.jpg",
        ],
    ];

    return $map[$style][$product] ?? null;
}

function hironoBlindTypeImage($blindType)
{
    return ($blindType === "set") ? "blindboxset.jpg" : "Hirono.jpg";
}

function hironoBoxDesignImage($design)
{
    $map = [
        "checkered"   => "checkered.jpg",
        "hirono_peek" => "peek.jpg",
    ];

    return $map[$design] ?? null;
}


/* image for each Hirono blind box optional extra — parehong placeholder image gaya ng Commission form */

function hironoBlindItemImage($itemKey)
{
    return "Hirono.jpg";
}


/* site base path — computed base sa project folder location, hindi umaasa sa bilang ng "../" */

$documentRoot = rtrim(str_replace("\\", "/", realpath($_SERVER["DOCUMENT_ROOT"])), "/");
$projectRoot  = rtrim(str_replace("\\", "/", realpath(__DIR__ . "/..")), "/");
$siteBase     = substr($projectRoot, strlen($documentRoot)) . "/";


/* must be logged in */

if (!isset($_SESSION["user_id"])) {

    header("Location: ../Login/Login.php");

    exit();

}

$user_id = $_SESSION["user_id"];


/* clean up any expired quotations (2 days) bago kunin ang list */

figurify_expire_old_quotes($conn);


/* banner flags (from confirm_order.php / submit_payment.php) */

$justCancelled  = isset($_GET["cancelled"]) && $_GET["cancelled"] === "1";
$justSubmitted  = isset($_GET["submitted"]) && $_GET["submitted"] === "1";


/* get all orders of this user (newest first) */

$stmt = $conn->prepare(
    "SELECT order_id, order_type, order_method, booking_date, rush_fee,
            subtotal, total_amount, status, created_at,
            quote_expires_at, review_token,
            shipping_name, shipping_address, shipping_contact, courier,
            payment_reference, payment_proof, paid_at,
            refund_account_name, refund_account_number, refund_method,
            balance_to_pay
     FROM orders
     WHERE user_id = ?
     ORDER BY created_at DESC"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$ordersResult = $stmt->get_result();

$orders = [];

while ($row = $ordersResult->fetch_assoc()) {

    $orders[] = $row;

}

$stmt->close();


/* review (kung meron) per "completed" order — para malaman "Leave a Review" vs "View My Review" */

$reviewsByOrder = [];

$reviewStmt = $conn->prepare(
    "SELECT r.order_id, r.rating
     FROM reviews r
     JOIN orders o ON o.order_id = r.order_id
     WHERE o.user_id = ?"
);
$reviewStmt->bind_param("i", $user_id);
$reviewStmt->execute();

$reviewResult = $reviewStmt->get_result();

while ($rRow = $reviewResult->fetch_assoc()) {
    $reviewsByOrder[(int) $rRow["order_id"]] = $rRow;
}

$reviewStmt->close();


/* old completed orders (bago pa yung review feature) walang review_token pa, gawan na */

foreach ($orders as &$reviewTokenOrder) {

    if ($reviewTokenOrder["status"] === "completed" && empty($reviewTokenOrder["review_token"])) {
        $reviewTokenOrder["review_token"] = figurify_ensure_review_token($conn, (int) $reviewTokenOrder["order_id"]);
    }

}
unset($reviewTokenOrder);


/* latest progress update per order, shown inside the order card regardless
   of response status. one query for all orders, matched below. */

$latestProgressStmt = $conn->prepare(
    "SELECT pu.update_id, pu.order_id, pu.image_path, pu.staff_message,
            pu.customer_response, pu.revision_note, pu.staff_reply, pu.is_final
     FROM order_progress_updates pu
     JOIN orders o ON o.order_id = pu.order_id
     WHERE o.user_id = ?
     ORDER BY pu.created_at DESC, pu.update_id DESC"
);

$latestProgressStmt->bind_param("i", $user_id);
$latestProgressStmt->execute();

$latestProgressResult = $latestProgressStmt->get_result();

$latestProgressByOrder = [];

while ($pRow = $latestProgressResult->fetch_assoc()) {

    // DESC order, kaya ang unang makita per order_id ang pinaka-bago — huwag nang palitan
    if (!isset($latestProgressByOrder[$pRow["order_id"]])) {
        $latestProgressByOrder[$pRow["order_id"]] = $pRow;
    }

}

$latestProgressStmt->close();



/* get figures + images for each order */

foreach ($orders as &$order) {

    $fstmt = $conn->prepare(
        "SELECT figure_id, figure_style, product_type, size_label,
                figure_name, notes, product_price, name_fee, figure_total,
                box_addon_type, box_addon_price,
                funko_box_type, funko_box_name, funko_box_number, funko_box_color,
                hirono_blind_type, hirono_box_design, hirono_box_color, hirono_letter,
                hirono_nickname, hirono_date, hirono_blind_items, hirono_blind_items_total,
                quoted_price
         FROM order_figures
         WHERE order_id = ?"
    );

    $fstmt->bind_param("i", $order["order_id"]);
    $fstmt->execute();

    $fresult = $fstmt->get_result();

    $figures = [];

    while ($frow = $fresult->fetch_assoc()) {

        $imgStmt = $conn->prepare(
            "SELECT image_path
             FROM order_figure_images
             WHERE figure_id = ?"
        );

        $imgStmt->bind_param("i", $frow["figure_id"]);
        $imgStmt->execute();

        $imgResult = $imgStmt->get_result();

        $images = [];

        while ($irow = $imgResult->fetch_assoc()) {

            $images[] = $irow["image_path"];

        }

        $imgStmt->close();

        $frow["images"] = $images;


        /* box reference images, Hirono Blind Box only */

        $boxImgStmt = $conn->prepare(
            "SELECT image_path
             FROM order_figure_box_images
             WHERE figure_id = ?"
        );

        $boxImgStmt->bind_param("i", $frow["figure_id"]);
        $boxImgStmt->execute();

        $boxImgResult = $boxImgStmt->get_result();

        $boxImages = [];

        while ($birow = $boxImgResult->fetch_assoc()) {

            $boxImages[] = $birow["image_path"];

        }

        $boxImgStmt->close();

        $frow["box_images"] = $boxImages;


        /* blind box extras saved as JSON {item_key: price}, decode to a list for display */

        $blindItemLabels = [
            "tear_blind_paper" => "Tear Blind Paper",
            "pouch"             => "Pouch",
            "digital_art"       => "Digital Art (Soft Copy) w/ Photo Card",
        ];

        $frow["blind_items_list"] = [];

        if (!empty($frow["hirono_blind_items"])) {

            $decodedItems = json_decode($frow["hirono_blind_items"], true);

            if (is_array($decodedItems)) {

                foreach ($decodedItems as $itemKey => $itemPrice) {

                    $frow["blind_items_list"][] = [
                        "label" => $blindItemLabels[$itemKey] ?? ucwords(str_replace("_", " ", $itemKey)),
                        "price" => (float) $itemPrice,
                        "image" => hironoBlindItemImage($itemKey),
                    ];

                }

            }

        }


        $figures[] = $frow;

    }

    $fstmt->close();

    $order["figures"] = $figures;

    $order["latest_progress_update"] = $latestProgressByOrder[$order["order_id"]] ?? null;

    /* pending revision request -> show "Revision" status instead of "Processing" */

    $order["has_revision"] = (
        !empty($order["latest_progress_update"])
        && $order["latest_progress_update"]["customer_response"] === "revision"
        && !in_array(
            figurify_status_key($order["status"]),
            ["to_ship", "shipped", "completed", "cancelled"],
            true
        )
    );

}

unset($order);


/* status filter options para sa dropdown katabi ng search bar — base
   lang sa statuses na talagang meron sa orders ng customer na ito,
   para walang option na laging walang laman */

$myOrdersFilterOptions = [];

foreach ($orders as $filterOrder) {

    if ($filterOrder["has_revision"]) {
        $filterKey   = "revision";
        $filterLabel = "Revision";
    } else {
        $filterKey   = figurify_status_key($filterOrder["status"]);
        $filterLabel = statusLabel($filterOrder["status"]);
    }

    if (!isset($myOrdersFilterOptions[$filterKey])) {
        $myOrdersFilterOptions[$filterKey] = $filterLabel;
    }

}


/* payment_unavailable notice from payment-shipping.php */

$paymentNotice       = isset($_GET["notice"]) ? $_GET["notice"] : null;
$paymentNoticeOrder  = null;

if ($paymentNotice === "payment_unavailable" && isset($_GET["order_id"])) {

    $paymentNoticeOrderId = (int) $_GET["order_id"];

    foreach ($orders as $order) {
        if ((int) $order["order_id"] === $paymentNoticeOrderId) {
            $paymentNoticeOrder = $order;
            break;
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

<title>My Orders — Clay and Stuff</title>

<link rel="stylesheet" href="my-orders.css?v=4">

</head>
<body>

<?php include "../Shared/navbar.php"; ?>

<header class="hero hero-orders">
    <div class="hero-content">
        <span class="hero-small">TRACK YOUR COMMISSIONS</span>
        <h1>My Orders,<br>All in One Place</h1>
        <p>
            Here's a list of every figure you've commissioned with us.
        </p>
    </div>
</header>

<main class="orders-page">

    <?php if ($justCancelled): ?>
        <div class="order-notice order-notice-cancel">
            The order has been cancelled.
        </div>
    <?php elseif ($justSubmitted): ?>
        <div class="order-notice order-notice-success">
            Payment details submitted! Our staff will verify it shortly.
        </div>
    <?php elseif ($paymentNotice === "payment_unavailable"): ?>
        <div class="order-notice order-notice-info">
            <?php if ($paymentNoticeOrder): ?>
                Order #<?php echo (int) $paymentNoticeOrder["order_id"]; ?> can no longer be paid from here —
                its status is now
                "<?php echo htmlspecialchars(statusLabel($paymentNoticeOrder["status"])); ?>",
                so the payment page isn't available anymore.
            <?php else: ?>
                That order can no longer be paid from here — its status has already changed,
                so the payment page isn't available anymore.
            <?php endif; ?>
        </div>
    <?php elseif ($paymentNotice === "order_not_found"): ?>
        <div class="order-notice order-notice-info">
            We couldn't find that order, or it doesn't belong to your account.
        </div>
    <?php endif; ?>


    <?php if (empty($orders)): ?>

        <div class="no-orders">

            <div class="no-orders-icon">
                📦
            </div>

            <p>
                You don't have any orders yet.
            </p>

            <a href="../Commission/commission.php">
                Start a Commission →
            </a>

        </div>

    <?php else: ?>

        <div class="orders-layout">

        <!-- far left: order list -->

        <div class="orders-list-col">

            <div class="orders-list-shell">

                <div class="orders-toolbar">

                    <div class="orders-filter-dropdown" id="myOrdersFilterDropdown">

                        <button
                            type="button"
                            class="orders-filter-trigger"
                            onclick="myOrdersToggleFilter()"
                        >
                            <span id="myOrdersFilterLabel">All</span>
                            <svg class="orders-filter-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                        </button>

                        <div class="orders-filter-menu">

                            <a
                                href="javascript:void(0)"
                                class="orders-filter-option active"
                                onclick="myOrdersSetFilter(this, 'all', 'All')"
                            >
                                All
                            </a>

                            <?php foreach ($myOrdersFilterOptions as $filterKey => $filterLabel): ?>
                                <a
                                    href="javascript:void(0)"
                                    class="orders-filter-option"
                                    onclick="myOrdersSetFilter(this, '<?php echo htmlspecialchars($filterKey, ENT_QUOTES, "UTF-8"); ?>', '<?php echo htmlspecialchars($filterLabel, ENT_QUOTES, "UTF-8"); ?>')"
                                >
                                    <?php echo htmlspecialchars($filterLabel, ENT_QUOTES, "UTF-8"); ?>
                                </a>
                            <?php endforeach; ?>

                        </div>

                    </div>

                    <div class="orders-search-wrap">
                        <input
                            type="text"
                            id="myOrdersSearch"
                            class="orders-search-input"
                            placeholder="Search order # / status / figure..."
                            oninput="myOrdersApplyFilters()"
                            autocomplete="off"
                        >
                    </div>

                </div>

                <div class="orders-list-scroll" id="myOrdersScroll">

                <?php foreach ($orders as $index => $order): ?>

                    <?php
                    $orderSearchBits = [
                        "order #" . $order["order_id"],
                        (string) $order["order_id"],
                        $order["has_revision"] ? "Revision" : statusLabel($order["status"]),
                        date("F j, Y", strtotime($order["created_at"])),
                    ];

                    foreach ($order["figures"] as $searchFig) {
                        $orderSearchBits[] = $searchFig["figure_style"] ?? "";
                        $orderSearchBits[] = $searchFig["product_type"] ?? "";
                    }

                    $orderSearchData = strtolower(implode(" ", array_filter($orderSearchBits)));
                    ?>

                    <button
                        type="button"
                        class="order-list-item"
                        data-order-target="order-panel-<?php echo (int) $order["order_id"]; ?>"
                        data-order-id="<?php echo (int) $order["order_id"]; ?>"
                        data-order-status="<?php
                            echo htmlspecialchars(
                                $order["status"]
                                . "::u" . (int) ($order["latest_progress_update"]["update_id"] ?? 0)
                                . "::" . (string) ($order["latest_progress_update"]["customer_response"] ?? "")
                            );
                        ?>"
                        data-search="<?php echo htmlspecialchars($orderSearchData, ENT_QUOTES, "UTF-8"); ?>"
                        data-status="<?php echo htmlspecialchars($order["has_revision"] ? "revision" : figurify_status_key($order["status"]), ENT_QUOTES, "UTF-8"); ?>"
                        onclick="selectOrder(this)"
                    >

                        <div class="order-list-top">

                            <span class="order-list-id">
                                Order #<?php echo (int) $order["order_id"]; ?>
                            </span>

                            <?php /* show "Revision" label kapag may pending revision request,
                                     hindi galing sa order_status_helper.php dahil di talaga siya
                                     status column */ ?>

                            <span class="order-status <?php echo $order["has_revision"] ? "status-revision" : figurify_status_class($order["status"]); ?>">
                                <?php echo $order["has_revision"] ? "Revision" : htmlspecialchars(statusLabel($order["status"])); ?>
                            </span>

                        </div>

                        <div class="order-list-date">
                            Placed on
                            <?php echo date("F j, Y", strtotime($order["created_at"])); ?>
                        </div>

                        <div class="order-list-count">
                            <?php
                            $figureCount = count($order["figures"]);
                            echo $figureCount . " " . ($figureCount === 1 ? "figure" : "figures");
                            ?>
                        </div>

                    </button>

                <?php endforeach; ?>

                <div class="orders-search-empty" id="myOrdersSearchEmpty" style="display:none;">
                    No orders match your search.
                </div>

                </div>

            </div>

        </div>


        <!-- everything else: order details -->

        <div class="orders-detail-col">

        <div
            class="order-detail-placeholder active"
            id="orderDetailPlaceholder"
        >
            <div class="no-orders-icon">
                👈
            </div>

            <p>
                Select an order from the left to view its
                full details.
            </p>
        </div>

        <?php foreach ($orders as $index => $order): ?>

            <div
                class="order-card order-detail-panel"
                id="order-panel-<?php echo (int) $order["order_id"]; ?>"
            >

                <div class="detail-grid">

                    <!-- main: order info + figure viewer -->

                    <div class="detail-main">

                        <!-- ---- ORDER INFORMATION CARD ---- -->

                        <div class="card order-info">

                            <div class="order-info-title">📝 Order Information</div>

                            <div class="order-info-grid">

                                <?php if (!empty($_SESSION["full_name"])): ?>
                                    <div class="info-chip">
                                        <span class="icon">👤</span>
                                        <div class="txt">
                                            <span class="dt">Customer Name</span>
                                            <span class="dd"><?php echo htmlspecialchars($_SESSION["full_name"]); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($_SESSION["email"])): ?>
                                    <div class="info-chip">
                                        <span class="icon">✉️</span>
                                        <div class="txt">
                                            <span class="dt">Email</span>
                                            <span class="dd"><?php echo htmlspecialchars($_SESSION["email"]); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="info-chip">
                                    <span class="icon">📅</span>
                                    <div class="txt">
                                        <span class="dt">Ordered On</span>
                                        <span class="dd"><?php echo date("F j, Y", strtotime($order["created_at"])); ?></span>
                                    </div>
                                </div>

                                <div class="info-chip">
                                    <span class="icon">
                                        <?php echo ($order["order_method"] === "create_style") ? "✨" : "📷"; ?>
                                    </span>
                                    <div class="txt">
                                        <span class="dt">Order Method</span>
                                        <span class="dd"><?php echo orderMethodLabel($order["order_method"]); ?></span>
                                    </div>
                                </div>

                                <div class="info-chip">
                                    <span class="icon">
                                        <?php echo ($order["order_type"] === "rush") ? "⚡" : "🌷"; ?>
                                    </span>
                                    <div class="txt">
                                        <span class="dt">Order Type</span>
                                        <span class="dd">
                                            <?php echo ($order["order_type"] === "rush") ? "Rush Order" : "Non-Rush Order"; ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="info-chip">
                                    <span class="icon">📅</span>
                                    <div class="txt">
                                        <span class="dt">Booking Date</span>
                                        <span class="dd"><?php echo date("F j, Y", strtotime($order["booking_date"])); ?></span>
                                    </div>
                                </div>

                            </div>

                        </div>
                        <!-- /.order-info -->


                        <!-- ---- FIGURE VIEWER CARD ---- -->

                        <div class="card figure-viewer-card">

                            <?php $figureCountInOrder = count($order["figures"]); ?>

                            <div class="order-figures-title">
                                Figures in This Order
                                (<?php echo $figureCountInOrder; ?>)
                            </div>

                            <?php if ($figureCountInOrder > 1): ?>

                                <!-- figure tabs, lalabas lang kapag 2+ figures -->

                                <div class="figure-tabs">

                                    <?php foreach ($order["figures"] as $fIndex => $tabFig): ?>

                                        <button
                                            type="button"
                                            class="figure-tab-btn<?php echo ($fIndex === 0) ? " active" : ""; ?>"
                                            data-figure-target="figure-panel-<?php echo (int) $order["order_id"]; ?>-<?php echo $fIndex; ?>"
                                            onclick="selectFigure(this)"
                                        >
                                            Figure #<?php echo $fIndex + 1; ?>
                                        </button>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>

                            <div class="order-figures">

                                <?php foreach ($order["figures"] as $index => $fig): ?>

                                    <?php
                                    $hasSize = figureHasSize($fig);

                                    $styleImg   = figureStyleImage($fig["figure_style"]);
                                    $productImg = productTypeImage($fig["figure_style"], $fig["product_type"]);

                                    ?>

                                    <div
                                        class="order-figure<?php echo ($index === 0) ? " active" : ""; ?>"
                                        id="figure-panel-<?php echo (int) $order["order_id"]; ?>-<?php echo $index; ?>"
                                    >

                                        <div class="viewer-grid<?php echo figurify_dressup_for_figure($fig) !== null ? " dressup-grid" : ""; ?>">

                                            <!-- left: figure details, notes, reference images -->

                                            <div class="viewer-column">

                                                <div class="details-title">
                                                    🧍 Figure #<?php echo $index + 1; ?> Details
                                                </div>

                                                <div class="detail-item-list">

                                                    <!-- figure style, always free -->
                                                    <div class="detail-item-card">

                                                        <?php if ($styleImg): ?>
                                                            <img
                                                                class="detail-item-image"
                                                                src="<?php echo $siteBase . "Image/" . htmlspecialchars($styleImg); ?>"
                                                                alt="<?php echo htmlspecialchars($fig["figure_style"]); ?>"
                                                            >
                                                        <?php else: ?>
                                                            <div class="detail-item-image detail-item-noimage">🧍</div>
                                                        <?php endif; ?>

                                                        <div class="detail-item-body">
                                                            <span class="detail-item-label">Figure Style</span>
                                                            <span class="detail-item-value"><?php echo htmlspecialchars($fig["figure_style"]); ?></span>
                                                        </div>

                                                    </div>

                                                    <!-- product type, presyo lang dito kapag walang size -->
                                                    <div class="detail-item-card">

                                                        <?php if ($productImg): ?>
                                                            <img
                                                                class="detail-item-image"
                                                                src="<?php echo $siteBase . "Image/" . htmlspecialchars($productImg); ?>"
                                                                alt="<?php echo htmlspecialchars($fig["product_type"]); ?>"
                                                            >
                                                        <?php else: ?>
                                                            <div class="detail-item-image detail-item-noimage">📦</div>
                                                        <?php endif; ?>

                                                        <div class="detail-item-body">
                                                            <span class="detail-item-label">Product Type</span>
                                                            <span class="detail-item-value"><?php echo htmlspecialchars($fig["product_type"]); ?></span>
                                                        </div>

                                                        <div class="detail-item-price<?php echo $hasSize ? " muted" : ""; ?>">
                                                            <?php echo $hasSize ? "—" : "₱" . number_format($fig["product_price"], 2); ?>
                                                        </div>

                                                    </div>

                                                    <!-- size -->
                                                    <div class="detail-item-card">

                                                        <div class="detail-item-image detail-item-noimage">📏</div>

                                                        <div class="detail-item-body">
                                                            <span class="detail-item-label">Size</span>
                                                            <span class="detail-item-value">
                                                                <?php echo $hasSize ? htmlspecialchars($fig["size_label"]) : "No size required"; ?>
                                                            </span>
                                                        </div>

                                                        <div class="detail-item-price<?php echo $hasSize ? "" : " muted"; ?>">
                                                            <?php echo $hasSize ? "₱" . number_format($fig["product_price"], 2) : "—"; ?>
                                                        </div>

                                                    </div>

                                                    <!-- figure name, presyo dito ay name fee -->
                                                    <?php if (figurify_dressup_for_figure($fig) === null): /* walang Figure Name sa Dress Up */ ?>
                                                    <div class="detail-item-card">

                                                        <div class="detail-item-image detail-item-noimage">🏷️</div>

                                                        <div class="detail-item-body">
                                                            <span class="detail-item-label">Figure Name</span>
                                                            <span class="detail-item-value"><?php echo htmlspecialchars($fig["figure_name"]); ?></span>
                                                        </div>

                                                        <div class="detail-item-price<?php echo ((float) $fig["name_fee"] > 0) ? "" : " muted"; ?>">
                                                            <?php echo ((float) $fig["name_fee"] > 0) ? "₱" . number_format($fig["name_fee"], 2) : "Free"; ?>
                                                        </div>

                                                    </div>
                                                    <?php endif; ?>

                                                </div>
                                                <!-- /.detail-item-list -->

                                                <?php if (!empty($fig["notes"])): ?>
                                                    <div class="notes-box">
                                                        <strong>Note to Artist</strong>
                                                        <p><?php echo nl2br(htmlspecialchars($fig["notes"])); ?></p>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- uploaded reference photos -->

                                                <?php if (figurify_dressup_for_figure($fig) !== null): ?>
                                                    <?php figurify_render_dressup_design($fig, "customer"); ?>
                                                <?php else: ?>
                                                <div class="reference-section">

                                                    <div class="gallery-label">
                                                        Reference Images
                                                        <span class="gallery-label-count">
                                                            <?php
                                                            $imgCount = count($fig["images"]);
                                                            echo "(" . $imgCount . " " . ($imgCount === 1 ? "photo" : "photos") . ")";
                                                            ?>
                                                        </span>
                                                    </div>

                                                    <?php if (!empty($fig["images"])): ?>

                                                        <div class="gallery-row">

                                                            <?php foreach ($fig["images"] as $imgIndex => $imgPath): ?>

                                                                <img
                                                                    src="<?php echo $siteBase . htmlspecialchars($imgPath); ?>"
                                                                    alt="<?php echo htmlspecialchars($fig["figure_name"]); ?> — photo <?php echo $imgIndex + 1; ?>"
                                                                    class="gallery-thumb"
                                                                    onclick="openImageLightbox('<?php echo $siteBase . htmlspecialchars($imgPath, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($fig["figure_name"], ENT_QUOTES); ?>')"
                                                                >

                                                            <?php endforeach; ?>

                                                        </div>

                                                    <?php else: ?>

                                                        <div class="no-image">
                                                            No Image
                                                        </div>

                                                    <?php endif; ?>

                                                </div>
                                                <?php endif; ?>

                                            </div>
                                            <!-- /.viewer-column (figure details) -->


                                            <!-- right: custom box details, only if may box add-on -->

                                            <?php if ($fig["box_addon_type"] === "funko_box"): ?>

                                                <div class="viewer-column">

                                                    <div class="box-card">

                                                        <div class="box-card-title">🎁 Custom Box</div>

                                                        <div class="detail-item-list">

                                                            <div class="detail-item-card">

                                                                <div class="detail-item-image detail-item-noimage">🎀</div>

                                                                <div class="detail-item-body">
                                                                    <span class="detail-item-label">Box Type</span>
                                                                    <span class="detail-item-value">
                                                                        <?php echo $fig["funko_box_type"] === "solo" ? "Solo Box" : "Couple Box"; ?>
                                                                    </span>
                                                                </div>

                                                                <div class="detail-item-price">
                                                                    ₱<?php echo number_format($fig["box_addon_price"], 2); ?>
                                                                </div>

                                                            </div>

                                                        </div>
                                                        <!-- /.detail-item-list -->

                                                        <div class="box-detail-grid">

                                                            <div>
                                                                <span>Name on Box</span>
                                                                <span><?php echo htmlspecialchars($fig["funko_box_name"]); ?></span>
                                                            </div>

                                                            <div>
                                                                <span>Box Number</span>
                                                                <span><?php echo htmlspecialchars($fig["funko_box_number"]); ?></span>
                                                            </div>

                                                            <div>
                                                                <span>Box Color</span>
                                                                <span><?php echo htmlspecialchars($fig["funko_box_color"]); ?></span>
                                                            </div>

                                                        </div>

                                                    </div>

                                                </div>

                                            <?php elseif ($fig["box_addon_type"] === "hirono_blind_box"): ?>

                                                <?php
                                                $blindImg  = hironoBlindTypeImage($fig["hirono_blind_type"]);
                                                $designImg = hironoBoxDesignImage($fig["hirono_box_design"]);
                                                ?>

                                                <div class="viewer-column">

                                                    <div class="box-card">

                                                        <div class="box-card-title">🎁 Custom Box</div>

                                                        <div class="detail-item-list">

                                                            <!-- blind box type -->
                                                            <div class="detail-item-card">

                                                                <img
                                                                    class="detail-item-image"
                                                                    src="<?php echo $siteBase . "Image/" . htmlspecialchars($blindImg); ?>"
                                                                    alt="Blind Box Type"
                                                                >

                                                                <div class="detail-item-body">
                                                                    <span class="detail-item-label">Box Type</span>
                                                                    <span class="detail-item-value">
                                                                        <?php echo $fig["hirono_blind_type"] === "regular" ? "Regular Blind Box" : "Blind Box Set"; ?>
                                                                    </span>
                                                                </div>

                                                                <div class="detail-item-price">
                                                                    ₱<?php echo number_format(hironoBaseBoxPrice($fig), 2); ?>
                                                                </div>

                                                            </div>

                                                            <!-- box design, walang sariling presyo -->
                                                            <?php if (!empty($fig["hirono_box_design"])): ?>
                                                                <div class="detail-item-card">

                                                                    <?php if ($designImg): ?>
                                                                        <img
                                                                            class="detail-item-image"
                                                                            src="<?php echo $siteBase . "Image/" . htmlspecialchars($designImg); ?>"
                                                                            alt="<?php echo htmlspecialchars(hironoBoxDesignLabel($fig["hirono_box_design"])); ?>"
                                                                        >
                                                                    <?php else: ?>
                                                                        <div class="detail-item-image detail-item-noimage">🎁</div>
                                                                    <?php endif; ?>

                                                                    <div class="detail-item-body">
                                                                        <span class="detail-item-label">Box Design</span>
                                                                        <span class="detail-item-value"><?php echo htmlspecialchars(hironoBoxDesignLabel($fig["hirono_box_design"])); ?></span>
                                                                    </div>

                                                                    <div class="detail-item-price muted">—</div>

                                                                </div>
                                                            <?php endif; ?>

                                                            <!-- optional extras, may presyo bawat isa -->
                                                            <?php foreach ($fig["blind_items_list"] as $extra): ?>
                                                                <div class="detail-item-card">

                                                                    <?php if (!empty($extra["image"])): ?>
                                                                        <img
                                                                            class="detail-item-image"
                                                                            src="<?php echo $siteBase . "Image/" . htmlspecialchars($extra["image"]); ?>"
                                                                            alt="<?php echo htmlspecialchars($extra["label"]); ?>"
                                                                        >
                                                                    <?php else: ?>
                                                                        <div class="detail-item-image detail-item-noimage">✨</div>
                                                                    <?php endif; ?>

                                                                    <div class="detail-item-body">
                                                                        <span class="detail-item-label">Optional Extra</span>
                                                                        <span class="detail-item-value"><?php echo htmlspecialchars($extra["label"]); ?></span>
                                                                    </div>

                                                                    <div class="detail-item-price">
                                                                        ₱<?php echo number_format($extra["price"], 2); ?>
                                                                    </div>

                                                                </div>
                                                            <?php endforeach; ?>

                                                        </div>
                                                        <!-- /.detail-item-list -->

                                                        <div class="box-detail-grid">

                                                            <div>
                                                                <span>Box Color</span>
                                                                <span><?php echo htmlspecialchars($fig["hirono_box_color"]); ?></span>
                                                            </div>

                                                            <div>
                                                                <span>Nickname</span>
                                                                <span><?php echo htmlspecialchars($fig["hirono_nickname"]); ?></span>
                                                            </div>

                                                            <div>
                                                                <span>Date</span>
                                                                <span>
                                                                    <?php
                                                                    echo !empty($fig["hirono_date"])
                                                                        ? date("F j", strtotime($fig["hirono_date"]))
                                                                        : htmlspecialchars($fig["hirono_date"]);
                                                                    ?>
                                                                </span>
                                                            </div>

                                                            <div class="full-row">
                                                                <span>Letter</span>
                                                                <span><?php echo nl2br(htmlspecialchars($fig["hirono_letter"])); ?></span>
                                                            </div>

                                                        </div>

                                                        <?php if (!empty($fig["box_images"])): ?>

                                                            <div class="box-reference-section">

                                                                <div class="gallery-label">Box Reference Images</div>

                                                                <div class="gallery-row">

                                                                    <?php foreach ($fig["box_images"] as $boxImgIndex => $boxImgPath): ?>

                                                                        <img
                                                                            src="<?php echo $siteBase . htmlspecialchars($boxImgPath); ?>"
                                                                            alt="Box reference photo <?php echo $boxImgIndex + 1; ?>"
                                                                            class="gallery-thumb"
                                                                            onclick="openImageLightbox('<?php echo $siteBase . htmlspecialchars($boxImgPath, ENT_QUOTES); ?>', 'Box Reference Image')"
                                                                        >

                                                                    <?php endforeach; ?>

                                                                </div>

                                                            </div>

                                                        <?php endif; ?>

                                                    </div>

                                                </div>

                                            <?php else: ?>

                                                <!-- right: no custom box, own column para di mag-stretch yung details -->

                                                <div class="viewer-column">

                                                    <div class="box-card box-card-empty">

                                                        <div class="box-card-title">🎁 Custom Box</div>

                                                        <div class="box-empty-state">

                                                            <div class="box-empty-icon">📦</div>

                                                            <p class="box-empty-title">
                                                                No Custom Box Added
                                                            </p>

                                                            <p class="box-empty-text">
                                                                This figure was ordered without a
                                                                custom box add-on.
                                                            </p>

                                                        </div>

                                                    </div>

                                                </div>

                                            <?php endif; ?>


                                            <?php if (figurify_dressup_for_figure($fig) !== null): ?>
                                                <!-- DRESS UP: 3D figure, katabi ng Figure Details -->
                                                <div class="viewer-column dressup-3d-column">
                                                    <?php figurify_render_dressup_viewer($fig); ?>
                                                </div>
                                            <?php endif; ?>

                                        </div>
                                        <!-- /.viewer-grid -->

                                    </div>
                                    <!-- /.order-figure -->

                                <?php endforeach; ?>

                            </div>
                            <!-- /.order-figures -->

                        </div>
                        <!-- /.figure-viewer-card -->


                        <?php
                        $hasPaymentInfo = !empty($order["payment_reference"])
                            || !empty($order["payment_proof"])
                            || !empty($order["paid_at"])
                            || !empty($order["shipping_name"])
                            || !empty($order["shipping_address"])
                            || !empty($order["shipping_contact"]);

                        $hasRefundInfo = !empty($order["refund_account_name"])
                            || !empty($order["refund_account_number"])
                            || !empty($order["refund_method"]);
                        ?>

                        <?php if ($hasPaymentInfo || $hasRefundInfo): ?>

                            <!-- payment / refund card, separate container from figure details above -->

                            <div class="card customer-payment-card">

                                <?php if ($hasPaymentInfo): ?>

                                    <div class="customer-payment-details">

                                        <div class="customer-payment-details-title">
                                            Payment &amp; Shipping Details
                                        </div>

                                        <div class="customer-payment-details-grid">

                                            <span>
                                                <strong>Ship To</strong>
                                                <?php echo htmlspecialchars((string) ($order["shipping_name"] ?? "")); ?>
                                            </span>

                                            <span>
                                                <strong>Address</strong>
                                                <?php echo htmlspecialchars((string) ($order["shipping_address"] ?? "")); ?>
                                            </span>

                                            <span>
                                                <strong>Contact</strong>
                                                <?php echo htmlspecialchars((string) ($order["shipping_contact"] ?? "")); ?>
                                            </span>

                                            <?php if (!empty($order["courier"])): ?>
                                                <span>
                                                    <strong>Courier</strong>
                                                    <?php echo htmlspecialchars(strtoupper((string) $order["courier"])); ?>
                                                </span>
                                            <?php endif; ?>

                                            <span>
                                                <strong>Reference No.</strong>
                                                <?php echo htmlspecialchars((string) ($order["payment_reference"] ?? "")); ?>
                                            </span>

                                            <?php if (!empty($order["paid_at"])): ?>
                                                <span>
                                                    <strong>Submitted</strong>
                                                    <?php echo date("F j, Y — g:i A", strtotime($order["paid_at"])); ?>
                                                </span>
                                            <?php endif; ?>

                                        </div>

                                        <?php if (!empty($order["payment_proof"])): ?>

                                            <div class="customer-payment-proof">

                                                <div class="customer-payment-proof-title">
                                                    Proof of Payment
                                                </div>

                                                <img
                                                    src="<?php echo $siteBase . htmlspecialchars($order["payment_proof"]); ?>"
                                                    alt="Proof of payment for order #<?php echo (int) $order["order_id"]; ?>"
                                                    class="customer-payment-proof-thumb"
                                                    onclick="openImageLightbox('<?php echo $siteBase . htmlspecialchars($order["payment_proof"], ENT_QUOTES); ?>', 'Proof of Payment')"
                                                >

                                            </div>

                                        <?php endif; ?>

                                    </div>

                                <?php endif; ?>

                                <?php if ($hasRefundInfo): ?>

                                    <!-- refund / return details -->

                                    <div class="customer-payment-details">

                                        <div class="customer-payment-details-title">
                                            Refund / Return Details
                                        </div>

                                        <div class="customer-payment-details-grid">

                                            <span>
                                                <strong>Account Name</strong>
                                                <?php echo htmlspecialchars((string) ($order["refund_account_name"] ?? "")); ?>
                                            </span>

                                            <span>
                                                <strong>Account Number</strong>
                                                <?php echo htmlspecialchars((string) ($order["refund_account_number"] ?? "")); ?>
                                            </span>

                                            <span>
                                                <strong>Preferred Bank/Method</strong>
                                                <?php echo htmlspecialchars((string) ($order["refund_method"] ?? "")); ?>
                                            </span>

                                        </div>

                                    </div>

                                <?php endif; ?>

                            </div>
                            <!-- /.customer-payment-card -->

                        <?php endif; ?>


                    </div>
                    <!-- /.detail-main -->


                    <!-- side: action box + order summary -->

                    <div class="detail-side">

                        <div class="card summary-card">

                            <?php if (!empty($order["latest_progress_update"])): ?>

                                <?php $ppu = $order["latest_progress_update"]; ?>

                                <!-- latest progress update, always shown even after responded -->

                                <?php
                                $ppuIsPending = ($ppu["customer_response"] === "pending") && empty($ppu["is_final"]);

                                $ppuBoxClass = "progress-update-box";
                                if (!empty($ppu["is_final"])) {
                                    $ppuBoxClass .= " progress-update-final";
                                } else {
                                    $ppuBoxClass .= " progress-update-" . htmlspecialchars($ppu["customer_response"]);
                                }
                                ?>

                                <div class="<?php echo $ppuBoxClass; ?>" id="progress-update-<?php echo (int) $order["order_id"]; ?>">

                                    <div class="progress-update-title">
                                        <?php
                                        if (!empty($ppu["is_final"])) {
                                            echo "🎉 Your Figure is Done!";
                                        } elseif ($ppu["customer_response"] === "pending") {
                                            echo "📸 New Progress Update";
                                        } elseif ($ppu["customer_response"] === "approved") {
                                            echo "✅ Progress Update — Approved";
                                        } elseif ($ppu["customer_response"] === "revision") {
                                            echo "🔄 Revision Requested";
                                        } elseif ($ppu["customer_response"] === "in_progress") {
                                            echo "🛠️ Revision In Progress";
                                        } elseif ($ppu["customer_response"] === "declined") {
                                            echo "⚠️ About Your Revision Request";
                                        }
                                        ?>
                                    </div>

                                    <img
                                        class="progress-update-image"
                                        src="../<?php echo htmlspecialchars($ppu["image_path"]); ?>"
                                        alt="Order progress photo"
                                        onclick="openImageLightbox('../<?php echo htmlspecialchars($ppu["image_path"], ENT_QUOTES); ?>', 'Progress Update')"
                                    >

                                    <?php if (!empty($ppu["staff_message"])): ?>
                                        <p class="progress-update-caption">
                                            <?php echo nl2br(htmlspecialchars($ppu["staff_message"])); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if ($ppuIsPending): ?>

                                        <!-- no response yet, hinihintay pa ang customer -->

                                        <p class="progress-update-question">
                                            Are you happy with this, or would you like a revision?
                                        </p>

                                        <form
                                            method="POST"
                                            action="respond_progress.php"
                                            class="progress-update-form"
                                            id="progressUpdateForm-<?php echo (int) $order["order_id"]; ?>"
                                        >

                                            <input type="hidden" name="update_id" value="<?php echo (int) $ppu["update_id"]; ?>">
                                            <input type="hidden" name="order_id" value="<?php echo (int) $order["order_id"]; ?>">
                                            <input
                                                type="hidden"
                                                name="response"
                                                id="progressResponseField-<?php echo (int) $order["order_id"]; ?>"
                                                value=""
                                            >

                                            <div class="progress-update-buttons" id="progressButtonsRow-<?php echo (int) $order["order_id"]; ?>">

                                                <button
                                                    type="button"
                                                    class="btn-progress-good"
                                                    onclick="submitProgressResponse(<?php echo (int) $order["order_id"]; ?>, 'approved')"
                                                >
                                                    ✅ It's Good!
                                                </button>

                                                <button
                                                    type="button"
                                                    class="btn-progress-revise"
                                                    onclick="showProgressRevisionBox(<?php echo (int) $order["order_id"]; ?>)"
                                                >
                                                    🔄 Request a Revision
                                                </button>

                                            </div>

                                            <div
                                                class="progress-update-revision-box"
                                                id="progressRevisionBox-<?php echo (int) $order["order_id"]; ?>"
                                                style="display:none;"
                                            >

                                                <label for="progressRevisionNote-<?php echo (int) $order["order_id"]; ?>">
                                                    What would you like changed?
                                                </label>

                                                <textarea
                                                    name="revision_note"
                                                    id="progressRevisionNote-<?php echo (int) $order["order_id"]; ?>"
                                                    rows="3"
                                                    placeholder="Tell us what to revise..."
                                                ></textarea>

                                                <button
                                                    type="button"
                                                    class="btn-progress-send-revision"
                                                    onclick="submitProgressResponse(<?php echo (int) $order["order_id"]; ?>, 'revision')"
                                                >
                                                    Send Revision Request
                                                </button>

                                            </div>

                                        </form>

                                    <?php elseif (!empty($ppu["is_final"])): ?>

                                        <!-- final photo, auto-approved -->

                                        <p class="progress-update-status progress-status-approved">
                                            Your figure is complete and ready to ship! 🎉
                                        </p>

                                    <?php elseif ($ppu["customer_response"] === "approved"): ?>

                                        <!-- already approved -->

                                        <p class="progress-update-status progress-status-approved">
                                            ✅ You approved this update.
                                        </p>

                                    <?php elseif ($ppu["customer_response"] === "revision"): ?>

                                        <!-- revision requested, hinihintay staff accept/decline -->

                                        <p class="progress-update-status progress-status-revision">
                                            🔄 You requested a revision<?php echo !empty($ppu["revision_note"]) ? ": “" . nl2br(htmlspecialchars($ppu["revision_note"])) . "”" : ""; ?>
                                            — waiting for our staff to respond.
                                        </p>

                                    <?php elseif ($ppu["customer_response"] === "in_progress"): ?>

                                        <!-- revision accepted, ginagawa na -->

                                        <p class="progress-update-status progress-status-in-progress">
                                            🛠️ Our staff accepted your revision request and is
                                            working on it. We'll send a new photo once it's ready.
                                        </p>

                                    <?php elseif ($ppu["customer_response"] === "declined"): ?>

                                        <!-- revision request declined -->

                                        <p class="progress-update-status progress-status-declined">
                                            ⚠️
                                            <?php
                                            echo !empty($ppu["staff_reply"])
                                                ? nl2br(htmlspecialchars($ppu["staff_reply"]))
                                                : "Our staff wasn't able to make this revision.";
                                            ?>
                                        </p>

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>

                            <?php if ($order["status"] === "quoted"): ?>

                                <!-- quoted, customer has 2 days to continue or cancel -->

                                <div class="action-box action-quoted">

                                    <p class="action-title">
                                        We sent you a quotation for this order!
                                    </p>

                                    <p class="action-text">
                                        Total price: ₱<?php echo number_format($order["total_amount"], 2); ?>.
                                        Please confirm if you'd like to continue with this
                                        order.
                                        <?php if (!empty($order["quote_expires_at"])): ?>
                                            <span class="action-deadline">
                                                You have until
                                                <?php echo date("F j, Y — g:i A", strtotime($order["quote_expires_at"])); ?>
                                                to respond, or this order will be
                                                cancelled automatically.
                                            </span>
                                        <?php endif; ?>
                                    </p>

                                    <div class="action-buttons">

                                        <form method="POST" action="confirm_order.php">
                                            <input type="hidden" name="order_id" value="<?php echo (int) $order["order_id"]; ?>">
                                            <input type="hidden" name="action" value="continue">
                                            <button
                                                type="submit"
                                                class="btn-continue"
                                            >
                                                Continue Order
                                            </button>
                                        </form>

                                        <form
                                            method="POST"
                                            action="confirm_order.php"
                                            onsubmit="return confirm('Are you sure you want to cancel this order? This cannot be undone.');"
                                        >
                                            <input type="hidden" name="order_id" value="<?php echo (int) $order["order_id"]; ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" class="btn-cancel">
                                                Cancel
                                            </button>
                                        </form>

                                    </div>

                                </div>

                            <?php elseif ($order["status"] === "awaiting_payment"): ?>

                                <!-- continued, kulang na lang sa payment/shipping form -->

                                <div class="action-box action-awaiting">

                                    <p class="action-title">
                                        Almost there! Please complete your payment and
                                        shipping details.
                                    </p>

                                    <div class="action-buttons">
                                        <a
                                            href="payment-shipping.php?order_id=<?php echo (int) $order["order_id"]; ?>"
                                            class="btn-continue btn-link"
                                        >
                                            Proceed to Payment
                                        </a>

                                        <form
                                            method="POST"
                                            action="confirm_order.php"
                                            onsubmit="return confirm('Are you sure you want to cancel this order? This cannot be undone.');"
                                        >
                                            <input type="hidden" name="order_id" value="<?php echo (int) $order["order_id"]; ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" class="btn-cancel">
                                                Cancel
                                            </button>
                                        </form>
                                    </div>

                                </div>

                            <?php elseif ($order["status"] === "to_verify"): ?>

                                <!-- awaiting staff verification of payment -->

                                <div class="action-box action-verify">

                                    <p class="action-title">
                                        Payment submitted — waiting for staff verification.
                                    </p>

                                    <p class="action-text">
                                        We'll notify you here as soon as it's verified.
                                    </p>

                                </div>

                            <?php elseif ($order["status"] === "awaiting_balance"): ?>

                                <!-- natitirang balance na dapat bayaran bago ma-ship out -->

                                <div class="action-box action-awaiting">

                                    <p class="action-title">
                                        A remaining balance of ₱<?php echo number_format((float) $order["balance_to_pay"], 2); ?>
                                        is due before we can ship your order.
                                    </p>

                                    <div class="action-buttons">
                                        <a
                                            href="pay-balance.php?order_id=<?php echo (int) $order["order_id"]; ?>"
                                            class="btn-continue btn-link"
                                        >
                                            Pay Balance →
                                        </a>
                                    </div>

                                </div>

                            <?php elseif ($order["status"] === "for_balance_approval"): ?>

                                <!-- diretso sa owner ang balance payment, walang staff-verification step -->

                                <div class="action-box action-verify">

                                    <p class="action-title">
                                        Balance payment submitted — waiting for the owner's approval.
                                    </p>

                                    <p class="action-text">
                                        Your order will be marked ready to ship once this is approved.
                                    </p>

                                </div>

                            <?php elseif ($order["status"] === "completed"): ?>

                                <!-- completed, pwede nang mag-review -->

                                <?php
                                $orderReview = $reviewsByOrder[(int) $order["order_id"]] ?? null;
                                $reviewToken = $order["review_token"] ?? "";
                                ?>

                                <div class="action-box action-completed">

                                    <?php if ($orderReview): ?>

                                        <p class="action-title">
                                            Thanks for your review!
                                            <?php echo str_repeat("★", (int) $orderReview["rating"]) . str_repeat("☆", 5 - (int) $orderReview["rating"]); ?>
                                        </p>

                                        <?php if ($reviewToken !== ""): ?>
                                            <div class="action-buttons">
                                                <a
                                                    href="submit_review.php?order_id=<?php echo (int) $order["order_id"]; ?>&token=<?php echo urlencode($reviewToken); ?>"
                                                    class="btn-continue btn-link"
                                                >
                                                    View My Review
                                                </a>
                                            </div>
                                        <?php endif; ?>

                                    <?php else: ?>

                                        <p class="action-title">
                                            This order is complete — how was your figure?
                                        </p>

                                        <p class="action-text">
                                            Leave a rating, a few words, and (optionally) a
                                            photo. You can also do this anytime from the
                                            link we emailed you.
                                        </p>

                                        <?php if ($reviewToken !== ""): ?>
                                            <div class="action-buttons">
                                                <a
                                                    href="submit_review.php?order_id=<?php echo (int) $order["order_id"]; ?>&token=<?php echo urlencode($reviewToken); ?>"
                                                    class="btn-continue btn-link"
                                                >
                                                    Leave a Review
                                                </a>
                                            </div>
                                        <?php endif; ?>

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>


                            <div class="summary-title">Order Summary</div>

                            <div class="summary-lines">

                                <?php
                                /* Bawat figure na may quotation (quoted_price hindi NULL) — "Figure #N" row */
                                foreach ($order["figures"] as $bIndex => $bFig):

                                    if ($bFig["quoted_price"] === null) {
                                        continue;
                                    }
                                ?>

                                    <div class="summary-line">
                                        <span class="label-wrap">
                                            <span class="icon">🧍</span>
                                            Figure #<?php echo $bIndex + 1; ?>
                                        </span>
                                        <span>₱<?php echo number_format((float) $bFig["quoted_price"], 2); ?></span>
                                    </div>

                                <?php endforeach; ?>

                                <?php if ($order["order_type"] === "rush" && (float) $order["rush_fee"] > 0): ?>

                                    <div class="summary-line">
                                        <span class="label-wrap">
                                            <span class="icon">⚡</span>
                                            Rush Fee
                                        </span>
                                        <span>₱<?php echo number_format($order["rush_fee"], 2); ?></span>
                                    </div>

                                <?php endif; ?>

                            </div>
                            <!-- /.summary-lines -->

                            <div class="summary-divider"></div>

                            <div class="summary-total">
                                <span class="label">
                                    <?php
                                    /* "Estimated" habang "pending", final na once may quotation */
                                    echo ($order["status"] === "pending")
                                        ? "Estimated Total Price"
                                        : "Total Price";
                                    ?>
                                </span>
                                <span class="amount">₱<?php echo number_format($order["total_amount"], 2); ?></span>
                            </div>

                            <?php if ($order["status"] === "quoted" && !empty($order["quote_expires_at"])): ?>

                                <div class="expiry-note">
                                    <span>⏳</span>
                                    <span>
                                        Please respond by
                                        <?php echo date("F j, Y — g:i A", strtotime($order["quote_expires_at"])); ?>,
                                        or this order will be cancelled automatically.
                                    </span>
                                </div>

                            <?php endif; ?>

                        </div>
                        <!-- /.summary-card -->

                    </div>
                    <!-- /.detail-side -->

                </div>
                <!-- /.detail-grid -->

            </div>
            <!-- /.order-detail-panel -->

        <?php endforeach; ?>

        </div>
        <!-- /.orders-detail-col -->

        </div>
        <!-- /.orders-layout -->

    <?php endif; ?>

</main>



<!-- image lightbox, click photo to enlarge -->

<div
    class="image-lightbox"
    id="imageLightbox"
    onclick="closeImageLightbox(event)"
>

    <div class="image-lightbox-content">

        <button
            type="button"
            class="image-lightbox-close"
            onclick="closeImageLightbox()"
            aria-label="Close"
        >
            ✕
        </button>

        <img
            id="lightboxImage"
            src=""
            alt="Enlarged figure photo"
        >

        <div
            class="image-lightbox-label"
            id="lightboxLabel"
        ></div>

    </div>

</div>


<script>

/* client-side search + status filter lang, walang extra request sa server */

var myOrdersStatusFilter = "all";

function myOrdersApplyFilters() {

    var searchInput = document.getElementById("myOrdersSearch");
    var q = searchInput ? searchInput.value.trim().toLowerCase() : "";

    var items = document.querySelectorAll("#myOrdersScroll .order-list-item");
    var visibleCount = 0;

    items.forEach(function (item) {

        var matchesSearch = (item.dataset.search || "").indexOf(q) !== -1;
        var matchesStatus = (myOrdersStatusFilter === "all") || (item.dataset.status === myOrdersStatusFilter);
        var matches = matchesSearch && matchesStatus;

        item.style.display = matches ? "" : "none";

        if (matches) {
            visibleCount++;
        }

    });

    var emptyMessage = document.getElementById("myOrdersSearchEmpty");

    if (emptyMessage) {
        emptyMessage.style.display = (visibleCount === 0 && items.length > 0) ? "" : "none";
    }

}

/* keeping the old name working too, in case may ibang tumatawag pa dito */
function filterMyOrders() {
    myOrdersApplyFilters();
}

/* status filter dropdown — bukas/sara, isa lang bukas sa isang pagkakataon */

function myOrdersToggleFilter() {

    var dropdown = document.getElementById("myOrdersFilterDropdown");

    if (!dropdown) {
        return;
    }

    var isOpen = dropdown.classList.contains("open");

    dropdown.classList.toggle("open", !isOpen);

}

function myOrdersSetFilter(el, value, label) {

    myOrdersStatusFilter = value;

    var labelEl = document.getElementById("myOrdersFilterLabel");
    if (labelEl) {
        labelEl.textContent = label;
    }

    document.querySelectorAll("#myOrdersFilterDropdown .orders-filter-option").forEach(function (opt) {
        opt.classList.toggle("active", opt === el);
    });

    var dropdown = document.getElementById("myOrdersFilterDropdown");
    if (dropdown) {
        dropdown.classList.remove("open");
    }

    myOrdersApplyFilters();

}

document.addEventListener("click", function (event) {

    var dropdown = document.getElementById("myOrdersFilterDropdown");

    if (dropdown && dropdown.classList.contains("open") && !dropdown.contains(event.target)) {
        dropdown.classList.remove("open");
    }

});

document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {
        var dropdown = document.getElementById("myOrdersFilterDropdown");
        if (dropdown) {
            dropdown.classList.remove("open");
        }
    }

});


/* switch which order's details show on the right */

function selectOrder(button) {

    document
        .querySelectorAll(".order-list-item")
        .forEach(function (item) {
            item.classList.remove("active");
        });

    button.classList.add("active");

    var placeholder = document.getElementById("orderDetailPlaceholder");

    if (placeholder) {
        placeholder.classList.remove("active");
    }

    document
        .querySelectorAll(".order-detail-panel")
        .forEach(function (panel) {
            panel.classList.remove("active");
        });

    var target = document.getElementById(button.dataset.orderTarget);

    if (target) {

        target.classList.add("active");

        target.scrollIntoView({
            behavior: "smooth",
            block: "start"
        });

    }

}


/* progress update response - approve or request revision, per order_id */

function showProgressRevisionBox(orderId) {

    var buttonsRow = document.getElementById("progressButtonsRow-" + orderId);
    var revisionBox = document.getElementById("progressRevisionBox-" + orderId);

    if (buttonsRow) {
        buttonsRow.style.display = "none";
    }

    if (revisionBox) {
        revisionBox.style.display = "flex";
    }

}

function submitProgressResponse(orderId, response) {

    if (response === "revision") {

        var noteField = document.getElementById("progressRevisionNote-" + orderId);
        var note = noteField ? noteField.value.trim() : "";

        if (note === "") {
            alert("Please tell us what you'd like revised.");
            return;
        }

    }

    var responseField = document.getElementById("progressResponseField-" + orderId);
    var form = document.getElementById("progressUpdateForm-" + orderId);

    if (responseField) {
        responseField.value = response;
    }

    if (form) {
        form.submit();
    }

}


/* close the currently open order detail */

function closeOrderDetail() {

    document
        .querySelectorAll(".order-list-item")
        .forEach(function (item) {
            item.classList.remove("active");
        });

    document
        .querySelectorAll(".order-detail-panel")
        .forEach(function (panel) {
            panel.classList.remove("active");
        });

    var placeholder = document.getElementById("orderDetailPlaceholder");

    if (placeholder) {
        placeholder.classList.add("active");
    }

}


/* switch which figure's details show (kapag 2+ figures), isa lang sa isang time */

function selectFigure(button) {

    var tabsWrap = button.closest(".figure-tabs");

    if (tabsWrap) {

        tabsWrap
            .querySelectorAll(".figure-tab-btn")
            .forEach(function (tab) {
                tab.classList.remove("active");
            });

    }

    button.classList.add("active");

    var figuresWrap = tabsWrap ? tabsWrap.nextElementSibling : null;

    if (figuresWrap) {

        figuresWrap
            .querySelectorAll(".order-figure")
            .forEach(function (panel) {
                panel.classList.remove("active");
            });

    }

    var target = document.getElementById(button.dataset.figureTarget);

    if (target) {
        target.classList.add("active");
    }

}


/* click a figure photo to enlarge it */

function openImageLightbox(src, label) {

    var lightbox = document.getElementById("imageLightbox");
    var image = document.getElementById("lightboxImage");
    var caption = document.getElementById("lightboxLabel");

    if (!lightbox || !image) {
        return;
    }

    image.src = src;

    if (caption) {
        caption.textContent = label || "";
    }

    lightbox.classList.add("show");

    document.body.style.overflow = "hidden";

}


function closeImageLightbox(event) {

    /* kapag nag-click sa loob mismo ng picture, huwag isara */
    if (event && event.target && event.target.id === "lightboxImage") {
        return;
    }

    var lightbox = document.getElementById("imageLightbox");

    if (!lightbox) {
        return;
    }

    lightbox.classList.remove("show");

    document.body.style.overflow = "";

}


document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {
        closeImageLightbox();
    }

});


/* Auto-open a specific order (galing sa Notifications page) — ?order_id=12 auto-opens
   at scrolls dito, imbes na maghanap sa listahan. */

(function openOrderFromQueryString() {

    var params = new URLSearchParams(window.location.search);
    var targetOrderId = params.get("order_id");

    if (!targetOrderId) {
        return;
    }

    var targetButton = document.querySelector(
        '.order-list-item[data-order-id="' + targetOrderId + '"]'
    );

    if (!targetButton) {
        return;
    }

    selectOrder(targetButton);

    targetButton.scrollIntoView({
        behavior: "smooth",
        block: "nearest"
    });

})();

</script>


<script>

/* remove ?submitted=1 / ?cancelled=1 from URL after showing the banner once,
   para di na ulit lumabas pag nag-reload */

(function cleanUpOneTimeBannerParams() {

    var url = new URL(window.location.href);
    var changed = false;

    if (url.searchParams.has("submitted")) {
        url.searchParams.delete("submitted");
        changed = true;
    }

    if (url.searchParams.has("cancelled")) {
        url.searchParams.delete("cancelled");
        changed = true;
    }

    // Tinatanggal din ang "notice" query param (mula sa payment-shipping.php),
    // para hindi paulit-ulit lumabas kapag ni-reload ang page.
    if (url.searchParams.has("notice")) {
        url.searchParams.delete("notice");
        changed = true;
    }

    if (changed) {
        window.history.replaceState({}, document.title, url.toString());
    }

})();


/* poll check_order_updates.php every few seconds, auto-reload kapag may nabagong status */

(function pollForOrderStatusChanges() {

    var orderButtons = document.querySelectorAll(".order-list-item[data-order-id]");

    // Huwag mag-stop kahit walang laman ang listahan — kailangan pa ring mag-poll
    // para ma-detect ang unang order habang bukas ang page.

    var POLL_INTERVAL_MS = 3000; // 3 seconds

    // Extra safety net: hintayin munang makita ang PAREHONG bagong estado sa
    // SUSUNOD na poll bago mag-reload, laban sa transient timing issues.
    var pendingChangeSnapshot = null;

    // Huwag i-reload ang page habang nagta-type ang customer (revision note, atbp.)
    function isUserComposingRightNow() {

        var active = document.activeElement;
        var tag = active && active.tagName ? active.tagName.toLowerCase() : "";

        return (tag === "textarea" || tag === "input" || tag === "select");
    }

    function checkForUpdates() {

        // Huwag mag-poll habang naka-minimize/naka-ibang tab, para di sayang ang request
        if (document.hidden) {
            return;
        }

        if (isUserComposingRightNow()) {
            return;
        }

        fetch("check_order_updates.php", {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store"
        })
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .then(function (latestStatuses) {

                if (!latestStatuses) {
                    return;
                }

                // I-double-check ulit — baka nagsimula mag-type habang hinihintay ang fetch()
                if (isUserComposingRightNow()) {
                    return;
                }

                var knownOrderIds = Array.prototype.map.call(orderButtons, function (button) {
                    return button.dataset.orderId;
                });

                var hasChanged = false;

                orderButtons.forEach(function (button) {

                    var orderId = button.dataset.orderId;
                    var currentStatus = button.dataset.orderStatus;
                    var latestStatus = latestStatuses[orderId];

                    if (latestStatus && latestStatus !== currentStatus) {
                        hasChanged = true;
                    }

                });

                // Tignan din kung may bagong order (order id na wala pa sa listahan)
                if (!hasChanged) {
                    hasChanged = Object.keys(latestStatuses).some(function (orderId) {
                        return knownOrderIds.indexOf(orderId) === -1;
                    });
                }

                if (!hasChanged) {
                    // walang totoong bago — reset ang pending confirmation (baka false-positive lang)
                    pendingChangeSnapshot = null;
                    return;
                }

                var currentSnapshot = JSON.stringify(latestStatuses);

                if (pendingChangeSnapshot === currentSnapshot) {

                    // Pareho sa 2 magkasunod na poll — sigurado na, mag-reload
                    window.location.reload();

                } else {

                    // Unang beses pa lang, i-store muna, hintayin ang pagkumpirma
                    pendingChangeSnapshot = currentSnapshot;

                }

            })
            .catch(function () {
                // tahimik mabigo — susubukan ulit sa susunod na interval
            });

    }

    setInterval(checkForUpdates, POLL_INTERVAL_MS);

})();

</script>


<?php include "../Shared/footer.php"; ?>

</body>
</html>