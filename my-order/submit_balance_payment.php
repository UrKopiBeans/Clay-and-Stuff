<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/notification_helper.php";


/* must be logged in */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../Login/Login.php");
    exit();
}

$user_id = $_SESSION["user_id"];


/* read posted fields — same pattern as submit_payment.php, pero ito
   yung balance payment na lang (hindi na kasama shipping/refund
   details, nakuha na yun sa unang payment) */

$order_id          = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$payment_reference = trim($_POST["payment_reference"] ?? "");


/* validate the uploaded proof-of-payment image bago pa man dumampi sa database */

$paymentProofPath = null;
$paymentProofOk    = false;

$allowedImageMimes = [
    "image/jpeg" => "jpg",
    "image/png"  => "png",
    "image/webp" => "webp",
];

if (
    isset($_FILES["payment_proof"]) &&
    $_FILES["payment_proof"]["error"] === UPLOAD_ERR_OK &&
    $_FILES["payment_proof"]["size"] > 0 &&
    $_FILES["payment_proof"]["size"] <= 5 * 1024 * 1024 // 5MB max
) {

    $tmpPath  = $_FILES["payment_proof"]["tmp_name"];
    $mimeType = mime_content_type($tmpPath);

    if (isset($allowedImageMimes[$mimeType])) {

        $extension = $allowedImageMimes[$mimeType];
        $fileName  = "balance_" . $order_id . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $extension;

        $uploadDir = __DIR__ . "/../uploads/payment_proofs/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (move_uploaded_file($tmpPath, $uploadDir . $fileName)) {

            $paymentProofPath = "uploads/payment_proofs/" . $fileName;
            $paymentProofOk    = true;
        }
    }
}


/* validate the rest of the form */

if (
    $order_id > 0 &&
    $payment_reference !== "" &&
    $paymentProofOk
) {

    /* make sure: ari niya ang order at "awaiting_balance" pa rin ito */

    $stmt = $conn->prepare(
        "SELECT user_id, status
         FROM orders
         WHERE order_id = ?"
    );

    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $order   = $result->fetch_assoc();

    $stmt->close();

    $ownsOrder       = $order && (int) $order["user_id"] === (int) $user_id;
    $awaitingBalance = $order && $order["status"] === "awaiting_balance";

    if ($ownsOrder && $awaitingBalance) {

        $updateStmt = $conn->prepare(
            "UPDATE orders
             SET balance_payment_reference = ?, balance_proof = ?,
                 balance_paid_at = NOW(), status = 'for_balance_approval'
             WHERE order_id = ?"
        );

        $updateStmt->bind_param(
            "ssi",
            $payment_reference,
            $paymentProofPath,
            $order_id
        );

        $updateStmt->execute();
        $updateStmt->close();


        /* notify the customer */

        $message = "We received your balance payment for order #" . $order_id
            . ". The owner will review it shortly.";

        figurify_notify_user($conn, (int) $user_id, $order_id, $message);


        /* notify owner + staff — may bagong balance payment na dapat aprubahan ng owner */

        figurify_notify_staff(
            $conn,
            $order_id,
            "Balance payment submitted for order #" . $order_id . " — needs owner approval."
        );

        $conn->close();

        header("Location: my-orders.php?order_id=" . $order_id . "&submitted=1");
        exit();
    }

}

$conn->close();


/* validation failed — balik sa form para maulit, walang "submitted=1" */

header("Location: pay-balance.php?order_id=" . $order_id . "&error=1");
exit();
