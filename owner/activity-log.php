<?php

require_once "owner-header.php";
require_once __DIR__ . "/../helpers/activity_log_helper.php";

// simpleng pagination — 30 entries kada page
$page = isset($_GET["page"]) ? max(1, (int) $_GET["page"]) : 1;
$perPage = 30;
$offset  = ($page - 1) * $perPage;

$logEntries = figurify_get_activity_log($conn, $perPage, $offset);
$totalLogs  = figurify_count_activity_log($conn);
$totalPages = max(1, (int) ceil($totalLogs / $perPage));

// mas friendly na label para sa bawat action_type
$actionLabels = [
    "order_verified"        => "Verified Order",
    "order_rejected"        => "Rejected Order",
    "order_status_updated"  => "Updated Order Status",
    "quotation_sent"        => "Sent Quotation",
    "revision_accepted"     => "Accepted Revision",
    "revision_declined"     => "Declined Revision",
    "user_created"          => "Created Account",
    "user_role_updated"     => "Changed User Role",
    "user_deleted"          => "Deleted Account",
];

function figurify_activity_label(string $actionType, array $labels): string
{
    return $labels[$actionType] ?? ucwords(str_replace("_", " ", $actionType));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../Image/logoclay.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Figurify — Activity Log</title>
    <link rel="stylesheet" href="../Shared/dashboard.css">

    <style>
        /* page-specific styles lang, gamit na yung shared dashboard.css sa layout/sidebar/cards */

        .activity-table {
            width: 100%;
            border-collapse: collapse;
        }

        .activity-table th,
        .activity-table td {
            text-align: left;
            padding: 12px 14px;
            border-bottom: 1px solid var(--gray-light, #eee7ea);
            font-size: 13.5px;
        }

        .activity-table th {
            color: var(--gray, #81777d);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11.5px;
            letter-spacing: .03em;
        }

        .activity-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
            background: var(--pink-soft, #fff0f9);
            color: var(--pink-dark, #b23e82);
            white-space: nowrap;
        }

        .activity-role {
            font-size: 11.5px;
            color: var(--gray, #81777d);
            text-transform: capitalize;
        }

        .activity-empty {
            padding: 40px 20px;
            text-align: center;
            color: var(--gray, #81777d);
        }

        .activity-pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            padding: 16px 0;
        }

        .activity-pagination a,
        .activity-pagination span {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            text-decoration: none;
            color: var(--black, #29252a);
        }

        .activity-pagination a {
            background: var(--pink-soft, #fff0f9);
        }

        .activity-pagination a:hover {
            background: var(--pink-light, #ffc4e8);
        }
    </style>
</head>

<body>

<div class="app">

    <?php require_once __DIR__ . "/owner-sidebar.php"; ?>

    <main class="main">

        <div class="user-page">

            <div class="page-header">

                <div>
                    <h2>Activity Log</h2>
                    <p>Record ng mga importanteng aksyon ng Staff at Owner — sino ang gumalaw sa isang order o account, at kailan.</p>
                </div>

                <div class="top-actions">
                    <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>
                </div>

            </div>

            <div class="card" style="padding: 0; overflow: hidden;">

                <?php if (empty($logEntries)): ?>

                    <div class="activity-empty">
                        Wala pang naitatalang aksyon.
                    </div>

                <?php else: ?>

                    <table class="activity-table">

                        <thead>
                            <tr>
                                <th>Date &amp; Time</th>
                                <th>Actor</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>Order</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($logEntries as $entry): ?>

                                <tr>
                                    <td><?php echo date("M j, Y g:i A", strtotime($entry["created_at"])); ?></td>
                                    <td>
                                        <?php echo e($entry["actor_name"]); ?>
                                        <div class="activity-role"><?php echo e($entry["actor_role"]); ?></div>
                                    </td>
                                    <td>
                                        <span class="activity-badge"><?php echo e(figurify_activity_label($entry["action_type"], $actionLabels)); ?></span>
                                    </td>
                                    <td><?php echo e($entry["description"]); ?></td>
                                    <td>
                                        <?php echo $entry["order_id"] ? ("#" . (int) $entry["order_id"]) : "—"; ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>

            <?php if ($totalPages > 1): ?>

                <div class="activity-pagination">

                    <?php if ($page > 1): ?>
                        <a href="activity-log.php?page=<?php echo $page - 1; ?>">← Previous</a>
                    <?php endif; ?>

                    <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

                    <?php if ($page < $totalPages): ?>
                        <a href="activity-log.php?page=<?php echo $page + 1; ?>">Next →</a>
                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>

</body>

</html>
