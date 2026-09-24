<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/expire_helper.php";


// must be logged in

if (!isset($_SESSION["user_id"])) {

    header("Location: ../Login/Login.php");
    exit();

}

$user_id = $_SESSION["user_id"];


// clean up expired quotations muna para makita agad ang auto-cancel

figurify_expire_old_quotes($conn);


// get all notifications of this user, newest first

$stmt = $conn->prepare(
    "SELECT notification_id, order_id, message, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$notifications = [];

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

$stmt->close();


// mark all as read, customer opened this page na

$markStmt = $conn->prepare(
    "UPDATE notifications
     SET is_read = 1
     WHERE user_id = ? AND is_read = 0"
);

$markStmt->bind_param("i", $user_id);
$markStmt->execute();
$markStmt->close();

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="../Image/logoclay.png">

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Notifications — Clay and Stuff</title>

<link rel="stylesheet" href="../my-order/my-orders.css">
  <link rel="stylesheet" href="notifications.css" />

</head>
<body>

<?php include "../Shared/navbar.php"; ?>

<header class="hero hero-notif">
    <div class="hero-content">
        <span class="hero-small">STAY UPDATED</span>
        <h1>Your<br>Notifications</h1>
        <p>
            Updates about your commissions — like when our staff sends
            you a quotation — will show up here.
        </p>
    </div>
</header>

<main class="orders-page">


    <?php if (empty($notifications)): ?>

        <div class="no-orders">

            <div class="no-orders-icon">
                🔔
            </div>

            <p>
                You don't have any notifications yet.
            </p>

            <a href="../my-order/my-orders.php">
                View My Orders →
            </a>

        </div>

    <?php else: ?>

        <div class="notification-list">

            <?php foreach ($notifications as $note): ?>

                <a
                    href="../my-order/my-orders.php<?php echo !empty($note["order_id"]) ? "?order_id=" . (int) $note["order_id"] : ""; ?>"
                    class="notification-card <?php echo $note["is_read"] ? "" : "unread"; ?>"
                >

                    <div class="notification-icon">
                        ₱
                    </div>

                    <div class="notification-body">

                        <p><?php echo htmlspecialchars($note["message"]); ?></p>

                        <span>
                            <?php echo date("F j, Y — g:i A", strtotime($note["created_at"])); ?>
                        </span>

                    </div>

                    <?php if (!$note["is_read"]): ?>
                        <div class="notification-dot"></div>
                    <?php endif; ?>

                </a>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>


<?php include "../Shared/footer.php"; ?>

</body>
</html>
