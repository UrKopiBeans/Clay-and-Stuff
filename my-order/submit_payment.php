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


/* read posted fields */

$order_id          = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
$shipping_name     = trim($_POST["shipping_name"] ?? "");
$shipping_address  = trim($_POST["shipping_address"] ?? "");
$pin_address_raw   = trim($_POST["pin_address"] ?? "");
$pin_address       = $pin_address_raw !== "" ? $pin_address_raw : null;
$shipping_contact  = trim($_POST["shipping_contact"] ?? "");
$payment_reference = trim($_POST["payment_reference"] ?? "");

$refund_account_name   = trim($_POST["refund_account_name"] ?? "");
$refund_account_number = trim($_POST["refund_account_number"] ?? "");
$refund_method         = trim($_POST["refund_method"] ?? "");
$refund_method_other   = trim($_POST["refund_method_other"] ?? "");

$courier = trim($_POST["courier"] ?? "");

$agree_terms = $_POST["agree_terms"] ?? "";

$allowedRefundMethods = ["gcash", "bpi", "other"];
$allowedCouriers      = ["lbc", "lalamove"];

/* if "Other" was picked, the actual bank/method name typed in
   is what we want to store */
$refundMethodValid = in_array($refund_method, $allowedRefundMethods, true)
    && ($refund_method !== "other" || $refund_method_other !== "");

$refund_method_final = ($refund_method === "other")
    ? $refund_method_other
    : $refund_method;


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
        $fileName  = "payment_" . $order_id . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $extension;

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
    $shipping_name !== "" &&
    $shipping_address !== "" &&
    preg_match('/^[0-9]{11}$/', $shipping_contact) &&
    in_array($courier, $allowedCouriers, true) &&
    $payment_reference !== "" &&
    $paymentProofOk &&
    $refund_account_name !== "" &&
    preg_match('/^[0-9]{1,11}$/', $refund_account_number) &&
    $refundMethodValid &&
    !empty($agree_terms)
) {

    /* make sure: ari niya ang order at "awaiting_payment" pa rin ito */

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

    $ownsOrder      = $order && (int) $order["user_id"] === (int) $user_id;
    $awaitingPaymnt = $order && $order["status"] === "awaiting_payment";

    if ($ownsOrder && $awaitingPaymnt) {

        $updateStmt = $conn->prepare(
            "UPDATE orders
             SET shipping_name = ?, shipping_address = ?, pin_address = ?, shipping_contact = ?, courier = ?,
                 payment_reference = ?, payment_proof = ?,
                 refund_account_name = ?, refund_account_number = ?, refund_method = ?,
                 paid_at = NOW(), status = 'to_verify'
             WHERE order_id = ?"
        );

        $updateStmt->bind_param(
            "ssssssssssi",
            $shipping_name,
            $shipping_address,
            $pin_address,
            $shipping_contact,
            $courier,
            $payment_reference,
            $paymentProofPath,
            $refund_account_name,
            $refund_account_number,
            $refund_method_final,
            $order_id
        );

        $updateStmt->execute();
        $updateStmt->close();


        /* notify the customer */

        $message = "We received your payment details for order #" . $order_id
            . ". Our staff will verify it shortly.";

        figurify_notify_user($conn, (int) $user_id, $order_id, $message);


        /* notify owner + staff — may bagong payment na dapat i-verify */

        figurify_notify_staff(
            $conn,
            $order_id,
            "Payment details submitted for order #" . $order_id . " — needs verification."
        );

        $conn->close();

        header("Location: my-orders.php?order_id=" . $order_id . "&submitted=1");
        exit();
    }

}

$conn->close();


/* validation failed — balik sa form para maulit, walang "submitted=1" */

header("Location: payment-shipping.php?order_id=" . $order_id . "&error=1");
exit();