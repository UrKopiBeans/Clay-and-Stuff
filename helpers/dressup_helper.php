<?php

/*
 * DRESS UP ORDER HELPER
 *
 * Ginagamit ng My Orders + lahat ng staff/owner order pages para ipakita ang
 * Dress Up (Create Style) design ng isang figure: 3D figure + listahan ng
 * napili (gender, skin, hair, top, bottom, shoes, colors).
 *
 * Paano gamitin sa page (nasa loob ng foreach ng figures):
 *
 *     <?php if (figurify_dressup_for_figure($fig) !== null): ?>
 *         <?php figurify_render_dressup_design($fig, "customer"); ?>
 *     <?php else: ?>
 *         ... reference images ...
 *     <?php endif; ?>
 *
 * May sariling DB connection ito (kagaya ng Shared/footer.php) dahil maraming
 * page ang nagsasara na ng $conn bago i-render ang HTML. Kapag wala pang
 * "dressup_data" column (hindi pa na-run ang Admin/add_dressup_orders.sql),
 * tahimik lang itong magbabalik ng null — hindi masisira ang page.
 */


if (!function_exists("figurify_dressup_connection")) {

    function figurify_dressup_connection()
    {
        static $connection = null;

        if ($connection !== null) {
            return $connection;
        }

        $connection = false;

        try {
            $candidate = @new mysqli("localhost", "root", "", "figurify_db");

            if ($candidate && !$candidate->connect_error) {
                $candidate->set_charset("utf8mb4");
                $connection = $candidate;
            }
        } catch (Throwable $error) {
            $connection = false;
        }

        return $connection;
    }


    /* decoded Dress Up design ng figure, o null kung hindi Dress Up */

    function figurify_dressup_for_figure(array $fig): ?array
    {
        static $cache = [];

        $figureId = (int) ($fig["figure_id"] ?? 0);

        if (array_key_exists("dressup_data", $fig)) {
            $raw = $fig["dressup_data"];
        } else {

            if ($figureId <= 0) {
                return null;
            }

            if (array_key_exists($figureId, $cache)) {
                return $cache[$figureId];
            }

            $raw = null;
            $connection = figurify_dressup_connection();

            if ($connection) {
                try {
                    $stmt = $connection->prepare(
                        "SELECT dressup_data FROM order_figures WHERE figure_id = ? LIMIT 1"
                    );

                    if ($stmt) {
                        $stmt->bind_param("i", $figureId);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        $raw = $row["dressup_data"] ?? null;
                    }
                } catch (Throwable $error) {
                    /* wala pang dressup_data column — Image Submission lang ang meron */
                    $raw = null;
                }
            }
        }

        $decoded = null;

        if (is_string($raw) && $raw !== "") {
            $json = json_decode($raw, true);

            if (is_array($json) && !empty($json["model"]["model"])) {
                $decoded = $json;
            }
        }

        if ($figureId > 0) {
            $cache[$figureId] = $decoded;
        }

        return $decoded;
    }


    function figurify_dressup_site_base(): string
    {
        static $base = null;

        if ($base !== null) {
            return $base;
        }

        $documentRoot = rtrim(str_replace("\\", "/", (string) realpath($_SERVER["DOCUMENT_ROOT"] ?? "")), "/");
        $projectRoot  = rtrim(str_replace("\\", "/", (string) realpath(__DIR__ . "/..")), "/");

        $base = ($documentRoot !== "" && strpos($projectRoot, $documentRoot) === 0)
            ? substr($projectRoot, strlen($documentRoot)) . "/"
            : "/";

        return $base;
    }


    function figurify_dressup_e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
    }


    function figurify_dressup_price_label(?array $item): string
    {
        if (!$item) {
            return "—";
        }

        $price = (float) ($item["price"] ?? 0);

        if ($price > 0) {
            return "₱" . number_format($price, 2);
        }

        return "";
    }


    function figurify_dressup_slot_icon(string $slot): string
    {
        $icons = [
            "hair"         => "💇",
            "girlHair"     => "💇",
            "keychainHair" => "💇",
            "top"          => "👕",
            "girlTop"      => "👕",
            "outfit"       => "👕",
            "bottom"       => "👖",
            "girlBottom"   => "👖",
            "pants"        => "👖",
            "shoes"        => "👟",
            "keychainHat"  => "🧢",
        ];

        return $icons[$slot] ?? "✨";
    }


    /* CSS + 3D scripts — isang beses lang ilalabas kada page */

    function figurify_dressup_assets_once(): void
    {
        static $printed = false;

        if ($printed) {
            return;
        }

        $printed = true;

        $base = figurify_dressup_site_base();
        $viewerJs = $base . "Shared/dressup-viewer.js?v=2";
        ?>
<style>
/* Dress Up figure layout: kaliwa = figure details + design, kanan = 3D (parehong taas), baba = custom box */
.dressup-grid{display:grid!important;grid-template-columns:minmax(0,1fr) minmax(0,1fr);grid-template-areas:"details view" "box box";gap:18px;align-items:stretch}
.dressup-grid > :first-child{grid-area:details;min-width:0}
.dressup-grid > :nth-child(2){grid-area:box;min-width:0}
.dressup-grid > .dressup-3d-column{grid-area:view;min-width:0;display:flex;flex-direction:column}
.dressup-3d-column .dressup-viewer-block{flex:1;display:flex;flex-direction:column;margin-top:0;gap:14px}
.dressup-3d-column .dressup-viewer-block .dressup-3d-stage{flex:1;height:auto;min-height:280px}
/* staff/owner: kaparehong card ng "Figure Details" para pantay ang itaas at taas */
.qv-viewer-column.dressup-3d-column .dressup-viewer-block{background:#fff;border:1.5px solid #C9BEC3;border-radius:20px;box-shadow:5px 7px 0 rgba(216,194,222,.25);padding:16px 20px}
.dressup-3d-column .dressup-design-title{font-size:13px;line-height:1.2}
/* Figure Style / Product Type / Size: walang larawan, maliit, tatlong magkakatabi */
.dressup-grid > :first-child .detail-item-list,
.dressup-grid > :first-child .qv-detail-item-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
.dressup-grid > :first-child .detail-item-image,
.dressup-grid > :first-child .qv-detail-item-image{display:none}
.dressup-grid > :first-child .detail-item-card,
.dressup-grid > :first-child .qv-detail-item-card{flex-direction:column;align-items:flex-start;justify-content:flex-start;gap:3px;padding:8px 10px;flex-wrap:nowrap;min-width:0}
.dressup-grid > :first-child .detail-item-body,
.dressup-grid > :first-child .qv-detail-item-body{min-width:0;width:100%}
.dressup-grid > :first-child .detail-item-label,
.dressup-grid > :first-child .qv-detail-item-label{font-size:9px}
.dressup-grid > :first-child .detail-item-value,
.dressup-grid > :first-child .qv-detail-item-value{font-size:12px;line-height:1.25}
.dressup-grid > :first-child .detail-item-price,
.dressup-grid > :first-child .qv-detail-item-price{font-size:11px;text-align:left}
.dressup-grid > :first-child .detail-item-price.muted,
.dressup-grid > :first-child .qv-detail-item-price-muted{display:none}
@media (max-width:900px){.dressup-grid{grid-template-columns:minmax(0,1fr);grid-template-areas:"details" "view" "box"}}
/* makitid na lalagyan (hal. side panel ng Owner/Staff): isa-isang column na lang */
*:has(> .dressup-grid){container-type:inline-size}
@container (max-width:480px){.dressup-grid{grid-template-columns:minmax(0,1fr);grid-template-areas:"details" "view" "box"}}
.dressup-design{display:flex;flex-direction:column;gap:14px;margin-top:14px}
.dressup-design-title{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:800;color:#8c3764;letter-spacing:.3px}
.dressup-design-title .dressup-badge{margin-left:auto;padding:3px 10px;border-radius:999px;background:#ffe4f2;color:#c2185b;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px}
.dressup-3d-stage{position:relative;width:100%;height:320px;border-radius:16px;overflow:hidden;border:1px solid #f0d5e5;background:radial-gradient(circle at 50% 35%,#ffffff 0%,#fff3fa 60%,#fbe6f1 100%)}
.dressup-3d-stage canvas{position:absolute;inset:0;display:block;width:100%!important;height:100%!important;cursor:grab;touch-action:none}
.dressup-3d-stage canvas:active{cursor:grabbing}
.dressup-3d-status{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;color:#a2688a;font-size:12px;font-weight:700;text-align:center;padding:16px;pointer-events:none}
.dressup-3d-status .dressup-3d-spinner{width:26px;height:26px;border-radius:50%;border:3px solid #f5cfe2;border-top-color:#df65a6;animation:dressupSpin .8s linear infinite}
.dressup-3d-stage.is-ready .dressup-3d-status{display:none}
.dressup-3d-stage.is-error .dressup-3d-spinner{display:none}
.dressup-3d-hint{position:absolute;left:0;right:0;bottom:8px;text-align:center;font-size:10.5px;color:#b08aa0;pointer-events:none}
@keyframes dressupSpin{to{transform:rotate(360deg)}}
.dressup-picks{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
.dressup-pick{display:flex;flex-direction:column;align-items:flex-start;gap:2px;padding:8px 10px;border-radius:12px;background:#fff;border:1px solid #f1dbe7;min-width:0}
.dressup-pick-body{display:flex;flex-direction:column;gap:1px;min-width:0;width:100%}
.dressup-pick-label{font-size:9px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;color:#b07a98}
.dressup-pick-value{font-size:12px;font-weight:700;color:#4a2c3c;line-height:1.25;word-break:break-word}
.dressup-pick-color{display:inline-flex;align-items:center;gap:5px;font-size:10.5px;font-weight:600;color:#7d5a6d}
.dressup-swatch{display:inline-block;width:11px;height:11px;border-radius:50%;border:1px solid rgba(0,0,0,.15);vertical-align:middle}
.dressup-pick-price{font-size:10.5px;font-weight:800;color:#d05c98;white-space:nowrap}
.dressup-pick-price.is-included{color:#8aa596;font-weight:700}
@media (max-width:640px){.dressup-3d-stage{height:300px}}
</style>
<script type="importmap">
{
    "imports": {
        "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
        "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
    }
}
</script>
<script type="module" src="<?php echo figurify_dressup_e($viewerJs); ?>"></script>
        <?php
    }


    /*
     * Ilalabas ang 3D figure + listahan ng napiling design.
     * $variant: "customer" (My Orders) o "staff" (owner/staff pages) — pareho
     * lang ang itsura, pang-label lang.
     */

    /* data na kailangan ng 3D viewer (body + accessories + kulay) */

    function figurify_dressup_viewer_json(array $design): string
    {
        $viewerData = [
            "category" => $design["category"] ?? "",
            "model"    => $design["model"]["model"] ?? "",
            "skin"     => $design["skin"]["color"] ?? "",
            "parts"    => [],
        ];

        foreach (($design["slots"] ?? []) as $slotRow) {
            $viewerData["parts"][] = [
                "slot"     => $slotRow["slot"] ?? "",
                "model"    => $slotRow["item"]["model"] ?? "",
                "color"    => $slotRow["colorValue"] ?? "",
                "isBottom" => !empty($slotRow["isBottom"]),
            ];
        }

        return (string) json_encode($viewerData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }


    /* 3D figure — nasa kanan ng Figure Details (tingnan ang .dressup-grid) */

    function figurify_render_dressup_viewer(array $fig): void
    {
        $design = figurify_dressup_for_figure($fig);

        if ($design === null) {
            return;
        }

        figurify_dressup_assets_once();

        $base = figurify_dressup_site_base();
        ?>
        <div class="dressup-design dressup-viewer-block">

            <div class="dressup-design-title">
                🧸 3D Figure
                <span class="dressup-badge">Dress Up</span>
            </div>

            <div
                class="dressup-3d-stage"
                data-dressup-viewer
                data-asset-base="<?php echo figurify_dressup_e($base . "Commission/"); ?>"
                data-design="<?php echo figurify_dressup_e(figurify_dressup_viewer_json($design)); ?>"
            >
                <canvas aria-label="3D preview of the ordered figure"></canvas>
                <div class="dressup-3d-status">
                    <span class="dressup-3d-spinner"></span>
                    <span class="dressup-3d-status-text">Loading 3D figure…</span>
                </div>
                <div class="dressup-3d-hint">Drag to rotate · Scroll to zoom</div>
            </div>

        </div>
        <?php
    }


    /* listahan ng napiling design (Gender, Skin, Hair, Top, Bottom, Shoes) —
       nasa ilalim ng Figure Style / Product Type / Size sa kaliwa */

    function figurify_render_dressup_design(array $fig, string $variant = "customer"): void
    {
        $design = figurify_dressup_for_figure($fig);

        if ($design === null) {
            return;
        }

        figurify_dressup_assets_once();

        $skin = $design["skin"] ?? null;
        ?>
        <div class="dressup-design" data-variant="<?php echo figurify_dressup_e($variant); ?>">

            <div class="dressup-design-title">🎨 Dress Up Design</div>

            <div class="dressup-picks">

                <div class="dressup-pick">
                    <div class="dressup-pick-body">
                        <span class="dressup-pick-label">Gender</span>
                        <span class="dressup-pick-value"><?php echo figurify_dressup_e($design["genderLabel"] ?? ($design["model"]["name"] ?? "")); ?></span>
                    </div>
                </div>

                <?php if ($skin): ?>
                    <div class="dressup-pick">
                        <div class="dressup-pick-body">
                            <span class="dressup-pick-label">Skin Tone</span>
                            <span class="dressup-pick-value">
                                <?php if (!empty($skin["color"])): ?>
                                    <span class="dressup-swatch" style="background:<?php echo figurify_dressup_e($skin["color"]); ?>"></span>
                                <?php endif; ?>
                                <?php echo figurify_dressup_e($skin["name"] ?? ""); ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php foreach (($design["slots"] ?? []) as $slotRow): ?>
                    <?php
                    $item       = $slotRow["item"] ?? [];
                    $priceLabel = figurify_dressup_price_label($item);
                    ?>
                    <div class="dressup-pick">
                        <div class="dressup-pick-body">
                            <span class="dressup-pick-label"><?php echo figurify_dressup_e($slotRow["label"] ?? ""); ?></span>
                            <span class="dressup-pick-value"><?php echo figurify_dressup_e($item["name"] ?? ""); ?></span>
                            <?php if (!empty($slotRow["colorName"]) || !empty($slotRow["colorValue"])): ?>
                                <span class="dressup-pick-color">
                                    <?php if (!empty($slotRow["colorValue"])): ?>
                                        <span class="dressup-swatch" style="background:<?php echo figurify_dressup_e($slotRow["colorValue"]); ?>"></span>
                                    <?php endif; ?>
                                    <?php echo figurify_dressup_e($slotRow["colorName"] ?: $slotRow["colorValue"]); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($priceLabel !== ""): ?>
                            <div class="dressup-pick-price"><?php echo figurify_dressup_e($priceLabel); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

            </div>

        </div>
        <?php
    }

}
