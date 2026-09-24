<?php

// static page, same pattern as privacy-policy.php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

?>
<!doctype html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../Image/logoclay.png">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Clay and Stuff — Refund Policy</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500;1,9..144,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="legal.css?v=2">
</head>

<body>

<?php include "../Shared/navbar.php"; ?>

<main class="legal-page">

    <div class="legal-header">
        <span class="legal-eyebrow">Legal</span>
        <h1>Refund Policy</h1>
        <p class="legal-updated">Last updated: <?php echo date("F j, Y"); ?></p>
    </div>

    <div class="legal-card">

        <h2>1. Before Production Starts</h2>
        <p>
            If you cancel your order <strong>before</strong> our staff has
            verified your payment and moved your order into production, you
            are entitled to a full refund of any amount you already paid.
        </p>

        <h2>2. After Production Has Started</h2>
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

        <h2>3. Defective or Incorrect Orders</h2>
        <p>
            If the figure you receive is significantly different from what
            was agreed upon (e.g. wrong size, wrong style, or damaged due to
            our workmanship — not damage caused during shipping by the
            courier), contact us within <strong>7 days</strong> of receiving
            your order with photos of the issue. We will offer, at our
            discretion, a replacement, a repair, or a refund.
        </p>

        <h2>4. Damage During Shipping</h2>
        <p>
            If your order arrives damaged due to mishandling by the courier,
            contact us within 48 hours of delivery with photos of the
            packaging and the item. We will help you file a claim with the
            courier and, where appropriate, offer a replacement or partial
            refund.
        </p>

        <h2>5. How Refunds Are Processed</h2>
        <p>
            Approved refunds are sent to the refund account name, account
            number, and method (e.g. GCash, BPI, or other bank/e-wallet) you
            provided at checkout. Please make sure these details are
            accurate and match a valid ID under your name — we are not
            responsible for refunds sent to an incorrect account you
            provided.
        </p>

        <h2>6. Refund Timeline</h2>
        <p>
            Approved refunds are processed within
            <span class="legal-placeholder">7–14 business days</span> from
            approval, depending on your chosen refund method.
        </p>

        <h2>7. How to Request a Refund</h2>
        <p>
            Log in to your account, go to
            <strong>My Orders</strong>, and contact us through
            <span class="legal-placeholder">clayandstuff@gmail.com</span>
            or the contact details in our footer, referencing your order
            number.
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
