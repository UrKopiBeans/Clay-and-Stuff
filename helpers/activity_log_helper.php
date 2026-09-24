<?php

/* Activity log — record ng mga importanteng aksyon ng Staff/Owner
   (verify/approve/reject order, quotation, revision, user management,
   atbp.) para may accountability kung sino ang gumalaw sa isang order
   o account, at kailan. Self-migrating table gaya ng login_attempts. */

if (!function_exists("figurify_ensure_activity_log_table")) {
    function figurify_ensure_activity_log_table(mysqli $conn): void
    {
        static $ensured = false;

        if ($ensured) {
            return;
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS activity_log (
                log_id      INT AUTO_INCREMENT PRIMARY KEY,
                actor_id    INT NULL,
                actor_name  VARCHAR(100) NOT NULL,
                actor_role  VARCHAR(20) NOT NULL,
                action_type VARCHAR(100) NOT NULL,
                description TEXT NOT NULL,
                order_id    INT NULL,
                created_at  DATETIME NOT NULL,
                INDEX idx_created_at (created_at)
            )"
        );

        $ensured = true;
    }
}


// i-record ang isang aksyon; kunin ang actor mula sa kasalukuyang
// session (kailangan naka-start na ang session)

if (!function_exists("figurify_log_activity")) {
    function figurify_log_activity(
        mysqli $conn,
        string $actionType,
        string $description,
        ?int $orderId = null
    ): void {

        figurify_ensure_activity_log_table($conn);

        $actorId   = isset($_SESSION["user_id"]) ? (int) $_SESSION["user_id"] : null;
        $actorName = $_SESSION["full_name"] ?? "Unknown";
        $actorRole = $_SESSION["role"] ?? "unknown";

        $stmt = $conn->prepare(
            "INSERT INTO activity_log
                (actor_id, actor_name, actor_role, action_type, description, order_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );

        $stmt->bind_param(
            "issssi",
            $actorId,
            $actorName,
            $actorRole,
            $actionType,
            $description,
            $orderId
        );

        $stmt->execute();
        $stmt->close();
    }
}


// kunin ang pinakabagong entries, pinaka-bago sa taas; $limit at
// $offset para sa pagination sa activity-log.php

if (!function_exists("figurify_get_activity_log")) {
    function figurify_get_activity_log(mysqli $conn, int $limit = 50, int $offset = 0): array
    {
        figurify_ensure_activity_log_table($conn);

        $stmt = $conn->prepare(
            "SELECT log_id, actor_id, actor_name, actor_role, action_type,
                    description, order_id, created_at
             FROM activity_log
             ORDER BY created_at DESC, log_id DESC
             LIMIT ? OFFSET ?"
        );

        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();

        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }
}


// total count, para sa pagination controls

if (!function_exists("figurify_count_activity_log")) {
    function figurify_count_activity_log(mysqli $conn): int
    {
        figurify_ensure_activity_log_table($conn);

        $result = $conn->query("SELECT COUNT(*) AS total FROM activity_log");
        $row    = $result->fetch_assoc();

        return (int) ($row["total"] ?? 0);
    }
}
