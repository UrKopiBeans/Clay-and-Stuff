<?php

/* Collection helper — collection-management.php (edit) at collection.php
   (display), base sa "collection_items" table. Auto-creates + seeds
   default products kung wala pang table. */

require_once __DIR__ . "/content_helper.php"; // para sa figurify_resolve_image_src()

function figurify_collection_ensure_table(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS collection_items (
            item_id        INT AUTO_INCREMENT PRIMARY KEY,
            section        VARCHAR(20)  NOT NULL DEFAULT 'normal',
            category       VARCHAR(30)  NOT NULL DEFAULT 'chibi',
            style          VARCHAR(60)  NOT NULL DEFAULT '',
            type           VARCHAR(60)  NOT NULL DEFAULT '',
            size           VARCHAR(30)  NOT NULL DEFAULT '',
            price          VARCHAR(30)  NOT NULL DEFAULT '',
            image          VARCHAR(255) NOT NULL DEFAULT '',
            is_bestseller  TINYINT(1)   NOT NULL DEFAULT 0,
            sort_order     INT          NOT NULL DEFAULT 0,
            created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $ensured = true;

    // seed default products kung bagong gawa pa lang ang table
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM collection_items");
    $count = $countResult ? (int) $countResult->fetch_assoc()["total"] : 0;

    if ($count > 0) {
        return;
    }

    $defaults = [
        ["normal",  "chibi",   "Chibi",       "Half-body",     "2\"",   "₱450",             "Chibi Head.jpg", 0],
        ["normal",  "chibi",   "Chibi",       "Framed",        "2\"",   "₱500",             "Chibi Head- Spiderman.jpg", 0],
        ["normal",  "chibi",   "Chibi",       "Couple Set",    "2\"",   "₱850",             "Hirono-Couple.jpg", 0],
        ["normal",  "chibi",   "Chibi",       "Waist-up",      "4\"",   "₱650",             "3.5” Customize Hirono Standee.jpg", 0],
        ["normal",  "chibi",   "Chibi",       "Framed",        "4\"",   "₱700",             "3.5” Customize Hirono Inspired Standee.jpg", 0],
        ["normal",  "chibi",   "Chibi",       "Full-body",     "4\"",   "₱900",             "Hirono.jpg", 1],
        ["normal",  "hirono",  "Hirono",      "Standee",       "2\"",   "₱600",             "Hirono.jpg", 0],
        ["normal",  "hirono",  "Hirono",      "Standee",       "3.5\"", "₱750",             "3.5” Customize Hirono Standee.jpg", 1],
        ["normal",  "hirono",  "Hirono",      "Couple Set",    "3.5\"", "₱1,800",           "Hirono-Couple.jpg", 0],
        ["normal",  "funko",   "Funko Pop",   "Standee",       "5\"",   "₱600",             "3.5” Customize Hirono Inspired Standee.jpg", 0],
        ["normal",  "hirono",  "Hirono",      "Blind Box Set", "3.5\"", "₱800",             "3.5” Customize Hirono With Blind Box Set.jpg", 0],
        ["normal",  "funko",   "Funko Pop",   "Standee",       "4\"",   "₱1,000",           "3.5” Customize Hirono Standee.jpg", 1],
        ["special", "funko",   "Funko Pop",   "Solo Figure",   "Custom", "Starts at ₱650",  "", 0],
        ["special", "special", "Pop Culture", "Custom Figure", "Custom", "Starts at ₱700",  "", 0],
        ["special", "custom",  "Custom Box",  "3D Box",        "Custom", "Starts at ₱500",  "", 0],
    ];

    $stmt = $conn->prepare("
        INSERT INTO collection_items
            (section, category, style, type, size, price, image, is_bestseller, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($defaults as $i => $row) {
        [$section, $category, $style, $type, $size, $price, $image, $bestseller] = $row;
        $sortOrder = $i;

        $stmt->bind_param(
            "sssssssii",
            $section,
            $category,
            $style,
            $type,
            $size,
            $price,
            $image,
            $bestseller,
            $sortOrder
        );

        $stmt->execute();
    }

    $stmt->close();
}


// kunin lahat ng produkto, grouped by "section" (normal/special), sorted
function figurify_collection_get_grouped(mysqli $conn): array
{
    figurify_collection_ensure_table($conn);

    $grouped = ["normal" => [], "special" => []];

    $result = $conn->query("
        SELECT * FROM collection_items
        ORDER BY section ASC, sort_order ASC, item_id ASC
    ");

    if (!$result) {
        return $grouped;
    }

    while ($row = $result->fetch_assoc()) {
        $section = $row["section"] === "special" ? "special" : "normal";
        $grouped[$section][] = $row;
    }

    return $grouped;
}


// filter ng items base sa category (chibi/hirono/funko)
function figurify_collection_filter_category(array $items, string $category): array
{
    return array_values(array_filter(
        $items,
        fn(array $item) => $item["category"] === $category
    ));
}


// data-filter tag string, ginagamit ng dropdown filter + JS sa Collection page
function figurify_collection_build_filter_tag(array $item): string
{
    $tags = [];
    $tags[] = $item["section"] === "special" ? "special" : "normal";

    if ($item["category"] !== "" && $item["category"] !== $tags[0]) {
        $tags[] = $item["category"];
    }

    if (!empty($item["is_bestseller"])) {
        $tags[] = "bestseller";
    }

    return implode(" ", array_unique($tags));
}


// render ng <article class="card"> ng isang produkto sa Collection page
function figurify_collection_render_card(array $item, string $fallbackImg): void
{
    $filterTag = figurify_collection_build_filter_tag($item);
    $imageSrc = figurify_resolve_image_src($item["image"] ?? "", $fallbackImg);
    $style = htmlspecialchars((string) $item["style"], ENT_QUOTES, "UTF-8");
    $type = htmlspecialchars((string) $item["type"], ENT_QUOTES, "UTF-8");
    $size = htmlspecialchars((string) $item["size"], ENT_QUOTES, "UTF-8");
    $price = htmlspecialchars((string) $item["price"], ENT_QUOTES, "UTF-8");
    $altText = trim($style . " — " . $type . ", " . $size);
    ?>
    <article class="card" data-filter="<?php echo htmlspecialchars($filterTag, ENT_QUOTES, "UTF-8"); ?>">
        <?php if (!empty($item["is_bestseller"])): ?>
            <span class="badge">Best Seller</span>
        <?php endif; ?>
        <div class="card-thumb"><img src="<?php echo htmlspecialchars($imageSrc, ENT_QUOTES, "UTF-8"); ?>" alt="<?php echo htmlspecialchars($altText, ENT_QUOTES, "UTF-8"); ?>"></div>
        <div class="card-body">
            <h3><?php echo $style; ?></h3>
            <div class="card-meta">
                <?php if ($type !== ""): ?><span class="meta-chip"><?php echo $type; ?></span><?php endif; ?>
                <?php if ($size !== ""): ?><span class="meta-chip"><?php echo $size; ?></span><?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="../Commission/commission.php" class="card-btn">Order Now</a>
                <span class="price"><?php echo $price; ?></span>
            </div>
        </div>
    </article>
    <?php
}
