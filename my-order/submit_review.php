<?php

/* leave a review page - pwede galing My Orders (naka-login) or sa email link
   (walang login, token na lang). one review per order lang, read-only na
   pag may nasumite na. */

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/review_helper.php";
require_once __DIR__ . "/../helpers/notification_helper.php";


/* read + validate order_id / token */

$order_id = isset($_GET["order_id"]) ? (int) $_GET["order_id"] : (int) ($_POST["order_id"] ?? 0);
$token    = trim($_GET["token"] ?? $_POST["token"] ?? "");

$pageError = "";
$order     = null;

if ($order_id <= 0 || $token === "") {

    $pageError = "This review link is invalid.";

} else {

    $stmt = $conn->prepare(
        "SELECT o.order_id, o.user_id, o.status, o.review_token,
                u.full_name
         FROM orders o
         JOIN users u ON u.user_id = o.user_id
         WHERE o.order_id = ?
         LIMIT 1"
    );
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order || empty($order["review_token"]) || !hash_equals($order["review_token"], $token)) {

        $pageError = "This review link is invalid or has expired.";
        $order     = null;

    } elseif (strtolower(trim((string) $order["status"])) !== "completed") {

        $pageError = "Reviews can only be left once your order is completed.";
        $order     = null;

    } elseif (isset($_SESSION["user_id"]) && (int) $_SESSION["user_id"] !== (int) $order["user_id"]) {

        // Naka-login sa ibang account — hindi sa kanya ang order na ito.
        $pageError = "This review link isn't for your account.";
        $order     = null;

    }

}


/* kung meron nang naisumiteng review, read-only na lang ipapakita, walang form */

$existingReview = $order ? figurify_get_review($conn, $order_id) : null;

$formError   = "";
$justSubmitted = isset($_GET["done"]) && $_GET["done"] === "1";


/* handle submit */

if ($order && !$existingReview && $_SERVER["REQUEST_METHOD"] === "POST") {

    $rating      = isset($_POST["rating"]) ? (int) $_POST["rating"] : 0;
    $reviewText  = trim($_POST["review_text"] ?? "");

    if ($rating < 1 || $rating > 5) {
        $formError = "Please choose a star rating.";
    }

    // Logged in as the order's own customer = "system", galing sa Gmail link = "email"
    $submittedVia = (isset($_SESSION["user_id"]) && (int) $_SESSION["user_id"] === (int) $order["user_id"])
        ? "system"
        : "email";

    $savedImagePaths = [];

    if ($formError === "" && !empty($_FILES["review_images"]["name"][0])) {

        $allowedExt  = ["jpg", "jpeg", "png", "gif", "webp"];
        $uploadDir   = __DIR__ . "/../uploads/orders/" . $order_id . "/reviews/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileCount = count($_FILES["review_images"]["name"]);

        for ($i = 0; $i < $fileCount && $i < 5; $i++) {

            if ($_FILES["review_images"]["error"][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $ext = strtolower(pathinfo($_FILES["review_images"]["name"][$i], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true)) {
                continue;
            }

            $safeName = "review_" . $order_id . "_" . time() . "_" . random_int(1000, 9999) . "_" . $i . "." . $ext;
            $destPath = $uploadDir . $safeName;

            if (move_uploaded_file($_FILES["review_images"]["tmp_name"][$i], $destPath)) {
                $savedImagePaths[] = "uploads/orders/" . $order_id . "/reviews/" . $safeName;
            }

        }

    }

    if ($formError === "") {

        // Huling check bago mag-INSERT, sakaling nag-race (sabay-sabay submit sa Gmail at My Orders)
        $recheck = figurify_get_review($conn, $order_id);

        if ($recheck) {

            $existingReview = $recheck;

        } else {

            $insertStmt = $conn->prepare(
                "INSERT INTO reviews (order_id, user_id, rating, review_text, submitted_via)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $insertStmt->bind_param(
                "iiiss",
                $order_id,
                $order["user_id"],
                $rating,
                $reviewText,
                $submittedVia
            );

            try {

                $insertStmt->execute();
                $reviewId = $insertStmt->insert_id;
                $insertStmt->close();

                foreach ($savedImagePaths as $imgPath) {

                    $imgStmt = $conn->prepare(
                        "INSERT INTO review_images (review_id, image_path) VALUES (?, ?)"
                    );
                    $imgStmt->bind_param("is", $reviewId, $imgPath);
                    $imgStmt->execute();
                    $imgStmt->close();

                }


                /* notify owner + staff — bagong review na naiwan ng customer */

                figurify_notify_staff(
                    $conn,
                    $order_id,
                    "A customer left a " . $rating . "-star review for order #" . $order_id . "."
                );

                header(
                    "Location: submit_review.php?order_id=" . $order_id
                    . "&token=" . urlencode($token) . "&done=1"
                );
                exit();

            } catch (\Throwable $e) {

                // Malamang parehong nagsubmit sabay-sabay — ipakita na lang yung nauna
                $existingReview = figurify_get_review($conn, $order_id);

            }

        }

    }

}

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="../Image/logoclay.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leave a Review — Clay and Stuff</title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: 'Segoe UI', Arial, sans-serif;
        background: #fdf4f8;
        margin: 0;
        padding: 30px 16px;
        color: #4a2a3a;
    }
    .card {
        max-width: 480px;
        margin: 0 auto;
        background: #ffffff;
        border-radius: 20px;
        padding: 28px 26px;
        box-shadow: 0 10px 30px rgba(200, 90, 140, .12);
    }
    h1 {
        font-size: 20px;
        color: #86365f;
        margin: 0 0 4px;
    }
    .sub {
        font-size: 13.5px;
        color: #8a6b78;
        margin-bottom: 22px;
    }
    .error-box {
        background: #fdeaea;
        color: #a33333;
        border-radius: 12px;
        padding: 14px 16px;
        font-size: 13.5px;
    }
    .success-box {
        background: #e9f7ef;
        color: #227a4c;
        border-radius: 12px;
        padding: 12px 16px;
        font-size: 13px;
        margin-bottom: 18px;
    }
    .stars {
        display: flex;
        flex-direction: row-reverse;
        justify-content: flex-end;
        gap: 4px;
        margin: 6px 0 20px;
    }
    .stars input { display: none; }
    .stars label {
        font-size: 34px;
        line-height: 1;
        color: #e3d3da;
        cursor: pointer;
        transition: color .15s;
    }
    .stars input:checked ~ label,
    .stars label:hover,
    .stars label:hover ~ label {
        color: #f2699b;
    }
    label.field-label {
        display: block;
        font-size: 12.5px;
        font-weight: bold;
        color: #75445e;
        margin-bottom: 6px;
    }
    textarea, input[type="file"] {
        width: 100%;
        font-family: inherit;
        font-size: 13.5px;
        border: 1px solid #f0d9e4;
        border-radius: 12px;
        padding: 12px;
        margin-bottom: 18px;
        resize: vertical;
        color: #4a2a3a;
    }
    textarea { min-height: 90px; }
    input[type="file"] { padding: 10px; background: #fff8fb; }
    .hint { font-size: 11.5px; color: #a58a95; margin: -12px 0 18px; }
    .btn-submit {
        width: 100%;
        border: none;
        padding: 13px 20px;
        border-radius: 14px;
        font-size: 14px;
        font-weight: bold;
        font-family: inherit;
        cursor: pointer;
        color: #fff;
        background: linear-gradient(135deg, #f2699b, #e0447f);
        box-shadow: 0 6px 14px rgba(224, 68, 127, .35);
    }
    .btn-submit:hover { filter: brightness(1.05); }

    .review-stars-readonly { font-size: 28px; color: #f2699b; letter-spacing: 2px; margin-bottom: 10px; }
    .review-text-readonly {
        font-size: 14px;
        line-height: 1.6;
        background: #fdf4f8;
        border-radius: 12px;
        padding: 14px 16px;
        margin-bottom: 16px;
        white-space: pre-wrap;
    }
    .review-images-readonly {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 6px;
    }
    .review-images-readonly img {
        width: 92px;
        height: 92px;
        object-fit: cover;
        border-radius: 12px;
        border: 1px solid #f0d9e4;
    }
    .meta { font-size: 11.5px; color: #a58a95; margin-top: 4px; }
    a.back-link {
        display: inline-block;
        margin-top: 20px;
        font-size: 12.5px;
        color: #86365f;
        text-decoration: none;
    }
</style>
</head>
<body>

<div class="card">

    <?php if ($pageError !== ""): ?>

        <h1>Leave a Review</h1>
        <div class="error-box"><?php echo htmlspecialchars($pageError); ?></div>

    <?php elseif ($existingReview): ?>

        <h1>Your Review</h1>
        <div class="sub">Order #<?php echo (int) $order_id; ?> — thank you for your feedback!</div>

        <?php if ($justSubmitted): ?>
            <div class="success-box">Thank you! Your review has been submitted.</div>
        <?php endif; ?>

        <div class="review-stars-readonly">
            <?php
            $rating = (int) $existingReview["rating"];
            echo str_repeat("★", $rating) . str_repeat("☆", 5 - $rating);
            ?>
        </div>

        <?php if (!empty($existingReview["review_text"])): ?>
            <div class="review-text-readonly"><?php echo nl2br(htmlspecialchars($existingReview["review_text"])); ?></div>
        <?php endif; ?>

        <?php if (!empty($existingReview["images"])): ?>
            <div class="review-images-readonly">
                <?php foreach ($existingReview["images"] as $imgPath): ?>
                    <img src="../<?php echo htmlspecialchars($imgPath); ?>" alt="Review photo">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="meta">
            Submitted <?php echo date("F j, Y — g:i A", strtotime($existingReview["created_at"])); ?>
            via <?php echo $existingReview["submitted_via"] === "email" ? "email" : "the website"; ?>.
            You can only submit one review per order.
        </p>

        <a class="back-link" href="my-orders.php">← Back to My Orders</a>

    <?php else: ?>

        <h1>Leave a Review</h1>
        <div class="sub">Order #<?php echo (int) $order_id; ?> — how was your figure?</div>

        <?php if ($formError !== ""): ?>
            <div class="error-box" style="margin-bottom:18px;"><?php echo htmlspecialchars($formError); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" action="submit_review.php">
            <input type="hidden" name="order_id" value="<?php echo (int) $order_id; ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <label class="field-label">Your Rating</label>
            <div class="stars">
                <input type="radio" name="rating" id="star5" value="5"><label for="star5">★</label>
                <input type="radio" name="rating" id="star4" value="4"><label for="star4">★</label>
                <input type="radio" name="rating" id="star3" value="3"><label for="star3">★</label>
                <input type="radio" name="rating" id="star2" value="2"><label for="star2">★</label>
                <input type="radio" name="rating" id="star1" value="1"><label for="star1">★</label>
            </div>

            <label class="field-label" for="review_text">Your Review</label>
            <textarea name="review_text" id="review_text" placeholder="Tell us about your experience..."></textarea>

            <label class="field-label" for="review_images">Add Photos (optional)</label>
            <input type="file" name="review_images[]" id="review_images" accept="image/*" multiple>
            <div class="hint">Up to 5 photos — JPG, PNG, GIF, or WEBP.</div>

            <button type="submit" class="btn-submit">Submit Review</button>
        </form>

    <?php endif; ?>

</div>

</body>
</html>
