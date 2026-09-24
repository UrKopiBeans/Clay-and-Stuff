<?php

/* Single source of truth ng status list/labels/colors — lahat ng page
   dapat kumuha dito. Flow: pending -> quoted -> awaiting_payment ->
   to_verify -> for_approval -> processing -> awaiting_balance ->
   to_verify_balance -> (for_balance_approval, kapag staff ang nag-
   verify) -> to_ship -> shipped -> completed (cancelled anytime). */

const FIGURIFY_ORDER_STATUSES = [

    "pending" => [
        "label" => "Pending",
        "bg"    => "#FFF3C4",
        "text"  => "#29252A",
    ],

    "quoted" => [
        "label" => "Quoted",
        "bg"    => "#E1F3E7",
        "text"  => "#29252A",
    ],

    "awaiting_payment" => [
        "label" => "Awaiting Payment",
        "bg"    => "#FBE8D6",
        "text"  => "#29252A",
    ],

    "to_verify" => [
        "label" => "For Verification",
        "bg"    => "#E1F3E7",
        "text"  => "#29252A",
    ],

    "for_approval" => [
        "label" => "For Approval",
        "bg"    => "#E1F3E7",
        "text"  => "#29252A",
    ],

    "processing" => [
        "label" => "Processing",
        "bg"    => "#FBE8D6",
        "text"  => "#29252A",
    ],

    /* Bago mag-Ship Out, kailangan munang bayaran ng customer ang
       balance — 3 baitang ito ang bagong sub-flow bago maging
       "Ready to Ship" (gaya ng downpayment: awaiting_payment ->
       to_verify -> for_approval, pero para na ito sa balance). */

    "awaiting_balance" => [
        "label" => "Awaiting Balance",
        "bg"    => "#FBE8D6",
        "text"  => "#29252A",
    ],

    "to_verify_balance" => [
        "label" => "Verifying Balance",
        "bg"    => "#E1F3E7",
        "text"  => "#29252A",
    ],

    "for_balance_approval" => [
        "label" => "For Balance Approval",
        "bg"    => "#E1F3E7",
        "text"  => "#29252A",
    ],

    "to_ship" => [
        "label" => "Ready to Ship",
        "bg"    => "#E8EDFF",
        "text"  => "#29252A",
    ],

    "shipped" => [
        "label" => "Shipped",
        "bg"    => "#E8EDFF",
        "text"  => "#29252A",
    ],

    "completed" => [
        "label" => "Completed",
        "bg"    => "#E1F3E7",
        "text"  => "#29252A",
    ],

    "cancelled" => [
        "label" => "Cancelled",
        "bg"    => "#FADCDC",
        "text"  => "#29252A",
    ],
];


// normalize raw DB status papuntang canonical key — kasama ang
// paglilinis ng "for approval" (may space) at mapping ng mga
// lumang status text (paid, approved, atbp.) sa tamang status

function figurify_status_key(?string $rawStatus): string
{
    $status = strtolower(trim((string) $rawStatus));
    $status = str_replace(["-", " "], "_", $status);

    $synonyms = [
        "forapproval" => "for_approval",

        "for_payment" => "awaiting_payment",
        "forpayment"  => "awaiting_payment",

        "verified" => "to_verify",
        "paid"     => "to_verify",

        "on_process" => "processing",
        "active"     => "processing",
        "confirmed"  => "processing",
        "approved"   => "processing",

        "canceled" => "cancelled",
        "declined" => "cancelled",
        "refunded" => "cancelled",
        "refund"   => "cancelled",
    ];

    if (isset($synonyms[$status])) {
        return $synonyms[$status];
    }

    if (isset(FIGURIFY_ORDER_STATUSES[$status])) {
        return $status;
    }

    return "pending";
}


// friendly label, e.g. "for approval" -> "For Approval"

function statusLabel(?string $rawStatus): string
{
    $key = figurify_status_key($rawStatus);

    return FIGURIFY_ORDER_STATUSES[$key]["label"];
}


// CSS-safe class name, e.g. "status-for_approval" — gamitin ito
// sa halip na i-print ang raw status (may space, nasisira ang class)

function figurify_status_class(?string $rawStatus): string
{
    return "status-" . figurify_status_key($rawStatus);
}


// hex colors, para sa JS-generated calendar badges atbp.

function figurify_status_colors(?string $rawStatus): array
{
    $key = figurify_status_key($rawStatus);

    return [
        "bg"   => FIGURIFY_ORDER_STATUSES[$key]["bg"],
        "text" => FIGURIFY_ORDER_STATUSES[$key]["text"],
    ];
}


// ready-to-print badge, e.g. <span class="order-status status-quoted">Quoted</span>

function figurify_status_badge(?string $rawStatus, string $baseClass = "order-status"): string
{
    $class = $baseClass . " " . figurify_status_class($rawStatus);
    $label = statusLabel($rawStatus);

    return '<span class="' . htmlspecialchars($class, ENT_QUOTES, "UTF-8") . '">'
        . htmlspecialchars($label, ENT_QUOTES, "UTF-8")
        . '</span>';
}
