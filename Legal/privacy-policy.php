<?php

// static read-only page, no DB query, uses the shared navbar/footer
// TODO: fill in the legal-placeholder spans with real details
// (e.g. DTI registration number) before this goes live

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

?>
<!doctype html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../Image/logoclay.png">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Clay and Stuff — Privacy Policy</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500;1,9..144,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="legal.css?v=2">
</head>

<body>

<?php include "../Shared/navbar.php"; ?>

<header class="hero hero-privacy">
    <div class="hero-content">
        <span class="hero-small">YOUR DATA, PROTECTED</span>
        <h1>Privacy<br>Policy</h1>
        <p>
            What we collect, why we collect it, and how we protect it.
        </p>
    </div>
</header>

<main class="legal-page">

    <div class="legal-header">
        <p class="legal-updated">Last updated: <?php echo date("F j, Y"); ?></p>
    </div>

    <div class="legal-card">

        <h2>1. Who We Are</h2>
        <p>
            Clay and Stuff ("we", "us", "our") operates this website to offer
            custom clay/figure commission services to customers in the
            Philippines. This Privacy Policy explains what personal
            information we collect through this website, why we collect it,
            how we use and protect it, and what rights you have over it.
        </p>
        <p>
            Business address: <span class="legal-placeholder">Brgy. Palingon, Calamba City, Laguna</span> ·
            Contact email: <span class="legal-placeholder">clayandstuff@gmail.com</span> ·
            Contact number: <span class="legal-placeholder">+63 910 281 4331</span>
        </p>

        <h2>2. Information We Collect</h2>
        <p>When you create an account, place a commission order, or contact us, we may collect:</p>
        <ul>
            <li><strong>Account information</strong> — your full name, email address, and password (stored in encrypted/hashed form, never in plain text).</li>
            <li><strong>Order and commission details</strong> — the figure style, size, add-ons, box options, and any reference images you upload for your commission.</li>
            <li><strong>Shipping information</strong> — recipient name, delivery address, and contact number.</li>
            <li><strong>Payment-related information</strong> — your payment reference number and a screenshot/photo of your proof of payment (e.g. GCash or bank transfer receipt). We do <strong>not</strong> collect or store your full card number, CVV, or online banking password — these are handled directly by your bank or e-wallet app, not by us.</li>
            <li><strong>Refund details</strong> — if you request a refund, the account name, account number, and preferred method (e.g. GCash, BPI) you provide so we can send the refund.</li>
            <li><strong>Reviews</strong> — any rating or review text you choose to submit about a completed order.</li>
            <li><strong>Communications</strong> — emails we send you for account verification, order updates, and notifications.</li>
        </ul>

        <h2>3. How We Use Your Information</h2>
        <ul>
            <li>To create and manage your customer account.</li>
            <li>To process, produce, and ship your commission order.</li>
            <li>To verify your payment and, when needed, process a refund.</li>
            <li>To send order status updates and account-related notifications.</li>
            <li>To respond to questions or concerns you send us.</li>
            <li>To improve our website, catalog, and commission process.</li>
        </ul>

        <h2>4. How We Store and Protect Your Information</h2>
        <p>
            Your information is stored in our database and is only accessible
            to authorized staff and the business owner for the purposes
            described above. Passwords are never stored as plain text — they
            are hashed before saving. Uploaded images (reference images and
            proof-of-payment screenshots) are stored on our server and are
            only viewable through your own account or by our staff who are
            verifying your order.
        </p>

        <h2>5. Who We Share Information With</h2>
        <p>
            We do not sell your personal information. We may share limited
            information with:
        </p>
        <ul>
            <li>Our email delivery service, used only to send account verification codes and order notifications.</li>
            <li>Our shipping courier (e.g. LBC, Lalamove), limited to the shipping name, address, and contact number needed to deliver your order.</li>
            <li>Government authorities, only if required by law.</li>
        </ul>

        <h2>6. Cookies</h2>
        <p>
            This website uses a basic session cookie so you can stay logged
            in while you browse. We do not currently use third-party
            advertising or analytics cookies. If that changes in the future,
            this policy will be updated and, where required, you will be
            asked for consent first.
        </p>

        <h2>7. Your Rights</h2>
        <p>
            Under the Philippine Data Privacy Act of 2012 (Republic Act No.
            10173), you have the right to:
        </p>
        <ul>
            <li>Be informed that your personal data is being collected and processed (this policy).</li>
            <li>Access the personal data we hold about you.</li>
            <li>Request correction of inaccurate personal data.</li>
            <li>Request deletion of your personal data, subject to legal or legitimate business retention needs (e.g. order/transaction records).</li>
            <li>Object to certain uses of your personal data.</li>
        </ul>
        <p>
            To exercise any of these rights, contact us at
            <span class="legal-placeholder">clayandstuff@gmail.com</span>.
        </p>

        <h2>8. Data Retention</h2>
        <p>
            We keep account and order information for as long as your account
            is active, and for a reasonable period afterward to comply with
            recordkeeping, dispute resolution, and legal obligations.
        </p>

        <h2>9. Children's Privacy</h2>
        <p>
            This website is not directed at children under 13. If we learn
            that we have unintentionally collected personal information from
            a child under 13 without parental consent, we will delete it.
        </p>

        <h2>10. Changes to This Policy</h2>
        <p>
            We may update this Privacy Policy from time to time. Material
            changes will be reflected by updating the "Last updated" date
            above.
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
