<?php

/* Customer review helper (rating + text + images) ng completed order.
   Ginagamit ng My Orders (naka-login) at completion email (review_token,
   walang login) — pareho papunta sa submit_review.php. */

require_once __DIR__ . "/../Admin/database.php";


if (!function_exists("figurify_ensure_review_token")) {

    // gets the order's review_token, generating and saving one if it's empty
    function figurify_ensure_review_token(mysqli $conn, int $orderId): string
    {
        $stmt = $conn->prepare(
            "SELECT review_token FROM orders WHERE order_id = ? LIMIT 1"
        );
        $stmt->bind_param("i", $orderId);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && !empty($row["review_token"])) {
            return $row["review_token"];
        }

        $token = bin2hex(random_bytes(20));

        $saveStmt = $conn->prepare(
            "UPDATE orders SET review_token = ? WHERE order_id = ?"
        );
        $saveStmt->bind_param("si", $token, $orderId);
        $saveStmt->execute();
        $saveStmt->close();

        return $token;
    }
}


if (!function_exists("figurify_review_url")) {

    // full link to the review page — works without login since
    // order_id + token is the "key"
    function figurify_review_url(int $orderId, string $token): string
    {
        $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
        $host   = $_SERVER["HTTP_HOST"] ?? "localhost";

        // Kunin ang project root (hal. "/clayandstuff") galing sa
        // kasalukuyang script — gumagana ito mula staff/, owner/,
        // o my-order/, dahil magkakapatid silang lahat sa ilalim
        // ng isang parehong project folder.
        $scriptDir   = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? ""));
        $projectRoot = rtrim(dirname($scriptDir), "/");

        return $scheme . "://" . $host . $projectRoot
            . "/my-order/submit_review.php?order_id=" . $orderId
            . "&token=" . urlencode($token);
    }
}


if (!function_exists("figurify_get_review")) {

    // gets the submitted review + images for an order, null if none yet
    function figurify_get_review(mysqli $conn, int $orderId): ?array
    {
        $stmt = $conn->prepare(
            "SELECT review_id, order_id, user_id, rating, review_text,
                    submitted_via, created_at
             FROM reviews
             WHERE order_id = ?
             LIMIT 1"
        );
        $stmt->bind_param("i", $orderId);
        $stmt->execute();

        $review = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$review) {
            return null;
        }

        $imgStmt = $conn->prepare(
            "SELECT image_path FROM review_images WHERE review_id = ? ORDER BY review_image_id ASC"
        );
        $imgStmt->bind_param("i", $review["review_id"]);
        $imgStmt->execute();

        $images = [];
        $imgResult = $imgStmt->get_result();

        while ($imgRow = $imgResult->fetch_assoc()) {
            $images[] = $imgRow["image_path"];
        }

        $imgStmt->close();

        $review["images"] = $images;

        return $review;
    }
}
