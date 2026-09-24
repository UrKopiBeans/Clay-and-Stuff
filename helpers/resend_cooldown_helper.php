<?php

/* Resend cooldown para sa verification code (Sign Up at Forgot
   Password) - 10 minuto bago puwede ulit magpadala ng bagong code
   sa parehong email, para hindi ma-spam ang inbox. */

if (!function_exists("figurify_resend_cooldown_seconds_remaining")) {

    function figurify_resend_cooldown_seconds_remaining(mysqli $conn, string $email): int
    {
        $cooldownSeconds = 10 * 60;

        $stmt = $conn->prepare(
            "SELECT created_at FROM email_verifications WHERE email = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (empty($row["created_at"])) {
            return 0;
        }

        $elapsed = time() - strtotime($row["created_at"]);
        $remaining = $cooldownSeconds - $elapsed;

        return $remaining > 0 ? $remaining : 0;
    }
}
