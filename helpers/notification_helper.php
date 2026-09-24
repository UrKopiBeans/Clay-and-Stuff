<?php

/* Shared "notify customer" helper — sumusulat sa notifications table
   AT nag-e-email. Pass $sendEmail = false kung may sariling email
   design na ang page (para hindi doble). */

require_once __DIR__ . "/mailer_helper.php";


if (!function_exists("figurify_notify_user")) {

    // inserts a notification for a user, and by default emails them too.
    // set $sendEmail = false if the page already sends its own email.
    // return value only reflects the DB save — email is best-effort.
    function figurify_notify_user(
        mysqli $conn,
        int $userId,
        ?int $orderId,
        string $message,
        int $isRead = 0,
        ?string $emailSubject = null,
        bool $sendEmail = true
    ): bool {

        // save sa notifications table (in-system)

        $notifyStmt = $conn->prepare(
            "INSERT INTO notifications (user_id, order_id, message, is_read)
             VALUES (?, ?, ?, ?)"
        );

        if ($notifyStmt === false) {
            return false;
        }

        $notifyStmt->bind_param("iisi", $userId, $orderId, $message, $isRead);
        $saved = $notifyStmt->execute();
        $notifyStmt->close();


        // email notification (Gmail, gamit ang mailer_helper)

        if ($sendEmail) {

            $userStmt = $conn->prepare(
                "SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1"
            );

            if ($userStmt !== false) {

                $userStmt->bind_param("i", $userId);
                $userStmt->execute();

                $customer = $userStmt->get_result()->fetch_assoc();
                $userStmt->close();

                if ($customer && !empty($customer["email"])) {

                    $subject = $emailSubject ?? (
                        $orderId
                            ? ("Order #" . $orderId . " Update — Clay and Stuff")
                            : "New Notification — Clay and Stuff"
                    );

                    $safeName    = htmlspecialchars($customer["full_name"]);
                    $safeMessage = nl2br(htmlspecialchars($message));

                    $emailBody = "
                        <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:20px;'>
                            <h2 style='color:#86365f;'>Clay and Stuff</h2>
                            <p>Hello <strong>" . $safeName . "</strong>,</p>
                            <p>" . $safeMessage . "</p>
                            <br>
                            <p style='font-size:13px;color:#777;'>
                                You can also view this notification anytime by
                                logging in and checking your Notifications page.
                            </p>
                            <p>Thank you,<br><strong>Clay and Stuff Team</strong></p>
                        </div>
                    ";

                    figurify_send_mail(
                        $customer["email"],
                        $customer["full_name"],
                        $subject,
                        $emailBody,
                        $message
                    );

                }

            }

        }

        return $saved;
    }

}


if (!function_exists("figurify_notify_staff")) {

    // sends the same notification to all admin/staff (bell only, no
    // email — email is for customers via figurify_notify_user).
    // $excludeUserId skips whoever just did the action, if applicable.
    function figurify_notify_staff(
        mysqli $conn,
        ?int $orderId,
        string $message,
        ?int $excludeUserId = null
    ): bool {

        $staffStmt = $conn->prepare(
            "SELECT user_id FROM users WHERE role IN ('admin', 'staff')"
        );

        if ($staffStmt === false) {
            return false;
        }

        $staffStmt->execute();
        $result = $staffStmt->get_result();

        $staffIds = [];

        while ($row = $result->fetch_assoc()) {

            $staffId = (int) $row["user_id"];

            if ($excludeUserId !== null && $staffId === $excludeUserId) {
                continue;
            }

            $staffIds[] = $staffId;
        }

        $staffStmt->close();

        if (empty($staffIds)) {
            return false;
        }

        $notifyStmt = $conn->prepare(
            "INSERT INTO notifications (user_id, order_id, message, is_read)
             VALUES (?, ?, ?, 0)"
        );

        if ($notifyStmt === false) {
            return false;
        }

        $savedAny = false;

        foreach ($staffIds as $staffId) {

            $notifyStmt->bind_param("iis", $staffId, $orderId, $message);

            if ($notifyStmt->execute()) {
                $savedAny = true;
            }

        }

        $notifyStmt->close();

        return $savedAny;
    }

}
