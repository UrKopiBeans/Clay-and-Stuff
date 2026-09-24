<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../helpers/csrf_helper.php";
require_once "../Admin/database.php";


if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    exit("Invalid request.");

}


if (!figurify_verify_csrf($_POST["csrf_token"] ?? null)) {

    exit("Your session has expired. Please refresh the page and try again.");

}


$full_name =
    trim($_POST["full_name"] ?? "");


$email =
    trim($_POST["email"] ?? "");


$password =
    $_POST["password"] ?? "";


$confirm_password =
    $_POST["confirm_password"] ?? "";


$agree_terms =
    $_POST["agree_terms"] ?? "";


/*
    Check fields
*/

if (
    empty($full_name) ||
    empty($email) ||
    empty($password) ||
    empty($confirm_password)
) {

    exit(
        "Please complete all fields."
    );

}


/*
    Check consent (Terms and Conditions + Privacy Policy)
*/

if (empty($agree_terms)) {

    exit(
        "Please agree to the Terms and Conditions and Privacy Policy before creating an account."
    );

}


/*
    Check password
*/

if (strlen($password) < 8) {

    exit(
        "Password must be at least 8 characters."
    );

}


if ($password !== $confirm_password) {

    exit(
        "Passwords do not match."
    );

}


/*
    Check if email was verified
*/

$verified_email =
    $_SESSION["verified_email"] ?? "";


if (
    empty($verified_email) ||
    strtolower($verified_email) !==
    strtolower($email)
) {

    exit(
        "Please verify your email first."
    );

}


/*
    Check verification database
*/

$stmt =
    $conn->prepare(
        "SELECT id
         FROM email_verifications
         WHERE email = ?
         AND verified = 1
         ORDER BY id DESC
         LIMIT 1"
    );


$stmt->bind_param(
    "s",
    $email
);


$stmt->execute();


$result =
    $stmt->get_result();


if ($result->num_rows !== 1) {

    $stmt->close();

    exit(
        "Please verify your email first."
    );

}


$stmt->close();


/*
    Check existing account
*/

$check =
    $conn->prepare(
        "SELECT user_id
         FROM users
         WHERE email = ?"
    );


$check->bind_param(
    "s",
    $email
);


$check->execute();

$check->store_result();


if ($check->num_rows > 0) {

    $check->close();

    exit(
        "This email is already registered."
    );

}


$check->close();


/*
    Hash password
*/

$hashed_password =
    password_hash(
        $password,
        PASSWORD_DEFAULT
    );


/*
    Create customer account
*/

$stmt =
    $conn->prepare(
        "INSERT INTO users
        (full_name, email, password, role)
        VALUES (?, ?, ?, 'customer')"
    );


$stmt->bind_param(
    "sss",
    $full_name,
    $email,
    $hashed_password
);


if ($stmt->execute()) {

    /*
        Clear verification session
    */

    unset(
        $_SESSION["verified_email"]
    );

    unset(
        $_SESSION["verified_name"]
    );


    echo "success";


} else {

    echo
        "Something went wrong while creating the account.";

}


$stmt->close();

$conn->close();

?>