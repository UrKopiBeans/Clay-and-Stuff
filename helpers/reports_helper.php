<?php

/* Reports/statistics helper — mga aggregation query para sa Reports
   page ng Owner at sa dashboard stat cards. "Naka-bayad na" order
   (paid_at IS NOT NULL) at hindi cancelled ang basehan ng revenue,
   dahil dun pa lang totoong kumita ang negosyo. */

if (!function_exists("figurify_get_sales_summary")) {
    function figurify_get_sales_summary(mysqli $conn): array
    {
        $result = $conn->query(
            "SELECT
                COUNT(*) AS paid_orders,
                COALESCE(SUM(total_amount), 0) AS total_revenue,
                COALESCE(AVG(total_amount), 0) AS avg_order_value
             FROM orders
             WHERE paid_at IS NOT NULL AND status != 'cancelled'"
        );
        $summary = $result->fetch_assoc();

        $monthResult = $conn->query(
            "SELECT COALESCE(SUM(total_amount), 0) AS month_revenue
             FROM orders
             WHERE paid_at IS NOT NULL AND status != 'cancelled'
               AND MONTH(paid_at) = MONTH(CURDATE())
               AND YEAR(paid_at) = YEAR(CURDATE())"
        );
        $monthRow = $monthResult->fetch_assoc();

        return [
            "paid_orders"     => (int) $summary["paid_orders"],
            "total_revenue"   => (float) $summary["total_revenue"],
            "avg_order_value" => (float) $summary["avg_order_value"],
            "month_revenue"   => (float) $monthRow["month_revenue"],
        ];
    }
}


// bilang ng orders sa bawat status, gamit ang parehong grouping/label
// na ginagamit na ng buong system (order_status_helper.php)

if (!function_exists("figurify_get_orders_by_status")) {
    function figurify_get_orders_by_status(mysqli $conn): array
    {
        $result = $conn->query("SELECT status, COUNT(*) AS total FROM orders GROUP BY status");

        $counts = [];

        while ($row = $result->fetch_assoc()) {
            $key = figurify_status_key($row["status"]);
            $counts[$key] = ($counts[$key] ?? 0) + (int) $row["total"];
        }

        $breakdown = [];

        foreach (FIGURIFY_ORDER_STATUSES as $key => $info) {
            $breakdown[] = [
                "key"   => $key,
                "label" => $info["label"],
                "bg"    => $info["bg"],
                "text"  => $info["text"],
                "count" => $counts[$key] ?? 0,
            ];
        }

        return $breakdown;
    }
}


// pinaka-madalas na naa-order na figure style + product type, sa
// mga order na bayad na, para makita kung alin ang pinaka-benta

if (!function_exists("figurify_get_top_products")) {
    function figurify_get_top_products(mysqli $conn, int $limit = 5): array
    {
        $stmt = $conn->prepare(
            "SELECT f.figure_style, f.product_type, COUNT(*) AS total_ordered,
                    COALESCE(SUM(f.figure_total), 0) AS total_revenue
             FROM order_figures f
             JOIN orders o ON o.order_id = f.order_id
             WHERE o.paid_at IS NOT NULL AND o.status != 'cancelled'
             GROUP BY f.figure_style, f.product_type
             ORDER BY total_ordered DESC
             LIMIT ?"
        );

        $stmt->bind_param("i", $limit);
        $stmt->execute();

        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }
}


// bilang ng orders na na-completed ngayong buwan — ginagamit ng Staff
// dashboard bilang non-financial na "progress" stat card

if (!function_exists("figurify_get_completed_this_month")) {
    function figurify_get_completed_this_month(mysqli $conn): int
    {
        // basehan ang shipped_at (pinakamalapit na may timestamp na
        // "natapos" na ang order), hindi created_at
        $result = $conn->query(
            "SELECT COUNT(*) AS total
             FROM orders
             WHERE status = 'completed'
               AND shipped_at IS NOT NULL
               AND MONTH(shipped_at) = MONTH(CURDATE())
               AND YEAR(shipped_at) = YEAR(CURDATE())"
        );
        $row = $result->fetch_assoc();

        return (int) ($row["total"] ?? 0);
    }
}


// revenue/order count kada araw, huling $days na araw — panggamit sa
// simpleng bar chart ng Reports page

if (!function_exists("figurify_get_sales_over_time")) {
    function figurify_get_sales_over_time(mysqli $conn, int $days = 14): array
    {
        $stmt = $conn->prepare(
            "SELECT DATE(paid_at) AS sale_date,
                    COUNT(*) AS orders_count,
                    COALESCE(SUM(total_amount), 0) AS revenue
             FROM orders
             WHERE paid_at IS NOT NULL AND status != 'cancelled'
               AND paid_at >= (CURDATE() - INTERVAL ? DAY)
             GROUP BY DATE(paid_at)
             ORDER BY sale_date ASC"
        );

        $stmt->bind_param("i", $days);
        $stmt->execute();

        $rowsByDate = [];

        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $rowsByDate[$row["sale_date"]] = $row;
        }

        $stmt->close();

        // punuin ang mga araw na walang benta ng zero, para tuloy-tuloy ang chart
        $series = [];

        for ($i = $days - 1; $i >= 0; $i--) {

            $date = date("Y-m-d", strtotime("-{$i} days"));

            $series[] = [
                "date"    => $date,
                "count"   => (int) ($rowsByDate[$date]["orders_count"] ?? 0),
                "revenue" => (float) ($rowsByDate[$date]["revenue"] ?? 0),
            ];

        }

        return $series;
    }
}
