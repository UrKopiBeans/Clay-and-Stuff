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


$email =
    trim($_POST["email"] ?? "");


$code =
    trim($_POST["code"] ?? "");


if (
    empty($email) ||
    empty($code)
) {

    exit(
        "Please enter the verification code."
    );

}


/*
    Check code
*/

$stmt =
    $conn->prepare(
        "SELECT id, name, email
         FROM email_verifications
         WHERE email = ?
         AND code = ?
         AND expires_at > NOW()
         AND verified = 0
         ORDER BY id DESC
         LIMIT 1"
    );


$stmt->bind_param(
    "ss",
    $email,
    $code
);


$stmt->execute();


$result =
    $stmt->get_result();


if ($result->num_rows === 1) {

    $verification =
        $result->fetch_assoc();


    /*
        Mark email as verified
    */

    $update =
        $conn->prepare(
            "UPDATE email_verifications
             SET verified = 1
             WHERE id = ?"
        );


    $update->bind_param(
        "i",
        $verification["id"]
    );


    $update->execute();

    $update->close();


    /*
        Save verified email in session
    */

    $_SESSION["verified_email"] =
        $email;


    $_SESSION["verified_name"] =
        $verification["name"];


    echo "success";


} else {

    echo
        "Invalid or expired verification code.";

}


$stmt->close();

$conn->close();

?>