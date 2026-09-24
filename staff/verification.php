<?php

require_once __DIR__ . "/staff-header.php";

$figurifyRole    = "staff";
$figurifyPageDir = __DIR__;

/* Shared sa owner/verification.php at staff/verification.php. Set $figurifyRole
   at $figurifyPageDir bago i-require. Same figure-card markup ng Quotation pero
   read-only dito, may sariling Verify/Decline + Payment view. */

$documentRoot = rtrim(str_replace("\\", "/", realpath($_SERVER["DOCUMENT_ROOT"])), "/");
$projectRoot  = rtrim(str_replace("\\", "/", realpath(__DIR__ . "/..")), "/");
$siteBase     = substr($projectRoot, strlen($documentRoot)) . "/";

/* Same helpers as quotation.php / my-orders.php para magtugma ang figure details display */

function figureHasSize($fig)
{
    return !empty($fig["size_label"]);
}

function hironoBaseBoxPrice($fig)
{
    return (float) $fig["box_addon_price"] - (float) $fig["hirono_blind_items_total"];
}

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

function hironoBoxDesignLabel($design)
{
    $labels = [
        "checkered"   => "Checkered",
        "hirono_peek" => "Hirono Peek",
    ];

    return $labels[$design] ?? $design;
}

function hironoBlindItemImage($itemKey)
{
    return "Hirono.jpg";
}

function orderMethodLabel($method)
{
    $labels = [
        "reference"    => "Image Submission",
        "create_style" => "Dress Up",
    ];

    return $labels[$method] ?? "Image Submission";
}


/* GET ONLY THE ORDERS WAITING FOR PAYMENT VERIFICATION — kaya sarili itong page,
   di na kailangang maghanap sa gitna ng lahat ng order sa Bookings. */

$orders = [];

$stmt = $conn->prepare(
    "SELECT o.order_id, o.order_type, o.order_method, o.booking_date, o.rush_fee,
            o.subtotal, o.total_amount, o.status, o.created_at,
            o.shipping_name, o.shipping_address, o.shipping_contact, o.courier,
            o.payment_reference, o.payment_proof, o.paid_at,
            o.refund_account_name, o.refund_account_number, o.refund_method,
            u.full_name, u.email
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     WHERE o.status = 'to_verify'
     ORDER BY o.paid_at ASC, o.created_at ASC"
);

$stmt->execute();

$ordersResult = $stmt->get_result();

while ($row = $ordersResult->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();


/* GET FIGURES + IMAGES FOR EACH ORDER — para makita ng staff ang in-order bago i-verify. */

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

    /* fallback kung wala pang hirono_box_design/hirono_blind_items columns sa DB */

    $figuresHaveExtendedHironoColumns = ($fstmt !== false);

    if (!$figuresHaveExtendedHironoColumns) {

        $fstmt = $conn->prepare(
            "SELECT figure_id, figure_style, product_type, size_label,
                    figure_name, notes, product_price, name_fee, figure_total,
                    box_addon_type, box_addon_price,
                    funko_box_type, funko_box_name, funko_box_number, funko_box_color,
                    hirono_blind_type, hirono_box_color, hirono_letter,
                    hirono_nickname, hirono_date, quoted_price
             FROM order_figures
             WHERE order_id = ?"
        );

    }

    if ($fstmt === false) {
        $order["figures"] = [];
        continue;
    }

    $fstmt->bind_param("i", $order["order_id"]);
    $fstmt->execute();

    $fresult = $fstmt->get_result();

    $figures = [];

    while ($frow = $fresult->fetch_assoc()) {

        /* fallback query, so fill in defaults for the missing hirono columns */
        if (!$figuresHaveExtendedHironoColumns) {
            $frow["hirono_box_design"]         = null;
            $frow["hirono_blind_items"]        = null;
            $frow["hirono_blind_items_total"]  = 0;
        }

        $imgStmt = $conn->prepare(
            "SELECT image_path
             FROM order_figure_images
             WHERE figure_id = ?"
        );

        $images = [];

        if ($imgStmt !== false) {

            $imgStmt->bind_param("i", $frow["figure_id"]);
            $imgStmt->execute();

            $imgResult = $imgStmt->get_result();

            while ($irow = $imgResult->fetch_assoc()) {
                $images[] = $irow["image_path"];
            }

            $imgStmt->close();

        }

        $frow["images"] = $images;


        /* box reference images (Hirono Blind Box only) — fails quietly kung wala pang table */

        $boxImages = [];

        $boxImgStmt = $conn->prepare(
            "SELECT image_path
             FROM order_figure_box_images
             WHERE figure_id = ?"
        );

        if ($boxImgStmt !== false) {

            $boxImgStmt->bind_param("i", $frow["figure_id"]);
            $boxImgStmt->execute();

            $boxImgResult = $boxImgStmt->get_result();

            while ($birow = $boxImgResult->fetch_assoc()) {
                $boxImages[] = $birow["image_path"];
            }

            $boxImgStmt->close();

        }

        $frow["box_images"] = $boxImages;


        /* optional Hirono blind box extras, saved as JSON { item_key: price } */

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
}

unset($order);

$conn->close();


/* which order is open on the right side — no auto-select, same as Quotation page */
$selectedOrderId = isset($_GET["order_id"])
    ? (int) $_GET["order_id"]
    : 0;

$selectedOrder = null;

foreach ($orders as $order) {

    if ((int) $order["order_id"] === $selectedOrderId) {
        $selectedOrder = $order;
        break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../Image/logoclay.png">

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Figurify — Payment Verification</title>

<style>
/* Figurify — staff verification page styles. All merged into one inline block
   (was 2 separate CSS files) so editing this page won't touch others. Copied
   from my-orders.css + staff/orders.css + staff/quotation.css. */

/* ---------- [1] FROM my-order/my-orders.css ---------- */
/* Clay and Stuff — My Orders */

*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

body{
    font-family:"Trebuchet MS",Arial,sans-serif;
    color:#75445e;
    background-color:#fffdf3;

    background-image:
        linear-gradient(rgba(214,198,229,.25) 1px, transparent 1px),
        linear-gradient(90deg, rgba(214,198,229,.25) 1px, transparent 1px);

    background-size:25px 25px;

    line-height:1.5;
}


/* PAGE / HEADER */

.orders-page{
    width:min(1400px,95%);
    margin:auto;
    padding:6px 0 24px;
}

.orders-header{
    margin-bottom:10px;
    flex-shrink:0;
}

.orders-header h1{
    color:#86365f;
    font-family:Georgia,serif;
    font-size:clamp(26px,4vw,34px);
}

.orders-header p{
    margin-top:6px;
    color:#956d80;
    font-size:13px;
}


/* TOP-OF-PAGE NOTICE BANNER */

.order-notice{
    margin-bottom:20px;
    padding:12px 16px;
    border-radius:14px;
    font-size:12px;
    font-weight:800;
}

.order-notice-success{
    background:#e3f6ea;
    color:#2f6b46;
    border:1px solid #bfe4cc;
}

.order-notice-cancel{
    background:#fdeaea;
    color:#a3403f;
    border:1px solid #f3c6c6;
}


/* NO ORDERS */

.no-orders{
    text-align:center;
    padding:60px 20px;
    background:rgba(255,255,255,.92);
    border:2px solid white;
    outline:1px solid #e4d5df;
    border-radius:22px;
    box-shadow:5px 7px 0 rgba(216,194,222,.3);
}

.no-orders-icon{
    font-size:42px;
    margin-bottom:10px;
}

.no-orders p{
    color:#956d80;
    font-size:13px;
    margin-bottom:14px;
}

.no-orders a{
    display:inline-block;
    padding:12px 26px;
    border-radius:20px;
    background:linear-gradient(135deg,#f2699b,#e0447f);
    color:white;
    text-decoration:none;
    font-size:11px;
    font-weight:900;
    box-shadow:0 6px 14px rgba(224,68,127,.35);
    transition:.15s;
}

.no-orders a:hover{
    filter:brightness(1.05);
    transform:translateY(-2px);
}


/* MAIN LAYOUT — FAR LEFT LIST / EVERYTHING ELSE */

.orders-layout{
    display:grid;
    grid-template-columns:270px 1fr;
    gap:22px;
    align-items:start;
}

@media (max-width:800px){

    .orders-layout{
        grid-template-columns:1fr;
    }

    .orders-list-col{
        position:static;
    }

}


/* PAGE SCROLL (desktop/tablet) — dati no-page-scroll shell, pinalitan para maabot
   ang footer. Order list (kaliwa) at order summary (kanan) ay sticky pa rin.  */


/* ============ FAR LEFT: ORDER LIST (sidebar) ============ */

.orders-list-col{
    position:sticky;
    top:98px; /* 78px navbar clearance + 20px breathing room, para di matakpan ng navbar */
}

.orders-list-shell{
    background:rgba(255,255,255,.92);
    border:2px solid white;
    outline:1px solid #e4d5df;
    border-radius:22px;
    box-shadow:5px 7px 0 rgba(216,194,222,.3);
    padding:18px 14px 14px;
}

.orders-list-scroll{
    display:flex;
    flex-direction:column;
    gap:12px;
    max-height:calc(100vh - 228px); /* 150px dati + 78px navbar clearance */
    overflow-y:auto;
    padding:3px 4px 5px 2px;
    scrollbar-width:thin;
    scrollbar-color:#f2a4ca transparent;
}

.orders-list-scroll::-webkit-scrollbar{width:6px;}
.orders-list-scroll::-webkit-scrollbar-track{background:transparent;}
.orders-list-scroll::-webkit-scrollbar-thumb{background:#f2a4ca;border-radius:10px;}

.order-list-item{
    display:flex;
    flex-direction:column;
    justify-content:center;
    width:100%;
    text-align:left;
    cursor:pointer;
    font-family:inherit;
    color:inherit;
    background:rgba(255,255,255,.92);
    border:2px solid white;
    outline:1px solid #e4d5df;
    border-radius:16px;
    padding:12px 14px;
    box-shadow:5px 7px 0 rgba(216,194,222,.25);
    transition:.15s;
}

.order-list-item:hover{
    transform:translateY(-2px);
}

.order-list-item.active{
    border-color:#f2a4ca;
    background:#fff0f7;
    box-shadow:5px 7px 0 rgba(242,164,202,.4);
}

.order-list-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    margin-bottom:6px;
}

.order-list-id{
    font-family:Georgia,serif;
    font-weight:bold;
    color:#873c63;
    font-size:13px;
}

.order-list-date{
    color:#a17488;
    font-size:9px;
    margin-bottom:4px;
}

.order-list-count{
    color:#a17488;
    font-size:9px;
    margin-top:2px;
}


/* STATUS BADGE COLORS (single source: order_status_helper.php) */

.order-status{
    flex-shrink:0;
    padding:5px 10px;
    border-radius:20px;
    font-size:8px;
    font-weight:900;
    letter-spacing:.4px;
    white-space:nowrap;
}

.status-pending{background: #FFF3C4;color: #29252A;}
.status-quoted{background: #E1F3E7;color: #29252A;}
.status-awaiting_payment{background: #FBE8D6;color: #29252A;}
.status-to_verify{background: #E1F3E7;color: #29252A;}
.status-for_approval{background: #E1F3E7;color: #29252A;}
.status-processing{background: #FBE8D6;color: #29252A;}
.status-awaiting_balance{background: #FBE8D6;color: #29252A;}
.status-to_verify_balance{background: #E1F3E7;color: #29252A;}
.status-for_balance_approval{background: #E1F3E7;color: #29252A;}
.status-to_ship{background: #E8EDFF;color: #29252A;}
.status-shipped{background: #E8EDFF;color: #29252A;}
.status-completed{background: #E1F3E7;color: #29252A;}
.status-cancelled{background: #FADCDC;color: #29252A;}


/* ORDER DETAIL — PLACEHOLDER / PANEL TOGGLE */

.orders-detail-col{
    min-width:0;
}

.order-detail-placeholder{
    display:none;
    text-align:center;
    padding:70px 20px;
    background:rgba(255,255,255,.85);
    border:2px solid white;
    outline:1px solid #e4d5df;
    border-radius:24px;
    box-shadow:5px 7px 0 rgba(216,194,222,.3);
}

.order-detail-placeholder.active{
    display:block;
}

.order-detail-placeholder .no-orders-icon{
    font-size:34px;
}

.order-detail-placeholder p{
    color:#956d80;
    font-size:13px;
}

.order-detail-panel{
    display:none;
}

.order-detail-panel.active{
    display:block;
}


/* ORDER CARD (the panel itself) */

.order-card{
    background:rgba(255,255,255,.95);
    border:3px solid white;
    outline:1px solid #e4d5df;
    border-radius:22px;
    padding:20px;
    box-shadow:5px 7px 0 rgba(216,194,222,.3);
    scroll-margin-top:98px; /* para pag scrollIntoView, di matabunan ng navbar */
}

/* DETAIL GRID — MAIN (order info + figures) / SIDE (summary) */

.detail-grid{
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:20px;
    align-items:start;
}

@media (max-width:1100px){
    .detail-grid{
        grid-template-columns:1fr;
    }
}

.detail-main{
    display:flex;
    flex-direction:column;
    gap:20px;
    min-width:0;
}

.detail-side{
    min-width:0;
}

@media (min-width:801px){
    .detail-side{
        position:sticky;
        top:98px; /* pareho ng left order-list para same ang space nila sa navbar */
        align-self:start;
    }
}


/* GENERIC CARD (order info / figure viewer / summary) */

.card{
    background:rgba(255,255,255,.92);
    border:2px solid white;
    outline:1px solid #e4d5df;
    border-radius:20px;
    box-shadow:5px 7px 0 rgba(216,194,222,.25);
    padding:20px;
}


/* ORDER INFORMATION CARD */

.order-info-title{
    color:#d2417a;
    font-weight:bold;
    font-size:13px;
    letter-spacing:.2px;
    margin-bottom:14px;
}

.order-info-grid{
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:10px;
}

@media (max-width:900px){
    .order-info-grid{grid-template-columns:repeat(2, minmax(0, 1fr));}
}

@media (max-width:520px){
    .order-info-grid{grid-template-columns:1fr;}
}

.info-chip{
    display:flex;
    align-items:center;
    gap:9px;
    background:#fffaf6;
    border:1px solid #f5e6ee;
    border-radius:12px;
    padding:9px 12px;
    font-size:12.5px;
    min-width:0;
}

.info-chip .icon{
    font-size:16px;
    flex-shrink:0;
}

.info-chip .txt{
    display:flex;
    flex-direction:column;
    min-width:0;
}

.info-chip .dt{
    font-size:10px;
    color:#a97b90;
    text-transform:uppercase;
    letter-spacing:.3px;
}

.info-chip .dd{
    font-weight:bold;
    color:#75445e;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}


/* FIGURE VIEWER CARD */

.order-figures-title{
    color:#a17488;
    font-size:10px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.5px;
    margin-bottom:12px;
}

.figure-tabs{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin-bottom:18px;
}

.figure-tab-btn{
    border:none;
    background:transparent;
    font-size:12px;
    font-weight:bold;
    color:#a97b90;
    padding:8px 16px;
    border-radius:14px;
    cursor:pointer;
    transition:.15s;
    font-family:inherit;
}

.figure-tab-btn.active{
    color:#d2417a;
    background:#fdeaf1;
}

.figure-tab-btn:hover:not(.active){
    color:#d2417a;
}

.order-figure:not(.active){
    display:none;
}


/* ============ VIEWER GRID: FIGURE DETAILS | CUSTOM BOX ============ */

.viewer-grid{
    display:grid;
    grid-template-columns:1.1fr 1.3fr;
    gap:22px;
    align-items:start;
}

@media (max-width:650px){
    .viewer-grid{grid-template-columns:1fr;}
}

.viewer-column{
    min-width:0;
    display:flex;
    flex-direction:column;
}

.details-title{
    color:#d2417a;
    font-weight:bold;
    font-size:13px;
    letter-spacing:.3px;
    margin-bottom:14px;
    display:flex;
    align-items:center;
    gap:6px;
}


/*   DETAIL ITEM CARD — reusable "line item" (image + label +
   value + price), used for Figure Details AND Custom Box. */

.detail-item-list{
    display:flex;
    flex-direction:column;
    gap:8px;
    margin-bottom:8px;
}

.detail-item-card{
    display:flex;
    align-items:center;
    gap:12px;
    background:#fffaf6;
    border:1px solid #f5e6ee;
    border-radius:14px;
    padding:9px 12px;
}

.detail-item-image{
    width:44px;
    height:44px;
    border-radius:10px;
    object-fit:cover;
    flex-shrink:0;
    background:#fff0f7;
}

.detail-item-noimage{
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:19px;
    background:#fdeaf1;
}

.detail-item-body{
    display:flex;
    flex-direction:column;
    flex:1;
    min-width:0;
    gap:1px;
}

.detail-item-label{
    font-size:10px;
    color:#a97b90;
    text-transform:uppercase;
    letter-spacing:.3px;
}

.detail-item-value{
    font-size:13px;
    color:#75445e;
    word-break:break-word;
}

.detail-item-price{
    flex-shrink:0;
    font-size:13px;
    font-weight:bold;
    color:#d2417a;
    white-space:nowrap;
    text-align:right;
}

.detail-item-price.muted{
    color:#c9b3bf;
    font-weight:normal;
}


/* NOTES TO ARTIST */

.notes-box{
    margin:4px 0 8px;
    padding:10px 12px;
    background:#fff8fc;
    border:1px dashed #e5a7c6;
    border-radius:12px;
    font-size:12.5px;
}

.notes-box strong{
    display:block;
    margin-bottom:4px;
    color:#873c63;
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.4px;
}

.notes-box p{
    color:#5c3348;
    line-height:1.5;
    word-break:break-word;
    white-space:pre-wrap;
}


/* REFERENCE IMAGE GALLERY */

.reference-section,
.box-reference-section{
    margin-top:auto;
    padding-top:14px;
}

.gallery-label{
    font-size:11.5px;
    font-weight:bold;
    color:#75445e;
    margin-bottom:10px;
    display:flex;
    align-items:baseline;
    gap:6px;
}

.gallery-label-count{
    font-weight:normal;
    font-size:10px;
    color:#a97b90;
}

.gallery-row{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.gallery-thumb{
    width:66px;
    height:66px;
    border-radius:12px;
    object-fit:cover;
    cursor:pointer;
    border:2px solid white;
    outline:1px solid #e6d4e4;
    background:#f3eef1;
    transition:.15s;
}

.gallery-thumb:hover{
    outline-color:#f2a4ca;
    transform:scale(1.05);
}

.no-image{
    width:100%;
    padding:16px 0;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#a99da4;
    font-size:11px;
    text-align:center;
    border-radius:12px;
    border:2px dashed #e6d4e4;
    background:#f9f5f4;
}


/* CUSTOM BOX CARD */

.box-card{
    background:#fdf4f8;
    border:1px solid #f4d9e6;
    border-radius:16px;
    padding:16px;
    height:100%;
    display:flex;
    flex-direction:column;
}

.box-card-title{
    color:#d2417a;
    font-weight:bold;
    font-size:13px;
    margin-bottom:14px;
    display:flex;
    align-items:center;
    gap:6px;
}

/* ---- CUSTOM BOX: EMPTY STATE (walang box add-on) ---- */

.box-card-empty{
    flex:1;
    align-items:center;
    justify-content:center;
    text-align:center;
    background:#fdf4f8;
    border:2px dashed #f0c6dc;
}

.box-empty-state{
    flex:1;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:6px;
    padding:26px 14px;
}

.box-empty-icon{
    font-size:32px;
    opacity:.55;
    margin-bottom:4px;
}

.box-empty-title{
    font-weight:bold;
    color:#a4476d;
    font-size:12.5px;
}

.box-empty-text{
    color:#b696a4;
    font-size:11px;
    max-width:220px;
    line-height:1.5;
}

.box-detail-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:11px 18px;
    font-size:12.5px;
    margin-bottom:6px;
}

.box-detail-grid div{
    min-width:0;
    display:flex;
    flex-direction:column;
    gap:1px;
}

.box-detail-grid div.full-row{
    grid-column:1 / -1;
}

.box-detail-grid div span:first-child{
    color:#a97b90;
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.3px;
}

.box-detail-grid div span:last-child{
    font-weight:bold;
    color:#873c63;
    word-break:break-word;
}

@media (max-width:520px){
    .box-detail-grid{grid-template-columns:1fr;}
}


/*   ORDER ACTION BOX (Quoted / Awaiting Payment / To Verify)
   — lives at the top of the Order Summary card. */

.action-box{
    border-radius:16px;
    padding:16px;
    margin-bottom:18px;
}

.action-box.action-quoted{
    background:#fdeaf1;
}

.action-box.action-awaiting{
    background:#fff3e2;
}

.action-box.action-verify{
    background:#eef6ee;
}

.action-title{
    font-weight:bold;
    font-size:13.5px;
    color:#75445e;
    margin-bottom:6px;
}

.action-text{
    font-size:12.5px;
    color:#8a6b78;
    line-height:1.5;
}

.action-deadline{
    display:block;
    margin-top:6px;
    font-size:11.5px;
    color:#c94f4f;
    font-weight:bold;
}

.action-buttons{
    display:flex;
    gap:10px;
    margin-top:14px;
    flex-wrap:wrap;
}

.action-buttons form{
    display:inline-block;
    flex:1;
}

.btn-continue,
.btn-cancel{
    display:inline-block;
    border:none;
    padding:11px 20px;
    border-radius:14px;
    font-size:12.5px;
    font-weight:bold;
    font-family:inherit;
    cursor:pointer;
    text-align:center;
    text-decoration:none;
    transition:.15s;
    width:100%;
}

.btn-continue{
    background:linear-gradient(135deg,#f2699b,#e0447f);
    color:white;
    box-shadow:0 6px 14px rgba(224,68,127,.35);
}

.btn-continue:hover{
    filter:brightness(1.05);
    transform:translateY(-2px);
}

.btn-cancel{
    border:1px solid #e0447f;
    background:white;
    color:#d2417a;
}

.btn-cancel:hover{
    background:#fdf4f8;
}

.btn-link{
    display:flex;
    align-items:center;
    justify-content:center;
}


/* ORDER SUMMARY (right column) */

.detail-side .summary-card{
    position:sticky;
    top:98px; /* pareho ng left order-list para same ang space nila sa navbar */
}

.summary-title{
    color:#d2417a;
    font-family:Georgia,serif;
    font-weight:bold;
    font-size:15px;
    margin-bottom:14px;
}

.summary-lines{
    display:flex;
    flex-direction:column;
}

.summary-line{
    display:flex;
    justify-content:space-between;
    align-items:center;
    font-size:13px;
    padding:8px 0;
    gap:10px;
}

.summary-line .icon{
    margin-right:8px;
}

.summary-line .label-wrap{
    display:flex;
    align-items:center;
    color:#75445e;
}

.summary-divider{
    height:1px;
    background:#f0e3ea;
    margin:8px 0;
}

.summary-total{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-top:6px;
}

.summary-total .label{
    color:#d2417a;
    font-weight:bold;
    font-size:11.5px;
    letter-spacing:.3px;
    text-transform:uppercase;
}

.summary-total .amount{
    color:#d2417a;
    font-weight:bold;
    font-size:22px;
    white-space:nowrap;
}

.expiry-note{
    margin-top:14px;
    display:flex;
    gap:10px;
    align-items:flex-start;
    background:#fff7f0;
    border-radius:14px;
    padding:12px 14px;
    font-size:12px;
    color:#8a6b78;
}


/* IMAGE LIGHTBOX (click a photo to enlarge) */

.image-lightbox{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(60,30,45,.75);
    z-index:5000; /* mas mataas sa navbar */
    align-items:center;
    justify-content:center;
    padding:30px;
}

.image-lightbox.show{
    display:flex;
}

.image-lightbox-content{
    position:relative;
    max-width:min(600px,92vw);
    text-align:center;
}

.image-lightbox-content img{
    max-width:100%;
    max-height:78vh;
    border-radius:14px;
    box-shadow:0 20px 60px rgba(0,0,0,.35);
    object-fit:contain;
    background:#fff;
}

.image-lightbox-label{
    margin-top:12px;
    color:#fff0f7;
    font-size:12px;
    font-weight:700;
}

.image-lightbox-close{
    position:absolute;
    top:-18px;
    right:-18px;
    width:34px;
    height:34px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:none;
    border-radius:50%;
    background:white;
    color:#d2417a;
    font-size:14px;
    font-weight:900;
    cursor:pointer;
    box-shadow:0 2px 6px rgba(0,0,0,.15);
    transition:.15s;
}

.image-lightbox-close:hover{
    background:#fdeaf1;
    transform:scale(1.08);
}

@media(max-width:500px){
    .image-lightbox-close{top:-14px;right:0;}
}


/* TERMS & CONDITIONS MODAL */

.terms-modal-overlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(60,30,45,.55);
    z-index:1000;
    align-items:center;
    justify-content:center;
    padding:20px;
}

.terms-modal-overlay.show{
    display:flex;
    overscroll-behavior:contain;
}

.terms-modal{
    background:white;
    width:min(480px,100%);
    max-height:85vh;
    display:flex;
    flex-direction:column;
    border-radius:20px;
    overflow:hidden;
}

.terms-modal-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:18px 20px;
    border-bottom:1px solid #f0e3ea;
}

.terms-modal-top h3{
    color:#86365f;
    font-family:Georgia,serif;
    font-size:18px;
}

.terms-modal-close{
    border:none;
    background:none;
    font-size:20px;
    cursor:pointer;
    color:#a97b90;
}

.terms-modal-body{
    padding:16px 20px;
    overflow-y:auto;
    overscroll-behavior:contain;
    font-size:12.5px;
    color:#75445e;
    line-height:1.6;
}

.terms-modal-body p{
    margin-bottom:10px;
}

.terms-modal-body ol{
    padding-left:18px;
}

.terms-modal-body li{
    margin-bottom:10px;
}

.terms-modal-body strong{
    color:#893a65;
}

.terms-modal-agree{
    padding:14px 20px;
    display:flex;
    gap:8px;
    align-items:flex-start;
    font-size:12px;
    color:#75445e;
    border-top:1px solid #f0e3ea;
    cursor:pointer;
}

.terms-modal-agree input{
    margin-top:2px;
    flex-shrink:0;
}

.terms-modal-confirm{
    margin:0 20px 18px;
    border:none;
    background:#f2a4ca;
    color:white;
    padding:12px;
    border-radius:14px;
    font-weight:bold;
    font-size:12.5px;
    cursor:pointer;
    transition:.15s;
}

.terms-modal-confirm:hover:not(:disabled){
    background:#e98db9;
}

.terms-modal-confirm:disabled{
    opacity:.5;
    cursor:not-allowed;
}



/* ---------- [2] FROM staff/orders.css ---------- */
/* SHARED orders.css (Owner + Staff) — dating magkahiwalay na kopya, iisa na lang para di mag-drift. */

/* FIGURIFY — STAFF ORDERS PAGE */

.orders-manager{
    padding: 20px;
}

.orders-manager-grid{
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 20px;
    align-items: start;
}


/* ================= LEFT: ORDER LIST ================= */

.staff-order-list{
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 700px;
    overflow-y: auto;
    padding-right: 4px;
}

.staff-order-item{
    display: block;
    text-decoration: none;
    color: inherit;
    background: var(--white);
    border: 2px solid var(--gray-light);
    border-radius: 14px;
    padding: 12px 14px;
    transition: .15s ease;
}

.staff-order-item:hover{
    border-color: var(--pink);
}

.staff-order-item.active{
    border-color: var(--pink);
    background: var(--pink-soft);
}

.staff-order-item-top{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;
}

.staff-order-item-customer{
    font-size: 12px;
    color: var(--black);
    font-weight: 700;
    margin-bottom: 2px;
}

.staff-order-item-meta{
    font-size: 10px;
    color: var(--gray);
}


/* ================= RIGHT: ORDER DETAIL ================= */

.staff-order-detail{
    background: var(--white);
    border: 2px solid var(--gray-light);
    border-radius: 16px;
    padding: 20px;
    min-height: 300px;
}

.staff-detail-header{
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
}

.staff-detail-header h3{
    color: var(--pink-dark);
    font-size: 18px;
}

.staff-detail-header p{
    font-size: 11px;
    color: var(--gray);
    margin-top: 2px;
}

.staff-detail-total{
    font-size: 18px;
    font-weight: 900;
    color: var(--pink-dark);
    white-space: nowrap;
}

.staff-detail-meta{
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    font-size: 11px;
    color: var(--gray);
    padding-bottom: 14px;
    margin-bottom: 14px;
    border-bottom: 2px dashed var(--gray-light);
}


/* ================= PAYMENT / SHIPPING VERIFICATION BOX ================= */

.staff-payment-details{
    padding: 14px;
    background: var(--white);
    border: 1.5px solid #C9BEC3;
    border-radius: 20px;
    box-shadow: 5px 7px 0 rgba(216, 194, 222, .25);
}

.staff-payment-details-title{
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .4px;
    color: var(--pink-dark, #a4476d);
    margin-bottom: 8px;
}

.staff-payment-details-grid{
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 8px 16px;
    font-size: 11px;
    color: var(--gray, #75445e);
}

.staff-payment-details-grid strong{
    color: var(--black, #4a2c3a);
    margin-right: 4px;
}


/* ================= PROOF OF PAYMENT THUMBNAIL ================= */

.staff-payment-proof{
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed var(--pink, #f2a4ca);
}

.staff-payment-proof-title{
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .4px;
    color: var(--pink-dark, #a4476d);
    margin-bottom: 8px;
}

.staff-payment-proof-thumb{
    max-width: 220px;
    max-height: 220px;
    border-radius: 12px;
    border: 2px solid white;
    outline: 1px solid #e4d5df;
    cursor: pointer;
    object-fit: cover;
    transition: .15s ease;
}

.staff-payment-proof-thumb:hover{
    transform: translateY(-2px);
}


/* ================= STATUS UPDATE FORM ================= */

.status-update-form{
    margin-bottom: 20px;
    padding-bottom: 18px;
    border-bottom: 2px dashed var(--gray-light);
}

.status-update-form label{
    display: block;
    font-size: 10px;
    font-weight: 700;
    color: var(--gray);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: .4px;
}

.status-update-row{
    display: flex;
    gap: 10px;
}

.status-update-row select{
    flex: 1;
    padding: 8px 10px;
    border-radius: 10px;
    border: 1px solid var(--gray-light);
    font-size: 12px;
    background: var(--white);
    color: var(--black);
}

.status-update-row button{
    padding: 8px 18px;
    border-radius: 10px;
    border: none;
    background: var(--pink);
    color: white;
    font-weight: 800;
    font-size: 11px;
    cursor: pointer;
    transition: .15s ease;
}

.status-update-row button:hover{
    background: var(--pink-dark);
}


/* ================= VERIFICATION APPROVE / REJECT ================= */

.verify-actions{
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 18px;
    border-bottom: 2px dashed var(--gray-light);
}

.verify-actions form{
    flex: 1;
}

.decline-reason-form{
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.decline-reason-select{
    width: 100%;
    padding: 9px 10px;
    border: 1px solid var(--gray-light);
    border-radius: 8px;
    font-size: 11.5px;
    font-family: inherit;
    background: var(--white);
    color: var(--black);
}

.decline-reason-select:invalid{
    color: var(--gray);
}

.decline-modal-overlay{
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.decline-modal-overlay.active{
    display: flex;
}

.decline-modal-box{
    background: var(--white);
    border-radius: 14px;
    padding: 22px;
    width: 100%;
    max-width: 360px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.decline-modal-title{
    font-size: 14px;
    font-weight: 800;
    color: var(--black);
    margin: 0;
}

.decline-modal-desc{
    font-size: 11.5px;
    color: var(--gray);
    margin: 0 0 4px;
}

.decline-reason-other{
    width: 100%;
    padding: 9px 10px;
    border: 1px solid var(--gray-light);
    border-radius: 8px;
    font-size: 11.5px;
    font-family: inherit;
    color: var(--black);
    resize: vertical;
    min-height: 60px;
}

.decline-modal-actions{
    display: flex;
    gap: 8px;
    margin-top: 4px;
}

.decline-modal-btn{
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: 10px;
    font-weight: 700;
    font-size: 11.5px;
    cursor: pointer;
}

.decline-modal-btn-cancel{
    background: var(--gray-light);
    color: var(--black);
}

.decline-modal-btn-send{
    background: var(--red);
    color: white;
}

.verify-btn{
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 10px;
    font-weight: 800;
    font-size: 12px;
    cursor: pointer;
    transition: .15s ease;
}

.verify-btn-approve{
    background: var(--green);
    color: white;
}

.verify-btn-approve:hover{
    background: #4f8d68;
}

.verify-btn-reject{
    background: var(--red);
    color: white;
}

.verify-btn-reject:hover{
    background: #c14f5d;
}

.verify-btn-update{
    background: var(--blue);
    color: white;
}

.verify-btn-update:hover{
    background: #4f6cc1;
}


/* ================= JUMP TO QUOTATION ================= */

.quotation-jump-link{
    display: inline-block;
    margin-bottom: 18px;
    padding: 9px 16px;
    border-radius: 10px;
    background: var(--pink-light);
    color: var(--pink-dark);
    font-size: 11px;
    font-weight: 800;
    text-decoration: none;
    transition: .15s ease;
}

.quotation-jump-link:hover{
    background: var(--pink);
    color: white;
}


/* ================= FIGURES GRID ================= */

.staff-figures{
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
}

.figure-notes{
    display: block;
    font-style: italic;
}


/* ================= PER-FIGURE PRICE BREAKDOWN ================= */

.figure-price-breakdown{
    margin-top: 8px;
    padding: 10px 12px;
    background: var(--pink-light, #fff0f7);
    border: 1px dashed var(--pink, #f2a4ca);
    border-radius: 10px;
}

.figure-price-row{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 3px 0;
    font-size: 11px;
    color: var(--gray, #75445e);
}

.figure-price-row-total{
    margin-top: 4px;
    padding-top: 6px;
    border-top: 1px dashed var(--pink, #f2a4ca);
    font-weight: 800;
    color: var(--black, #4a2c3a);
}

.figure-price-row-quoted{
    margin-top: 2px;
    padding: 6px 8px;
    background: var(--pink, #f2a4ca);
    border-radius: 8px;
    color: white;
    font-weight: 900;
}


/* ================= EMPTY STATE ================= */

.staff-order-detail .empty-state{
    text-align: center;
    padding: 60px 20px;
    color: var(--gray);
    font-size: 13px;
}


/* ================= DETAIL VIEW TOGGLE (Customer Order / Payment) ================= */

.detail-view-toggle{
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}

.detail-toggle-btn{
    padding: 9px 18px;
    border-radius: 10px;
    border: 2px solid var(--gray-light);
    background: var(--white);
    color: var(--gray, #75445e);
    font-weight: 800;
    font-size: 12px;
    cursor: pointer;
    transition: .15s ease;
}

.detail-toggle-btn:hover{
    border-color: var(--pink);
}

.detail-toggle-btn.active{
    background: var(--pink);
    border-color: var(--pink);
    color: white;
}

.detail-view-panel{
    display: none;
}

.detail-view-panel.active{
    display: block;
}


/* ================= RESPONSIVE ================= */

@media (max-width: 900px){

    .orders-manager-grid{
        grid-template-columns: 1fr;
    }

    .staff-order-list{
        max-height: none;
    }

}

/* ---------- [3] FROM staff/quotation.css ---------- */
/* shared quotation.css (Owner + Staff), 3-column workspace. "qv-" prefix kasi
   my-orders.css/orders.css also load here and already use generic names. */

/* search bar sa loob ng .qv-toolbar, same pattern ng Quotation page markup */
.qv-toolbar{
    margin-top: 8px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}

.qv-tabs{
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.qv-tab{
    text-align: center;
    padding: 7px 14px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    color: var(--gray);
    background: var(--background);
    border: 1px solid transparent;
    cursor: pointer;
    white-space: nowrap;
}

.qv-tab.active{
    background: var(--pink-light);
    color: var(--pink-dark);
    border-color: var(--pink);
}

/* .qv-workspace is just a layout wrapper, no box styling — .qv-list-panel
   and .qv-right-col carry the card look instead */
.qv-workspace{
    background: transparent;
    border: none;
    outline: none;
    border-radius: 0;
    box-shadow: none;
}

/* fixed-height layout: LEFT (.qv-list-panel) and RIGHT (.qv-right-col) each
   scroll on their own inside 100vh. Stacks back to normal scroll on mobile. */

.qv-shell{
    display: grid;
    grid-template-columns: minmax(0, 1fr) 360px;
    gap: 20px;
    align-items: start;
}

/* form wraps center + right columns for the shared submit, but forms are
   block by default and would break the grid — display:contents fixes that */
.qv-shell form{
    display: contents;
}

.qv-right-col{
    display: flex;
    flex-direction: column;
    gap: 8px;
}

@media (min-width: 1181px){

    html, body.quotation-page{
        height: 100%;
        overflow: hidden;
    }

    body.quotation-page .app{
        height: 100vh;
    }

    body.quotation-page .main{
        height: 100vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    body.quotation-page .qv-workspace{
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .qv-shell{
        flex: 1 1 auto;
        min-height: 0;
        height: 100%;
        align-items: stretch;
    }

    .qv-list-panel{
        height: 100%;
        min-height: 0;
    }

    .qv-right-col{
        height: 100%;
        min-height: 0;
        overflow-y: auto;
        background: var(--white);
        border-radius: 20px;
        border: 1.5px solid #C9BEC3;
        box-shadow: 5px 7px 0 rgba(216, 194, 222, .25);
        padding: 16px 18px 20px;
    }

}

.qv-figure-panels{
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.qv-figure-panel{
    display: none;
    flex-direction: column;
    gap: 14px;
}

.qv-figure-panel.active{
    display: flex;
}

/* mobile/tablet: stacks to one column, normal page scroll na lang */
@media (max-width: 1180px){
    .qv-shell{
        grid-template-columns: 1fr;
    }
}


/* LEFT — ORDER LIST */

.qv-list-panel{
    background: var(--white);
    border-radius: 20px;
    border: 1.5px solid #C9BEC3;
    box-shadow: 5px 7px 0 rgba(216, 194, 222, .25);
    padding: 16px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.qv-search{
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--background);
    border: 1px solid var(--gray-light);
    border-radius: 999px;
    padding: 8px 14px;
    margin-bottom: 0;
    width: 200px;
    flex-shrink: 0;
}

.qv-search input{
    border: none;
    background: none;
    outline: none;
    font-size: 13px;
    width: 100%;
    color: var(--black);
    font-family: inherit;
}

.qv-search svg{ flex-shrink: 0; color: var(--gray); }

/* Table na ngayon ang order list (di na card stack). .qv-list ang may internal
   scroll, sticky ang header row.  */
.qv-list{
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
    border: 1px solid var(--gray-light);
    border-radius: 14px;
}

.qv-table{
    width: 100%;
    min-width: 760px;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 12.5px;
    table-layout: fixed;
}

/* parehong % width ng bawat column — Order / Email / Order Type /
   Order Method / Book Date / Status, sunod sa pagkakasunod nila sa
   <thead> */
.qv-table th:nth-child(1), .qv-table td:nth-child(1) { width: 8%; }
.qv-table th:nth-child(2), .qv-table td:nth-child(2) { width: 30%; }
.qv-table th:nth-child(3), .qv-table td:nth-child(3) { width: 14%; }
.qv-table th:nth-child(4), .qv-table td:nth-child(4) { width: 18%; }
.qv-table th:nth-child(5), .qv-table td:nth-child(5) { width: 14%; }
.qv-table th:nth-child(6), .qv-table td:nth-child(6) { width: 16%; }

/* Email column lang ang pwedeng lumampas sa laki niya (pinakamahaba),
   kaya dito lang ilalagay ang ellipsis kapag hindi na kasya */
.qv-table td:nth-child(2) {
    overflow: hidden;
    text-overflow: ellipsis;
}

.qv-table thead th{
    position: sticky;
    top: 0;
    z-index: 1;
    background: var(--background);
    color: var(--gray);
    font-size: 10.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .3px;
    text-align: left;
    padding: 10px 12px;
    white-space: nowrap;
    border-bottom: 1.5px solid var(--gray-light);
    border-right: 1px solid var(--gray-light);
}

.qv-table thead th:last-child{
    border-right: none;
}

.qv-row{
    cursor: pointer;
    transition: .15s ease;
}

.qv-row td{
    padding: 12px;
    border-bottom: 1px solid var(--gray-light);
    border-right: 1px solid var(--gray-light);
    white-space: nowrap;
    color: var(--black);
}

.qv-row td:last-child{
    border-right: none;
}

/* Walang linya na nakasabit sa ilalim ng huling row para maganda
   ang rounded na ibabang sulok ng ".qv-list".  */
.qv-table tbody tr:last-child td{
    border-bottom: none;
}

.qv-row td.qv-cell-id{
    font-weight: 800;
    color: var(--pink-dark, var(--pink));
}

.qv-row:hover{
    background: var(--background);
}

.qv-row.active{
    background: #FFF3C4;
}

.qv-row.active td{
    color: #8A6D1D;
}

.qv-row.active td:first-child{
    box-shadow: inset 3px 0 0 #C9A227;
}

.qv-row.qv-hidden{
    display: none;
}

.qv-pill{
    display: inline-block;
    font-size: 9.5px;
    font-weight: 800;
    padding: 3px 9px;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: .3px;
    white-space: nowrap;
}

.qv-list-footer{
    margin-top: 14px;
    font-size: 11px;
    color: var(--gray);
    text-align: center;
}


/* CENTER — ORDER DETAIL */

.qv-detail-col{
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    gap: 16px;
    padding: 2px 10px 18px 2px;
}

.qv-header-card{
    background: var(--white);
    border-radius: 20px;
    border: 1.5px solid #C9BEC3;
    box-shadow: 5px 7px 0 rgba(216, 194, 222, .25);
    padding: 16px 20px;
}

/* ORDER INFORMATION CARD — same visual language ng Customer's My Orders page. */

.order-info-title{
    color: var(--pink-dark);
    font-weight: bold;
    font-size: 13px;
    letter-spacing: .2px;
    margin-bottom: 14px;
}

.order-info-grid{
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

@media (max-width: 900px){
    .order-info-grid{grid-template-columns: repeat(2, minmax(0, 1fr));}
}

@media (max-width: 520px){
    .order-info-grid{grid-template-columns: 1fr;}
}

.info-chip{
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fffaf6;
    border: 1px solid #f5e6ee;
    border-radius: 12px;
    padding: 8px 10px;
    font-size: 11px;
    min-width: 0;
}

.info-chip .icon{
    font-size: 14px;
    flex-shrink: 0;
}

.info-chip .txt{
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.info-chip .dt{
    font-size: 9px;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: .3px;
}

.info-chip .dd{
    font-size: 11px;
    font-weight: bold;
    color: var(--pink-dark);
    white-space: normal;
    overflow-wrap: break-word;
    word-break: break-word;
}


/* ---------- FIGURE DETAILS + CUSTOM BOX ---------- */
/* stacked na, di na side by side — masikip na sa 2-container layout */
.qv-viewer-grid{
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.qv-viewer-column{
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.qv-viewer-column .qv-figure-card,
.qv-viewer-column .qv-box-card{
    height: auto;
}


/* ---------- GALLERY + FIGURE DETAILS CARD ---------- */

.qv-figure-card{
    background: var(--white);
    border-radius: 20px;
    border: 1.5px solid #C9BEC3;
    box-shadow: 5px 7px 0 rgba(216, 194, 222, .25);
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
}

.qv-figure-tabs{
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
}

.qv-figure-tab-pill{
    padding: 7px 16px;
    border-radius: 999px;
    font-size: 12.5px;
    font-weight: 700;
    color: var(--gray);
    background: var(--background);
    border: none;
    cursor: pointer;
}

.qv-figure-tab-pill.active{
    background: var(--pink);
    color: var(--white);
}

.qv-figure-body{
    display: flex;
    flex-direction: column;
}


/* ---------- FIGURE DETAILS ---------- */

.qv-details-head{
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--pink-dark);
    font-weight: 800;
    font-size: 13px;
    margin-bottom: 14px;
}


/* DETAIL ITEM CARD — same disenyo ng detail-item-card sa Customer My Orders page. */

.qv-detail-item-list{
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.qv-detail-item-card{
    display: flex;
    align-items: center;
    gap: 12px;
    background: #fffaf6;
    border: 1px solid #f5e6ee;
    border-radius: 14px;
    padding: 9px 12px;
    flex-wrap: wrap;
}

.qv-detail-item-image{
    width: 44px;
    height: 44px;
    border-radius: 10px;
    object-fit: cover;
    flex-shrink: 0;
    background: var(--pink-light);
}

.qv-detail-item-noimage{
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 19px;
}

.qv-detail-item-body{
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 120px;
    gap: 1px;
}

.qv-detail-item-label{
    font-size: 10px;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: .3px;
}

.qv-detail-item-value{
    font-size: 13px;
    color: var(--black);
    word-break: break-word;
}

.qv-detail-item-price{
    flex-shrink: 0;
    font-size: 13px;
    font-weight: 700;
    color: var(--pink-dark);
    white-space: nowrap;
    text-align: right;
}

.qv-detail-item-price-muted{
    color: var(--gray);
    font-weight: 400;
}

.qv-spec-label{
    font-size: 10.5px;
    color: var(--black);
    margin-bottom: 3px;
    font-weight: 700;
}

.qv-spec-value{
    font-size: 12px;
    font-weight: 400;
}

.qv-spec-price{
    font-size: 11px;
    color: var(--pink-dark);
    font-weight: 700;
    margin-top: 2px;
}

/* makes an item span the full row (Date, Letter) so it's not squeezed next to unrelated fields */
.qv-spec-full{
    grid-column: 1 / -1;
}

/* forces a new row para hindi magdikit ang Optional Extras sa Date row */
.qv-row-start{
    grid-column-start: 1;
}

.qv-letter-value{
    font-size: 11px;
    line-height: 1.5;
}

.qv-note-box{
    background: var(--pink-soft);
    border: 1px dashed var(--pink);
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 13px;
    color: var(--black);
    line-height: 1.5;
    margin-top: 4px;
}

.qv-notes-row{
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px dashed var(--gray-light);
}


/* PER-FIGURE READ-ONLY TOTAL (Verification page only) — "Price For This Figure" row,
   nested sa loob ng figure panel (sa Quotation, nasa summary sidebar na lang, editable pa).  */

.qv-figure-total-row{
    margin-top: 4px;
    padding-top: 16px;
    border-top: 1px dashed var(--gray-light);
}


/* ---------- REFERENCE IMAGES ---------- */

.qv-reference-row{
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px dashed var(--gray-light);
}

.qv-reference-label{
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11.5px;
    font-weight: 800;
    color: var(--pink-dark);
    margin-bottom: 10px;
}

.qv-reference-thumbs{
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.qv-ref-thumb{
    width: 54px;
    height: 54px;
    border-radius: 10px;
    overflow: hidden;
    background: linear-gradient(150deg, #ffe1ec, #ffc2d6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: var(--pink-dark);
    cursor: pointer;
    border: none;
    padding: 0;
}

.qv-ref-thumb img{
    width: 100%;
    height: 100%;
    object-fit: cover;
}


/* ---------- CUSTOM BOX CARD ---------- */

.qv-box-card{
    background: var(--white);
    border-radius: 20px;
    border: 1.5px solid #C9BEC3;
    box-shadow: 5px 7px 0 rgba(216, 194, 222, .25);
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
}

.qv-box-info-head{
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--pink-dark);
    font-weight: 800;
    font-size: 13px;
    margin-bottom: 14px;
}

.qv-box-spec-grid{
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
    gap: 12px 16px;
}

/* Empty state kapag walang custom box — same "No Custom Box Added" state ng Customer My Orders. */
.qv-box-card-empty{
    border: 1px solid var(--gray-light);
    outline: none;
    box-shadow: none;
    background: var(--white);
    flex: 1;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.qv-box-empty-icon{
    font-size: 32px;
    opacity: .55;
    margin-bottom: 4px;
}

.qv-box-empty-title{
    font-weight: 800;
    color: var(--pink-dark);
    font-size: 13.5px;
    margin-bottom: 4px;
}

.qv-box-empty-text{
    color: var(--gray);
    font-size: 11.5px;
    max-width: 220px;
    line-height: 1.5;
}


/* RIGHT — QUOTATION SUMMARY */

/* May sarili nang container/box ang Verification summary ngayon, kaya tinanggal
   na ang dashed divider — box border/shadow na ang naghihiwalay visually.  */
.qv-summary{
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    background: var(--white);
    border-radius: 18px;
    border: 1px solid var(--gray-light);
    box-shadow: 4px 5px 0 rgba(216, 194, 222, .2);
    margin-top: 6px;
}

.qv-summary-head{
    background: var(--pink-light);
    padding: 14px 18px;
    flex-shrink: 0;
    border-radius: 17px 17px 0 0;
}

.qv-summary-head h2{
    font-size: 16px;
    color: var(--pink-dark);
    font-weight: 700;
}

.qv-summary-body{
    padding: 16px 18px 18px;
}

.qv-line-list{
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 14px;
}

.qv-line{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.qv-line-label{
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--black);
}

.qv-line-icon{
    width: 26px;
    height: 26px;
    border-radius: 8px;
    background: var(--pink-light);
    color: var(--pink-dark);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    flex-shrink: 0;
}

.qv-line-value{
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
}

.qv-line-input-wrap{
    display: flex;
    align-items: center;
    gap: 4px;
    background: var(--pink-soft);
    border: 1.5px solid var(--pink);
    border-radius: 8px;
    padding: 4px 8px;
}

.qv-line-input-wrap span{
    font-size: 12px;
    font-weight: 800;
    color: var(--pink-dark);
}

.qv-line-input{
    width: 84px;
    border: none;
    background: none;
    outline: none;
    font-family: inherit;
    font-size: 13px;
    font-weight: 800;
    color: var(--black);
    text-align: right;
    -moz-appearance: textfield;
}

.qv-line-input::-webkit-outer-spin-button,
.qv-line-input::-webkit-inner-spin-button{
    -webkit-appearance: none;
    margin: 0;
}

.qv-divider{
    border: none;
    border-top: 1px dashed var(--gray-light);
    margin: 14px 0;
}

.qv-total-row{
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: nowrap;
    gap: 10px;
    margin-bottom: 16px;
}

.qv-total-row span{
    font-size: 11.5px;
    font-weight: 800;
    color: var(--pink-dark);
    text-transform: uppercase;
    letter-spacing: .3px;
}

.qv-total-row strong{
    font-size: 20px;
    color: var(--pink-dark);
}

.qv-price-breakdown{
    background: var(--pink-soft);
    border: 1.5px dashed var(--pink);
    border-radius: 14px;
    padding: 14px 16px;
    margin-bottom: 16px;
}

.qv-price-row{
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.qv-price-row span{
    font-size: 11.5px;
    font-weight: 800;
    color: var(--pink-dark);
    text-transform: uppercase;
    letter-spacing: .3px;
}

.qv-price-row strong{
    font-size: 18px;
    color: var(--pink-dark);
}

.qv-price-row-total{
    padding-bottom: 10px;
    border-bottom: 1px dashed var(--pink);
    margin-bottom: 10px;
}

.qv-price-row-downpayment strong{
    font-size: 20px;
}

.qv-validity-note{
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: var(--background);
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 11.5px;
    color: var(--gray);
    margin-bottom: 16px;
}

.qv-validity-note strong{
    display: block;
    color: var(--black);
    font-size: 12px;
    margin-bottom: 2px;
}

.qv-send-btn{
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 999px;
    background: var(--pink);
    color: var(--white);
    font-weight: 800;
    font-size: 13.5px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    transition: .15s ease;
}

.qv-send-btn:hover{ background: var(--pink-dark); }

.qv-summary-hint{
    margin-top: 12px;
    font-size: 10.5px;
    color: var(--gray);
    line-height: 1.5;
}

.qv-locked-value{
    color: var(--pink-dark);
    font-weight: 900;
}

.qv-empty{
    padding: 40px 16px;
    text-align: center;
    color: var(--gray);
    font-size: 13px;
}

/* thin scrollbar for .qv-list and .qv-right-col, the two scrollable panels */
.qv-list::-webkit-scrollbar,
.qv-right-col::-webkit-scrollbar{
    width: 6px;
}

.qv-list::-webkit-scrollbar-thumb,
.qv-right-col::-webkit-scrollbar-thumb{
    background: var(--gray-light);
    border-radius: 999px;
}


/* PART 2: dashboard.css styles, needed here dahil kasama ang staff-sidebar.php */

/* shared dashboard styles ng owner and staff (dati magkahiwalay, kaya nag-drift) */


/*   FIGURIFY — OWNER DASHBOARD
   Pastel / Cute / Modern / Clean */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --pink: #f78fd4;
    --pink-dark: #b23e82;
    --pink-light: #ffc4e8;
    --pink-soft: #fff0f9;

    --black: #29252a;
    --gray: #81777d;
    --gray-light: #eee7ea;

    --white: #ffffff;
    --background: #f8f4f5;

    --green: #65a77e;
    --green-light: #e1f3e7;

    --yellow: #d59b2b;
    --yellow-light: #fff1cc;

    --blue: #6689dd;
    --blue-light: #e8edff;

    --red: #d96b78;
    --red-light: #ffe4e8;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: "Segoe UI", Arial, sans-serif;
    color: var(--black);
    min-height: 100vh;

    background-color: #fffdf3;

    background-image:
        linear-gradient(rgba(214, 198, 229, .25) 1px, transparent 1px),
        linear-gradient(90deg, rgba(214, 198, 229, .25) 1px, transparent 1px);

    background-size: 25px 25px;
}

button,
input,
a {
    font-family: inherit;
}

button,
a {
    cursor: pointer;
}

a {
    text-decoration: none;
    color: inherit;
}

/* APP */

.app {
    min-height: 100vh;
}

/* SIDEBAR */

/* Sidebar styling moved to Shared/dashboard-sidebar.php (single shared component for Owner + Staff) */


.owner-mini {
    background: rgba(255,255,255,.45);

    border: 1px solid rgba(41,37,42,.12);

    padding: 10px;

    border-radius: 14px;

    display: flex;
    align-items: center;

    gap: 9px;
}

.avatar {
    width: 36px;
    height: 36px;

    border-radius: 11px;

    background: white;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 900;
}

.owner-mini strong {
    display: block;
    font-size: 12px;
    max-width: 130px;

    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.owner-mini span {
    display: block;

    font-size: 10px;

    color: #705c64;

    margin-top: 2px;
}

.logout {
    margin-top: 8px;
    color: #713447;
}

/* MAIN */

.main {
    margin-left: 230px;

    width: calc(100% - 230px);

    padding: 22px 22px 38px 14px;
}

/* TOPBAR */

.topbar {
    display: flex;

    align-items: flex-start;
    justify-content: space-between;

    margin-bottom: 16px;
}

.welcome-label {
    color: var(--pink-dark);

    font-size: 9px;

    font-weight: 900;

    letter-spacing: 1.7px;

    margin-bottom: 5px;
}

.welcome h2 {
    font-size: 27px;

    letter-spacing: -1px;
}

.welcome p {
    color: var(--gray);

    font-size: 12px;

    margin-top: 5px;
}

/* DATE TIME */

.datetime {
    display: flex;
    align-items: center;

    gap: 9px;

    margin-top: 10px;

    color: #8b747d;

    font-size: 11px;

    font-weight: 700;
}

.datetime-icon {
    width: 27px;
    height: 27px;

    background: var(--pink-light);

    border-radius: 9px;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 13px;
}

#currentDate {
    color: #765f68;
}

#currentTime {
    color: #a4476d;
    font-weight: 900;
}

.datetime-dot {
    color: #c7aeb8;
}

/* TOP ACTIONS */

.top-actions {
    display: flex;
    align-items: center;

    gap: 9px;
}

.search-box {
    width: 220px;

    background: white;

    border: 1px solid var(--gray-light);

    border-radius: 12px;

    padding: 11px 13px;

    outline: none;

    font-size: 11px;

    transition: .2s;
}

.search-box:focus {
    border-color: var(--pink);

    box-shadow:
        0 0 0 3px var(--pink-light);
}

.notification-btn {
    position: relative;

    width: 42px;
    height: 42px;

    background: white;

    border: 1px solid var(--gray-light);

    border-radius: 12px;

    font-size: 17px;

    transition: .2s;
}

.notification-btn:hover {
    background: var(--pink-soft);
}

.notification-badge {
    position: absolute;

    top: -5px;
    right: -5px;

    min-width: 17px;
    height: 17px;

    padding: 0 4px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: var(--pink);

    color: white;

    border-radius: 50%;

    border: 2px solid var(--background);

    font-size: 8px;
    font-weight: 900;
}

/* Duplicate ".card" rule (mula sa lumang shared CSS) ang dahilan ng gray border
   dati — nag-o-override sa unang definition. Tinanggal na.  */

/* STATISTICS */

.stats {
    display: grid;

    grid-template-columns:
        3fr 1.1fr;

    align-items: start;

    gap: 14px;

    margin-bottom: 18px;
}

.stats-left {
    display: grid;

    grid-template-columns:
        repeat(3,1fr);

    align-items: start;

    gap: 14px;
}

.stat-card {
    position: relative;

    overflow: hidden;
}

.stat-card::after {
    content: "";

    position: absolute;

    width: 80px;
    height: 80px;

    border-radius: 50%;

    background: var(--pink-light);

    right: -28px;
    top: -30px;
}

.approval-card::after {
    background: var(--yellow-light);
}

.stat-label {
    color: var(--black);

    font-size: 10px;

    font-weight: 900;
}

.stat-number {
    font-size: 30px;

    font-weight: 900;

    margin-top: 6px;
}

.stat-trend {
    margin-top: 5px;

    font-size: 10px;

    font-weight: 800;

    color: var(--green);
}

.attention {
    color: var(--yellow);
}

.shipment-stat-card {
    z-index: 1;

    display: flex;

    flex-direction: column;
}

.shipment-stat-card .stat-label {
    margin-bottom: 12px;
}

.shipment-stat-card .shipment-list {
    flex: 1;
}

/*   DASHBOARD LAYOUT (left column: stats + calendar +
   recent bookings / right column: tall shipment card) */

.dashboard-layout {
    display: grid;

    grid-template-columns:
        2.3fr 1fr;

    gap: 18px;

    align-items: stretch;

    margin-bottom: 18px;
}

.dashboard-left {
    display: flex;
    flex-direction: column;

    gap: 18px;
}

.dashboard-right {
    display: flex;

    height: 100%;
}

.dashboard-right .shipment-stat-card {
    flex: 1;

    width: 100%;
    height: 100%;
}

.bottom-row {
    display: grid;

    grid-template-columns:
        1fr 1.2fr;

    gap: 18px;

    align-items: start;
}

/* DASHBOARD GRID */

.dashboard-grid {
    display: grid;

    grid-template-columns:
        1fr 1.2fr;

    gap: 18px;

    align-items: start;
}

/* CARD HEADER */

.card-header {
    display: flex;

    align-items: center;
    justify-content: space-between;

    margin-bottom: 15px;
}

.card-header h3 {
    font-size: 15px;
}

.view-link {
    border: none;

    background: transparent;

    color: #a4476d;

    font-size: 11px;

    font-weight: 800;
}

.view-link:hover {
    text-decoration: underline;
}

/* ORDERS */

.order-row {
    display: grid;

    grid-template-columns:
        1.2fr .9fr .75fr .75fr;

    gap: 8px;

    align-items: center;

    padding: 13px 3px;

    border-top: 1px solid #f0e7eb;

    font-size: 11px;
}

.order-row.header {
    border-top: none;

    color: var(--black);

    font-size: 9px;

    font-weight: 800;

    text-transform: uppercase;
}

.customer {
    display: flex;

    align-items: center;

    gap: 8px;
}

.customer-icon {
    width: 31px;
    height: 31px;

    border-radius: 10px;

    background: #fbe5ed;

    display: flex;
    align-items: center;
    justify-content: center;
}

.customer strong {
    display: block;

    font-size: 11px;
}

.customer small {
    display: block;

    color: #9a8c94;

    font-size: 9px;

    margin-top: 2px;
}

/* STATUS */

.status {
    display: inline-block;

    padding: 5px 8px;

    border-radius: 99px;

    font-size: 9px;

    font-weight: 900;

    white-space: nowrap;
}

/* STATUS COLORS — pare-pareho sa Staff Dashboard, Calendar, at My Orders ng customer.
   Single source: Admin/order_status_helper.php.  */

.status-pending {
    background: #FFF3C4;
    color: #29252A;
}

.status-quoted {
    background: #E1F3E7;
    color: #29252A;
}

.status-awaiting_payment {
    background: #FBE8D6;
    color: #29252A;
    white-space: normal;
    max-width: 64px;
    text-align: center;
    line-height: 1.25;
}

.status-to_verify {
    background: #E1F3E7;
    color: #29252A;
}

.status-for_approval {
    background: #E1F3E7;
    color: #29252A;
}

.status-processing {
    background: #FBE8D6;
    color: #29252A;
}

.status-awaiting_balance {
    background: #FBE8D6;
    color: #29252A;
}

.status-to_verify_balance {
    background: #E1F3E7;
    color: #29252A;
}

.status-for_balance_approval {
    background: #E1F3E7;
    color: #29252A;
}

.status-to_ship {
    background: #E8EDFF;
    color: #29252A;
}

.status-completed {
    background: #E1F3E7;
    color: #29252A;
}

.status-cancelled {
    background: #FADCDC;
    color: #29252A;
}

/* CALENDAR CONTROLS */

.calendar-controls {
    display: flex;

    align-items: center;
    justify-content: space-between;

    margin-bottom: 10px;
}

.calendar-controls button {
    width: 30px;
    height: 30px;

    display: flex;
    align-items: center;
    justify-content: center;

    border: none;

    background: var(--pink-light);

    color: var(--pink-dark);

    border-radius: 50%;

    font-size: 16px;
    line-height: 1;
    font-weight: 900;

    cursor: pointer;

    transition: .2s;
}

.calendar-controls button:hover {
    background: var(--pink);
    color: white;

    transform: scale(1.06);
}

.calendar-controls strong {
    font-size: 12px;
}

/* CALENDAR */

.calendar-week {
    display: grid;

    grid-template-columns:
        repeat(7,1fr);

    gap: 4px;

    margin-bottom: 3px;
}

.calendar-week div {
    text-align: center;

    color: var(--black);

    font-size: 8px;
    font-weight: 900;

    padding: 4px 0;
}

.calendar-days {
    display: grid;

    grid-template-columns:
        repeat(7,1fr);

    gap: 4px;
}

.day {
    height: 30px;

    border-radius: 9px;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 10px;

    transition: .2s;
}

.day:hover {
    background: var(--pink-soft);
    cursor: pointer;
}

.day.empty {
    background: transparent;
}

.day.today {
    background: var(--pink);

    color: var(--black);

    font-weight: 900;
}

.day.booked {
    background: var(--pink-light);

    color: #a4476d;

    font-weight: 800;
}

.day.selected {
    outline: 2px solid var(--pink);
    outline-offset: 1px;
}

/* Dashboard calendar is read-only preview lang, di gaya ng full calendar page — kaya walang hover cursor. */
#calendarDays .day {
    pointer-events: none;
}

#calendarDays .day:hover {
    background: transparent;
    cursor: default;
}

/* CALENDAR LEGEND */

.calendar-legend {
    display: flex;

    gap: 15px;

    margin-top: 13px;

    font-size: 9px;

    color: #95878e;
}

.calendar-legend span {
    display: flex;

    align-items: center;

    gap: 5px;
}

.legend-dot {
    width: 8px;
    height: 8px;

    border-radius: 50%;
}

.today-dot {
    background: var(--pink);
}

.booked-dot {
    background: var(--pink-light);

    border: 1px solid #e8b9ca;
}

/* SHIPMENTS */

.shipment-list {
    display: grid;
    gap: 10px;
}

.shipment {
    display: flex;

    align-items: center;
    justify-content: space-between;

    background: #fff8fa;

    border: 1px solid #f3e4e9;

    padding: 10px 11px;

    border-radius: 12px;
}

.shipment strong {
    font-size: 11px;
}

.shipment small {
    display: block;

    color: #9b8f95;

    font-size: 9px;

    margin-top: 3px;
}

.shipment-icon {
    font-size: 18px;
}

.empty-shipment {
    min-height: 55px;
}

/* OVERVIEW */

.activity-card {
    margin-top: 18px;
}

.online-badge {
    color: var(--green);

    background: var(--green-light);

    padding: 5px 9px;

    border-radius: 99px;

    font-size: 9px;

    font-weight: 900;
}

.overview-grid {
    display: grid;

    grid-template-columns:
        repeat(4,1fr);

    gap: 10px;
}

.overview-box {
    display: flex;

    align-items: center;

    gap: 10px;

    background: var(--pink-soft);

    border: 1px solid #f4e2e8;

    border-radius: 13px;

    padding: 12px;
}

.overview-icon {
    width: 32px;
    height: 32px;

    border-radius: 10px;

    background: white;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 900;

    color: var(--pink-dark);
}

.overview-box strong {
    display: block;

    font-size: 14px;
}

.overview-box small {
    display: block;

    color: #9b8f95;

    font-size: 9px;

    margin-top: 2px;
}

/* QUICK ACTIONS */

.quick-actions {
    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 10px;

    margin-top: 18px;
}

.quick-btn {
    padding: 12px;

    background: var(--pink-soft);

    border: 1px dashed #d9b6c3;

    border-radius: 12px;

    font-size: 10px;

    font-weight: 800;

    transition: .2s;
}

.quick-btn:hover {
    background: var(--pink-light);

    transform: translateY(-1px);
}

/* EMPTY */

.empty-state {
    padding: 30px 10px;

    text-align: center;

    color: #a0959b;

    font-size: 11px;
}

/* MODAL */

.modal-overlay {
    display: none;

    position: fixed;

    inset: 0;

    background: rgba(35,25,30,.38);

    z-index: 999;

    align-items: center;
    justify-content: center;
}

.modal-overlay.show {
    display: flex;
}

.modal {
    width: min(420px,90%);

    background: white;

    border-radius: 20px;

    padding: 24px;

    box-shadow:
        0 25px 70px rgba(0,0,0,.22);

    animation: modalIn .2s ease;
}

@keyframes modalIn {

    from {
        opacity: 0;
        transform:
            translateY(10px)
            scale(.97);
    }

    to {
        opacity: 1;
        transform:
            translateY(0)
            scale(1);
    }
}

.modal-top {
    display: flex;

    justify-content: space-between;

    align-items: center;
}

.modal h3 {
    font-size: 18px;
}

.close-modal {
    width: 30px;
    height: 30px;

    border: none;

    background: var(--pink-light);

    border-radius: 9px;

    font-size: 18px;
}

.modal p {
    color: var(--gray);

    font-size: 12px;

    line-height: 1.6;

    margin: 12px 0 18px;
}

.modal-button {
    border: none;

    background: var(--pink);

    color: var(--black);

    padding: 10px 18px;

    border-radius: 10px;

    font-weight: 800;
}

/* RESPONSIVE */

@media (max-width: 1100px) {

    .stats {
        grid-template-columns: 1fr;
    }

    .dashboard-layout {
        grid-template-columns: 1fr;
    }

    .stats-left {
        grid-template-columns:
            repeat(3,1fr);
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .bottom-row {
        grid-template-columns: 1fr;
    }

    .overview-grid {
        grid-template-columns:
            repeat(2,1fr);
    }
}

@media (max-width: 800px) {

    .sidebar {
        width: 76px;
    }

    .brand {
        padding-left: 0;
        justify-content: center;
    }

    .brand h1,
    .brand small,
    .nav-btn span:last-child,
    .owner-mini > div:not(.avatar) {
        display: none;
    }

    .nav-btn {
        justify-content: center;
    }

    .main {
        margin-left: 76px;

        width: calc(100% - 76px);
    }

    .search-box {
        width: 180px;
    }
}

@media (max-width: 600px) {

    .main {
        padding: 18px;
    }

    .topbar {
        flex-direction: column;

        gap: 15px;
    }

    .top-actions {
        width: 100%;
    }

    .search-box {
        flex: 1;
    }

    .stats-left {
        grid-template-columns:
            1fr 1fr;
    }

    .order-row {
        grid-template-columns:
            1.5fr .8fr .7fr;
    }

    .order-row > div:nth-child(2) {
        display: none;
    }

    .datetime {
        flex-wrap: wrap;
    }

    .overview-grid {
        grid-template-columns: 1fr;
    }
}

/* INNER PAGE */

.page-description {
    color: var(--gray);
    font-size: 11px;
    margin-top: 4px;
}

/* BOOKING TABLE */

.booking-table {
    width: 100%;
    overflow-x: auto;
}

.booking-row {
    min-width: 720px;

    display: grid;

    grid-template-columns:
        .8fr
        1fr
        1fr
        .9fr
        .9fr;

    align-items: center;

    gap: 12px;

    padding: 14px 8px;

    border-top: 1px solid var(--gray-light);

    font-size: 11px;
}

.booking-header {
    border-top: none;

    color: var(--gray);

    font-size: 9px;

    font-weight: 900;

    text-transform: uppercase;
}

.booking-row strong {
    font-size: 11px;
}

/* .completed-row (mula completed.php) — hiwalay sa .booking-row (5 columns lang)
   dahil 6 columns ito (may Customer at Courier).  */

.completed-row {
    min-width: 760px;

    display: grid;

    grid-template-columns:
        .7fr
        1fr
        .8fr
        .9fr
        .8fr
        .8fr;

    align-items: center;

    gap: 12px;

    padding: 14px 8px;

    border-top: 1px solid var(--gray-light);

    font-size: 11px;
}

.completed-header {
    border-top: none;

    color: var(--gray);

    font-size: 9px;

    font-weight: 900;

    text-transform: uppercase;
}

.completed-row strong {
    font-size: 11px;
}

/* FULL CALENDAR */

.full-calendar-card {
    height: fit-content;
}

.calendar-page-controls {
    display: flex;

    align-items: center;

    justify-content: space-between;

    margin: 10px 0 25px;
}

.calendar-page-controls h3 {
    font-size: 18px;
}

.calendar-page-controls button {
    width: 42px;
    height: 42px;

    border: none;

    background: var(--pink-light);

    color: var(--pink-dark);

    border-radius: 12px;

    font-size: 25px;

    font-weight: 900;

    transition: .2s;
}

.calendar-page-controls button:hover {
    background: var(--pink);

    color: white;
}

.full-week,
.full-calendar-days {
    width: 100%;
}

.full-week div {
    font-size: 12px;
    font-weight: 800;
}

.full-calendar-days {
    gap: 6px;
}

.large-day {
    height: 46px;

    border: none;

    border-radius: 11px;

    font-size: 12px;
}

.large-day:hover {
    background: var(--pink-soft);
}

.large-day.today {
    background: var(--pink);
}

.large-day.booked {
    background: var(--pink-light);

    color: var(--pink-dark);
}

/* RESPONSIVE INNER PAGES */

@media (max-width: 800px) {

    .quotation-info {
        grid-template-columns: 1fr;
    }

    .full-calendar-card {
        overflow-x: auto;
    }

    .full-calendar-days,
    .full-week {
        min-width: 600px;
    }

}

</style>

<!-- =====================================================
     Note: dating may sariling "#orderView ..." fallback style
     block dito na gumagamit ng ID selector. Tinanggal na ito
     ngayon nang tuluyan — ang ID selector kasi ay LAGING
     nananalo laban sa class selectors ng quotation.css/
     orders.css kahit maayos naka-load ang mga ito, kaya iba
     lagi ang itsura ng Customer Order tab dito kumpara sa
     Quotation page (hal. 60px vs 70px na thumbnail, hardcoded
     colors sa halip na var(--pink)/var(--pink-dark)). Ang
     Figure Details / Custom Box cards dito ay gumagamit na
     ngayon ng eksaktong parehong "qv-figure-card / qv-box-card"
     markup at classes ng Quotation page (mula sa quotation.css
     na lang, walang sariling override dito), kaya iisa na lang
     ang pinagmumulan ng estilo at palaging magkatugma ang
     dalawang pahina. Ang function/flow (Payment view, Verify/
     Decline, read-only price per figure) ay nananatiling sarili
     ng Verification — magkaiba pa rin sila doon sa Quotation. -->

</head>


<body class="quotation-page">


<div class="app">



<?php require_once $figurifyPageDir . "/" . $figurifyRole . "-sidebar.php"; ?>



<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <!-- TOPBAR -->

    <header class="topbar">

        <div class="welcome">

            <h2>
                Payment Verification
            </h2>

        </div>

    

        <div class="top-actions">

            <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>

        </div>

    </header>


    <!-- =====================================================
         VERIFICATION WORKSPACE — same 3-column shell (order
         list / order detail / summary) as the Quotation page,
         pero ang kanang panel dito ay para sa Verify/Decline
         sa halip na pagbibigay ng presyo.
    ===================================================== -->

    <section class="card qv-workspace" style="padding: 10px 0 20px 0;">

        <div class="qv-shell">


            <!-- ================= LEFT: ORDER LIST ================= -->

            <div class="qv-list-panel">

                <div class="qv-toolbar">

                    <div class="qv-search">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input
                            type="text"
                            id="qvSearchInput"
                            placeholder="Search order ID, name..."
                            oninput="qvFilterOrders(this.value)"
                        >
                    </div>

                </div>

                <?php if (empty($orders)): ?>

                    <div class="qv-empty">
                        Nothing to verify right now.
                    </div>

                <?php else: ?>

                    <div class="qv-list" id="qvOrderList">

                        <table class="qv-table">

                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Email</th>
                                    <th>Order Type</th>
                                    <th>Order Method</th>
                                    <th>Book Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($orders as $order): ?>

                                    <?php
                                    $isActive     = ((int) $order["order_id"] === $selectedOrderId);
                                    $statusColors = figurify_status_colors($order["status"]);
                                    $qvSearchText = strtolower($order["order_id"] . " " . $order["full_name"] . " " . $order["email"]);
                                    $qvRowHref    = "verification.php?order_id=" . (int) $order["order_id"];
                                    ?>

                                    <tr
                                        class="qv-row<?php echo $isActive ? " active" : ""; ?>"
                                        data-href="<?php echo e($qvRowHref); ?>"
                                        data-search="<?php echo e($qvSearchText); ?>"
                                        data-order-id="<?php echo (int) $order["order_id"]; ?>"
                                        data-order-status="<?php echo e($order["status"]); ?>"
                                        onclick="window.location.href = this.dataset.href;"
                                    >

                                        <td class="qv-cell-id">
                                            #<?php echo (int) $order["order_id"]; ?>
                                        </td>

                                        <td><?php echo e($order["email"]); ?></td>

                                        <td>
                                            <?php echo ($order["order_type"] === "rush") ? "Rush" : "Non-Rush"; ?>
                                        </td>

                                        <td>
                                            <?php echo e(orderMethodLabel($order["order_method"])); ?>
                                        </td>

                                        <td>
                                            <?php echo date("M j, Y", strtotime($order["booking_date"])); ?>
                                        </td>

                                        <td>
                                            <?php echo figurify_status_badge($order["status"]); ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                    <div class="qv-list-footer" id="qvListFooter">
                        Showing <?php echo count($orders); ?> of <?php echo count($orders); ?> orders
                    </div>

                <?php endif; ?>

            </div>


            <!-- ================= NO ORDER SELECTED ================= -->

            <?php if (!$selectedOrder): ?>

                <div class="qv-right-col">

                    <div class="qv-detail-col">
                        <div class="qv-header-card">
                            <div class="qv-empty">
                                Select an order on the left to review its payment details.
                            </div>
                        </div>
                    </div>

                    <div class="qv-summary">
                        <div class="qv-summary-head"><h2>Verification Details</h2></div>
                        <div class="qv-summary-body">
                            <div class="qv-empty">Nothing to verify yet.</div>
                        </div>
                    </div>

                </div>

            <?php else: ?>

                    <!-- ================= RIGHT CONTAINER: DETAIL + SUMMARY ================= -->

                    <div class="qv-right-col">

                    <!-- ---- ORDER DETAIL ---- -->

                    <div class="qv-detail-col">

                        <!-- Order Information card removed — Email, Order Type,
                             Order Method, Book Date, and Status are already shown
                             as columns in the order list on the left, same
                             simplification as the Quotation page. -->

                        <!-- Customer Order / Payment toggle inalis na — palagi na
                             lang ngayong nakikita ang buong Figure Details, hindi na
                             kailangang i-toggle. Ang Payment & Shipping Details/Refund
                             details naman ay nasa ibaba na ng Figure Details mismo. -->

                    <!-- ================= CUSTOMER ORDER VIEW (FIGURES) =================
                         Kaparehong "qv-figure-card / qv-box-card" na disenyo ng
                         Quotation page (Figure Details + Custom Box Details,
                         mga larawan, notes) — pero read-only lang dito, dahil
                         iba ang function ng Verification: dito, tinitingnan
                         lang ng staff/owner ang BUONG order bago i-verify ang
                         bayad (walang editable price input tulad ng sa
                         Quotation, may sarili itong "Price For This Figure"
                         na read-only row sa halip). -->

                        <div class="qv-figure-panels">

                            <?php foreach ($selectedOrder["figures"] as $qIndex => $fig): ?>

                                <?php
                                /* try/catch per figure kaya kahit sira yung isa, tuloy pa rin ang iba */
                                try {
                                ob_start();

                                $hasSize = figureHasSize($fig);

                                $styleImg   = figureStyleImage($fig["figure_style"]);
                                $productImg = productTypeImage($fig["figure_style"], $fig["product_type"]);

                                $existingQuote = ($fig["quoted_price"] !== null)
                                    ? (float) $fig["quoted_price"]
                                    : (float) $fig["figure_total"];

                                $panelId = "verify-figure-panel-" . (int) $selectedOrder["order_id"] . "-" . $qIndex;
                                ?>

                                <div
                                    class="qv-figure-panel<?php echo ($qIndex === 0) ? " active" : ""; ?>"
                                    id="<?php echo $panelId; ?>"
                                >

                                    <div class="qv-viewer-grid<?php echo figurify_dressup_for_figure($fig) !== null ? " dressup-grid" : ""; ?>">

                                    <!-- ============ LEFT: FIGURE DETAILS + GALLERY ============ -->

                                    <div class="qv-viewer-column">

                                    <div class="qv-figure-card">

                                        <?php if (count($selectedOrder["figures"]) > 1): ?>

                                            <!-- FIGURE TABS — nasa loob na ng figure card,
                                                 sa itaas ng "Figure Details" heading. -->

                                            <div class="qv-figure-tabs">

                                                <?php foreach ($selectedOrder["figures"] as $tabIndex => $tabFig): ?>

                                                    <button
                                                        type="button"
                                                        class="qv-figure-tab-pill<?php echo ($tabIndex === $qIndex) ? " active" : ""; ?>"
                                                        data-figure-target="verify-figure-panel-<?php echo (int) $selectedOrder["order_id"]; ?>-<?php echo $tabIndex; ?>"
                                                        onclick="selectFigure(this)"
                                                    >
                                                        Figure <?php echo $tabIndex + 1; ?>
                                                    </button>

                                                <?php endforeach; ?>

                                            </div>

                                        <?php endif; ?>

                                        <div class="qv-details-head">
                                            Figure <?php echo $qIndex + 1; ?> Details
                                        </div>

                                        <div class="qv-figure-body">

                                            <!-- FIGURE SPECS — kaparehong "qv-detail-item-card"
                                                 na disenyo ng Quotation page. -->

                                            <div class="qv-detail-item-list">

                                                <div class="qv-detail-item-card">

                                                    <?php if ($styleImg): ?>
                                                        <img
                                                            class="qv-detail-item-image"
                                                            src="<?php echo e($siteBase . "Image/" . $styleImg); ?>"
                                                            alt="<?php echo e($fig["figure_style"]); ?>"
                                                        >
                                                    <?php else: ?>
                                                        <div class="qv-detail-item-image qv-detail-item-noimage">🧍</div>
                                                    <?php endif; ?>

                                                    <div class="qv-detail-item-body">
                                                        <span class="qv-detail-item-label">Figure Style</span>
                                                        <span class="qv-detail-item-value"><?php echo e($fig["figure_style"]); ?></span>
                                                    </div>

                                                </div>

                                                <div class="qv-detail-item-card">

                                                    <?php if ($productImg): ?>
                                                        <img
                                                            class="qv-detail-item-image"
                                                            src="<?php echo e($siteBase . "Image/" . $productImg); ?>"
                                                            alt="<?php echo e($fig["product_type"]); ?>"
                                                        >
                                                    <?php else: ?>
                                                        <div class="qv-detail-item-image qv-detail-item-noimage">📦</div>
                                                    <?php endif; ?>

                                                    <div class="qv-detail-item-body">
                                                        <span class="qv-detail-item-label">Product Type</span>
                                                        <span class="qv-detail-item-value"><?php echo e($fig["product_type"]); ?></span>
                                                    </div>

                                                    <div class="qv-detail-item-price<?php echo $hasSize ? " qv-detail-item-price-muted" : ""; ?>">
                                                        <?php echo $hasSize ? "" : "₱" . number_format($fig["product_price"], 2); ?>
                                                    </div>

                                                </div>

                                                <div class="qv-detail-item-card">

                                                    <div class="qv-detail-item-image qv-detail-item-noimage">📏</div>

                                                    <div class="qv-detail-item-body">
                                                        <span class="qv-detail-item-label">Size</span>
                                                        <span class="qv-detail-item-value">
                                                            <?php echo $hasSize ? e($fig["size_label"]) : "No size required"; ?>
                                                        </span>
                                                    </div>

                                                    <div class="qv-detail-item-price<?php echo $hasSize ? "" : " qv-detail-item-price-muted"; ?>">
                                                        <?php echo $hasSize ? "₱" . number_format($fig["product_price"], 2) : ""; ?>
                                                    </div>

                                                </div>

                                                <?php if (figurify_dressup_for_figure($fig) === null): /* walang Figure Name sa Dress Up */ ?>
                                                <div class="qv-detail-item-card">

                                                    <div class="qv-detail-item-image qv-detail-item-noimage">🏷️</div>

                                                    <div class="qv-detail-item-body">
                                                        <span class="qv-detail-item-label">Figure Name</span>
                                                        <span class="qv-detail-item-value"><?php echo !empty($fig["figure_name"]) ? e($fig["figure_name"]) : "None"; ?></span>
                                                    </div>

                                                    <div class="qv-detail-item-price<?php echo ((float) $fig["name_fee"] > 0) ? "" : " qv-detail-item-price-muted"; ?>">
                                                        <?php echo ((float) $fig["name_fee"] > 0) ? "₱" . number_format($fig["name_fee"], 2) : ""; ?>
                                                    </div>

                                                </div>
                                                <?php endif; ?>

                                            </div>
                                            <!-- /.qv-detail-item-list -->

                                            <!-- REFERENCE PHOTOS -->

                                            <?php if (figurify_dressup_for_figure($fig) !== null): ?>
                                                <?php figurify_render_dressup_design($fig, "staff"); ?>
                                            <?php else: ?>
                                            <div class="qv-reference-row">

                                                <div class="qv-reference-label">
                                                    <?php
                                                    $imgCount = count($fig["images"]);
                                                    echo $imgCount > 0
                                                        ? $imgCount . " Reference " . ($imgCount === 1 ? "Photo" : "Photos")
                                                        : "No Reference Photos Uploaded";
                                                    ?>
                                                </div>

                                                <?php if (!empty($fig["images"])): ?>

                                                    <div class="qv-reference-thumbs">

                                                        <?php foreach ($fig["images"] as $imgIndex => $imgPath): ?>

                                                            <?php $fullImg = $siteBase . $imgPath; ?>

                                                            <button
                                                                type="button"
                                                                class="qv-ref-thumb"
                                                                onclick="openImageLightbox('<?php echo e($fullImg); ?>', '<?php echo e($fig["figure_name"]); ?>')"
                                                            >
                                                                <img
                                                                    src="<?php echo e($fullImg); ?>"
                                                                    alt="<?php echo e($fig["figure_name"]); ?> — photo <?php echo $imgIndex + 1; ?>"
                                                                >
                                                            </button>

                                                        <?php endforeach; ?>

                                                    </div>

                                                <?php endif; ?>

                                            </div>
                                            <?php endif; ?>

                                        </div>
                                        <!-- /.qv-figure-body -->

                                        <?php if (!empty($fig["notes"])): ?>

                                            <div class="qv-notes-row">
                                                <div class="qv-spec-label">Customer Notes</div>
                                                <div class="qv-note-box"><?php echo nl2br(e($fig["notes"])); ?></div>
                                            </div>

                                        <?php endif; ?>

                                    </div>
                                    <!-- /.qv-figure-card -->

                                    </div>
                                    <!-- /.qv-viewer-column (figure details) -->


                                    <!-- ============ RIGHT: CUSTOM BOX DETAILS ============ -->

                                    <div class="qv-viewer-column">

                                    <?php if ($fig["box_addon_type"] === "funko_box"): ?>

                                        <div class="qv-box-card">

                                            <div class="qv-box-info-head">🎁 Custom Box Details</div>

                                            <div class="qv-box-spec-grid">

                                                <div>
                                                    <div class="qv-spec-label">Box Type</div>
                                                    <div class="qv-spec-value">
                                                        <?php echo $fig["funko_box_type"] === "solo" ? "Solo Box" : "Couple Box"; ?>
                                                    </div>
                                                    <div class="qv-spec-price">₱<?php echo number_format($fig["box_addon_price"], 2); ?></div>
                                                </div>

                                                <div>
                                                    <div class="qv-spec-label">Name on Box</div>
                                                    <div class="qv-spec-value"><?php echo e($fig["funko_box_name"]); ?></div>
                                                </div>

                                                <div>
                                                    <div class="qv-spec-label">Box Number</div>
                                                    <div class="qv-spec-value"><?php echo e($fig["funko_box_number"]); ?></div>
                                                </div>

                                                <div>
                                                    <div class="qv-spec-label">Box Color</div>
                                                    <div class="qv-spec-value"><?php echo e($fig["funko_box_color"]); ?></div>
                                                </div>

                                            </div>

                                        </div>

                                    <?php elseif ($fig["box_addon_type"] === "hirono_blind_box"): ?>

                                        <?php
                                        $blindImg  = hironoBlindTypeImage($fig["hirono_blind_type"]);
                                        $designImg = hironoBoxDesignImage($fig["hirono_box_design"]);
                                        ?>

                                        <div class="qv-box-card">

                                            <div class="qv-box-info-head">🎁 Custom Box Details</div>

                                            <div class="qv-box-spec-grid">

                                                <div>
                                                    <div class="qv-spec-label">Box Type</div>
                                                    <div class="qv-spec-value">
                                                        <?php echo $fig["hirono_blind_type"] === "regular" ? "Regular Blind Box" : "Blind Box Set"; ?>
                                                    </div>
                                                    <div class="qv-spec-price">₱<?php echo number_format(hironoBaseBoxPrice($fig), 2); ?></div>
                                                </div>

                                                <?php if (!empty($fig["hirono_box_design"])): ?>
                                                    <div>
                                                        <div class="qv-spec-label">Box Design</div>
                                                        <div class="qv-spec-value"><?php echo e(hironoBoxDesignLabel($fig["hirono_box_design"])); ?></div>
                                                    </div>
                                                <?php endif; ?>

                                                <div>
                                                    <div class="qv-spec-label">Box Color</div>
                                                    <div class="qv-spec-value"><?php echo e($fig["hirono_box_color"]); ?></div>
                                                </div>

                                                <div>
                                                    <div class="qv-spec-label">Nickname</div>
                                                    <div class="qv-spec-value"><?php echo e($fig["hirono_nickname"]); ?></div>
                                                </div>

                                                <div>
                                                    <div class="qv-spec-label">Date</div>
                                                    <div class="qv-spec-value">
                                                        <?php
                                                        echo !empty($fig["hirono_date"])
                                                            ? date("F j", strtotime($fig["hirono_date"]))
                                                            : e($fig["hirono_date"]);
                                                        ?>
                                                    </div>
                                                </div>

                                                <?php foreach ($fig["blind_items_list"] as $extraIndex => $extra): ?>
                                                    <div<?php echo ($extraIndex === 0) ? ' class="qv-row-start"' : ''; ?>>
                                                        <div class="qv-spec-label">Optional Extra</div>
                                                        <div class="qv-spec-value"><?php echo e($extra["label"]); ?></div>
                                                        <div class="qv-spec-price">₱<?php echo number_format($extra["price"], 2); ?></div>
                                                    </div>
                                                <?php endforeach; ?>

                                                <div class="qv-spec-full">
                                                    <div class="qv-spec-label">Letter</div>
                                                    <div class="qv-spec-value qv-letter-value"><?php echo nl2br(e($fig["hirono_letter"])); ?></div>
                                                </div>

                                            </div>

                                            <?php if (!empty($fig["box_images"])): ?>

                                                <div class="qv-reference-row">

                                                    <div class="qv-reference-label">Box Reference Images</div>

                                                    <div class="qv-reference-thumbs">

                                                        <?php foreach ($fig["box_images"] as $boxImgIndex => $boxImgPath): ?>

                                                            <?php $fullBoxImg = $siteBase . $boxImgPath; ?>

                                                            <button
                                                                type="button"
                                                                class="qv-ref-thumb"
                                                                onclick="openImageLightbox('<?php echo e($fullBoxImg); ?>', 'Box Reference Image')"
                                                            >
                                                                <img
                                                                    src="<?php echo e($fullBoxImg); ?>"
                                                                    alt="Box reference photo <?php echo $boxImgIndex + 1; ?>"
                                                                >
                                                            </button>

                                                        <?php endforeach; ?>

                                                    </div>

                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    <?php else: ?>

                                        <!-- Walang box add-on na pinili ang customer para sa
                                             figure na ito — ipinapakita pa rin ang card, pero
                                             walang laman, para malinaw na "wala" ito, kagaya
                                             ng empty state sa Quotation page. -->

                                        <div class="qv-box-card qv-box-card-empty">

                                            <div class="qv-box-empty-icon">📦</div>

                                            <div class="qv-box-empty-title">
                                                No Custom Box Added
                                            </div>

                                            <p class="qv-box-empty-text">
                                                This figure was ordered without a custom
                                                box add-on.
                                            </p>

                                        </div>

                                    <?php endif; ?>

                                    </div>
                                    <!-- /.qv-viewer-column (custom box) -->


                                        <?php if (figurify_dressup_for_figure($fig) !== null): ?>
                                            <!-- DRESS UP: 3D figure, katabi ng Figure Details -->
                                            <div class="qv-viewer-column dressup-3d-column">
                                                <?php figurify_render_dressup_viewer($fig); ?>
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                    <!-- /.qv-viewer-grid -->

                                </div>
                                <!-- /.qv-figure-panel -->

                                <?php
                                /* no error, print the buffered HTML */
                                echo ob_get_clean();

                                } catch (\Throwable $figureRenderError) {

                                    /* error na, drop the half-built HTML */
                                    if (ob_get_level() > 0) {
                                        ob_end_clean();
                                    }
                                    ?>

                                    <div
                                        class="qv-figure-panel<?php echo ($qIndex === 0) ? " active" : ""; ?>"
                                        id="verify-figure-panel-<?php echo (int) $selectedOrder["order_id"]; ?>-<?php echo $qIndex; ?>"
                                    >
                                        <div class="staff-payment-details">
                                            <div class="staff-payment-details-title">
                                                ⚠ May problema sa pagpapakita ng figure na ito (#<?php echo (int) ($fig["figure_id"] ?? 0); ?>)
                                            </div>
                                            <div class="staff-payment-details-grid">
                                                <span>
                                                    <?php echo e($figureRenderError->getMessage()); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <?php
                                }
                                ?>

                            <?php endforeach; ?>

                        </div>
                        <!-- /.qv-figure-panels -->


                    <!-- ================= PAYMENT & SHIPPING DETAILS + REFUND/RETURN DETAILS =================
                         Nasa ilalim na ito ng Figure Details (hindi na naka-toggle
                         sa likod ng "Payment" button) — laging nakikita na. -->


                        <!-- ================= PAYMENT & SHIPPING DETAILS ================= -->

                        <div class="staff-payment-details">

                            <div class="staff-payment-details-title">
                                Customer's Payment &amp; Shipping Details
                            </div>

                            <div class="staff-payment-details-grid">

                                <span>
                                    <strong>Ship To:</strong>
                                    <?php echo e($selectedOrder["shipping_name"]); ?>
                                </span>

                                <span>
                                    <strong>Address:</strong>
                                    <?php echo e($selectedOrder["shipping_address"]); ?>
                                </span>

                                <span>
                                    <strong>Contact:</strong>
                                    <?php echo e($selectedOrder["shipping_contact"]); ?>
                                </span>

                                <span>
                                    <strong>Courier:</strong>
                                    <?php echo e(strtoupper((string) ($selectedOrder["courier"] ?? ""))); ?>
                                </span>

                                <span>
                                    <strong>Reference No.:</strong>
                                    <?php echo e($selectedOrder["payment_reference"]); ?>
                                </span>

                                <?php if (!empty($selectedOrder["paid_at"])): ?>
                                    <span>
                                        <strong>Submitted:</strong>
                                        <?php echo date("F j, Y — g:i A", strtotime($selectedOrder["paid_at"])); ?>
                                    </span>
                                <?php endif; ?>

                            </div>

                            <?php if (!empty($selectedOrder["payment_proof"])): ?>

                                <div class="staff-payment-proof">

                                    <div class="staff-payment-proof-title">
                                        Proof of Payment
                                    </div>

                                    <img
                                        src="<?php echo $siteBase . e($selectedOrder["payment_proof"]); ?>"
                                        alt="Proof of payment for order #<?php echo (int) $selectedOrder["order_id"]; ?>"
                                        class="staff-payment-proof-thumb"
                                        onclick="openImageLightbox('<?php echo $siteBase . e($selectedOrder["payment_proof"]); ?>', 'Proof of Payment — Order #<?php echo (int) $selectedOrder["order_id"]; ?>')"
                                    >

                                </div>

                            <?php endif; ?>

                        </div>


                        <!-- ================= REFUND / RETURN DETAILS ================= -->

                        <div class="staff-payment-details">

                            <div class="staff-payment-details-title">
                                Customer's Refund / Return Details
                            </div>

                            <div class="staff-payment-details-grid">

                                <span>
                                    <strong>Account Name:</strong>
                                    <?php echo e($selectedOrder["refund_account_name"]); ?>
                                </span>

                                <span>
                                    <strong>Account Number:</strong>
                                    <?php echo e($selectedOrder["refund_account_number"]); ?>
                                </span>

                                <span>
                                    <strong>Preferred Bank/Method:</strong>
                                    <?php echo e($selectedOrder["refund_method"]); ?>
                                </span>

                            </div>

                        </div>


                    </div>
                    <!-- /.qv-detail-col -->


                    <!-- ================= RIGHT: VERIFICATION SUMMARY ================= -->

                    <div class="qv-summary">

                        <div class="qv-summary-head">
                            <h2>Verification Details</h2>
                        </div>

                        <div class="qv-summary-body">

                            <div class="qv-price-breakdown">

                                <div class="qv-price-row qv-price-row-total">
                                    <span>Total Price</span>
                                    <strong>₱<?php echo number_format($selectedOrder["total_amount"], 2); ?></strong>
                                </div>

                                <div class="qv-price-row qv-price-row-downpayment">
                                    <span>50% Downpayment</span>
                                    <strong>₱<?php echo number_format($selectedOrder["total_amount"] / 2, 2); ?></strong>
                                </div>

                            </div>

                            <div class="qv-validity-note">
                                <span>⚠️</span>
                                <div>
                                    <strong>Check the reference number and proof of payment</strong>
                                    against what the customer submitted before deciding.
                                    <?php
                                    echo ($figurifyRole === "owner")
                                        ? "Verifying moves this order straight to Processing."
                                        : "Verifying here sends it to the owner for final approval.";
                                    ?>
                                </div>
                            </div>

                            <?php
                            $verifySource     = "to_verify";
                            $declineModalId   = "declineModal_to_verify";
                            $approveConfirm   = ($figurifyRole === "owner") ? "Approve this payment and move the order to Processing?" : "Verify this payment and send it to the owner for final approval?";
                            $declineConfirmMsg = "Reject this payment and cancel the order? This cannot be undone.";
                            ?>

                            <div class="verify-actions" style="margin: 0; padding-bottom: 0; border-bottom: none;">

                                <form
                                    method="POST"
                                    action="verify_order.php"
                                    onsubmit="return confirm(<?php echo json_encode($approveConfirm); ?>);"
                                >

                                    <input type="hidden" name="order_id" value="<?php echo (int) $selectedOrder["order_id"]; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="source" value="<?php echo e($verifySource); ?>">

                                    <button type="submit" class="verify-btn verify-btn-approve">
                                        Verify
                                    </button>

                                </form>

                                <form
                                    method="POST"
                                    action="verify_order.php"
                                    class="decline-reason-form"
                                    onsubmit="return figurifyConfirmDecline(this, <?php echo json_encode($declineConfirmMsg); ?>);"
                                >

                                    <input type="hidden" name="order_id" value="<?php echo (int) $selectedOrder["order_id"]; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="source" value="<?php echo e($verifySource); ?>">

                                    <button type="button" class="verify-btn verify-btn-reject" onclick="figurifyOpenDeclineModal('<?php echo $declineModalId; ?>')">
                                        Decline
                                    </button>

                                    <div class="decline-modal-overlay" id="<?php echo $declineModalId; ?>">
                                        <div class="decline-modal-box">
                                            <h3 class="decline-modal-title">Decline Order</h3>
                                            <p class="decline-modal-desc">
                                                Pick a reason. This will cancel the order and notify the customer.
                                            </p>

                                            <select
                                                name="decline_reason"
                                                class="decline-reason-select"
                                                required
                                                onchange="figurifyToggleDeclineOther(this)"
                                            >
                                                <option value="" disabled selected>Select a reason…</option>
                                                <option value="incomplete_order_details">Incomplete Order Details</option>
                                                <option value="insufficient_down_payment">Insufficient Down Payment</option>
                                                <option value="no_down_payment_received">No Down Payment Received</option>
                                                <option value="unable_to_fulfill">Unable to Fulfill the Order</option>
                                                <option value="other">Other (please specify)</option>
                                            </select>

                                            <textarea
                                                name="decline_reason_other"
                                                class="decline-reason-other"
                                                placeholder="Type the reason…"
                                                maxlength="190"
                                                style="display:none;"
                                            ></textarea>

                                            <div class="decline-modal-actions">
                                                <button type="button" class="decline-modal-btn decline-modal-btn-cancel" onclick="figurifyCloseDeclineModal('<?php echo $declineModalId; ?>')">Cancel</button>
                                                <button type="submit" class="decline-modal-btn decline-modal-btn-send">Send</button>
                                            </div>
                                        </div>
                                    </div>

                                </form>

                            </div>

                            <p class="qv-summary-hint">
                                <?php
                                echo ($figurifyRole === "owner")
                                    ? "Declining will cancel the order and notify the customer."
                                    : "Declining will cancel the order right away, so double-check the reference number first.";
                                ?>
                            </p>

                        </div>

                    </div>
                    <!-- /.qv-summary -->

                    </div>
                    <!-- /.qv-right-col -->

            <?php endif; ?>

        </div>
        <!-- /.qv-shell -->

    </section>


</main>

</div>


<!-- =========================================================
     IMAGE LIGHTBOX (click any figure photo to enlarge)
========================================================= -->

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

// decline pop-up — checked bago i-submit: kung "Other" ang reason, dapat may laman ang textbox
function figurifyConfirmDecline(form, message) {
    var select = form.querySelector(".decline-reason-select");
    if (select && select.value === "other") {
        var other = form.querySelector(".decline-reason-other");
        if (other && other.value.trim() === "") {
            alert("Please type the reason.");
            other.focus();
            return false;
        }
    }
    return confirm(message);
}

// buksan ang decline pop-up (Decline button)
function figurifyOpenDeclineModal(modalId) {
    var overlay = document.getElementById(modalId);
    if (overlay) {
        overlay.classList.add("active");
    }
}

// isara ang decline pop-up (Cancel button, walang isu-submit)
function figurifyCloseDeclineModal(modalId) {
    var overlay = document.getElementById(modalId);
    if (overlay) {
        overlay.classList.remove("active");
    }
}

// ipakita/itago ang textbox pag pinili/tinanggal ang "Other" sa dropdown
function figurifyToggleDeclineOther(select) {
    var textarea = select.closest("form").querySelector(".decline-reason-other");
    if (!textarea) {
        return;
    }
    if (select.value === "other") {
        textarea.style.display = "block";
        textarea.required = true;
    } else {
        textarea.style.display = "none";
        textarea.required = false;
        textarea.value = "";
    }
}

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


/* SWITCH WHICH FIGURE'S DETAILS ARE SHOWN (kapag may 2+ figure) — same pattern ng My Orders. */

function selectFigure(button) {

    var panelsWrap = button.closest(".qv-figure-panels");

    if (!panelsWrap) {
        return;
    }

    var targetId = button.dataset.figureTarget;

    /* each figure panel has its own tab copies, sync "active" across all of them */
    panelsWrap
        .querySelectorAll(".qv-figure-tab-pill")
        .forEach(function (tab) {
            tab.classList.toggle("active", tab.dataset.figureTarget === targetId);
        });

    panelsWrap
        .querySelectorAll(".qv-figure-panel")
        .forEach(function (panel) {
            panel.classList.toggle("active", panel.id === targetId);
        });

}


/* CUSTOMER ORDER / PAYMENT VIEW TOGGLE */

/* LEFT PANEL — quick client-side search, same pattern ng Quotation page. */

function qvFilterOrders(query) {

    var q = query.trim().toLowerCase();
    var cards = document.querySelectorAll("#qvOrderList .qv-row");
    var visibleCount = 0;

    cards.forEach(function (card) {

        var matches = card.dataset.search.indexOf(q) !== -1;

        card.classList.toggle("qv-hidden", !matches);

        if (matches) {
            visibleCount++;
        }

    });

    var footer = document.getElementById("qvListFooter");

    if (footer) {
        footer.textContent = "Showing " + visibleCount + " of " + cards.length + " orders";
    }

}


/* KEEP SCROLL POSITION ACROSS ORDER CLICKS — normal navigation reloads the page,
   kaya i-save/restore ang scroll ng left at right panels, same pattern ng Quotation.  */

(function () {

    var panels = [
        { el: document.getElementById("qvOrderList"), key: "qvOrderListScroll" },
        { el: document.querySelector(".qv-right-col"), key: "qvDetailScroll" }
    ];

    panels.forEach(function (panel) {

        if (!panel.el) {
            return;
        }

        var savedScroll = sessionStorage.getItem(panel.key);

        if (savedScroll !== null) {
            panel.el.scrollTop = parseInt(savedScroll, 10) || 0;
        }

    });

    document.querySelectorAll("#qvOrderList .qv-row").forEach(function (card) {
        card.addEventListener("click", function () {

            panels.forEach(function (panel) {

                if (panel.el) {
                    sessionStorage.setItem(panel.key, panel.el.scrollTop);
                }

            });

        });
    });

})();


function switchDetailView(view) {

    var orderView   = document.getElementById("orderView");
    var paymentView = document.getElementById("paymentView");

    var orderBtn   = document.getElementById("orderViewBtn");
    var paymentBtn = document.getElementById("paymentViewBtn");

    if (!orderView || !paymentView || !orderBtn || !paymentBtn) {
        return;
    }

    var showOrder = (view === "order");

    orderView.classList.toggle("active", showOrder);
    paymentView.classList.toggle("active", !showOrder);

    orderBtn.classList.toggle("active", showOrder);
    paymentBtn.classList.toggle("active", !showOrder);

}

</script>

</body>
</html>