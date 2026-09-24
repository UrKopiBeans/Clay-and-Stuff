<?php

/* Static page, same pattern as privacy-policy.php.
   Commission/order terms lang (payment, production, shipping,
   cancellations) — hiwalay sa general Terms and Conditions,
   linked sa checkout (my-order/payment-shipping.php). */

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

?>
<!doctype html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../Image/logoclay.png">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Clay and Stuff — Commission &amp; Order Terms</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500;1,9..144,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="legal.css?v=2">
</head>

<body>

<?php include "../Shared/navbar.php"; ?>

<header class="hero hero-commission-terms">
    <div class="hero-content">
        <span class="hero-small">ORDERS &amp; PAYMENTS</span>
        <h1>Commission &amp;<br>Order Terms</h1>
        <p>
            The rules for placing, paying for, and receiving a commission
            order.
        </p>
    </div>
</header>

<main class="legal-page">

    <div class="legal-header">
        <p class="legal-updated">Last updated: <?php echo date("F j, Y"); ?></p>
        <p style="font-size:13px;color:#8a6b7d;margin-top:6px;">
            This page covers placing, paying for, and receiving a commission
            order. For terms about your account and using this website in
            general, see our
            <a href="terms-and-conditions.php">Terms and Conditions</a>.
        </p>
    </div>

    <div class="legal-card">

        <h2>1. Placing a Commission Order</h2>
        <ul>
            <li>An order is placed either through Image Submission (uploading reference images) or through the Dress Up customizer, followed by choosing figure details, box/add-ons, and confirming your Order Summary.</li>
            <li>Orders are subject to our booking limits (Rush and Non-Rush slots) shown during booking, and to our staff's confirmation of feasibility based on your references.</li>
            <li>Prices shown at checkout are the total amount due for that order, including any add-ons and box options you selected.</li>
            <li>Once you submit payment details, your order proceeds to verification by our staff before production begins.</li>
        </ul>

        <h2>2. Payment</h2>
        <p>
            Full or agreed partial payment (as shown at checkout) must be
            submitted with a valid proof of payment (screenshot or photo of
            your GCash/bank transfer receipt) and a payment reference number.
            Orders will not proceed to production until payment has been
            verified by our staff. Submitting false or altered proof of
            payment is grounds for order cancellation.
        </p>

        <h2>3. Production and Customization</h2>
        <p>
            Each figure is individually handmade based on the references,
            options, and details you provide. Because these are handmade,
            minor variations from the reference image (color shade, exact
            proportions, small details) are normal and are not considered
            defects. If your commission is based on a character, artwork, or
            brand you do not personally own the rights to, you confirm that
            it is for personal, non-commercial use, and you are responsible
            for making sure your request does not infringe someone else's
            intellectual property.
        </p>

        <h2>4. Order Timeline</h2>
        <p>
            Estimated completion and shipping timelines shown on this website
            are estimates, not guarantees, and may be affected by order
            volume, materials, or circumstances outside our control. We will
            notify you through your account/notifications if there is a
            significant delay.
        </p>

        <h2>5. Shipping</h2>
        <p>
            Orders are shipped through the courier you select at checkout
            (e.g. LBC, Lalamove). Once handed over to the courier, delivery
            time and handling are subject to that courier's own service. We
            are not liable for delays or damage caused solely by the courier,
            but we will assist you in filing a claim where applicable.
        </p>

        <h2>6. Cancellations and Refunds</h2>
        <p>
            Because every figure is individually handmade, refund
            eligibility depends on how far along your order is when you
            cancel:
        </p>

        <h3>6.1 Before Production Starts</h3>
        <p>
            If you cancel your order <strong>before</strong> our staff has
            verified your payment and moved your order into production,
            you are entitled to a full refund of any amount you already
            paid.
        </p>

        <h3>6.2 After Production Has Started</h3>
        <p>
            Because every figure is individually handmade based on your
            chosen references and options, once your payment has been
            verified and production has started, orders can no longer be
            cancelled for a full refund. In this case:
        </p>
        <ul>
            <li>If production has just started and materials have not yet been significantly used, a <strong>partial refund</strong> may be given at our discretion, minus the cost of materials and labor already spent.</li>
            <li>If the figure is already substantially complete or finished, no refund will be given, but we will still proceed to complete and ship your order.</li>
        </ul>

        <h3>6.3 Defective or Incorrect Orders</h3>
        <p>
            If the figure you receive is significantly different from what
            was agreed upon (e.g. wrong size, wrong style, or damaged due
            to our workmanship — not damage caused during shipping by the
            courier), contact us within <strong>7 days</strong> of
            receiving your order with photos of the issue. We will offer,
            at our discretion, a replacement, a repair, or a refund.
        </p>

        <h3>6.4 Damage During Shipping</h3>
        <p>
            If your order arrives damaged due to mishandling by the
            courier, contact us within 48 hours of delivery with photos of
            the packaging and the item. We will help you file a claim with
            the courier and, where appropriate, offer a replacement or
            partial refund.
        </p>

        <h3>6.5 How Refunds Are Processed</h3>
        <p>
            Approved refunds are sent to the refund account name, account
            number, and method (e.g. GCash, BPI, or other bank/e-wallet)
            you provided at checkout. Please make sure these details are
            accurate and match a valid ID under your name — we are not
            responsible for refunds sent to an incorrect account you
            provided.
        </p>

        <h3>6.6 Refund Timeline</h3>
        <p>
            Approved refunds are processed within
            <span class="legal-placeholder">7–14 business days</span> from
            approval, depending on your chosen refund method.
        </p>

        <h3>6.7 How to Request a Refund or Cancellation</h3>
        <p>
            Log in to your account, go to <strong>My Orders</strong>, and
            either use the Cancel Order option there (available before
            payment is verified), or contact us through
            <span class="legal-placeholder">clayandstuff@gmail.com</span>
            or the contact details in our footer, referencing your order
            number.
        </p>

        <h2>7. Limitation of Liability</h2>
        <p>
            To the extent permitted by law, our liability for any claim
            relating to this order is limited to the amount you paid for
            that order. We are not liable for indirect or consequential
            damages.
        </p>

        <h2>8. Governing Law</h2>
        <p>
            These Commission &amp; Order Terms are governed by the laws of
            the Republic of the Philippines, including the Consumer Act of
            the Philippines (RA 7394) and the Electronic Commerce Act (RA
            8792).
        </p>

        <h2>9. Contact Us</h2>
        <p>
            Questions about an order can be sent to
            <span class="legal-placeholder">clayandstuff@gmail.com</span>.
        </p>

        <div class="legal-note">
            This page is provided for transparency and general compliance
            purposes. It is not a substitute for formal legal advice.
        </div>

    </div>

    <a class="legal-back-link" href="../Home/Home.php">&larr; Back to Home</a>

</main>

<?php include "../Shared/footer.php"; ?>

</body>
</html>
