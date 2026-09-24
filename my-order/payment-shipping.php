<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/order_status_helper.php";


/* must be logged in */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../Login/Login.php");
    exit();
}

$user_id  = $_SESSION["user_id"];
$order_id = isset($_GET["order_id"]) ? (int) $_GET["order_id"] : 0;


/* get this order — must belong to this customer */

$stmt = $conn->prepare(
    "SELECT order_id, total_amount, status
     FROM orders
     WHERE order_id = ? AND user_id = ?"
);

$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();
$order   = $result->fetch_assoc();

$stmt->close();
$conn->close();


/* invalid or not awaiting_payment anymore -> balik sa My Orders na may notice banner */

if (!$order) {
    header("Location: my-orders.php?notice=order_not_found");
    exit();
}

if ($order["status"] !== "awaiting_payment") {

    /* nakabayad na (hal. pinindot ang Back pagkatapos magbayad, o na-double
       click ang Submit) — diretso na lang sa order, walang babala */
    $notYetPayableStatuses = ["pending", "quoted", "cancelled"];

    if (!in_array(figurify_status_key($order["status"]), $notYetPayableStatuses, true)) {
        header("Location: my-orders.php?order_id=" . $order_id);
        exit();
    }

    header("Location: my-orders.php?order_id=" . $order_id . "&notice=payment_unavailable");
    exit();
}

$hasError = isset($_GET["error"]) && $_GET["error"] === "1";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="../Image/logoclay.png">

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Payment & Shipping Details — Clay and Stuff</title>

<link rel="stylesheet" href="my-orders.css">
<style>
/* just styling here, field names/ids match what submit_payment.php expects */

.payment-shipping-card{
    max-width: 1300px;
    margin: 0 auto;
}



/* step header */

.section-header{
    display: flex;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 22px;
}

.section-icon{
    width: 40px;
    height: 40px;

    flex-shrink: 0;

    border-radius: 50%;

    background: #fff0fa;
    border: 2px solid #f2bad8;

    display: flex;
    align-items: center;
    justify-content: center;

    color: #ec83b8;
    font-size: 20px;
}

.step-label{
    display: block;
    color: #bd5c8d;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 2px;
    text-transform: uppercase;
}

.section-header h2{
    margin-top: 2px;
    color: #8c3764;
    font-family: Georgia, serif;
    font-size: 30px;
    line-height: 1.1;
}

.section-header p{
    margin-top: 5px;
    color: #956d80;
    font-size: 13px;
}

.payment-shipping-section-header{
    max-width: 1300px;
    margin-left: auto;
    margin-right: auto;
    padding-top: 22px;
}

.payment-shipping-error-banner{
    max-width: 1300px;
    margin: 0 auto 16px;

    padding: 12px 16px;

    background: #ffe4e8;
    color: #a4394a;
    border: 1px solid #f0b8c2;
    border-radius: 14px;

    font-size: 12px;
    font-weight: 800;
}


/* 3-column card grid (card-box style) */

.payment-shipping-grid{
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
    align-items: stretch;
    margin-bottom: 22px;
}

.payment-shipping-container{
    background: rgba(255,255,255,.98);
    border: 2px solid #f0e1ec;
    border-radius: 20px;
    padding: 22px;

    display: flex;
    flex-direction: column;
    gap: 14px;

    box-shadow: 0 4px 12px rgba(215,192,221,.15);
}

.payment-details-container{
    border-color: #cfe0f5;
}

.refund-details-container{
    border-color: #dcebd0;
}

/* card header: icon + title + subtitle */

.ps-card-header{
    display: flex;
    align-items: flex-start;
    gap: 12px;

    padding-bottom: 14px;
    margin-bottom: 2px;
    border-bottom: 1.5px dashed #f0e1ec;
}

.ps-card-icon{
    width: 36px;
    height: 36px;
    flex-shrink: 0;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;
    background: #fff0fa;
    border: 2px solid #f2bad8;
    color: #ec83b8;
    font-size: 18px;
}

.payment-details-container .ps-card-icon{
    background: #f0f6ff;
    border-color: #bcd6f2;
    color: #5085c9;
}

.refund-details-container .ps-card-icon{
    background: #f3faec;
    border-color: #c3e0ac;
    color: #6fa34e;
}

.ps-card-header-text h3{
    color: #8c3764;
    font-family: Georgia, serif;
    font-size: 16px;
    font-weight: normal;
}

.payment-details-container .ps-card-header-text h3{
    color: #3f5f8f;
}

.refund-details-container .ps-card-header-text h3{
    color: #4c7a37;
}

.ps-card-header-text p{
    color: #956d80;
    font-size: 11px;
    margin-top: 2px;
}

.refund-note{
    padding: 10px 12px;

    background: #fdfff9;
    border: 1.5px dashed #c3e0ac;
    border-radius: 12px;

    font-size: 11px;
    line-height: 1.6;
    color: #4c7a37;
}

.payment-total-box{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;

    padding: 12px 14px;

    background: #fbfdff;
    border: 1.5px dashed #bcd6f2;
    border-radius: 12px;

    font-size: 11px;
    font-weight: 700;
    color: #3f5f8f;
}

.payment-total-box strong{
    font-size: 17px;
    color: #2a4d7c;
}


/* address stack (2 separate lines) */

.address-stack{
    display: flex;
    flex-direction: column;
    gap: 8px;
}


/* notice boxes */

.notice-box{
    display: flex;
    align-items: center;
    gap: 10px;

    background: #fff5f8;
    border: 1.5px solid #f9d5e3;
    border-radius: 12px;
    padding: 10px 12px;

    font-size: 11px;
    color: #a04a78;
}

.notice-box-warning{
    background: #fff0f3;
    border-color: #f4c2d2;
    color: #8c3764;
}

.secure-notice{
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;

    font-size: 10px;
    color: #956d80;
    text-align: center;
    margin-top: 2px;
}


/* price breakdown (total price / 50% downpayment) */

.price-breakdown-list{
    background: #fbfdff;
    border: 1.5px dashed #bcd6f2;
    border-radius: 14px;
    padding: 14px 16px;
}

.price-breakdown-top{
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}

.price-order-number{
    font-size: 10px;
    font-weight: bold;
    color: #5085c9;
    letter-spacing: 1px;
}

.price-bag-icon{
    font-size: 16px;
}

.price-row{
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: #3f5f8f;
}

.price-row.total-row{
    font-weight: bold;
    font-size: 15px;
    color: #2a4d7c;
    border-top: 1px dashed #bcd6f2;
    padding-top: 10px;
    margin-top: 2px;
}

.price-row.downpayment-row{
    font-weight: bold;
    font-size: 15px;
    color: #d96b9c;
    border-top: 1px solid #bcd6f2;
    padding-top: 10px;
    margin-top: 10px;
}

@media (max-width: 900px){

    .payment-shipping-grid{
        grid-template-columns: 1fr;
    }

}


/* payment proof upload */

.payment-proof-upload{
    position: relative;
}

.payment-proof-preview{
    display: flex;
    align-items: center;
    justify-content: center;

    min-height: 140px;

    padding: 18px;

    background: #fff8fc;
    border: 2px dashed #d17ca7;
    border-radius: 14px;

    text-align: center;

    overflow: hidden;

    transition: .15s;
}

.payment-proof-upload:hover .payment-proof-preview{
    background: #ffe7f5;
}

.payment-proof-placeholder{
    color: #a04a78;
    font-size: 11px;
    line-height: 1.6;
}

.payment-proof-preview img{
    max-width: 100%;
    max-height: 200px;
    border-radius: 10px;
    object-fit: contain;
}

.payment-proof-upload input[type="file"]{
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}

.payment-form{
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.payment-form-group{
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.payment-form-group label{
    font-size: 11px;
    font-weight: bold;
    color: #86365f;
    letter-spacing: .3px;
}

.payment-details-container .payment-form-group label{
    color: #3f5f8f;
}

.refund-details-container .payment-form-group label{
    color: #4c7a37;
}

.payment-form-group input,
.payment-form-group select,
.payment-form-group textarea{
    padding: 10px 14px;
    border-radius: 12px;
    border: 1px solid #e5d5df;
    background: #fffdf9;
    font-size: 13px;
    font-family: inherit;
    color: #75445e;
    outline: none;
    transition: .2s;
}

.payment-form-group input::placeholder,
.payment-form-group textarea::placeholder{
    color: #bfa8b6;
}

.payment-form-group input:focus,
.payment-form-group select:focus,
.payment-form-group textarea:focus{
    border-color: #f2a4ca;
    background: white;
    box-shadow: 0 0 0 3px rgba(242,164,202,.2);
}

.payment-form-hint{
    font-size: 11px;
    /* darkened para pumasa sa contrast check (WCAG AA) */
    color: #8a5568;
    font-style: italic;
}

.payment-submit-wrapper{
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;

    border-top: 1.5px dashed #f0e1ec;
    margin-top: 6px;
    padding-top: 22px;
}

/* required checkbox, links to actual Terms/Refund pages */
.payment-terms-group{
    display: flex;
    align-items: flex-start;
    gap: 8px;
    max-width: 420px;
    text-align: left;
}

.payment-terms-group input[type="checkbox"]{
    width: 16px;
    height: 16px;
    margin-top: 2px;
    flex-shrink: 0;
    cursor: pointer;
    accent-color: #c96b9c;
}

.payment-terms-group label{
    font-size: 12px;
    color: #6b4c5c;
    line-height: 1.5;
    cursor: pointer;
}

.payment-terms-group a{
    color: #c96b9c;
    font-weight: 600;
    text-decoration: underline;
}

.payment-actions-row{
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 14px;
    margin-top: 18px;
}

.payment-actions-row form{
    display: flex;
    margin: 0;
    padding: 0;
    border: none;
}

.payment-submit-btn,
.payment-cancel-btn{
    width: 220px;
    max-width: 100%;

    padding: 15px 30px;
    border-radius: 16px;
    border: none;
    font-weight: 900;
    font-size: 14px;
    letter-spacing: .4px;
    text-align: center;
    cursor: pointer;
    transition: .2s;
}

.payment-submit-btn{
    background: #4f9d6c;
    color: white;
    box-shadow: 0 4px 15px rgba(79,157,108,.4);
}

.payment-submit-btn:hover{
    background: #3f8558;
    box-shadow: 0 6px 20px rgba(79,157,108,.5);
}

.payment-cancel-btn{
    background: #d9534f;
    color: white;
    box-shadow: 0 4px 15px rgba(217,83,79,.35);
}

.payment-cancel-btn:hover{
    background: #c1443f;
    box-shadow: 0 6px 20px rgba(217,83,79,.45);
}


/* courier note */

.courier-note{
    font-size: 10px;
    color: #a04a78;
    margin: -2px 0 2px;
}


/* refund method — radio option cards */

.refund-method-options{
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 8px;
}

.refund-method-option{
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;

    padding: 10px 6px;

    background: #fdfff9;
    border: 1px solid #d9e8cd;
    border-radius: 12px;

    text-align: center;
    font-size: 11px;
    font-weight: 700;
    color: #4c7a37;

    cursor: pointer;
    transition: .15s;
}

.refund-method-option input[type="radio"]{
    accent-color: #6fa34e;
    cursor: pointer;
}

.refund-method-option.selected{
    border-color: #8fc26b;
    background: #eef8e4;
    color: #3f6a2c;
}

#refundMethodOther{
    display: none;
    margin-top: 2px;
}


/* qr code images */

.qr-code-row{
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.qr-code-item{
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;

    padding: 10px;

    background: #fbfdff;
    border: 1px solid #cfe0f5;
    border-radius: 14px;
}

.qr-code-img{
    width: 100%;
    max-width: 150px;
    height: auto;
    aspect-ratio: auto;
    object-fit: contain; /* buo ang QR, walang natatabas */

    background: #fff;
    padding: 6px;
    box-sizing: border-box;

    border-radius: 10px;
    border: 2px solid #bcd6f2;

    cursor: zoom-in;
    transition: .15s;
}

.qr-code-img:hover{
    transform: scale(1.03);
    border-color: #8fb8e8;
}

.qr-code-caption{
    font-size: 11px;
    font-weight: 700;
    color: #3f5f8f;
}


/* qr code lightbox — naka-gitna ng screen, katamtaman ang laki */

.qr-lightbox-overlay{
    display: none;

    position: fixed;
    inset: 0;
    z-index: 5000; /* mas mataas sa navbar (1000–2000) para di matakpan */

    align-items: center;
    justify-content: center;

    background: rgba(20,20,30,.7);
    padding: 20px;
}

.qr-lightbox-overlay.show{
    display: flex;
}

.qr-lightbox-box{
    position: relative;

    width: min(340px, 88vw);

    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;

    padding: 22px 22px 18px;

    background: #fff;
    border-radius: 18px;
    box-shadow: 0 18px 50px rgba(0,0,0,.3);
}

.qr-lightbox-box img{
    display: block;

    width: 100%;
    max-width: 280px;
    max-height: 60vh;
    height: auto;

    object-fit: contain;
    background: #fff;
    border-radius: 10px;
}

.qr-lightbox-box p{
    margin: 0;
    color: #3f5f8f;
    font-size: 14px;
    font-weight: 800;
    text-align: center;
}

.qr-lightbox-hint{
    font-size: 11px !important;
    font-weight: 600 !important;
    color: #8a8fa3 !important;
}

.qr-lightbox-close{
    position: absolute;
    top: 8px;
    right: 10px;

    width: 32px;
    height: 32px;

    border: none;
    border-radius: 50%;

    background: #f1f4fa;
    color: #3f5f8f;

    font-size: 20px;
    line-height: 1;
    cursor: pointer;
}

.qr-lightbox-close:hover{
    background: #e2e9f5;
}
</style>

</head>
<body>

<?php include "../Shared/navbar.php"; ?>

<header class="hero hero-payment">
    <div class="hero-content">
        <span class="hero-small">ALMOST THERE</span>
        <h1>Payment &amp;<br>Shipping Details</h1>
        <p>
            Please fill out your shipping details and complete payment
            below.
        </p>
    </div>
</header>

<main class="orders-page">

    <?php if ($hasError): ?>

        <div class="payment-shipping-error-banner">
            ⚠ Please check your details and upload a valid payment
            screenshot (JPG, PNG, or WEBP, max 5MB) before submitting.
        </div>

    <?php endif; ?>

    <div class="order-card payment-shipping-card">

        <form
            id="paymentDetailsForm"
            method="POST"
            action="submit_payment.php"
            class="payment-form"
            enctype="multipart/form-data"
            onsubmit="return prepareShippingAddress()"
        >

            <input
                type="hidden"
                name="order_id"
                value="<?php echo (int) $order["order_id"]; ?>"
            >

            <div class="payment-shipping-grid">

                <!-- column 1: shipping details -->

                <div class="payment-shipping-container shipping-details-container">

                    <div class="ps-card-header">
                        <div class="ps-card-icon">📦</div>
                        <div class="ps-card-header-text">
                            <h3>Shipping Details</h3>
                            <p>Where should we send your order?</p>
                        </div>
                    </div>

                    <div class="payment-form-group">
                        <label for="shippingName">Full Name (Last Name, First Name, Middle Name)</label>
                        <input
                            type="text"
                            name="shipping_name"
                            id="shippingName"
                            required
                        >
                    </div>

                    <div class="payment-form-group">
                        <label>Full Address</label>
                        <div class="address-stack">
                            <input
                                type="text"
                                id="shippingAddressLine1"
                                placeholder="House / Building / Street"
                                required
                            >
                            <input
                                type="text"
                                id="shippingAddressLine2"
                                placeholder="Barangay / City / Province"
                                required
                            >
                        </div>
                        <input type="hidden" name="shipping_address" id="shippingAddress">
                    </div>

                    <div class="payment-form-group">
                        <label for="pinAddress">Pin Address / Google Maps Link (Optional)</label>
                        <input
                            type="text"
                            name="pin_address"
                            id="pinAddress"
                            placeholder="Paste Google Maps pin link or landmark"
                        >
                    </div>

                    <div class="payment-form-group">
                        <label for="shippingContact">Contact Number</label>
                        <input
                            type="text"
                            name="shipping_contact"
                            id="shippingContact"
                            inputmode="numeric"
                            pattern="[0-9]{11}"
                            maxlength="11"
                            placeholder="e.g. 09171234567"
                            onkeypress="return (event.charCode >= 48 && event.charCode <= 57)"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                            required
                        >
                    </div>

                    <div class="payment-form-group">
                        <label for="courier">Courier</label>
                        <p class="courier-note">
                            Artist Location: Brgy. Palingon, Calamba City, Laguna
                        </p>
                        <select name="courier" id="courier" required>
                            <option value="">-- Select courier --</option>
                            <option value="lbc">LBC</option>
                            <option value="lalamove">Lalamove</option>
                        </select>
                    </div>

                    <div class="notice-box">
                        <span>🚚</span>
                        <span>Please ensure all details are correct to avoid delivery delays.</span>
                    </div>

                </div>


                <!-- column 2: payment -->

                <div class="payment-shipping-container payment-details-container">

                    <div class="ps-card-header">
                        <div class="ps-card-icon">💳</div>
                        <div class="ps-card-header-text">
                            <h3>Payment</h3>
                            <p>Complete your payment here.</p>
                        </div>
                    </div>

                    <div class="price-breakdown-list">

                        <div class="price-breakdown-top">
                            <span class="price-order-number">ORDER #<?php echo (int) $order["order_id"]; ?></span>
                            <span class="price-bag-icon">🛍️</span>
                        </div>

                        <div class="price-row total-row">
                            <span>Total Price</span>
                            <span>₱<?php echo number_format($order["total_amount"], 2); ?></span>
                        </div>

                        <div class="price-row downpayment-row">
                            <span>50% Downpayment</span>
                            <span>₱<?php echo number_format($order["total_amount"] / 2, 2); ?></span>
                        </div>

                    </div>

                    <div class="notice-box notice-box-warning">
                        <span>⚠️</span>
                        <span>Please make sure the downpayment amount you pay is complete and correct. An incorrect payment may cause your order to be cancelled.</span>
                    </div>

                    <div class="payment-form-group">
                        <label>Scan to Pay</label>

                        <div class="qr-code-row">

                            <div class="qr-code-item">
                                <img
                                    src="../Image/qrgcash.jpg"
                                    alt="GCash QR Code"
                                    class="qr-code-img"
                                    onclick="openQrLightbox(this.src, 'GCash QR Code')"
                                >
                                <span class="qr-code-caption">GCash</span>
                            </div>

                            <div class="qr-code-item">
                                <img
                                    src="../Image/qrbpi.jpg"
                                    alt="BPI QR Code"
                                    class="qr-code-img"
                                    onclick="openQrLightbox(this.src, 'BPI QR Code')"
                                >
                                <span class="qr-code-caption">BPI</span>
                            </div>

                        </div>

                        <p class="payment-form-hint">
                            Tap a QR code to enlarge it for scanning.
                        </p>
                    </div>

                    <div class="payment-form-group">
                        <label for="paymentReference">Reference / Transaction Number</label>
                        <input
                            type="text"
                            name="payment_reference"
                            id="paymentReference"
                            placeholder="e.g. GCash reference number"
                            required
                        >
                    </div>

                    <div class="payment-form-group">

                        <label for="paymentProof">
                            Proof of Payment (Screenshot / QR Receipt)
                        </label>

                        <div class="payment-proof-upload" id="paymentProofUpload">

                            <div class="payment-proof-preview" id="paymentProofPreview">

                                <span class="payment-proof-placeholder">
                                    🧾<br>
                                    Click to upload your payment screenshot
                                    <br>
                                    <span style="font-size:10px;">JPG, PNG, WEBP up to 5MB</span>
                                </span>

                                <img
                                    id="paymentProofPreviewImg"
                                    alt="Payment proof preview"
                                    style="display:none;"
                                >

                            </div>

                            <input
                                type="file"
                                name="payment_proof"
                                id="paymentProof"
                                accept="image/jpeg,image/png,image/webp"
                                required
                                onchange="previewPaymentProof(this)"
                            >

                        </div>

                        <p class="payment-form-hint">
                            Upload a clear screenshot of your payment (e.g.
                            the GCash/bank confirmation showing the amount
                            and reference number).
                        </p>

                    </div>

                    <div class="secure-notice">
                        <span>🔒</span> Your payment details are secure.
                    </div>

                </div>


                <!-- column 3: refund / return details -->

                <div class="payment-shipping-container refund-details-container">

                    <div class="ps-card-header">
                        <div class="ps-card-icon">↩</div>
                        <div class="ps-card-header-text">
                            <h3>Refund / Return Details</h3>
                            <p>For refund or return transactions.</p>
                        </div>
                    </div>

                    <p class="refund-note">
                        We collect this upfront so we can process any
                        return smoothly if ever needed.
                    </p>

                    <div class="payment-form-group">
                        <label for="refundAccountName">Account Name</label>
                        <input
                            type="text"
                            name="refund_account_name"
                            id="refundAccountName"
                            required
                        >
                    </div>

                    <div class="payment-form-group">
                        <label for="refundAccountNumber">Account Number / E-Wallet Number</label>
                        <input
                            type="text"
                            name="refund_account_number"
                            id="refundAccountNumber"
                            inputmode="numeric"
                            pattern="[0-9]{1,11}"
                            maxlength="11"
                            placeholder="e.g. 09171234567"
                            onkeypress="return (event.charCode >= 48 && event.charCode <= 57)"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                            required
                        >
                    </div>

                    <div class="payment-form-group">
                        <label>Preferred Bank / Method</label>

                        <div class="refund-method-options" id="refundMethodOptions">

                            <label class="refund-method-option">
                                <input
                                    type="radio"
                                    name="refund_method"
                                    value="gcash"
                                    onchange="toggleRefundMethodOther(this)"
                                    required
                                >
                                GCash
                            </label>

                            <label class="refund-method-option">
                                <input
                                    type="radio"
                                    name="refund_method"
                                    value="bpi"
                                    onchange="toggleRefundMethodOther(this)"
                                >
                                BPI
                            </label>

                            <label class="refund-method-option">
                                <input
                                    type="radio"
                                    name="refund_method"
                                    value="other"
                                    onchange="toggleRefundMethodOther(this)"
                                >
                                Other
                            </label>

                        </div>

                        <input
                            type="text"
                            name="refund_method_other"
                            id="refundMethodOther"
                            placeholder="Please specify bank/method"
                        >
                    </div>

                    <div class="notice-box notice-box-warning">
                        <span>⚠️</span>
                        <span>Ensure refund account details match your valid ID.</span>
                    </div>

                </div>

            </div>

            <div class="payment-submit-wrapper">

                <p class="payment-form-hint">
                    After you submit, our staff will review this first to
                    verify your payment before your order is considered
                    confirmed.
                </p>

                <div class="payment-terms-group">
                    <input type="checkbox" id="paymentAgreeTerms" name="agree_terms" required>
                    <label for="paymentAgreeTerms">
                        I have read and agree to the
                        <a href="../Legal/commission-terms.php" target="_blank" rel="noopener">Commission &amp; Order Terms</a>.
                    </label>
                </div>

            </div>

        </form>

        <div class="payment-actions-row">

            <button type="submit" form="paymentDetailsForm" class="payment-submit-btn">
                Submit Payment
            </button>

            <form
                method="POST"
                action="confirm_order.php"
                class="payment-shipping-cancel-form"
                onsubmit="return confirm('Are you sure you want to cancel this order? This cannot be undone.');"
            >
                <input type="hidden" name="order_id" value="<?php echo (int) $order["order_id"]; ?>">
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="payment-cancel-btn">
                    Cancel
                </button>
            </form>

        </div>

    </div>

</main>


<!-- qr code lightbox -->

<div class="qr-lightbox-overlay" id="qrLightboxOverlay" onclick="closeQrLightbox(event)">

    <div class="qr-lightbox-box">
        <button type="button" class="qr-lightbox-close" aria-label="Close" onclick="closeQrLightbox()">&times;</button>
        <img id="qrLightboxImg" alt="">
        <p id="qrLightboxLabel"></p>
        <p class="qr-lightbox-hint">Scan this QR code using your GCash / BPI app</p>
    </div>

</div>


<script>

/* combine the 2 address fields into 1 hidden field before submit -
   yun lang ang binabasa ng submit_payment.php */

var paymentFormSubmitting = false;

function prepareShippingAddress() {

    /* isang beses lang maisusumite ang bayad (iwas double click) */
    if (paymentFormSubmitting) {
        return false;
    }

    var line1 = document.getElementById("shippingAddressLine1").value.trim();
    var line2 = document.getElementById("shippingAddressLine2").value.trim();

    document.getElementById("shippingAddress").value = line1 + ", " + line2;

    paymentFormSubmitting = true;

    var submitButton = document.querySelector(".payment-submit-btn");

    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = "Submitting payment…";
    }

    return true;

}


function previewPaymentProof(input) {

    var placeholder = document.querySelector("#paymentProofPreview .payment-proof-placeholder");
    var previewImg  = document.getElementById("paymentProofPreviewImg");

    if (!input.files || !input.files[0] || !previewImg) {
        return;
    }

    var reader = new FileReader();

    reader.onload = function (event) {

        previewImg.src = event.target.result;
        previewImg.style.display = "block";

        if (placeholder) {
            placeholder.style.display = "none";
        }

    };

    reader.readAsDataURL(input.files[0]);

}


/* click a QR code to enlarge it */

function openQrLightbox(src, label) {

    var overlay = document.getElementById("qrLightboxOverlay");
    var image   = document.getElementById("qrLightboxImg");
    var caption = document.getElementById("qrLightboxLabel");

    if (!overlay || !image) {
        return;
    }

    image.src = src;
    image.alt = label ? (label + " QR code") : "Payment QR code";

    if (caption) {
        caption.textContent = label || "";
    }

    overlay.classList.add("show");
    document.body.style.overflow = "hidden";

}

function closeQrLightbox(event) {

    /* kapag nag-click sa loob mismo ng picture, huwag isara */
    if (event && event.target && event.target.closest && event.target.closest(".qr-lightbox-box") && !event.target.closest(".qr-lightbox-close")) {
        return;
    }

    var overlay = document.getElementById("qrLightboxOverlay");

    if (!overlay) {
        return;
    }

    overlay.classList.remove("show");
    document.body.style.overflow = "";

}

document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {
        closeQrLightbox();
    }

});


/* highlight picked refund method, show "Other" field lang kapag Other */

function toggleRefundMethodOther(selectedRadio) {

    var cards = document.querySelectorAll("#refundMethodOptions .refund-method-option");

    cards.forEach(function (card) {
        card.classList.remove("selected");
    });

    if (selectedRadio) {
        var chosenCard = selectedRadio.closest(".refund-method-option");
        if (chosenCard) {
            chosenCard.classList.add("selected");
        }
    }

    var otherSelected = document.querySelector('input[name="refund_method"][value="other"]').checked;
    var otherInput     = document.getElementById("refundMethodOther");

    if (!otherInput) {
        return;
    }

    otherInput.style.display = otherSelected ? "block" : "none";
    otherInput.required      = otherSelected;

    if (!otherSelected) {
        otherInput.value = "";
    }

}

</script>

<?php include "../Shared/footer.php"; ?>

</body>
</html>
