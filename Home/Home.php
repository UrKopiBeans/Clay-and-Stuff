<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

// db connection
$conn = new mysqli("localhost", "root", "", "figurify_db");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

require_once __DIR__ . "/../helpers/content_helper.php";

// content the owner edited in Content Management, falls back to
// the defaults below if nothing's saved yet
$siteContent = figurify_get_all_content($conn);

function c($key, $default)
{
    global $siteContent;
    return figurify_content($siteContent, $key, $default);
}

$customer_name = "";
$customer_email = "";
$profile_picture = "";

// get logged in customer, if any
if (isset($_SESSION["user_id"])) {

    $user_id = $_SESSION["user_id"];

    $stmt = $conn->prepare("
        SELECT full_name, email, profile_picture
        FROM users
        WHERE user_id = ?
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $customer_name = $user["full_name"];
        $customer_email = $user["email"];
        $profile_picture = $user["profile_picture"];
    }

    $stmt->close();
}


// reviews come from the `reviews` table, no dummy content

$homeReviews = [];

$reviewsQuery = $conn->prepare(
    "SELECT r.review_id, r.rating, r.review_text, u.full_name,
            (SELECT ri.image_path
             FROM review_images ri
             WHERE ri.review_id = r.review_id
             ORDER BY ri.review_image_id ASC
             LIMIT 1) AS review_image
     FROM reviews r
     JOIN users u ON u.user_id = r.user_id
     WHERE r.rating >= 4
     ORDER BY r.created_at DESC
     LIMIT 12"
);

if ($reviewsQuery) {

    $reviewsQuery->execute();
    $reviewsResult = $reviewsQuery->get_result();

    while ($reviewRow = $reviewsResult->fetch_assoc()) {
        $homeReviews[] = $reviewRow;
    }

    $reviewsQuery->close();

}
?>
<!doctype html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../Image/logoclay.png">

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Clay and Stuff — Home</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500;1,9..144,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- home css -->
    <link rel="stylesheet" href="Home.css?v=3" />

</head>

<body>


<!-- navbar -->

<?php include "../Shared/navbar.php"; ?>


<!-- hero carousel -->

<header class="hero" id="top">

    <div class="carousel-track" id="carouselTrack">

        <!-- slide 1: content from Content Management, image is now the full slide background -->
        <div
            class="carousel-slide"
            style="background-image: url('<?php echo htmlspecialchars(figurify_resolve_image_src(c("hero_image", ""), "https://images.unsplash.com/photo-1534447677768-be436bb09401?auto=format&fit=crop&w=800&q=80"), ENT_QUOTES, "UTF-8"); ?>');"
        >
            <div class="slide-inner">

                <div class="hero-copy">

                    <p class="eyebrow">
                        <?php echo htmlspecialchars(c("hero_eyebrow", "Tiny keepsakes for big feelings"), ENT_QUOTES, "UTF-8"); ?>
                    </p>

                    <h1>
                        <?php
                        $heroHeading = c("hero_heading", "Turning memories into mini masterpieces");
                        echo htmlspecialchars(str_replace("|", " ", $heroHeading), ENT_QUOTES, "UTF-8");
                        ?>
                    </h1>

                    <p class="tagline">
                        <?php echo htmlspecialchars(c("hero_tagline", "Hand-sculpted polymer clay pieces made from your favorite moments — no two are ever exactly alike."), ENT_QUOTES, "UTF-8"); ?>
                    </p>

                    <div class="hero-actions">
                        <a class="btn" href="../Collection/collection.php">
                            <?php echo htmlspecialchars(c("hero_link_text", "Shop the collection"), ENT_QUOTES, "UTF-8"); ?>
                            <span>→</span>
                        </a>
                    </div>

                </div>

            </div>

        </div>

        <!-- slide 2: commission promo -->
        <div
            class="carousel-slide"
            style="background-image: url('<?php echo htmlspecialchars(figurify_resolve_image_src(c("slide2_image", ""), "https://images.unsplash.com/photo-1513519245088-0e12902e5a38?auto=format&fit=crop&w=800&q=80"), ENT_QUOTES, "UTF-8"); ?>');"
        >
            <div class="slide-inner">

                <div class="hero-copy">
                    <p class="eyebrow"><?php echo htmlspecialchars(c("slide2_eyebrow", "Customized just for you"), ENT_QUOTES, "UTF-8"); ?></p>
                    <h1><?php echo htmlspecialchars(c("slide2_heading", "Handcrafted couple figures made with love"), ENT_QUOTES, "UTF-8"); ?></h1>
                    <p class="tagline"><?php echo htmlspecialchars(c("slide2_tagline", "Send a photo, tell us your story, and we'll shape it into a little keepsake you can hold onto forever."), ENT_QUOTES, "UTF-8"); ?></p>
                    <div class="hero-actions">
                        <a class="btn" href="../Commission/commission.php"><?php echo htmlspecialchars(c("slide2_link_text", "Commission a piece"), ENT_QUOTES, "UTF-8"); ?> <span>→</span></a>
                    </div>
                </div>

            </div>

        </div>

        <!-- slide 3: wedding / special events promo -->
        <div
            class="carousel-slide"
            style="background-image: url('<?php echo htmlspecialchars(figurify_resolve_image_src(c("slide3_image", ""), "https://images.unsplash.com/photo-1579783902614-a3fb3927b675?auto=format&fit=crop&w=800&q=80"), ENT_QUOTES, "UTF-8"); ?>');"
        >
            <div class="slide-inner">

                <div class="hero-copy">
                    <p class="eyebrow"><?php echo htmlspecialchars(c("slide3_eyebrow", "Special events & weddings"), ENT_QUOTES, "UTF-8"); ?></p>
                    <h1><?php echo htmlspecialchars(c("slide3_heading", "Capture your special day in cute clay style"), ENT_QUOTES, "UTF-8"); ?></h1>
                    <p class="tagline"><?php echo htmlspecialchars(c("slide3_tagline", "From bridal parties to first dances, we sculpt the day you'll want to keep on your shelf."), ENT_QUOTES, "UTF-8"); ?></p>
                    <div class="hero-actions">
                        <a class="btn" href="../Collection/collection.php"><?php echo htmlspecialchars(c("slide3_link_text", "Explore wedding sets"), ENT_QUOTES, "UTF-8"); ?> <span>→</span></a>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <div class="carousel-dots" id="carouselDots">
        <span class="dot active" data-index="0"></span>
        <span class="dot" data-index="1"></span>
        <span class="dot" data-index="2"></span>
    </div>

</header>


<!-- main home content -->

<main class="page-container">


    <!-- about -->

    <section class="about-section" id="overview">
        <div class="about-grid">

            <div class="about-media">
                <div class="about-video-box">
                    <video autoplay muted loop playsinline>
                        <source src="<?php echo htmlspecialchars(c("about_video", "https://assets.mixkit.co/videos/preview/mixkit-hands-working-on-pottery-42995-large.mp4"), ENT_QUOTES, "UTF-8"); ?>" type="video/mp4">
                    </video>
                </div>
                <div class="about-badge"><?php echo htmlspecialchars(c("about_badge", "Clay & Stuff"), ENT_QUOTES, "UTF-8"); ?></div>
            </div>

            <div class="about-content">

                <p class="eyebrow">
                    <?php echo htmlspecialchars(c("about_heading", "About us"), ENT_QUOTES, "UTF-8"); ?>
                </p>

                <h2>
                    <?php echo htmlspecialchars(c("about_script", "Crafting smiles out of tiny pieces of clay"), ENT_QUOTES, "UTF-8"); ?>
                </h2>

                <p>
                    <?php echo htmlspecialchars(c("about_text", "Naniniwala kami na ang mga pinakamagandang alaala ay nabubuhay sa maliliit na detalye. Ang bawat obra ay buong-pusong hinuhubog para maging natatanging regalo para sa iyo at sa iyong mga mahal sa buhay."), ENT_QUOTES, "UTF-8"); ?>
                </p>

                <p>
                    <?php echo htmlspecialchars(c("about_sign", "Mula sa mga custom couple figures hanggang sa mga espesyal na tagpo sa buhay, ginagawa naming sining ang iyong mga kwento."), ENT_QUOTES, "UTF-8"); ?>
                </p>

            </div>

        </div>
    </section>


    <!-- featured orders -->

    <section class="featured-section" id="collections">

        <div class="section-heading">
            <h2><?php echo htmlspecialchars(c("featured_heading", "Featured orders"), ENT_QUOTES, "UTF-8"); ?></h2>
            <p><?php echo htmlspecialchars(c("featured_subtitle", "Our most-loved pieces, shaped one at a time"), ENT_QUOTES, "UTF-8"); ?></p>
        </div>

        <div class="orders-grid">

            <?php
            // defaults, overridden by Content Management if set
            $featuredProducts = [
                1 => ["image" => "Hirono-Couple.jpg", "title" => "Custom Couple Figurine", "price" => "₱850"],
                2 => ["image" => "Hirono.jpg",         "title" => "Hirono Inspired",         "price" => "₱650"],
                3 => ["image" => "ch-fbs.jpg",         "title" => "Wedding",                 "price" => "₱1,200"],
                4 => ["image" => "ch-ho.jpg",          "title" => "Custom Keychains",         "price" => "₱150 each"],
            ];
            ?>

            <?php foreach ($featuredProducts as $pIndex => $pDefault): ?>

                <!-- PRODUCT <?php echo $pIndex; ?> -->
                <div class="order-card">
                    <div class="order-media">
                        <span class="order-price-tag"><?php echo htmlspecialchars(c("product{$pIndex}_price", $pDefault["price"]), ENT_QUOTES, "UTF-8"); ?></span>
                        <img
                            src="<?php echo htmlspecialchars(figurify_resolve_image_src(c("product{$pIndex}_image", ""), "../Image/" . $pDefault["image"]), ENT_QUOTES, "UTF-8"); ?>"
                            alt="<?php echo htmlspecialchars(c("product{$pIndex}_title", $pDefault["title"]), ENT_QUOTES, "UTF-8"); ?>"
                        >
                    </div>
                    <div class="order-body">
                        <h3 class="order-title">
                            <?php echo htmlspecialchars(c("product{$pIndex}_title", $pDefault["title"]), ENT_QUOTES, "UTF-8"); ?>
                        </h3>
                        <?php $productCaption = c("product{$pIndex}_caption", ""); ?>
                        <?php if ($productCaption !== ""): ?>
                            <p class="order-caption"><?php echo htmlspecialchars($productCaption, ENT_QUOTES, "UTF-8"); ?></p>
                        <?php endif; ?>
                        <a href="../Commission/commission.php" class="order-btn">Order now <span>→</span></a>
                    </div>
                </div>

            <?php endforeach; ?>


        </div>

        <p class="featured-note">
            <?php echo htmlspecialchars(c("featured_note", "We can make it exactly how you imagine it — from the pose to the little details."), ENT_QUOTES, "UTF-8"); ?>
        </p>

    </section>


    <!-- features banner -->

    <section class="features-banner">
        <div class="features-banner-inner">

            <div class="feature-item">
                <div class="feature-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 20.5C12 20.5 4 15.4 4 9.6C4 6.6 6.3 4.5 9 4.5C10.6 4.5 12 5.3 12 5.3C12 5.3 13.4 4.5 15 4.5C17.7 4.5 20 6.6 20 9.6C20 15.4 12 20.5 12 20.5Z" stroke="#db2777" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="feature-title-main"><?php echo htmlspecialchars(c("feature1_title", "Handmade"), ENT_QUOTES, "UTF-8"); ?></div>
                <div class="feature-subtitle"><?php echo htmlspecialchars(c("feature1_subtitle", "with love"), ENT_QUOTES, "UTF-8"); ?></div>
            </div>

            <div class="feature-item">
                <div class="feature-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6H14M4 12H10M4 18H14" stroke="#831843" stroke-width="1.8" stroke-linecap="round"/>
                        <circle cx="17" cy="6" r="2.3" stroke="#831843" stroke-width="1.8"/>
                        <circle cx="13" cy="12" r="2.3" stroke="#831843" stroke-width="1.8"/>
                        <circle cx="17" cy="18" r="2.3" stroke="#831843" stroke-width="1.8"/>
                    </svg>
                </div>
                <div class="feature-title-main"><?php echo htmlspecialchars(c("feature2_title", "Customize"), ENT_QUOTES, "UTF-8"); ?></div>
                <div class="feature-subtitle"><?php echo htmlspecialchars(c("feature2_subtitle", "your order"), ENT_QUOTES, "UTF-8"); ?></div>
            </div>

            <div class="feature-item">
                <div class="feature-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 3.5L19.5 6.7V11.2C19.5 15.9 16.5 19.7 12 21C7.5 19.7 4.5 15.9 4.5 11.2V6.7L12 3.5Z" stroke="#c17a1f" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M8.7 11.7L10.8 13.8L15.3 9.3" stroke="#c17a1f" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="feature-title-main"><?php echo htmlspecialchars(c("feature3_title", "Secured"), ENT_QUOTES, "UTF-8"); ?></div>
                <div class="feature-subtitle"><?php echo htmlspecialchars(c("feature3_subtitle", "packaging"), ENT_QUOTES, "UTF-8"); ?></div>
            </div>

            <div class="feature-item">
                <div class="feature-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 11.5C21 16.2 16.97 20 12 20C10.7 20 9.46 19.74 8.34 19.26L4 20.5L5.4 16.68C4.52 15.34 4 13.78 4 12.1C4 7.4 8.03 3.5 12.5 3.5C17.2 3.5 21 7.05 21 11.5Z" stroke="#a81457" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 11.5C9 12.05 8.55 12.5 8 12.5C7.45 12.5 7 12.05 7 11.5C7 10.95 7.45 10.5 8 10.5C8.55 10.5 9 10.95 9 11.5Z" fill="#a81457"/>
                        <path d="M13 11.5C13 12.05 12.55 12.5 12 12.5C11.45 12.5 11 12.05 11 11.5C11 10.95 11.45 10.5 12 10.5C12.55 10.5 13 10.95 13 11.5Z" fill="#a81457"/>
                        <path d="M17 11.5C17 12.05 16.55 12.5 16 12.5C15.45 12.5 15 12.05 15 11.5C15 10.95 15.45 10.5 16 10.5C16.55 10.5 17 10.95 17 11.5Z" fill="#a81457"/>
                    </svg>
                </div>
                <div class="feature-title-main"><?php echo htmlspecialchars(c("feature4_title", "Friendly"), ENT_QUOTES, "UTF-8"); ?></div>
                <div class="feature-subtitle"><?php echo htmlspecialchars(c("feature4_subtitle", "service"), ENT_QUOTES, "UTF-8"); ?></div>
            </div>

        </div>
    </section>


    <!-- reviews -->

    <section class="reviews-section" id="reviews">

        <div class="reviews-header">
            <h2><?php echo htmlspecialchars(c("reviews_heading", "What our customers say"), ENT_QUOTES, "UTF-8"); ?></h2>
            <p><?php echo htmlspecialchars(c("reviews_subtitle", "Loved by our amazing collectors & gift-givers"), ENT_QUOTES, "UTF-8"); ?></p>
        </div>

        <?php if (empty($homeReviews)): ?>

            <!-- no reviews yet, just show empty state -->

            <div class="reviews-empty">
                <p>No reviews yet — be the first to share your experience!</p>
            </div>

        <?php else: ?>

            <?php
            // marquee needs the track looped 2x for a seamless scroll,
            // but that looks repetitive with too few reviews, so only
            // loop it once we hit this threshold
            $reviewsMarqueeThreshold = 4;
            $enableReviewsMarquee    = count($homeReviews) >= $reviewsMarqueeThreshold;
            $reviewLoopPasses        = $enableReviewsMarquee ? 2 : 1;
            ?>

            <div class="marquee-wrapper">
                <div class="marquee-track<?php echo $enableReviewsMarquee ? "" : " marquee-track-static"; ?>">

                    <?php
                    for ($loopPass = 0; $loopPass < $reviewLoopPasses; $loopPass++):
                        foreach ($homeReviews as $homeReview):
                            $reviewName  = htmlspecialchars($homeReview["full_name"]);
                            $reviewStars = str_repeat("★", (int) $homeReview["rating"]) . str_repeat("☆", 5 - (int) $homeReview["rating"]);
                            $reviewText  = htmlspecialchars($homeReview["review_text"] ?: "");
                    ?>

                        <div class="review-card">
                            <div class="review-top">
                                <div class="review-author">
                                    <div class="review-avatar"><img src="../Image/logoclay.png" alt="Clay and Stuff"></div>
                                    <div class="review-meta">
                                        <h4><?php echo $reviewName; ?></h4>
                                        <span class="review-email">Verified Customer</span>
                                    </div>
                                </div>
                                <div class="review-stars"><?php echo $reviewStars; ?></div>
                            </div>
                            <div class="review-bottom">
                                <?php if ($reviewText !== ""): ?>
                                    <p class="review-text">"<?php echo $reviewText; ?>"</p>
                                <?php endif; ?>
                                <?php if (!empty($homeReview["review_image"])): ?>
                                    <img class="review-thumb" src="../<?php echo htmlspecialchars($homeReview["review_image"]); ?>" alt="Customer photo">
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php
                        endforeach;
                    endfor;
                    ?>

                </div>
            </div>

        <?php endif; ?>

    </section>


    <!-- commission CTA, links to Commission.php -->

    <section class="commission-section" id="commission">

        <p class="eyebrow">
            <?php echo htmlspecialchars(c("commission_eyebrow", "Let's make something personal"), ENT_QUOTES, "UTF-8"); ?>
        </p>

        <h2>
            <?php echo htmlspecialchars(c("commission_heading", "Commission a figure"), ENT_QUOTES, "UTF-8"); ?>
        </h2>

        <p class="commission-text">
            <?php echo htmlspecialchars(c("commission_text", "Tell us your idea and we'll bring it to life in clay."), ENT_QUOTES, "UTF-8"); ?>
        </p>

        <a class="btn" href="../Commission/commission.php">
            Get started <span>→</span>
        </a>

    </section>


</main>


<!-- footer -->

<?php include "../Shared/footer.php"; ?>


<!-- hero carousel script -->

<script>
document.addEventListener("DOMContentLoaded", function () {

    const track = document.getElementById("carouselTrack");
    const originalSlides = document.querySelectorAll(".carousel-slide");
    const dots = document.querySelectorAll("#carouselDots .dot");

    if (!track || originalSlides.length === 0) {
        return;
    }

    const firstClone = originalSlides[0].cloneNode(true);
    track.appendChild(firstClone);

    let currentIndex = 0;
    const totalOriginalSlides = originalSlides.length;

    function updateSlide(withTransition = true) {

        track.style.transition = withTransition
            ? "transform .7s cubic-bezier(.65,0,.35,1)"
            : "none";

        track.style.transform = `translateX(-${currentIndex * 100}%)`;

        const dotIndex = currentIndex % totalOriginalSlides;

        dots.forEach((dot, index) => {
            dot.classList.toggle("active", index === dotIndex);
        });

    }

    function nextSlide() {

        currentIndex++;
        updateSlide(true);

        if (currentIndex === totalOriginalSlides) {
            setTimeout(() => {
                currentIndex = 0;
                updateSlide(false);
            }, 700);
        }

    }

    let slideInterval = setInterval(nextSlide, 4500);

    dots.forEach(dot => {
        dot.addEventListener("click", function () {
            currentIndex = parseInt(this.getAttribute("data-index"), 10);
            updateSlide(true);
            clearInterval(slideInterval);
            slideInterval = setInterval(nextSlide, 4500);
        });
    });

});
</script>


</body>
</html>
