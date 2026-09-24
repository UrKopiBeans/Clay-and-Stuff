<?php

/* =====================================================================
   GOOGLE SIGN-IN HANDLER
   ---------------------------------------------------------------------
   Tinatanggap nito ang "access_token" na galing sa Google account
   picker (lumalabas lang ito pagkatapos i-click ang sarili nating
   plain na "Continue with Google" button sa Login.php). Ginagamit
   natin ang token na iyon para kunin ang totoong email/pangalan/
   profile picture mula sa Google mismo, tapos:

     - Kung meron nang account na gamit ang email na iyon -> i-uupdate
       lang ang kanilang profile_picture sa totoong Gmail photo, at
       ilo-login sila (ayon sa role nila sa database).

     - Kung wala pang account -> gagawa ng bagong customer account
       gamit ang impormasyon mula sa Google (walang usable password
       dahil sa Google na sila mag-lo-login).

   Hindi ito nakakaapekto sa existing email/password login sa
   Login.php — dagdag na option lang ito.
===================================================================== */

header("Content-Type: application/json");

require_once __DIR__ . "/../helpers/session_helper.php";
require_once __DIR__ . "/google_config.php";
require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/access_helper.php";

$accessToken = $_POST["access_token"] ?? "";

if ($accessToken === "") {
    echo json_encode(["success" => false, "message" => "Walang natanggap na Google access token."]);
    exit();
}

/* =========================================================
   1. KUNIN ANG PROFILE INFO DIRETSO SA GOOGLE GAMIT ANG TOKEN
========================================================= */

$ch = curl_init("https://www.googleapis.com/oauth2/v3/userinfo");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $accessToken]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    echo json_encode(["success" => false, "message" => "Hindi na-verify ang Google account. Subukan ulit."]);
    exit();
}

$payload = json_decode($response, true);

$googleEmail   = $payload["email"] ?? "";
$googleName    = $payload["name"] ?? "";
$googlePicture = $payload["picture"] ?? "";
$emailVerified = $payload["email_verified"] ?? false;
$emailVerified = ($emailVerified === true || $emailVerified === "true");

if ($googleEmail === "" || !$emailVerified) {
    echo json_encode(["success" => false, "message" => "Hindi ma-verify ang Gmail address na ito."]);
    exit();
}

/* =========================================================
   2. TINGNAN KUNG MERON NANG ACCOUNT GAMIT ANG EMAIL NA ITO
========================================================= */

$stmt = $conn->prepare(
    "SELECT user_id, full_name, email, role
     FROM users
     WHERE email = ?
     LIMIT 1"
);
$stmt->bind_param("s", $googleEmail);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {

    /* -------- MERON NANG ACCOUNT: i-update lang ang picture -------- */

    $user = $result->fetch_assoc();
    $stmt->close();

    $updateStmt = $conn->prepare(
        "UPDATE users SET profile_picture = ? WHERE user_id = ?"
    );
    $updateStmt->bind_param("si", $googlePicture, $user["user_id"]);
    $updateStmt->execute();
    $updateStmt->close();

    $userId     = $user["user_id"];
    $fullName   = $user["full_name"];
    $role       = $user["role"];

} else {

    /* -------- WALANG ACCOUNT: gumawa ng bago (customer) -------- */

    $stmt->close();

    $fullName = $googleName !== "" ? $googleName : $googleEmail;
    $role     = "customer";

    /* Random na password lang (hindi na ito magagamit dahil sa
       Google na ang gagamitin niyang paraan para mag-login) */
    $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $insertStmt = $conn->prepare(
        "INSERT INTO users (full_name, email, password, role, profile_picture)
         VALUES (?, ?, ?, ?, ?)"
    );
    $insertStmt->bind_param("sssss", $fullName, $googleEmail, $randomPassword, $role, $googlePicture);
    $insertStmt->execute();

    $userId = $insertStmt->insert_id;
    $insertStmt->close();
}

$conn->close();

/* =========================================================
   3. I-LOGIN (SESSION) BASE SA ROLE
========================================================= */

$rolePortalMap = [
    "admin"    => "owner",
    "staff"    => "staff",
    "customer" => "customer",
];

figurify_start_session($rolePortalMap[$role] ?? "customer");

$_SESSION["user_id"]         = $userId;
$_SESSION["full_name"]       = $fullName;
$_SESSION["email"]           = $googleEmail;
$_SESSION["role"]            = $role;
$_SESSION["profile_picture"] = $googlePicture;

/* Bagong "last_seen" tracking — para makita sa User Management
   (owner side) kung Online o Offline (at kailan huling gumalaw)
   ang account na ito. */
figurify_touch_last_seen();

switch ($role) {
    case "admin":
        $redirect = "../owner/ownerdashboard.php";
        break;
    case "staff":
        $redirect = "../staff/staffdashboard.php";
        break;
    default:
        $redirect = "../Home/Home.php";
        break;
}

echo json_encode(["success" => true, "redirect" => $redirect]);
exit();
