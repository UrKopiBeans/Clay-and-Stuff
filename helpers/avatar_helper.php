<?php

/* Profile picture: kung may na-upload na photo, gamitin iyon; kung
   wala, gumawa ng bilog na may kulay + unang letra ng pangalan
   (parang Gmail/Slack) sa halip na generic icon. */

if (!function_exists("figurify_avatar_initial")) {

    function figurify_avatar_initial(string $name): string
    {
        $name = trim($name);

        if ($name === "") {
            return "?";
        }

        return mb_strtoupper(mb_substr($name, 0, 1));
    }
}

if (!function_exists("figurify_avatar_color")) {

    function figurify_avatar_color(string $name): string
    {
        /* Mga kulay na tugma sa palette ng site (pink/purple) */
        $colors = [
            "#e77fbd", "#b9a0ef", "#f472b6", "#9b3f72",
            "#db2777", "#a88be5", "#831843", "#c026d3",
        ];

        $index = crc32($name) % count($colors);

        return $colors[$index];
    }
}

if (!function_exists("figurify_render_avatar")) {

    /* Nagbabalik ng HTML (img o initials div) para sa .profile-avatar.
       $siteBase = prefix na idadagdag sa local uploaded photo paths. */
    function figurify_render_avatar(string $name, ?string $picture, string $altText = "Profile", string $siteBase = ""): string
    {
        $picture = trim((string) $picture);

        $initial = figurify_avatar_initial($name);
        $color   = figurify_avatar_color($name);

        if ($picture !== "") {

            $isFullUrl = (stripos($picture, "http://") === 0 || stripos($picture, "https://") === 0);
            $src       = $isFullUrl ? $picture : ($siteBase . $picture);

            /* Kung hindi ma-load ang naka-save na larawan (na-expire,
               blocked, o burado na), awtomatikong papalitan ng
               colored-initials avatar sa halip na broken image icon. */
            $fallbackHtml = "<div class='avatar-initials' style='background:"
                . htmlspecialchars($color, ENT_QUOTES)
                . ";'>" . htmlspecialchars($initial, ENT_QUOTES) . "</div>";

            return '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($altText) . '" '
                 . 'referrerpolicy="no-referrer" '
                 . 'onerror="this.outerHTML=\'' . $fallbackHtml . '\';">';
        }

        return '<div class="avatar-initials" style="background:' . htmlspecialchars($color) . ';">'
             . htmlspecialchars($initial)
             . '</div>';
    }
}
