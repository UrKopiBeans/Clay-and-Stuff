<?php

require_once "owner-header.php";
require_once __DIR__ . "/../helpers/reports_helper.php";

$salesSummary  = figurify_get_sales_summary($conn);
$statusCounts  = figurify_get_orders_by_status($conn);
$topProducts   = figurify_get_top_products($conn, 5);
$salesOverTime = figurify_get_sales_over_time($conn, 14);

// para sa bar chart heights — kunin ang pinakamataas na revenue sa
// buong series, gagamitin bilang 100% na height
$maxDayRevenue = 0.0;

foreach ($salesOverTime as $day) {
    if ($day["revenue"] > $maxDayRevenue) {
        $maxDayRevenue = $day["revenue"];
    }
}

$maxStatusCount = 1;

foreach ($statusCounts as $status) {
    if ($status["count"] > $maxStatusCount) {
        $maxStatusCount = $status["count"];
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../Image/logoclay.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Figurify — Statistics</title>
    <link rel="stylesheet" href="../Shared/dashboard.css">

    <style>
        /* page-specific lang, gamit na yung shared dashboard.css sa layout/sidebar/stat-card */

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        @media (max-width: 900px) {
            .reports-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .reports-section {
            margin-bottom: 20px;
        }

        .reports-section h3 {
            margin-bottom: 14px;
        }

        .status-bar-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .status-bar-label {
            width: 140px;
            font-size: 13px;
            flex-shrink: 0;
        }

        .status-bar-track {
            flex: 1;
            background: var(--gray-light, #eee7ea);
            border-radius: 8px;
            height: 18px;
            overflow: hidden;
        }

        .status-bar-fill {
            height: 100%;
            border-radius: 8px;
        }

        .status-bar-count {
            width: 34px;
            text-align: right;
            font-size: 13px;
            font-weight: 600;
            flex-shrink: 0;
        }

        .top-products-list {
            list-style: none;
        }

        .top-products-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-light, #eee7ea);
            font-size: 13.5px;
        }

        .top-products-list li:last-child {
            border-bottom: none;
        }

        .top-product-rank {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--pink-soft, #fff0f9);
            color: var(--pink-dark, #b23e82);
            font-size: 11px;
            font-weight: 700;
            margin-right: 10px;
        }

        .sales-chart {
            display: flex;
            align-items: flex-end;
            gap: 6px;
            height: 160px;
            padding-top: 10px;
        }

        .sales-chart-bar-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            height: 100%;
        }

        .sales-chart-bar {
            width: 100%;
            max-width: 26px;
            background: linear-gradient(180deg, var(--pink, #f78fd4), var(--pink-dark, #b23e82));
            border-radius: 4px 4px 0 0;
            min-height: 2px;
        }

        .sales-chart-day {
            font-size: 9.5px;
            color: var(--gray, #81777d);
            margin-top: 6px;
            white-space: nowrap;
        }

        .reports-empty {
            padding: 20px;
            text-align: center;
            color: var(--gray, #81777d);
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
                    <h2>Statistics</h2>
                    <p>Sales at order overview — para makita kung kumusta ang negosyo.</p>
                </div>

                <div class="top-actions">
                    <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>
                </div>

            </div>

            <!-- STAT CARDS -->
            <div class="reports-grid">

                <div class="card stat-card">
                    <div class="stat-label">TOTAL REVENUE</div>
                    <div class="stat-number">₱<?php echo number_format($salesSummary["total_revenue"], 2); ?></div>
                    <div class="stat-trend">● All-time, paid orders</div>
                </div>

                <div class="card stat-card">
                    <div class="stat-label">THIS MONTH</div>
                    <div class="stat-number">₱<?php echo number_format($salesSummary["month_revenue"], 2); ?></div>
                    <div class="stat-trend">● Revenue this month</div>
                </div>

                <div class="card stat-card">
                    <div class="stat-label">PAID ORDERS</div>
                    <div class="stat-number"><?php echo $salesSummary["paid_orders"]; ?></div>
                    <div class="stat-trend">● Confirmed &amp; paid</div>
                </div>

                <div class="card stat-card">
                    <div class="stat-label">AVG ORDER VALUE</div>
                    <div class="stat-number">₱<?php echo number_format($salesSummary["avg_order_value"], 2); ?></div>
                    <div class="stat-trend">● Per paid order</div>
                </div>

            </div>

            <!-- SALES OVER TIME -->
            <div class="card reports-section" style="padding: 20px;">

                <h3>Sales — Last 14 Days</h3>

                <?php if ($maxDayRevenue <= 0): ?>

                    <div class="reports-empty">Wala pang benta sa huling 14 araw.</div>

                <?php else: ?>

                    <div class="sales-chart">

                        <?php foreach ($salesOverTime as $day): ?>

                            <?php $barHeightPct = $maxDayRevenue > 0 ? max(2, ($day["revenue"] / $maxDayRevenue) * 100) : 2; ?>

                            <div class="sales-chart-bar-wrap">
                                <div
                                    class="sales-chart-bar"
                                    style="height: <?php echo $barHeightPct; ?>%;"
                                    title="<?php echo date("M j", strtotime($day["date"])); ?>: ₱<?php echo number_format($day["revenue"], 2); ?> (<?php echo $day["count"]; ?> order<?php echo $day["count"] === 1 ? "" : "s"; ?>)"
                                ></div>
                                <div class="sales-chart-day"><?php echo date("n/j", strtotime($day["date"])); ?></div>
                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

            <!-- ORDERS BY STATUS + TOP PRODUCTS -->
            <div class="reports-grid" style="grid-template-columns: 1.3fr 1fr;">

                <div class="card reports-section" style="padding: 20px;">

                    <h3>Orders by Status</h3>

                    <?php foreach ($statusCounts as $status): ?>

                        <?php if ($status["count"] === 0) continue; ?>

                        <div class="status-bar-row">
                            <div class="status-bar-label"><?php echo e($status["label"]); ?></div>
                            <div class="status-bar-track">
                                <div
                                    class="status-bar-fill"
                                    style="width: <?php echo max(4, ($status["count"] / $maxStatusCount) * 100); ?>%; background: <?php echo e($status["text"]); ?>;"
                                ></div>
                            </div>
                            <div class="status-bar-count"><?php echo $status["count"]; ?></div>
                        </div>

                    <?php endforeach; ?>

                </div>

                <div class="card reports-section" style="padding: 20px;">

                    <h3>Top Products</h3>

                    <?php if (empty($topProducts)): ?>

                        <div class="reports-empty">Wala pang paid na order.</div>

                    <?php else: ?>

                        <ul class="top-products-list">

                            <?php foreach ($topProducts as $i => $product): ?>

                                <li>
                                    <span>
                                        <span class="top-product-rank"><?php echo $i + 1; ?></span>
                                        <?php echo e($product["figure_style"] . " — " . $product["product_type"]); ?>
                                    </span>
                                    <span><?php echo (int) $product["total_ordered"]; ?>x</span>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </main>

</div>

</body>

</html>
