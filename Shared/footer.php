<?php

// Footer has its own DB connection kasi may pages na nagsasara na
// ng $conn nila bago pa ma-include ito.

require_once __DIR__ . "/../helpers/content_helper.php";

$footerContent = [];

$footerConn = @new mysqli("localhost", "root", "", "figurify_db");

if ($footerConn && !$footerConn->connect_error) {
    $footerConn->set_charset("utf8mb4");
    $footerContent = figurify_get_all_content($footerConn);
    $footerConn->close();
}

function cs_footer_text(string $key, string $default): string
{
    global $footerContent;
    return figurify_content($footerContent, $key, $default);
}

$cs_footer_email = cs_footer_text("contact_email", "clayandstuff@gmail.com");

?>

<!-- FontAwesome for the social icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>

footer {
    margin-top: 20px;
    padding: 26px 8% 14px;
    background: linear-gradient(135deg, #ffd6ef 0%, #ffc4e8 100%);
    /* solid na kulay (hindi opacity) para pasado sa WCAG AA
       4.5:1 contrast minimum */
    color: #831843; /* solid color for WCAG AA contrast */
    border-top: 4px solid #f472b6;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.5;
    box-shadow: 0 -4px 20px rgba(131, 24, 67, 0.1);
}

footer .footer-brand {
    margin-bottom: 12px;
}

footer .footer-brand strong {
    font-family: Georgia, serif;
    font-size: 22px;
    letter-spacing: 0.5px;
    color: #831843;
}

footer .footer-brand p {
    font-size: 13px;
    margin-top: 6px;
    color: #831843; /* solid color, was opacity before (contrast) */
    font-style: italic;
}

footer .footer-links {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 28px;
    margin: 16px 0;
}

footer .footer-links div {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

footer .footer-links b {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #831843;
    margin-bottom: 6px;
    border-bottom: 2px solid rgba(131, 24, 67, 0.25);
    padding-bottom: 4px;
    display: inline-block;
}

footer .footer-links a,
footer .footer-links span {
    color: #831843; /* solid color, was opacity before (contrast) */
    text-decoration: none;
    font-size: 12px;
    transition: all 0.3s ease;
}

footer .footer-links a:hover {
    color: #db2777;
    transform: translateX(4px);
}

/* social icons in one row, colored, with bigger logos */
.social-icons {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center;
    gap: 10px;
    margin-top: 8px;
    white-space: nowrap;
}

.social-icons a {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    font-size: 18px;
    text-decoration: none;
    color: #ffffff !important;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
    flex-shrink: 0;
}

/* each brand's actual color */
.social-icons a.facebook { background-color: #1877f2; }
.social-icons a.instagram { background: radial-gradient(circle at 30% 107%, #fdf497 0%, #df3496 45%, #a51e85 60%, #4c2882 90%); }
.social-icons a.tiktok { background-color: #000000; }
.social-icons a.gmail { background-color: #ea4335; }

.social-icons a:hover {
    transform: translateY(-3px) scale(1.1);
    box-shadow: 0 5px 12px rgba(0, 0, 0, 0.25);
}

footer .copyright {
    padding-top: 10px;
    border-top: 1px solid rgba(131, 24, 67, 0.2);
    text-align: center;
    font-size: 11px;
    color: #831843; /* solid color, was opacity before (contrast) */
    letter-spacing: 0.3px;
}

/* mobile responsive */

@media (max-width: 800px) {

    footer .footer-links {
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

}

@media (max-width: 500px) {

    footer {
        padding: 20px 5% 12px;
    }

    footer .footer-links {
        grid-template-columns: 1fr;
        gap: 16px;
    }

}
</style>

<footer>


    <div class="footer-brand">

        <strong>
            Clay and Stuff
        </strong>

        <p>
            <?php echo htmlspecialchars(cs_footer_text("footer_tagline", "Made with little bits of joy."), ENT_QUOTES, "UTF-8"); ?>
        </p>

    </div>


    <div class="footer-links">


        <!-- QUICK LINKS -->

        <div>

            <b>
                Quick Links
            </b>

            <a href="../Home/Home.php">
                Overview
            </a>

            <a href="../Collection/collection.php">
                Collections
            </a>

            <a href="../Commission/commission.php">
                Commission
            </a>

        </div>


        <!-- COMMISSION -->

        <div>

            <b>
                Commission
            </b>

            <a href="../Commission/commission.php#createStyleForm">
                Dress Up
            </a>

            <a href="../Commission/commission.php">
                Image Submission
            </a>

        </div>


        <!-- LEGAL -->

        <div>

            <b>
                Legal
            </b>

            <a href="../Legal/privacy-policy.php">
                Privacy Policy
            </a>

            <a href="../Legal/terms-and-conditions.php">
                Terms and Conditions
            </a>

            <a href="../Legal/commission-terms.php">
                Commission &amp; Order Terms
            </a>

        </div>


        <!-- CONTACT & SOCIALS -->

        <div>

            <b>
                Contact Us
            </b>

            <span>
                <?php echo htmlspecialchars(cs_footer_text("contact_address", "Brgy. Palingon, Calamba City, Laguna"), ENT_QUOTES, "UTF-8"); ?>
            </span>

            <span>
                <?php echo htmlspecialchars(cs_footer_text("contact_phone", "+63 910 281 4331"), ENT_QUOTES, "UTF-8"); ?>
            </span>

            <span>
                <?php echo htmlspecialchars($cs_footer_email, ENT_QUOTES, "UTF-8"); ?>
            </span>

            <!-- social icons -->
            <div class="social-icons">

                <a href="https://facebook.com" target="_blank" title="Facebook" class="facebook">
                    <i class="fa-brands fa-facebook-f"></i>
                </a>

                <a href="https://instagram.com" target="_blank" title="Instagram" class="instagram">
                    <i class="fa-brands fa-instagram"></i>
                </a>

                <a href="https://tiktok.com" target="_blank" title="TikTok" class="tiktok">
                    <i class="fa-brands fa-tiktok"></i>
                </a>

                <a href="mailto:<?php echo htmlspecialchars($cs_footer_email, ENT_QUOTES, "UTF-8"); ?>" title="Email Us" class="gmail">
                    <i class="fa-solid fa-envelope"></i>
                </a>

            </div>

        </div>


    </div>


    <div class="copyright">

        © <?php echo date("Y"); ?> Clay and Stuff | Est. 2021 — All Rights Reserved.

    </div>


</footer>

