<?php

// static page, same pattern as privacy-policy.php
// this covers account/site use only (consent checkbox on signup);
// order/commission terms live in commission-terms.php instead

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

?>
<!doctype html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../Image/logoclay.png">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Clay and Stuff — Terms and Conditions</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500;1,9..144,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="legal.css?v=2">
</head>

<body>

<?php include "../Shared/navbar.php"; ?>

<header class="hero hero-terms">
    <div class="hero-content">
        <span class="hero-small">ACCOUNT &amp; USAGE</span>
        <h1>Terms and<br>Conditions</h1>
        <p>
            The rules for creating an account and using this website.
        </p>
    </div>
</header>

<main class="legal-page">

    <div class="legal-header">
        <p class="legal-updated">Last updated: <?php echo date("F j, Y"); ?></p>
        <p style="font-size:13px;color:#8a6b7d;margin-top:6px;">
            This page covers your account and use of this website. For terms
            specific to placing and paying for a commission order, see our
            <a href="commission-terms.php">Commission &amp; Order Terms</a>.
        </p>
    </div>

    <div class="legal-card">

        <h2>1. Acceptance of Terms</h2>
        <p>
            By creating an account or otherwise using this website, you agree
            to these Terms and Conditions and to our
            <a href="privacy-policy.php">Privacy Policy</a>. If you do not
            agree, please do not create an account or use this website.
        </p>

        <h2>2. Who Can Use This Website</h2>
        <p>
            This website is intended for individuals who are at least 18
            years old, or who have the consent of a parent or guardian to
            create an account and transact with us.
        </p>

        <h2>3. Your Account</h2>
        <p>
            When you sign up, you agree to provide accurate information
            (your name and a valid email address) and to keep it up to date.
            You may only create and use one account for yourself.
        </p>

        <h2>4. Account Security</h2>
        <p>
            You are responsible for keeping your account password
            confidential and for all activity that happens under your
            account. Notify us immediately if you suspect unauthorized
            access or a security issue with your account.
        </p>

        <h2>5. Acceptable Use</h2>
        <p>
            You agree not to misuse this website — for example, by
            attempting to access another user's account, interfering with
            the website's normal operation, or submitting false information.
        </p>

        <h2>6. Intellectual Property</h2>
        <p>
            The Clay and Stuff name, logo, and the content on this website
            (including catalog photos, figure style designs, page text, and
            layout) belong to us or are used with permission, and are
            protected by applicable intellectual property laws. You may not
            copy, reproduce, or reuse them for your own commercial purposes
            without our written consent. This does not affect ownership of
            reference images you personally upload for your own commission —
            see our
            <a href="commission-terms.php">Commission &amp; Order Terms</a>
            for how those are used.
        </p>

        <h2>7. Reviews</h2>
        <p>
            Reviews may only be submitted by customers for their own
            completed orders. Reviews should be honest and based on genuine
            experience. We reserve the right to remove reviews that are
            abusive, fraudulent, or unrelated to an actual order.
        </p>

        <h2>8. Termination</h2>
        <p>
            We may suspend or terminate your account if you violate these
            Terms — for example, through fraud, abuse, providing false
            information, or misusing the website. This does not cancel or
            affect any order already in progress except where allowed under
            our
            <a href="commission-terms.php">Commission &amp; Order Terms</a>.
            You may also close your account at any time by contacting us.
        </p>

        <h2>9. Limitation of Liability</h2>
        <p>
            To the extent permitted by law, we are not liable for indirect
            or consequential damages arising from your use of this website
            or your account. Liability specific to a commission order is
            covered in our
            <a href="commission-terms.php">Commission &amp; Order Terms</a>.
        </p>

        <h2>10. Governing Law</h2>
        <p>
            These Terms are governed by the laws of the Republic of the
            Philippines, including the Electronic Commerce Act (RA 8792),
            without prejudice to your rights under the Data Privacy Act of
            2012 (RA 10173) as described in our
            <a href="privacy-policy.php">Privacy Policy</a>.
        </p>

        <h2>11. Changes to These Terms</h2>
        <p>
            We may update these Terms from time to time. Continued use of
            this website after changes are posted means you accept the
            updated Terms.
        </p>

        <h2>12. Contact Us</h2>
        <p>
            Questions about these Terms can be sent to
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
