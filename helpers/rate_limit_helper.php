<?php

/* Login lockout: 5 failed attempts sa loob ng 10 minuto para sa
   parehong email = naka-lock muna, may countdown pa bago puwede
   ulit. Ginagawa awtomatiko ang login_attempts table sa unang
   pagtawag, walang kailangang manual na SQL migration. */

if (!function_exists("figurify_ensure_login_attempts_table")) {

    function figurify_ensure_login_attempts_table(mysqli $conn): void
    {
        static $ensured = false;

        if ($ensured) {
            return;
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                attempted_at DATETIME NOT NULL,
                INDEX idx_email_time (email, attempted_at)
            )"
        );

        $ensured = true;
    }
}

if (!function_exists("figurify_login_lockout_settings")) {

    function figurify_login_lockout_settings(): array
    {
        return [
            "maxAttempts"   => 5,
            "windowMinutes" => 10,
        ];
    }
}

if (!function_exists("figurify_login_lockout_seconds_remaining")) {

    // Ilang segundo pa bago puwede ulit mag-attempt. 0 = hindi naka-lock.
    function figurify_login_lockout_seconds_remaining(mysqli $conn, string $email): int
    {
        figurify_ensure_login_attempts_table($conn);

        $settings = figurify_login_lockout_settings();
        $windowSeconds = $settings["windowMinutes"] * 60;
        $windowStart = date("Y-m-d H:i:s", time() - $windowSeconds);

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c, MIN(attempted_at) AS oldest
             FROM login_attempts
             WHERE email = ? AND attempted_at > ?"
        );
        $stmt->bind_param("ss", $email, $windowStart);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $count = (int) ($row["c"] ?? 0);

        if ($count < $settings["maxAttempts"] || empty($row["oldest"])) {
            return 0;
        }

        // Bibilang mula sa pinakaunang failed attempt sa loob ng window.
        $unlocksAt = strtotime($row["oldest"]) + $windowSeconds;
        $remaining = $unlocksAt - time();

        return max($remaining, 1);
    }
}

if (!function_exists("figurify_record_failed_login")) {

    function figurify_record_failed_login(mysqli $conn, string $email): void
    {
        figurify_ensure_login_attempts_table($conn);

        $stmt = $conn->prepare(
            "INSERT INTO login_attempts (email, attempted_at) VALUES (?, NOW())"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists("figurify_clear_login_attempts")) {

    function figurify_clear_login_attempts(mysqli $conn, string $email): void
    {
        figurify_ensure_login_attempts_table($conn);

        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->close();
    }
}


/* Generic rate-limiting para sa ibang forms (order/review submission)
   laban sa spam. $actionType = anong form, $identifier = email/session
   id. Hiwalay na table sa login_attempts (spam prevention, hindi lockout). */

if (!function_exists("figurify_ensure_action_attempts_table")) {

    function figurify_ensure_action_attempts_table(mysqli $conn): void
    {
        static $ensured = false;

        if ($ensured) {
            return;
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS action_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                action_type VARCHAR(50) NOT NULL,
                identifier VARCHAR(255) NOT NULL,
                attempted_at DATETIME NOT NULL,
                INDEX idx_action_identifier_time (action_type, identifier, attempted_at)
            )"
        );

        $ensured = true;
    }
}

if (!function_exists("figurify_action_rate_limit_seconds_remaining")) {

    // 0 = puwede pa mag-submit; > 0 = ilang segundo pa bago puwede ulit
    function figurify_action_rate_limit_seconds_remaining(
        mysqli $conn,
        string $actionType,
        string $identifier,
        int $maxAttempts,
        int $windowMinutes
    ): int {

        figurify_ensure_action_attempts_table($conn);

        $windowSeconds = $windowMinutes * 60;
        $windowStart   = date("Y-m-d H:i:s", time() - $windowSeconds);

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c, MIN(attempted_at) AS oldest
             FROM action_attempts
             WHERE action_type = ? AND identifier = ? AND attempted_at > ?"
        );
        $stmt->bind_param("sss", $actionType, $identifier, $windowStart);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $count = (int) ($row["c"] ?? 0);

        if ($count < $maxAttempts || empty($row["oldest"])) {
            return 0;
        }

        $unlocksAt = strtotime($row["oldest"]) + $windowSeconds;
        $remaining = $unlocksAt - time();

        return max($remaining, 1);
    }
}

if (!function_exists("figurify_record_action_attempt")) {

    function figurify_record_action_attempt(mysqli $conn, string $actionType, string $identifier): void
    {
        figurify_ensure_action_attempts_table($conn);

        $stmt = $conn->prepare(
            "INSERT INTO action_attempts (action_type, identifier, attempted_at) VALUES (?, ?, NOW())"
        );
        $stmt->bind_param("ss", $actionType, $identifier);
        $stmt->execute();
        $stmt->close();
    }
}
