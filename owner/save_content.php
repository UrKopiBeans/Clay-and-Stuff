<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/content_helper.php";

// Owner access guard
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../Login/Login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: content-management.php");
    exit();
}

$ownerId = $_SESSION["user_id"];

// Lahat ng editable fields sa home page. Bagong field sa content-management.php? Idagdag din dito.

$editableFields = [

    /* HERO — SLIDE 1 */
    "hero_eyebrow",
    "hero_heading",
    "hero_tagline",
    "hero_link_text",
    "hero_image",
    "hero_stamp",

    /* HERO — SLIDE 2 */
    "slide2_eyebrow",
    "slide2_heading",
    "slide2_tagline",
    "slide2_link_text",
    "slide2_image",
    "slide2_stamp",

    /* HERO — SLIDE 3 */
    "slide3_eyebrow",
    "slide3_heading",
    "slide3_tagline",
    "slide3_link_text",
    "slide3_image",
    "slide3_stamp",

    /* ABOUT */
    "about_heading",
    "about_script",
    "about_text",
    "about_sign",
    "about_video",
    "about_badge",

    /* FEATURED ORDERS */
    "featured_heading",
    "featured_subtitle",
    "product1_image", "product1_title", "product1_price", "product1_caption",
    "product2_image", "product2_title", "product2_price", "product2_caption",
    "product3_image", "product3_title", "product3_price", "product3_caption",
    "product4_image", "product4_title", "product4_price", "product4_caption",
    "featured_note",

    /* FEATURES BANNER */
    "feature1_title", "feature1_subtitle",
    "feature2_title", "feature2_subtitle",
    "feature3_title", "feature3_subtitle",
    "feature4_title", "feature4_subtitle",

    /* REVIEWS */
    "reviews_heading",
    "reviews_subtitle",

    /* COMMISSION CTA */
    "commission_eyebrow",
    "commission_heading",
    "commission_text",

    /* FOOTER / CONTACT */
    "footer_tagline",
    "contact_address",
    "contact_phone",
    "contact_email",
];

foreach ($editableFields as $field) {

    if (!isset($_POST[$field])) {
        continue;
    }

    $value = trim((string) $_POST[$field]);

    figurify_save_content($conn, $field, $value, $ownerId);

}

// tanggalin sa Image/carousel/ ang mga file na wala nang gumagamit
$freshContent = figurify_get_all_content($conn);
figurify_sync_category_images("carousel", array_values($freshContent));

header("Location: content-management.php?saved=1");
exit();
