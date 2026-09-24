<?php
/*
 * Scrollable na container para sa Content Management / Collection Management.
 * Kapag humaba ang laman ng isang section (card), hindi na hahaba ang page:
 * yung laman lang sa ilalim ng header ang magi-scroll.
 * Yung header (title + "+ Add" button) laging nakikita sa taas.
 *
 * Gamit: i-include bago ang </body>
 *   include __DIR__ . "/../Shared/section-scroll.php";
 */
?>
<style>
.content-section-body {
    display: flex;
    flex-direction: column;
    gap: 12px;

    max-height: 520px;     /* lampas dito, magi-scroll na */
    overflow-y: auto;
    overscroll-behavior: contain;

    padding-right: 6px;    /* space para sa scrollbar */
    margin-right: -6px;
}

/* pink na scrollbar para bagay sa theme */
.content-section-body::-webkit-scrollbar {
    width: 8px;
}

.content-section-body::-webkit-scrollbar-track {
    background: #fbf1f6;
    border-radius: 8px;
}

.content-section-body::-webkit-scrollbar-thumb {
    background: #e7b9cf;
    border-radius: 8px;
}

.content-section-body::-webkit-scrollbar-thumb:hover {
    background: #d995b6;
}

.content-section-body {
    scrollbar-width: thin;
    scrollbar-color: #e7b9cf #fbf1f6;
}

/* maliit na hint sa ilalim kapag may natatagong laman pa */
.content-section.has-more-content {
    position: relative;
}

.content-section.has-more-content::after {
    content: "Scroll for more ↓";
    display: block;

    text-align: center;
    font-size: 11px;
    font-weight: 700;
    color: #c07a9c;

    padding-top: 4px;
}

.content-section.scrolled-to-end::after {
    content: none;
}

@media (max-width: 700px) {
    .content-section-body {
        max-height: 420px;
    }
}
</style>

<script>
(function () {
    const sections = document.querySelectorAll(".content-form .content-section");

    sections.forEach(function (section) {
        // alamin kung alin ang header (may "header-row" sa Collection page)
        const header =
            section.querySelector(":scope > .content-section-header-row") ||
            section.querySelector(":scope > .content-section-header");

        if (!header) return;

        // ilipat lahat ng laman pagkatapos ng header sa loob ng scroll box
        const body = document.createElement("div");
        body.className = "content-section-body";

        let next = header.nextSibling;
        while (next) {
            const move = next;
            next = next.nextSibling;
            body.appendChild(move);
        }

        section.appendChild(body);

        function updateHint() {
            const overflowing = body.scrollHeight > body.clientHeight + 2;
            const atEnd = body.scrollTop + body.clientHeight >= body.scrollHeight - 4;

            section.classList.toggle("has-more-content", overflowing);
            section.classList.toggle("scrolled-to-end", overflowing && atEnd);
        }

        body.addEventListener("scroll", updateHint);
        window.addEventListener("resize", updateHint);

        // kapag may nadagdag/natanggal na item (hal. "+ Add Chibi"), i-update ang hint
        new MutationObserver(updateHint).observe(body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ["style", "class"]
        });

        // images/videos na naglo-load pa: i-check ulit kapag tapos na
        body.querySelectorAll("img, video").forEach(function (media) {
            media.addEventListener("load", updateHint);
            media.addEventListener("loadedmetadata", updateHint);
        });

        updateHint();
    });
})();
</script>
