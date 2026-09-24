<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../helpers/csrf_helper.php";

date_default_timezone_set("Asia/Manila");

require_once "../Admin/database.php";
require_once __DIR__ . "/../helpers/mailer_helper.php";
require_once __DIR__ . "/../helpers/resend_cooldown_helper.php";


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exit("Invalid request.");
}


if (!figurify_verify_csrf($_POST["csrf_token"] ?? null)) {
    exit("Your session has expired. Please refresh the page and try again.");
}


$name = trim($_POST["full_name"] ?? "");
$email = trim($_POST["email"] ?? "");


if (empty($name) || empty($email)) {
    exit("Please enter your name and Gmail.");
}


if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Please enter a valid email address.");
}


/*
    Check if email already exists
*/

$check = $conn->prepare(
    "SELECT user_id
     FROM users
     WHERE email = ?"
);

$check->bind_param("s", $email);
$check->execute();
$check->store_result();


if ($check->num_rows > 0) {

    $check->close();

    exit("This email is already registered.");
}


$check->close();


/*
    Resend cooldown - 10 minutes bago puwede ulit magpadala ng bagong
    code sa parehong email
*/

$cooldownRemaining = figurify_resend_cooldown_seconds_remaining($conn, $email);

if ($cooldownRemaining > 0) {
    exit("COOLDOWN|" . $cooldownRemaining . "|Please wait before requesting another code.");
}


/*
    Generate 6-digit verification code
*/

$code = str_pad(
    random_int(0, 999999),
    6,
    "0",
    STR_PAD_LEFT
);


/*
    Code expires after 5 minutes
*/

$expires = date(
    "Y-m-d H:i:s",
    time() + (5 * 60)
);


/*
    Delete previous code
*/

$delete = $conn->prepare(
    "DELETE FROM email_verifications
     WHERE email = ?"
);

$delete->bind_param("s", $email);
$delete->execute();
$delete->close();


/*
    Save new verification code
*/

$stmt = $conn->prepare(
    "INSERT INTO email_verifications
    (name, email, code, expires_at)
    VALUES (?, ?, ?, ?)"
);

$stmt->bind_param(
    "ssss",
    $name,
    $email,
    $code,
    $expires
);


if (!$stmt->execute()) {

    $stmt->close();
    $conn->close();

    exit("Failed to create verification code.");
}

$stmt->close();


/* Padala via Gmail gamit ang shared figurify_send_mail() — Admin/mail_config.php ang credentials. */

$emailBody = "

    <div style='
        font-family: Arial, sans-serif;
        max-width: 500px;
        margin: auto;
        padding: 20px;
    '>

        <h2>
            Clay And Stuff Email Verification
        </h2>

        <p>
            Hello
            <strong>" .
            htmlspecialchars($name) .
            "</strong>,
        </p>

        <p>
            Your verification code is:
        </p>

        <h1 style='
            letter-spacing: 8px;
            font-size: 32px;
        '>
            " . $code . "
        </h1>

        <p>
            This code will expire in
            <strong>5 minutes</strong>.
        </p>

        <p>
            If you did not create a
            Clay And Stuff account, you can
            ignore this email.
        </p>

        <br>

        <p>
            Thank you,<br>
            <strong>Figurify Team</strong>
        </p>

    </div>

";

$altBody =
    "Your Clay And Stuff verification code is: "
    . $code
    . ". This code expires in 5 minutes.";

$sent = figurify_send_mail(
    $email,
    $name,
    "Clay And Stuff Email Verification Code",
    $emailBody,
    $altBody
);

echo $sent ? "success" : "Email could not be sent. Please try again.";

$conn->close();

?>