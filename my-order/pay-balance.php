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
    "SELECT order_id, total_amount, balance_to_pay, status
     FROM orders
     WHERE order_id = ? AND user_id = ?"
);

$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();
$order   = $result->fetch_assoc();

$stmt->close();
$conn->close();


/* invalid or not awaiting_balance anymore -> balik sa My Orders na may notice banner */

if (!$order) {
    header("Location: my-orders.php?notice=order_not_found");
    exit();
}

if ($order["status"] !== "awaiting_balance") {

    /* nabayaran na ang balance (Back button / double click) — diretso sa order, walang babala */
    $balanceAlreadyPaidStatuses = ["to_verify_balance", "for_balance_approval", "to_ship", "shipped", "completed"];

    if (in_array(figurify_status_key($order["status"]), $balanceAlreadyPaidStatuses, true)) {
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

<title>Pay Remaining Balance — Clay and Stuff</title>

<link rel="stylesheet" href="my-orders.css">
<style>
/* halos parehong pattern lang ng payment-shipping.php (QR + proof upload),
   pero iisang column lang dito — shipping/refund details, nakuha na sa
   unang payment, hindi na kailangan ulitin dito */

.balance-payment-card{
    max-width: 640px;
    margin: 0 auto;
}

.balance-payment-cancel-note{
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px dashed var(--gray-light, #eee7ea);
    text-align: center;
    font-size: 11px;
    color: #956d80;
}


/* step header (galing payment-shipping.php) */

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

.balance-payment-section-header{
    max-width: 640px;
    margin-left: auto;
    margin-right: auto;
    padding-top: 22px;
}

.balance-payment-error-banner{
    max-width: 640px;
    margin: 0 auto 16px;

    padding: 12px 16px;

    background: #ffe4e8;
    color: #a4394a;
    border: 1px solid #f0b8c2;
    border-radius: 14px;

    font-size: 12px;
    font-weight: 800;
}

.payment-shipping-container{
    background: rgba(255,255,255,.98);
    border: 2px solid #cfe0f5;
    border-radius: 20px;
    padding: 22px;

    display: flex;
    flex-direction: column;
    gap: 14px;

    box-shadow: 0 4px 12px rgba(215,192,221,.15);
}

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
    background: #f0f6ff;
    border: 2px solid #bcd6f2;
    color: #5085c9;
    font-size: 18px;
}

.ps-card-header-text h3{
    color: #3f5f8f;
    font-family: Georgia, serif;
    font-size: 16px;
    font-weight: normal;
}

.ps-card-header-text p{
    color: #956d80;
    font-size: 11px;
    margin-top: 2px;
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
    color: #3f5f8f;
    letter-spacing: .3px;
}

.payment-form-group input{
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

.payment-form-group input::placeholder{
    color: #bfa8b6;
}

.payment-form-group input:focus{
    border-color: #f2a4ca;
    background: white;
    box-shadow: 0 0 0 3px rgba(242,164,202,.2);
}

.payment-form-hint{
    font-size: 11px;
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

.payment-submit-wrapper button{
    align-self: center;
    width: auto;
    min-width: 240px;

    padding: 15px 40px;
    border-radius: 16px;
    border: none;
    background: #d96b9c;
    color: white;
    font-weight: 900;
    font-size: 14px;
    letter-spacing: .4px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(217,107,156,.4);
    transition: .2s;
}

.payment-submit-wrapper button:hover{
    background: #c25384;
    box-shadow: 0 6px 20px rgba(217,107,156,.5);
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

<main class="orders-page">

    <div class="section-header balance-payment-section-header">

        <div class="section-icon">
            ✦
        </div>

        <div>

            <h2>
                Pay Remaining Balance
            </h2>

            <p>
                Please complete your remaining balance so we can ship out
                your order.
            </p>

        </div>

    </div>

    <?php if ($hasError): ?>

        <div class="balance-payment-error-banner">
            ⚠ Please check your details and upload a valid payment
            screenshot (JPG, PNG, or WEBP, max 5MB) before submitting.
        </div>

    <?php endif; ?>

    <div class="order-card balance-payment-card">

        <form
            method="POST"
            action="submit_balance_payment.php"
            class="payment-form"
            enctype="multipart/form-data"
            onsubmit="if (this.dataset.submitting) { return false; } this.dataset.submitting = '1'; var b = this.querySelector('button[type=submit]'); if (b) { b.disabled = true; b.textContent = 'Submitting payment…'; } return true;"
        >

            <input
                type="hidden"
                name="order_id"
                value="<?php echo (int) $order["order_id"]; ?>"
            >

            <div class="payment-shipping-container payment-details-container">

                <div class="ps-card-header">
                    <div class="ps-card-icon">💳</div>
                    <div class="ps-card-header-text">
                        <h3>Balance Payment</h3>
                        <p>Complete your remaining balance here.</p>
                    </div>
                </div>

                <div class="payment-total-box">
                    <span>Remaining Balance</span>
                    <strong>₱<?php echo number_format((float) $order["balance_to_pay"], 2); ?></strong>
                </div>

                <div class="notice-box notice-box-warning">
                    <span>⚠️</span>
                    <span>Please make sure the amount you pay matches the remaining balance above. An incorrect payment may delay your shipment.</span>
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
                    <label for="balancePaymentReference">Reference / Transaction Number</label>
                    <input
                        type="text"
                        name="payment_reference"
                        id="balancePaymentReference"
                        placeholder="e.g. GCash reference number"
                        required
                    >
                </div>

                <div class="payment-form-group">

                    <label for="balancePaymentProof">
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
                            id="balancePaymentProof"
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

            <div class="payment-submit-wrapper">

                <p class="payment-form-hint">
                    After you submit, our staff will review this first to
                    verify your payment before your order is marked ready
                    to ship.
                </p>

                <button type="submit">Submit Balance Payment ✦</button>

            </div>

        </form>

        <p class="balance-payment-cancel-note">
            Need help with this payment? Please contact our staff.
        </p>

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

</script>

<?php include "../Shared/footer.php"; ?>

</body>
</html>
