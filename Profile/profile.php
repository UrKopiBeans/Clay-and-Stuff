<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../helpers/avatar_helper.php";
require_once __DIR__ . "/../Admin/database.php";


// must be logged in

if (!isset($_SESSION["user_id"])) {

    header("Location: ../Login/Login.php");
    exit();

}

$user_id = $_SESSION["user_id"];

$profileError  = "";
$passwordError = "";
$avatarError   = "";

// base path para tama ang avatar src kahit saan i-include ang page
$documentRoot = rtrim(str_replace("\\", "/", realpath($_SERVER["DOCUMENT_ROOT"])), "/");
$projectRoot  = rtrim(str_replace("\\", "/", realpath(__DIR__ . "/..")), "/");
$siteBase     = substr($projectRoot, strlen($documentRoot)) . "/";


// handle form submissions

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $formAction = $_POST["form_action"] ?? "";


    // upload / change profile photo

    if ($formAction === "upload_avatar") {

        $allowedExt  = ["jpg", "jpeg", "png", "gif", "webp"];
        $maxFileSize = 3 * 1024 * 1024; // 3MB

        if (!isset($_FILES["avatar_photo"]) || $_FILES["avatar_photo"]["error"] === UPLOAD_ERR_NO_FILE) {

            $avatarError = "Please choose a photo to upload.";

        } elseif ($_FILES["avatar_photo"]["error"] !== UPLOAD_ERR_OK) {

            $avatarError = "There was a problem uploading your photo. Please try again.";

        } elseif ($_FILES["avatar_photo"]["size"] > $maxFileSize) {

            $avatarError = "Photo is too large. Please choose a file under 3MB.";

        } else {

            $originalName = $_FILES["avatar_photo"]["name"];
            $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true)) {

                $avatarError = "Unsupported file type. Please upload a JPG, PNG, GIF, or WEBP image.";

            } else {

                $uploadDir = __DIR__ . "/../uploads/avatars/";

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $safeName = "user-" . $user_id . "-" . time() . "." . $ext;
                $destPath = $uploadDir . $safeName;

                if (move_uploaded_file($_FILES["avatar_photo"]["tmp_name"], $destPath)) {

                    $relativePath = "uploads/avatars/" . $safeName;

                    $stmt = $conn->prepare(
                        "UPDATE users
                         SET profile_picture = ?
                         WHERE user_id = ?"
                    );

                    $stmt->bind_param("si", $relativePath, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    $_SESSION["profile_picture"] = $relativePath;

                    header("Location: profile.php?avatar_updated=1");
                    exit();

                } else {

                    $avatarError = "Could not save the uploaded photo. Please try again.";

                }

            }

        }

    }


    // update full name

    if ($formAction === "update_profile") {

        $full_name = trim($_POST["full_name"] ?? "");

        if ($full_name === "") {

            $profileError = "Full name cannot be empty.";

        } elseif (mb_strlen($full_name) > 100) {

            $profileError = "Full name is too long.";

        } else {

            $stmt = $conn->prepare(
                "UPDATE users
                 SET full_name = ?
                 WHERE user_id = ?"
            );

            $stmt->bind_param("si", $full_name, $user_id);
            $stmt->execute();
            $stmt->close();

            $_SESSION["full_name"] = $full_name;

            header("Location: profile.php?updated=1");
            exit();

        }

    }


    // change password

    if ($formAction === "change_password") {

        $current_password = $_POST["current_password"] ?? "";
        $new_password      = $_POST["new_password"] ?? "";
        $confirm_password  = $_POST["confirm_password"] ?? "";

        if ($current_password === "" || $new_password === "" || $confirm_password === "") {

            $passwordError = "Please fill out all password fields.";

        } elseif (strlen($new_password) < 8) {

            $passwordError = "New password must be at least 8 characters.";

        } elseif ($new_password !== $confirm_password) {

            $passwordError = "New passwords do not match.";

        } else {

            $stmt = $conn->prepare(
                "SELECT password
                 FROM users
                 WHERE user_id = ?
                 LIMIT 1"
            );

            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            $result = $stmt->get_result();
            $row    = $result->fetch_assoc();

            $stmt->close();

            if (!$row || !password_verify($current_password, $row["password"])) {

                $passwordError = "Current password is incorrect.";

            } else {

                $hashed = password_hash($new_password, PASSWORD_DEFAULT);

                $updateStmt = $conn->prepare(
                    "UPDATE users
                     SET password = ?
                     WHERE user_id = ?"
                );

                $updateStmt->bind_param("si", $hashed, $user_id);
                $updateStmt->execute();
                $updateStmt->close();

                header("Location: profile.php?pw_updated=1");
                exit();

            }

        }

    }

}


// get current user info

$stmt = $conn->prepare(
    "SELECT full_name, email, role, created_at, profile_picture
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$user   = $result->fetch_assoc();

$stmt->close();
$conn->close();

if (!$user) {

    header("Location: ../Login/Login.php");
    exit();

}

$roleLabels = [
    "customer" => "Customer",
    "staff"    => "Staff",
    "admin"    => "Owner / Admin",
];

$roleLabel  = $roleLabels[$user["role"]] ?? ucfirst($user["role"]);
$memberSince = date("F Y", strtotime($user["created_at"]));

$profile_avatar_html = figurify_render_avatar(
    $user["full_name"],
    $user["profile_picture"],
    "Profile",
    $siteBase
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="../Image/logoclay.png">

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Profile — Clay and Stuff</title>

<link rel="stylesheet" href="../my-order/my-orders.css">
<style>
/* profile page styles */

.profile-layout{
    display:grid;

    grid-template-columns:300px 1fr;

    gap:24px;

    align-items:start;
}

@media (max-width:820px){

    .profile-layout{
        grid-template-columns:1fr;
    }

}


/* summary card */

.profile-summary-card{
    text-align:center;

    display:flex;

    flex-direction:column;

    align-items:center;

    gap:6px;

    padding:32px 20px;
}

.profile-summary-card .profile-avatar{
    width:88px;
    height:88px;

    border-radius:50%;

    background:#fbe3ef;

    border:3px solid white;
    outline:1px solid #e4d5df;

    display:flex;
    align-items:center;
    justify-content:center;

    overflow:hidden;

    margin-bottom:8px;
}

.profile-summary-card .profile-avatar img{
    width:100%;
    height:100%;

    object-fit:cover;
}

.profile-summary-card .profile-avatar .avatar-initials{
    width:100%;
    height:100%;

    display:flex;
    align-items:center;
    justify-content:center;

    color:#ffffff;
    font-weight:800;
    font-size:32px;
}

.profile-avatar-form{
    margin-bottom:6px;
}

.profile-avatar-upload-btn{
    display:inline-block;

    padding:8px 16px;

    font-size:11px;
    font-weight:900;

    cursor:pointer;

    border-radius:20px;

    background:linear-gradient(135deg,#f2699b,#e0447f);
    color:white;

    box-shadow:0 6px 14px rgba(224,68,127,.35);

    transition:.15s;
}

.profile-avatar-upload-btn:hover{
    filter:brightness(1.05);
}

.profile-summary-name{
    font-family:Georgia,serif;

    font-size:20px;
    font-weight:700;

    color:#86365f;
}

.profile-summary-email{
    font-size:13px;

    color:#956d80;

    word-break:break-all;
}

.profile-role-badge{
    display:inline-block;

    margin-top:10px;

    padding:6px 16px;

    border-radius:20px;

    background:#f2a4ca;

    color:white;

    font-size:11px;
    font-weight:900;

    letter-spacing:.5px;

    text-transform:uppercase;
}

.profile-member-since{
    margin-top:12px;

    font-size:12px;

    color:#a883a0;
}


/* form cards */

.profile-forms-col{
    display:flex;

    flex-direction:column;

    gap:20px;
}

.profile-form-title{
    font-family:Georgia,serif;

    color:#86365f;

    font-size:19px;

    margin-bottom:16px;
}

.profile-form{
    display:flex;

    flex-direction:column;

    gap:6px;
}

.profile-form label{
    font-size:12px;

    font-weight:700;

    color:#75445e;

    margin-top:10px;
}

.profile-form input{
    padding:12px 14px;

    border-radius:12px;

    border:2px solid #ecd9e4;

    background:#fffdf8;

    color:#75445e;

    font-family:inherit;

    font-size:14px;

    outline:none;

    transition:.15s;
}

.profile-form input:focus{
    border-color:#f2a4ca;
}

.profile-form input:disabled{
    background:#f5eef2;

    color:#a883a0;

    cursor:not-allowed;
}

.profile-field-hint{
    font-size:11px;

    color:#a883a0;
}

.profile-form .btn-continue{
    align-self:flex-start;

    margin-top:18px;

    border:none;

    font-family:inherit;
}
</style>

</head>
<body>

<?php include "../Shared/navbar.php"; ?>

<header class="hero hero-profile">
    <div class="hero-content">
        <span class="hero-small">YOUR ACCOUNT</span>
        <h1>My<br>Profile</h1>
        <p>
            View your account details, update your name, or change
            your password.
        </p>
    </div>
</header>

<main class="orders-page profile-page">


    <?php if (isset($_GET["updated"])): ?>

        <div class="order-notice order-notice-success">
            ✅ Your profile has been updated.
        </div>

    <?php endif; ?>

    <?php if (isset($_GET["pw_updated"])): ?>

        <div class="order-notice order-notice-success">
            ✅ Your password has been changed.
        </div>

    <?php endif; ?>

    <?php if (isset($_GET["avatar_updated"])): ?>

        <div class="order-notice order-notice-success">
            ✅ Your profile photo has been updated.
        </div>

    <?php endif; ?>


    <div class="profile-layout">

        <!-- profile summary -->

        <div class="order-card profile-summary-card">

            <div class="profile-avatar">
                <?php echo $profile_avatar_html; ?>
            </div>

            <?php if ($avatarError !== ""): ?>
                <div class="order-notice order-notice-cancel">
                    <?php echo htmlspecialchars($avatarError); ?>
                </div>
            <?php endif; ?>

            <form
                method="POST"
                action="profile.php"
                enctype="multipart/form-data"
                class="profile-avatar-form"
            >
                <input type="hidden" name="form_action" value="upload_avatar">

                <label for="avatar_photo" class="btn-continue btn-link profile-avatar-upload-btn">
                    Change Photo
                </label>
                <input
                    type="file"
                    id="avatar_photo"
                    name="avatar_photo"
                    accept=".jpg,.jpeg,.png,.gif,.webp"
                    onchange="this.form.submit()"
                    hidden
                >
            </form>

            <div class="profile-summary-name">
                <?php echo htmlspecialchars($user["full_name"]); ?>
            </div>

            <div class="profile-summary-email">
                <?php echo htmlspecialchars($user["email"]); ?>
            </div>

            <span class="profile-role-badge">
                <?php echo htmlspecialchars($roleLabel); ?>
            </span>

            <div class="profile-member-since">
                Member since <?php echo htmlspecialchars($memberSince); ?>
            </div>

        </div>


        <!-- edit forms -->

        <div class="profile-forms-col">

            <!-- EDIT NAME / EMAIL -->

            <div class="order-card profile-form-card">

                <h2 class="profile-form-title">Account Information</h2>

                <?php if ($profileError !== ""): ?>
                    <div class="order-notice order-notice-cancel">
                        <?php echo htmlspecialchars($profileError); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="profile.php" class="profile-form">

                    <input type="hidden" name="form_action" value="update_profile">

                    <label for="full_name">Full Name</label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?php echo htmlspecialchars($user["full_name"]); ?>"
                        required
                        maxlength="100"
                    >

                    <label for="email_display">Email</label>
                    <input
                        type="email"
                        id="email_display"
                        value="<?php echo htmlspecialchars($user["email"]); ?>"
                        disabled
                    >
                    <span class="profile-field-hint">
                        Email can't be changed here. Contact us if you need to update it.
                    </span>

                    <button type="submit" class="btn-continue">
                        Save Changes
                    </button>

                </form>

            </div>


            <!-- CHANGE PASSWORD -->

            <div class="order-card profile-form-card">

                <h2 class="profile-form-title">Change Password</h2>

                <?php if ($passwordError !== ""): ?>
                    <div class="order-notice order-notice-cancel">
                        <?php echo htmlspecialchars($passwordError); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="profile.php" class="profile-form">

                    <input type="hidden" name="form_action" value="change_password">

                    <label for="current_password">Current Password</label>
                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        required
                    >

                    <label for="new_password">New Password</label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        required
                        minlength="8"
                    >

                    <label for="confirm_password">Confirm New Password</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                        minlength="8"
                    >

                    <button type="submit" class="btn-continue">
                        Update Password
                    </button>

                </form>

            </div>

        </div>

    </div>

</main>


<?php include "../Shared/footer.php"; ?>

</body>
</html>
