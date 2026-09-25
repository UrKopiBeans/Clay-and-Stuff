import * as THREE from "three";

import {
    GLTFLoader
} from "three/addons/loaders/GLTFLoader.js";

import {
    OrbitControls
} from "three/addons/controls/OrbitControls.js";


// DOM elements

const canvas =
    document.getElementById("figureViewer");

const figureArea =
    document.querySelector(".figure-area");

const selectedList =
    document.getElementById("selectedList");

const dressUpTotalPrice =
    document.getElementById("dressUpTotalPrice");

const resetBtn =
    document.getElementById("resetBtn");

const continueBtn =
    document.getElementById("continueBtn");

const productDetailsPanel =
    document.getElementById("productDetailsPanel");

const figureDetailsContainer =
    document.getElementById("figureDetailsContainer");

const sizeChoiceGrid =
    document.getElementById("sizeChoiceGrid");

const boxChoiceGrid =
    document.getElementById("boxChoiceGrid");

const figureNameInput =
    document.getElementById("figureNameInput");

const boxNameInput =
    document.getElementById("boxNameInput");

const boxNumberInput =
    document.getElementById("boxNumberInput");

const boxColorInput =
    document.getElementById("boxColorInput");

const COMMISSION_RETURN_KEY =
    "figurifyCommissionReturn";

const COMMISSION_ORDER_TYPE_KEY =
    "figurifyCommissionOrderType";

const COMMISSION_BOOKING_DATE_KEY =
    "figurifyCommissionBookingDate";

const COMMISSION_FIGURE_CATEGORY_KEY =
    "figurifyCommissionFigureCategory";

const CUSTOMIZATION_STORAGE_KEY =
    "figurifyCustomization";

const pageMode =
    document.body.dataset.page || "editor";

const isPreviewPage =
    pageMode === "preview";

const isDetailsPage =
    pageMode === "details";

const isFinalSummaryPage =
    pageMode === "finalSummary";

const isEditPage =
    new URLSearchParams(window.location.search).get("edit") === "1";

// "?resume=1" — dapat bumalik ang "Back" mula sa finalsummary.php sa eksaktong
// step kung saan pinindot ang "Next" (hindi mag-reset pabalik sa Figure Style).
const isResumePage =
    new URLSearchParams(window.location.search).get("resume") === "1";

const styleCards =
    Array.from(
        document.querySelectorAll(
            '[data-tab="figure"]'
        )
    );


const itemCards =
    Array.from(
        document.querySelectorAll(
            "[data-slot]"
        )
    );


const funkoPanel =
    document.getElementById("funkoPanel");

const hironoPanel =
    document.getElementById("hironoPanel");

const chibiPanel =
    document.getElementById("chibiPanel");

const figureStyleSection =
    document.getElementById("figureStyleSection");


const sectionIds = {
    funko: {
        skin: "funkoSkinSection",
        hair: "funkoHairSection",
        girlHair: "funkoGirlHairSection",
        girlTop: "funkoGirlTopSection",
        girlTopColor: "funkoGirlTopColorSection",
        girlBottom: "funkoGirlBottomSection",
        girlBottomColor: "funkoGirlBottomColorSection",
        bottomColor: "funkoBottomPantsColorSection",
        hairColor: "funkoHairColorSection",
        top: "funkoTopSection",
        topColor: "funkoTopColorSection",
        bottom: "funkoBottomSection",
        pantsColor: "funkoBottomPantsColorSection",
        shoes: "funkoShoesSection"
    },
    hirono: {
        type: "hironoTypeSection",
        standee: "hironoStandeeSection",
        keychain: "hironoKeychainSection",
        skin: "hironoSkinSection",
        hair: "hironoHairSection",
        hairColor: "hironoHairColorSection",
        outfit: "hironoOutfitSection",
        outfitColor: "hironoOutfitColorSection",
        pants: "hironoPantsSection",
        pantsColor: "hironoPantsColorSection",
        shoes: "hironoShoesSection",
        shoesColor: "hironoShoesColorSection",
        keychainHair: "hironoKeychainHairSection",
        keychainHairColor: "hironoKeychainHairColorSection",
        keychainHat: "hironoKeychainHatSection"
    },
    chibi: {
        type: "chibiTypeSection",
        skin: "chibiSkinSection",
        hair: "chibiHairSection",
        girlHair: "chibiGirlHairSection",
        hairColor: "chibiHairColorSection",
        girlHairColor: "chibiGirlHairColorSection",
        top: "chibiTopSection",
        girlTop: "chibiGirlTopSection",
        bottom: "chibiBottomSection",
        girlBottom: "chibiGirlBottomSection",
        shoes: "chibiShoesSection",
        // Keychain may sariling top/bottom models (galing sa DressUp)
        keychainBoyTop: "chibiBoyKeychainTopSection",
        keychainBoyBottom: "chibiBoyKeychainBottomSection",
        keychainGirlTop: "chibiGirlKeychainTopSection",
        keychainGirlBottom: "chibiGirlKeychainBottomSection"
    }
};


const categoryPanels = {
    funko: funkoPanel,
    hirono: hironoPanel,
    chibi: chibiPanel
};


function getStorageValue(key) {

    try {
        return localStorage.getItem(key);
    }
    catch (error) {
        console.warn("Unable to read storage key:", key, error);
        return null;
    }

}


function getCustomizationState() {

    const raw =
        getStorageValue(
            CUSTOMIZATION_STORAGE_KEY
        );


    if (!raw) {
        return null;
    }


    try {
        return JSON.parse(raw);
    }
    catch (error) {
        console.warn(
            "Unable to parse customization state:",
            error
        );
        return null;
    }

}


function formatMoney(amount) {

    return "₱" +
        Number(amount || 0)
            .toLocaleString("en-PH");

}


function formatBookingDate(value) {

    if (!value) {
        return "Not selected";
    }

    const date =
        new Date(
            `${value}T00:00:00`
        );

    if (Number.isNaN(date.getTime())) {
        return "Not selected";
    }

    return date.toLocaleDateString(
        "en-PH",
        {
            month: "long",
            day: "numeric",
            year: "numeric"
        }
    );

}


function getCommissionOrderTypeValue() {
    return getStorageValue(COMMISSION_ORDER_TYPE_KEY);
}


function getCommissionOrderTypeLabel() {

    const value =
        getCommissionOrderTypeValue();

    if (value === "rush") {
        return "⚡ Rush Order";
    }

    if (value === "nonrush") {
        return "🌷 Non-Rush Order";
    }

    return "Not selected";

}


function getCommissionRushFee() {

    if (getCommissionOrderTypeValue() !== "rush") {
        return 0;
    }

    // Kapareho ng server (helpers/booking_helper.php): ₱200 ang rush fee ng
    // "Head Only" na produkto, ₱500 ang iba — para tugma ang total dito at sa
    // Payment page.
    if (
        state.currentCategory === "hirono" &&
        state.hirono.mode === "headKeychain"
    ) {
        return 200;
    }

    return 500;
}


function getCommissionBookingDateValue() {
    return getStorageValue(COMMISSION_BOOKING_DATE_KEY);
}


function getCommissionBookingDateLabel() {
    return formatBookingDate(
        getCommissionBookingDateValue()
    );
}


function getCommissionBookingStatusLabel() {

    const value =
        getCommissionBookingDateValue();

    if (!value) {
        return "Choose a date from the calendar";
    }

    const date =
        new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return "Choose a date from the calendar";
    }

    return date.toLocaleDateString(
        "en-PH",
        { weekday: "long" }
    );

}


function getCommissionFigureCategoryValue() {
    return getStorageValue(COMMISSION_FIGURE_CATEGORY_KEY);
}


function getCommissionFigureCategoryLabel() {

    const value =
        getCommissionFigureCategoryValue();

    return value || "Not selected";

}


// state

function createCategoryState() {

    return {
        mode: null,
        productType: null,
        model: null,
        skin: null,
        hair: null,
        girlHair: null,
        hairColor: null,
        girlHairColor: null,
        top: null,
        girlTop: null,
        topColor: null,
        girlTopColor: null,
        bottom: null,
        bottomColor: null,
        girlBottom: null,
        girlBottomColor: null,
        outfit: null,
        outfitColor: null,
        pants: null,
        pantsColor: null,
        shoes: null,
        shoesColor: null
    };

}


const state = {
    currentCategory: null,
    funko: createCategoryState(),
    hirono: createCategoryState(),
    chibi: createCategoryState()
};


const PRODUCT_DETAIL_CONFIGS = {
    funkoBoy: {
        title: "Funko Pop Boy Details",
        description: "Custom Funko box for your figure.",
        sizes: ["3 inches", "4 inches", "5 inches"],
        boxes: [
            { id: "solo", label: "Solo Box", price: 500, details: true, swatch: "linear-gradient(145deg,#ffe0ef,#f6c6de)" },
            { id: "couple", label: "Couple Box", price: 800, details: true, swatch: "linear-gradient(145deg,#e8ddff,#d5c4f7)" }
        ],
        boxLabel: "Custom Funko Box"
    },
    funkoGirl: {
        title: "Funko Pop Girl Details",
        description: "Custom Funko box for your figure.",
        sizes: ["3 inches", "4 inches", "5 inches"],
        boxes: [
            { id: "solo", label: "Solo Box", price: 500, details: true, swatch: "linear-gradient(145deg,#ffe0ef,#f6c6de)" },
            { id: "couple", label: "Couple Box", price: 800, details: true, swatch: "linear-gradient(145deg,#e8ddff,#d5c4f7)" }
        ],
        boxLabel: "Custom Funko Box"
    },
    hironoStandee: {
        title: "Hirono Standee Details",
        description: "Full body standee with blind box options and add-ons.",
        sizes: ["2 inches", "3.5 inches"],
        boxes: [],
        hirono: true
    },
    hironoKeychain: {
        title: "Hirono Keychain Details",
        description: "Keychain with blind box options and add-ons.",
        sizes: ["2 inches"],
        boxes: [],
        hirono: true
    },
    hironoHeadKeychain: {
        title: "Hirono Head Keychain Details",
        description: "Head-only keychain with blind box options and add-ons.",
        sizes: [],
        boxes: [],
        hirono: true
    },
    chibiBoy: {
        title: "Chibi Boy Details",
        description: "Chibi figures do not have box choices.",
        sizes: ["2 inches", "3 inches", "4 inches", "5 inches"],
        boxes: []
    },
    chibiBoyKeychain: {
        title: "Chibi Boy Keychain Details",
        description: "Chibi keychains do not have box choices.",
        sizes: ["2 inches"],
        boxes: []
    },
    chibiGirl: {
        title: "Chibi Girl Details",
        description: "Chibi figures do not have box choices.",
        sizes: ["2 inches", "3 inches", "4 inches", "5 inches"],
        boxes: []
    },
    chibiGirlKeychain: {
        title: "Chibi Girl Keychain Details",
        description: "Chibi keychains do not have box choices.",
        sizes: ["2 inches"],
        boxes: []
    }
};


const PRODUCT_SIZE_PRICES = {
    funkoBoy: { "3 inches": 900, "4 inches": 1200, "5 inches": 1500 },
    funkoGirl: { "3 inches": 900, "4 inches": 1200, "5 inches": 1500 },
    chibiBoy: { "2 inches": 500, "3 inches": 680, "4 inches": 900, "5 inches": 1200 },
    chibiGirl: { "2 inches": 500, "3 inches": 680, "4 inches": 900, "5 inches": 1200 },
    chibiBoyKeychain: { "2 inches": 500 },
    chibiGirlKeychain: { "2 inches": 500 },
    hironoStandee: { "2 inches": 600, "3.5 inches": 950 },
    hironoKeychain: { "2 inches": 600 }
};


let productDetails = {
    productKey: "",
    size: "",
    figureName: "",
    box: "",
    boxName: "",
    boxNumber: "",
    boxColor: "",
    blindBox: "regular",
    blindBoxSelected: false,
    hironoAddons: [],
    boxDesign: "checkered",
    boxDesignSelected: false,
    boxNickname: "",
    boxLetter: "",
    boxDateYmd: ""
};

let navigationSlot = isDetailsPage ? "productDetails" : null;

let pendingFigureCategory = null;


const ACCESSORY_COLOR_SLOT = {
    hair: "hairColor",
    girlHair: "girlHairColor",
    girlTop: "girlTopColor",
    girlBottom: "girlBottomColor",
    top: "topColor",
    outfit: "outfitColor",
    pants: "pantsColor",
    shoes: "shoesColor",
    keychainHair: "keychainHairColor"
};


const SLOT_ORDER = {
    funkoBoy: [
        "model",
        "skin",
        "hair",
        "hairColor",
        "top",
        "topColor",
        "bottom",
        "pantsColor",
        "shoes",
        "shoesColor"
    ],
    funkoGirl: [
        "model",
        "skin",
        "girlHair",
        "girlHairColor",
        "girlTop",
        "girlTopColor",
        "girlBottom",
        "girlBottomColor",
        "shoes",
        "shoesColor"
    ],
    chibiBoy: [
        "model",
        "skin",
        "hair",
        "hairColor",
        "top",
        "topColor",
        "bottom",
        "bottomColor",
        "shoes",
        "shoesColor"
    ],
    chibiGirl: [
        "model",
        "skin",
        "girlHair",
        "girlHairColor",
        "girlTop",
        "girlTopColor",
        "girlBottom",
        "girlBottomColor",
        "shoes",
        "shoesColor"
    ],
    // Keychain: walang hiwalay na Hair step — kasama na ang buhok sa keychain model
    chibiBoyKeychain: [
        "model",
        "skin",
        "top",
        "topColor",
        "bottom",
        "bottomColor",
        "shoes",
        "shoesColor"
    ],
    chibiGirlKeychain: [
        "model",
        "skin",
        "girlTop",
        "girlTopColor",
        "girlBottom",
        "girlBottomColor",
        "shoes",
        "shoesColor"
    ],
    hironoStandee: [
        "model",
        "skin",
        "hair",
        "hairColor",
        "outfit",
        "outfitColor",
        "pants",
        "pantsColor",
        "shoes",
        "shoesColor"
    ],
    hironoKeychain: [
        "model",
        "skin",
        "hair",
        "hairColor",
        "outfit",
        "outfitColor",
        "pants",
        "pantsColor",
        "shoes",
        "shoesColor"
    ],
    hironoHeadKeychain: [
        "model",
        "skin",
        "keychainHair",
        "keychainHairColor",
        "keychainHat"
    ]
};


const PRICE_FALLBACKS = {
    funko: {
        hair: 50,
        girlHair: 50,
        top: {
            "T-Shirt": 80,
            Hoodie: 100
        },
        girlTop: {
            "Girl Shirt 01": 80,
            "Girl Shirt 02": 100,
            "Girl Shirt 03": 100
        },
        bottom: 90,
        girlBottom: 90
    },
    chibi: {
        hair: 50,
        girlHair: 50
    },
    hirono: {
        hair: 50,
        outfit: {
            "Hirono Polo": 80,
            "Hirono Jersey": 0
        },
        pants: 90,
        shoes: 80,
        keychainHair: 50,
        keychainHat: 0
    }
};


// three.js setup

const loader = new GLTFLoader();

let scene = null;
let camera = null;
let renderer = null;
let controls = null;
let figureResizeObserver = null;
let figureShadow = null;

let bodyModel = null;

const currentObjects = {
    hair: null,
    girlHair: null,
    top: null,
    girlTop: null,
    bottom: null,
    girlBottom: null,
    outfit: null,
    pants: null,
    shoes: null,
    keychainHair: null,
    keychainHat: null
};


const loadTokens = {
    body: 0,
    hair: 0,
    girlHair: 0,
    top: 0,
    girlTop: 0,
    bottom: 0,
    girlBottom: 0,
    outfit: 0,
    pants: 0,
    shoes: 0,
    keychainHair: 0,
    keychainHat: 0
};


let renderToken = 0;


// basic helpers

function getActiveState() {

    if (!state.currentCategory) {
        return null;
    }

    return state[state.currentCategory];

}


function getHironoMode() {

    const hironoState =
        state.hirono;


    return hironoState.mode || "standee";

}


function getActiveSlotOrder() {

    if (state.currentCategory === "hirono") {
        if (getHironoMode() === "headKeychain") {
            return SLOT_ORDER.hironoHeadKeychain;
        }

        return getHironoMode() === "keychain"
            ? SLOT_ORDER.hironoKeychain
            : SLOT_ORDER.hironoStandee;
    }

    if (state.currentCategory === "funko") {
        return isFunkoGirlModel(
            state.funko.model
        )
            ? SLOT_ORDER.funkoGirl
            : SLOT_ORDER.funkoBoy;
    }

    if (state.currentCategory === "chibi") {
        if (state.chibi.productType === "keychain") {
            return isChibiGirlModel(state.chibi.model)
                ? SLOT_ORDER.chibiGirlKeychain
                : SLOT_ORDER.chibiBoyKeychain;
        }

        return isChibiGirlModel(
            state.chibi.model
        )
            ? SLOT_ORDER.chibiGirl
            : SLOT_ORDER.chibiBoy;
    }


    return SLOT_ORDER[state.currentCategory] || [];

}


function getSlotColorSlot(slot) {

    const category =
        state.currentCategory;

    if (
        category === "funko" &&
        isFunkoGirlModel(state.funko.model)
    ) {
        if (slot === "hairColor") {
            return "girlHair";
        }

        if (slot === "topColor") {
            return "girlTop";
        }

        if (slot === "bottomColor") {
            return "girlBottom";
        }
    }

    // Funko bottom may sariling pantsColor handling (applyBottomPartColors)
    if (slot === "bottom" && category !== "funko") {
        return "bottomColor";
    }

    return ACCESSORY_COLOR_SLOT[slot] || null;

}


function isFunkoGirlModel(modelItem) {

    return Boolean(
        modelItem &&
        /girl/i.test(
            String(
                modelItem.name || ""
            )
        )
    );

}


function isChibiGirlModel(modelItem) {

    return Boolean(
        modelItem &&
        /girl/i.test(
            String(
                modelItem.name || ""
            )
        )
    );

}


function getProductDetailsKey() {

    if (state.currentCategory === "hirono") {
        if (getHironoMode() === "headKeychain") {
            return "hironoHeadKeychain";
        }

        return getHironoMode() === "keychain"
            ? "hironoKeychain"
            : "hironoStandee";
    }

    if (state.currentCategory === "funko") {
        return isFunkoGirlModel(state.funko.model)
            ? "funkoGirl"
            : "funkoBoy";
    }

    if (state.currentCategory === "chibi") {
        if (state.chibi.productType === "keychain") {
            return isChibiGirlModel(state.chibi.model)
                ? "chibiGirlKeychain"
                : "chibiBoyKeychain";
        }

        return isChibiGirlModel(state.chibi.model)
            ? "chibiGirl"
            : "chibiBoy";
    }

    return "";
}


function getProductDetailsConfig() {
    return PRODUCT_DETAIL_CONFIGS[getProductDetailsKey()] || null;
}


function getProductSizePrice() {

    const prices =
        PRODUCT_SIZE_PRICES[getProductDetailsKey()] || {};

    return prices[productDetails.size] || 0;

}


function getProductDetailsAddon() {

    const config = getProductDetailsConfig();

    if (!config) {
        return 0;
    }

    const selectedBox =
        config.boxes.find(box => box.id === productDetails.box);

    if (config.hirono) {
        const blindBoxPrice = productDetails.blindBoxSelected
            ? (productDetails.blindBox === "set" ? 350 : 150)
            : 0;
        const addonPrices = {
            tearPaper: 50,
            pouch: 50,
            digitalArt: 150
        };

        return (productDetails.figureName.trim() ? 50 : 0) +
            blindBoxPrice +
            productDetails.hironoAddons.reduce(
                (total, addon) => total + (addonPrices[addon] || 0),
                0
            );
    }

    return (productDetails.figureName.trim() ? 50 : 0) +
        (selectedBox ? selectedBox.price : 0);
}


function saveProductDetails() {
    saveCustomizationSnapshot(false, false);
    updateSelectedItemsUI();
    updatePriceDisplay();
}


function renderSizeSection() {

    const sizeSection =
        document.getElementById("figureSizeSection");

    if (!sizeSection || !sizeChoiceGrid) {
        return;
    }

    const activeState = getActiveState();
    const config = getProductDetailsConfig();
    const productKey = getProductDetailsKey();

    if (!activeState || !config) {
        return;
    }

    const requiredSlots = state.currentCategory === "chibi"
        ? ["productType", "model", "skin"]
        : state.currentCategory === "hirono"
            ? ["mode", "model", "skin"]
            : ["model", "skin"];

    if (!requiredSlots.every(slot => Boolean(activeState[slot]))) {
        return;
    }

    if (productDetails.productKey !== productKey) {
        productDetails = {
            productKey,
            size: "",
            sizeSelected: false,
            figureName: "",
            box: "",
            boxSelected: false,
            boxName: "",
            boxNumber: "",
            boxColor: "",
            blindBox: "regular",
            blindBoxSelected: false,
            hironoAddons: [],
            boxDesign: "checkered",
            boxDesignSelected: false,
            boxNickname: "",
            boxLetter: "",
            boxDateYmd: ""
        };
    }

    // Keep size empty until the customer explicitly chooses one. The first
    // size must not be treated as an automatic selection.
    if (productDetails.size && !config.sizes.includes(productDetails.size)) {
        productDetails.size = "";
    }

    sizeChoiceGrid.innerHTML = config.sizes.length
        ? config.sizes.map(size => `
        <button type="button" class="detail-choice-card${productDetails.sizeSelected && productDetails.size === size ? " selected" : ""}" data-size="${size}">
            <strong>${size.replace(" inches", "\"")}</strong>
            <span>PHP ${PRODUCT_SIZE_PRICES[productKey]?.[size] || 0}</span>
        </button>
    `).join("")
        : `<p class="detail-hint">No size requirement for this product type.</p>`;

    sizeChoiceGrid.querySelectorAll("[data-size]").forEach(button => {
        button.addEventListener("click", () => {
            productDetails.size = button.dataset.size;
            productDetails.sizeSelected = true;
            renderSizeSection();
            saveProductDetails();

            // saveProductDetails() alone doesn't recalculate Next's disabled state —
            // need updateSectionVisibility() too, or Next stays disabled after picking Size.
            updateSectionVisibility();
        });
    });

}


function renderProductDetailsPanel() {

    if (!productDetailsPanel || !boxChoiceGrid) {
        return;
    }

    if (navigationSlot !== "productDetails") {
        productDetailsPanel.hidden = true;
        return;
    }

    const activeState = getActiveState();
    const config = getProductDetailsConfig();
    const productKey = getProductDetailsKey();

    if (!activeState || !activeState.model || !activeState.skin || !config) {
        productDetailsPanel.hidden = true;
        return;
    }

    const requiredSlots = state.currentCategory === "chibi"
        ? ["productType", "model", "skin"]
        : state.currentCategory === "hirono"
            ? ["mode", "model", "skin"]
            : ["model", "skin"];

    if (!requiredSlots.every(slot => Boolean(activeState[slot]))) {
        productDetailsPanel.hidden = true;
        return;
    }

    productDetailsPanel.hidden = false;

    if (productDetails.productKey !== productKey) {
        productDetails = {
            productKey,
            size: "",
            sizeSelected: false,
            figureName: "",
            box: "",
            boxSelected: false,
            boxName: "",
            boxNumber: "",
            boxColor: "",
            blindBox: "regular",
            blindBoxSelected: false,
            hironoAddons: [],
            boxDesign: "checkered",
            boxDesignSelected: false,
            boxNickname: "",
            boxLetter: "",
            boxDateYmd: ""
        };
    }

    if (productDetails.box && !config.boxes.some(box => box.id === productDetails.box)) {
        productDetails.box = "";
    }

    // Walang box (Chibi): itago BUONG panel at linisin ang inline styles/.box-mode
    // na posibleng naiwan galing sa Funko/Hirono na naunang na-render.
    if (!config.hirono && !config.boxes.length) {
        productDetailsPanel.hidden = true;
        productDetailsPanel.style.display = "none";
        productDetailsPanel.classList.remove("box-mode");
        productDetailsPanel.style.background = "";
        productDetailsPanel.style.border = "";
        productDetailsPanel.style.padding = "";
        productDetailsPanel.style.borderRadius = "";
        return;
    }

    productDetailsPanel.style.display = "";

    const title = document.getElementById("productDetailsTitle");
    const description = document.getElementById("productDetailsDescription");
    const detailsSkinSwatch = document.getElementById("detailsSkinSwatch");

    if (title) title.textContent = config.title;
    if (description) description.textContent = config.description;

    if (detailsSkinSwatch) {
        const skinColor = activeState.skin?.color || "";
        detailsSkinSwatch.hidden = !skinColor;
        detailsSkinSwatch.style.backgroundColor = skinColor || "transparent";
    }

    boxChoiceGrid.innerHTML = config.boxes.map(box => `
        <button type="button" class="detail-choice-card box-choice-card${productDetails.boxSelected && productDetails.box === box.id ? " selected" : ""}" data-box="${box.id}">
            <span class="image-check">✓</span>
            <div class="detail-card-swatch" style="background:${box.swatch || "linear-gradient(145deg,#ffe0ef,#e8ddff)"};"></div>
            <strong>${box.label}</strong>
            <span>${box.price ? `PHP ${box.price}` : "No additional fee"}</span>
        </button>
    `).join("");

    const figureNameField = document.getElementById("figureNameField");
    // Walang "Figure Name" sa Dress Up — laging nakatago (at walang ₱50 fee)
    if (figureNameField) {
        figureNameField.hidden = true;
    }

    const hasBoxOptions = Boolean(config.hirono) || config.boxes.length > 0;

    // Funko/Hirono: "BOX / ADD-ONS" header lang, itago ang produkto-specific
    // title/description at card bg/border via .box-mode. Chibi: same as before.
    // NOTE: gamit ang existing outer "productDetailsPanel" var — huwag i-redeclare
    // ng const dito, sisira ito sa mga naunang reference sa itaas ng function.
    if (productDetailsPanel) {
        productDetailsPanel.classList.toggle("box-mode", hasBoxOptions);
        // Inline styles para hindi umasa sa commission.css (kung na-cache
        // ang CSS, dito pa rin mawawala ang pink background at border).
        productDetailsPanel.style.background = hasBoxOptions ? "none" : "";
        productDetailsPanel.style.border = hasBoxOptions ? "none" : "";
        productDetailsPanel.style.padding = hasBoxOptions ? "0" : "";
        productDetailsPanel.style.borderRadius = hasBoxOptions ? "0" : "";
    }

    // NOTE: gaya ng design-step-jump, may CSS rules dito na mas mataas ang
    // specificity kaysa sa default [hidden] ng browser, kaya inline display
    // ang ginagamit para siguradong matago talaga.
    [title, description].forEach(element => {
        if (!element) return;
        element.hidden = hasBoxOptions;
        element.style.display = hasBoxOptions ? "none" : "";
    });

    const productDetailsKicker = document.getElementById("productDetailsKicker");
    if (productDetailsKicker) {
        productDetailsKicker.textContent = hasBoxOptions
            ? "BOX / ADD-ONS"
            : "FIGURE DETAILS";
        // Parehong laki/kulay/font ang dalawang label na ito, hindi depende sa hasBoxOptions
        productDetailsKicker.style.display = "block";
        productDetailsKicker.style.fontSize = "20px";
        productDetailsKicker.style.letterSpacing = "1.5px";
        productDetailsKicker.style.lineHeight = "1.25";
        productDetailsKicker.style.margin = "0";
        // Inline color para hindi umasa sa cache ng CSS — may generic
        // rule (".section-title span") na gumagawa nito na gray kung
        // wala ito.
        productDetailsKicker.style.color = "#bd5c8d";
    }

    const boxChoiceGroup = document.getElementById("boxChoiceGroup");
    if (boxChoiceGroup) {
        boxChoiceGroup.hidden = config.hirono || !config.boxes.length;
        const boxLabel = boxChoiceGroup.querySelector(".detail-label");
        if (boxLabel) {
            boxLabel.textContent = config.boxLabel || "Box";
        }
    }

    const boxDetailFields = document.getElementById("boxDetailFields");
    const selectedBox = config.boxes.find(box => box.id === productDetails.box);
    const canEditFunkoBoxDetails = Boolean(selectedBox?.details);
    if (boxDetailFields) {
        // Chibi (walang box): itago ang buong fields. Hindi sapat ang .hidden lang —
        // may CSS rule (display:grid) na mas mataas ang specificity, kaya inline
        // display ang ginagamit para talagang matago.
        const hideBoxDetailFields = config.hirono || !config.boxes.length;
        boxDetailFields.hidden = hideBoxDetailFields;
        boxDetailFields.style.display = hideBoxDetailFields ? "none" : "";
    }

    const boxLockedHint = document.getElementById("boxLockedHint");
    if (boxLockedHint) {
        boxLockedHint.hidden = config.hirono || !config.boxes.length || canEditFunkoBoxDetails;
    }

    [boxNameInput, boxNumberInput, boxColorInput].forEach(input => {
        if (input) input.disabled = !canEditFunkoBoxDetails;
    });

    const hironoFields = document.getElementById("hironoDetailFields");
    const hironoBlindBoxGrid = document.getElementById("hironoBlindBoxGrid");
    const hironoAddonGrid = document.getElementById("hironoAddonGrid");
    const hironoBoxDesignGrid = document.getElementById("hironoBoxDesignGrid");
    const hironoBoxFields = document.getElementById("hironoBoxFields");

    if (hironoFields) hironoFields.hidden = !config.hirono;

    if (config.hirono && hironoBlindBoxGrid && hironoAddonGrid && hironoBoxDesignGrid && hironoBoxFields) {
        hironoBlindBoxGrid.innerHTML = [
            { id: "regular", label: "Regular Blind Box", price: 150, image: "Hirono.jpg" },
            { id: "set", label: "Blind Box Set", price: 350, image: "blindboxset.jpg" }
        ].map(({ id, label, price, image }) => `
            <button type="button" class="detail-choice-card box-choice-card${productDetails.blindBoxSelected && productDetails.blindBox === id ? " selected" : ""}" data-blind-box="${id}">
                <span class="image-check">✓</span>
                <img class="detail-card-image" src="../Image/${image}" alt="${label}">
                <strong>${label}</strong>
                <span>PHP ${price}</span>
            </button>
        `).join("");

        const addons = [
            { id: "tearPaper", label: "Tear Blind Paper", price: 50 },
            { id: "pouch", label: "Pouch", price: 50 },
            { id: "digitalArt", label: "Digital Art (Soft Copy) w/ Photo Card", price: 150 }
        ].map(addon => ({ ...addon, image: "Hirono.jpg" }));

        hironoAddonGrid.innerHTML = addons.map(({ id, label, price, image }) => `
            <button type="button" class="detail-choice-card box-choice-card${productDetails.hironoAddons.includes(id) ? " selected" : ""}" data-addon="${id}">
                <span class="image-check">✓</span>
                <img class="detail-card-image" src="../Image/${image}" alt="${label}">
                <strong>${label}</strong>
                <span>PHP ${price}</span>
            </button>
        `).join("");

        hironoBoxDesignGrid.innerHTML = [
            { id: "checkered", label: "Checkered", image: "checkered.jpg" },
            { id: "peek", label: "Hirono Peek", image: "peek.jpg" }
        ].map(({ id, label, image }) => `
            <button type="button" class="detail-choice-card box-choice-card${productDetails.boxDesignSelected && productDetails.boxDesign === id ? " selected" : ""}" data-box-design="${id}">
                <span class="image-check">✓</span>
                <img class="detail-card-image" src="../Image/${image}" alt="${label}">
                <strong>${label}</strong>
                <span>Choose box design</span>
            </button>
        `).join("");

        const colorField = productDetails.boxDesign === "peek"
            ? `<select class="product-detail-input" data-detail-field="boxColor"><option value="">Select Box Color</option><option value="Wood">Wood</option><option value="Black & White">Black &amp; White</option></select>`
            : `<input class="product-detail-input" data-detail-field="boxColor" value="${productDetails.boxColor}" maxlength="30" placeholder="Example: Cream White">`;

        const formattedBoxDate = formatMonthDay(productDetails.boxDateYmd);
        const [selectedMonth = "", selectedDay = ""] = formattedBoxDate.split("/");
        const monthOptions = [
            ["01", "January"], ["02", "February"], ["03", "March"],
            ["04", "April"], ["05", "May"], ["06", "June"],
            ["07", "July"], ["08", "August"], ["09", "September"],
            ["10", "October"], ["11", "November"], ["12", "December"]
        ].map(([value, label]) => `<option value="${value}"${selectedMonth === value ? " selected" : ""}>${label}</option>`).join("");
        const dayOptions = Array.from({ length: 31 }, (_, index) => {
            const value = String(index + 1).padStart(2, "0");
            return `<option value="${value}"${selectedDay === value ? " selected" : ""}>${index + 1}</option>`;
        }).join("");

        hironoBoxFields.innerHTML = `
            <label><span class="detail-label">Box Color</span>${colorField}</label>
            <label><span class="detail-label">Nickname</span><input class="product-detail-input" data-detail-field="boxNickname" value="${productDetails.boxNickname}" maxlength="30" placeholder="Example: Bubbles"></label>
            <label><span class="detail-label">Letter (Short Love Letter)</span><textarea class="product-detail-input" data-detail-field="boxLetter" rows="4" placeholder="Write a short love letter/message here...">${productDetails.boxLetter}</textarea></label>
            <div class="product-detail-date-row">
                <label><span class="detail-label">Month</span><select class="product-detail-input" id="boxDateMonth"><option value="">Choose month</option>${monthOptions}</select></label>
                <label><span class="detail-label">Day</span><select class="product-detail-input" id="boxDateDay"><option value="">Choose day</option>${dayOptions}</select></label>
            </div>
        `;

        hironoAddonGrid.querySelectorAll("[data-addon]").forEach(button => {
            button.addEventListener("click", () => {
                const addon = button.dataset.addon;
                productDetails.hironoAddons = productDetails.hironoAddons.includes(addon)
                    ? productDetails.hironoAddons.filter(item => item !== addon)
                    : [...productDetails.hironoAddons, addon];
                renderProductDetailsPanel();
                saveProductDetails();
            });
        });

            hironoBlindBoxGrid.querySelectorAll("[data-blind-box]").forEach(button => {
            button.addEventListener("click", () => {
                productDetails.blindBox = button.dataset.blindBox;
                productDetails.blindBoxSelected = true;
                renderProductDetailsPanel();
                saveProductDetails();
            });
        });

        hironoBoxDesignGrid.querySelectorAll("[data-box-design]").forEach(button => {
            button.addEventListener("click", () => {
                productDetails.boxDesign = button.dataset.boxDesign;
                productDetails.boxDesignSelected = true;
                if (productDetails.boxDesign === "peek" && !["Wood", "Black & White"].includes(productDetails.boxColor)) {
                    productDetails.boxColor = "";
                }
                renderProductDetailsPanel();
                saveProductDetails();
            });
        });
    }

        boxChoiceGrid.querySelectorAll("[data-box]").forEach(button => {
        button.addEventListener("click", () => {
            const boxId = button.dataset.box;

            // Toggle: pag-click ulit sa parehong selected box, i-unselect ito
            // (kapareho ng toggle behavior ng Optional Extras).
            const isAlreadySelected =
                productDetails.boxSelected &&
                productDetails.box === boxId;

            if (isAlreadySelected) {
                productDetails.box = "";
                productDetails.boxSelected = false;
            } else {
                productDetails.box = boxId;
                productDetails.boxSelected = true;
            }

            renderProductDetailsPanel();
            saveProductDetails();
        });
    });

    if (figureNameInput) figureNameInput.value = productDetails.figureName;
    if (boxNameInput) boxNameInput.value = productDetails.boxName;
    if (boxNumberInput) boxNumberInput.value = productDetails.boxNumber;
    if (boxColorInput) boxColorInput.value = productDetails.boxColor;

        productDetailsPanel.querySelectorAll("[data-detail-field]").forEach(input => {
        input.value = productDetails[input.dataset.detailField] || "";
        input.oninput = () => {
            productDetails[input.dataset.detailField] = input.value;
            saveProductDetails();
        };
        input.onchange = input.oninput;
    });

    const detailsPrice = document.getElementById("productDetailsPrice");
    if (detailsPrice) {
        detailsPrice.textContent = `PHP ${getProductDetailsAddon()}`;
    }
}


function getSectionId(category, slot) {

    return sectionIds[category] && sectionIds[category][slot];

}


function setVisibleById(id, visible) {

    const element =
        document.getElementById(id);


    if (element) {
        element.hidden = !visible;
    }

}


function setFigurePromptVisible(visible) {

    if (figureArea) {
        figureArea.dataset.promptVisible = visible ? "true" : "false";
    }

}


function clearSelectedClasses(selector) {

    document.querySelectorAll(selector).forEach(
        function(card) {
            card.classList.remove("selected");
        }
    );

}


function markSelectedCard(card) {

    const figure =
        card.dataset.figure;


    const slot =
        card.dataset.slot;


    clearSelectedClasses(
        `[data-figure="${figure}"][data-slot="${slot}"]`
    );


    card.classList.add(
        "selected"
    );

}


function disposeObject3D(object3D) {

    if (!object3D) {
        return;
    }


    object3D.traverse(
        function(node) {

            if (!node.isMesh) {
                return;
            }


            if (node.geometry) {
                node.geometry.dispose();
            }


            if (!node.material) {
                return;
            }


            const materials =
                Array.isArray(
                    node.material
                )
                    ? node.material
                    : [node.material];


            materials.forEach(
                function(material) {

                    if (material.map) {
                        material.map.dispose();
                    }

                    if (material.normalMap) {
                        material.normalMap.dispose();
                    }

                    if (material.roughnessMap) {
                        material.roughnessMap.dispose();
                    }

                    if (material.metalnessMap) {
                        material.metalnessMap.dispose();
                    }

                    material.dispose();

                }
            );

        }
    );

}


function removeFromScene(object3D) {

    if (!object3D) {
        return;
    }


    if (object3D.parent) {
        object3D.parent.remove(
            object3D
        );
    }
    else if (scene) {
        scene.remove(
            object3D
        );
    }


    disposeObject3D(
        object3D
    );

}


function clearCurrentScene() {

    removeFromScene(
        bodyModel
    );

    bodyModel = null;


    if (figureShadow) {
        figureShadow.visible = false;
    }


    Object.keys(
        currentObjects
    ).forEach(
        function(slot) {

            removeFromScene(
                currentObjects[slot]
            );

            currentObjects[slot] = null;

        }
    );

}


function configureModel(model) {

    model.traverse(
        function(node) {

            if (!node.isMesh) {
                return;
            }

            // Preserve the GLB's original acrylic stand appearance.
            if (String(node.name || "").toLowerCase().includes("acrylic_stand")) {
                return;
            }


            node.castShadow = true;
            node.receiveShadow = true;


            if (!node.material) {
                return;
            }


            const materials =
                Array.isArray(
                    node.material
                )
                    ? node.material
                    : [node.material];


            materials.forEach(
                function(material) {

                    material.transparent = false;
                    material.needsUpdate = true;

                }
            );

        }
    );

}


function hasRenderableMeshes(object3D) {

    if (!object3D) {
        return false;
    }


    let meshCount = 0;


    object3D.traverse(
        function(node) {
            if (node.isMesh) {
                meshCount += 1;
            }
        }
    );


    return meshCount > 0;

}


function shouldSkipMaterial(material, skipFragments) {

    if (!material || !skipFragments.length) {
        return false;
    }


    const materialName =
        String(
            material.name || ""
        ).toLowerCase();


    return skipFragments.some(
        function(fragment) {
            return materialName.includes(
                fragment
            );
        }
    );

}


function clearMaterialTextureMaps(material) {

    if (!material) {
        return;
    }


    [
        "map",
        "alphaMap",
        "aoMap",
        "bumpMap",
        "displacementMap",
        "emissiveMap",
        "envMap",
        "lightMap",
        "metalnessMap",
        "normalMap",
        "roughnessMap",
        "specularMap",
        "clearcoatMap",
        "clearcoatNormalMap",
        "clearcoatRoughnessMap",
        "sheenColorMap",
        "sheenRoughnessMap",
        "iridescenceMap",
        "iridescenceThicknessMap",
        "transmissionMap",
        "thicknessMap"
    ].forEach(
        function(mapKey) {
            if (material[mapKey]) {
                material[mapKey] = null;
            }
        }
    );

}


function applyColorToModel(model, color, options) {

    if (!model) {
        return false;
    }


    const settings =
        options || {};


    const targetColor =
        new THREE.Color(
            color
        );


    const skipFragments =
        Array.isArray(
            settings.skipMaterialFragments
        )
            ? settings.skipMaterialFragments.map(
                function(fragment) {
                    return String(fragment).toLowerCase();
                }
            )
            : [
                "design"
            ];

    const skipNodeFragments = Array.isArray(settings.skipNodeFragments)
        ? settings.skipNodeFragments.map(fragment => String(fragment).toLowerCase())
        : [];


    let changed =
        false;


    model.traverse(
        function(node) {

            const nodeName = String(node.name || "").toLowerCase();
            if (skipNodeFragments.some(fragment => nodeName.includes(fragment))) {
                return;
            }

            if (
                !node.isMesh ||
                !node.material
            ) {
                return;
            }


            const materials =
                Array.isArray(
                    node.material
                )
                    ? node.material
                    : [node.material];


            const nextMaterials =
                materials.map(
                    function(material) {

                        if (shouldSkipMaterial(
                            material,
                            skipFragments
                        )) {
                            return material;
                        }


                        const newMaterial =
                            material.clone();

                        if (settings.clearTextureMaps) {
                            clearMaterialTextureMaps(
                                newMaterial
                            );
                        }

                        if (newMaterial.color) {
                            newMaterial.color.copy(
                                targetColor
                            );
                        }
                        newMaterial.needsUpdate = true;


                        changed = true;


                        return newMaterial;

                    }
                );


            node.material =
                Array.isArray(
                    node.material
                )
                    ? nextMaterials
                    : nextMaterials[0];

        }
    );


    return changed;

}


function applyColorToMeshList(meshes, color, options) {

    if (!Array.isArray(meshes) || !meshes.length) {
        return false;
    }


    const settings =
        options || {};


    const targetColor =
        new THREE.Color(
            color
        );


    const skipFragments =
        Array.isArray(
            settings.skipMaterialFragments
        )
            ? settings.skipMaterialFragments.map(
                function(fragment) {
                    return String(fragment).toLowerCase();
                }
            )
            : [
                "design"
            ];


    let changed =
        false;


    meshes.forEach(
        function(mesh) {

            if (!mesh || !mesh.material) {
                return;
            }


            const materials =
                Array.isArray(
                    mesh.material
                )
                    ? mesh.material
                    : [mesh.material];


            const nextMaterials =
                materials.map(
                    function(material) {

                        if (shouldSkipMaterial(
                            material,
                            skipFragments
                        )) {
                            return material;
                        }


                        const newMaterial =
                            material.clone();

                        if (settings.clearTextureMaps) {
                            clearMaterialTextureMaps(
                                newMaterial
                            );
                        }

                        if (newMaterial.color) {
                            newMaterial.color.copy(
                                targetColor
                            );
                        }
                        newMaterial.needsUpdate = true;


                        changed = true;


                        return newMaterial;

                    }
                );


            mesh.material =
                Array.isArray(
                    mesh.material
                )
                    ? nextMaterials
                    : nextMaterials[0];

        }
    );


    return changed;

}


function isShoeLikeMesh(mesh) {

    if (!mesh) {
        return false;
    }


    const searchableText =
        [
            mesh.name,
            mesh.parent && mesh.parent.name,
            mesh.material &&
                !Array.isArray(mesh.material) &&
                mesh.material.name
        ].filter(Boolean).join(" ").toLowerCase();


    return /shoe|sneaker|boot|foot|sole/.test(
        searchableText
    );

}


function getBottomModelParts(model) {

    const meshes = [];


    if (!model) {
        return {
            pants: [],
            shoes: []
        };
    }


    model.traverse(
        function(node) {
            if (node.isMesh) {
                meshes.push(
                    node
                );
            }
        }
    );


    if (!meshes.length) {
        return {
            pants: [],
            shoes: []
        };
    }


    const indexedMeshes =
        meshes.map(
            function(mesh, index) {
                const box =
                    new THREE.Box3().setFromObject(
                        mesh
                    );


                return {
                    mesh,
                    index,
                    centerY: box.getCenter(
                        new THREE.Vector3()
                    ).y
                };
            }
        ).sort(
            function(a, b) {
                return b.centerY - a.centerY;
            }
        );


    const shoeTaggedMeshes =
        indexedMeshes.filter(
            function(item) {
                return isShoeLikeMesh(
                    item.mesh
                );
            }
        );


    const pantsTaggedMeshes =
        indexedMeshes.filter(
            function(item) {
                return !isShoeLikeMesh(
                    item.mesh
                );
            }
        );


    if (
        shoeTaggedMeshes.length &&
        pantsTaggedMeshes.length
    ) {
        return {
            pants: pantsTaggedMeshes.map(
                function(item) {
                    return item.mesh;
                }
            ),
            shoes: shoeTaggedMeshes.map(
                function(item) {
                    return item.mesh;
                }
            )
        };
    }


    let splitIndex = 1;
    let largestGap = 0;


    for (let index = 0; index < indexedMeshes.length - 1; index += 1) {
        const currentMesh =
            indexedMeshes[index];
        const nextMesh =
            indexedMeshes[index + 1];
        const gap =
            currentMesh.centerY - nextMesh.centerY;


        if (gap > largestGap) {
            largestGap = gap;
            splitIndex = index + 1;
        }
    }


    if (largestGap <= 0) {
        splitIndex = Math.max(
            1,
            Math.ceil(
                indexedMeshes.length / 2
            )
        );
    }
    else {
        splitIndex = Math.min(
            Math.max(
                1,
                splitIndex
            ),
            indexedMeshes.length - 1
        );
    }


    return {
        pants: indexedMeshes
            .slice(
                0,
                splitIndex
            )
            .map(
                function(item) {
                    return item.mesh;
                }
            ),
        shoes: indexedMeshes
            .slice(
                splitIndex
            )
            .map(
                function(item) {
                    return item.mesh;
                }
            )
    };

}


function applyBottomPartColors(model, pantsColor) {

    const parts =
        getBottomModelParts(
            model
        );


    if (pantsColor) {
        applyColorToMeshList(
            parts.pants,
            pantsColor,
            {
                clearTextureMaps: true,
                skipMaterialFragments: [
                    "design",
                    "logo",
                    "print"
                ]
            }
        );
    }
}


function loadGLTF(path) {

    if (!path) {
        const error =
            new Error("Model path is missing.");

        console.error(
            "[Figurify 3D] Model path is missing.",
            error
        );

        return Promise.reject(
            error
        );
    }

    return new Promise(
        function(resolve, reject) {

            loader.load(
                path,
                function(gltf) {
                    resolve(
                        gltf.scene
                    );
                },
                undefined,
                function(error) {
                    console.error(
                        `[Figurify 3D] Failed to load: ${path}`,
                        error
                    );

                    reject(
                        error
                    );
                }
            );

        }
    );

}


function getCurrentItemName(category, slot) {

    const categoryState =
        state[category];


    const item =
        categoryState && categoryState[slot];


    return item ? item.name : null;

}


function getCurrentItemPath(category, slot) {

    const categoryState =
        state[category];


    const item =
        categoryState && categoryState[slot];


    return item ? item.model : null;

}


function getLoadedObject(slot) {

    return currentObjects[slot] || null;

}


// Soft contact shadow sa ilalim ng figure (radial gradient plane, hindi
// nakadepende sa lights kaya pare-pareho sa lahat ng models).
function updateFigureShadow(box, size, center) {

    if (!scene) {
        return;
    }


    if (!figureShadow) {
        const shadowCanvas =
            document.createElement("canvas");

        shadowCanvas.width = 256;
        shadowCanvas.height = 256;

        const context =
            shadowCanvas.getContext("2d");

        const gradient =
            context.createRadialGradient(
                128, 128, 0,
                128, 128, 128
            );

        gradient.addColorStop(0, "rgba(0, 0, 0, 0.45)");
        gradient.addColorStop(0.5, "rgba(0, 0, 0, 0.2)");
        gradient.addColorStop(1, "rgba(0, 0, 0, 0)");

        context.fillStyle = gradient;
        context.fillRect(0, 0, 256, 256);

        figureShadow =
            new THREE.Mesh(
                new THREE.PlaneGeometry(1, 1),
                new THREE.MeshBasicMaterial(
                    {
                        map: new THREE.CanvasTexture(shadowCanvas),
                        transparent: true,
                        depthWrite: false
                    }
                )
            );

        figureShadow.rotation.x = -Math.PI / 2;
        figureShadow.renderOrder = -1;

        scene.add(
            figureShadow
        );
    }


    const footprint =
        Math.max(
            size.x,
            size.z
        ) * 1.3;


    figureShadow.scale.set(
        footprint,
        footprint * 0.75,
        1
    );

    figureShadow.position.set(
        center.x,
        box.min.y + 0.001,
        center.z
    );

    figureShadow.visible = true;

}


function frameFigure() {

    if (!bodyModel || !camera || !controls) {
        return;
    }


    const box =
        new THREE.Box3();


    box.expandByObject(
        bodyModel
    );


    Object.values(
        currentObjects
    ).forEach(
        function(object3D) {
            if (object3D) {
                box.expandByObject(
                    object3D
                );
            }
        }
    );


    if (box.isEmpty()) {
        return;
    }


    const size =
        box.getSize(
            new THREE.Vector3()
        );


    const center =
        box.getCenter(
            new THREE.Vector3()
        );


    const maxSize =
        Math.max(
            size.x,
            size.y,
            size.z
        );


    updateFigureShadow(
        box,
        size,
        center
    );


    const distance =
        maxSize /
        (
            2 *
            Math.tan(
                THREE.MathUtils.degToRad(
                    camera.fov / 2
                )
            )
        );


    camera.position.set(
        center.x,
        center.y,
        center.z + distance * 1.35
    );

    camera.lookAt(
        center
    );


    controls.target.copy(
        center
    );

    controls.update();

}


function resize3D() {

    if (
        !renderer ||
        !camera ||
        !figureArea
    ) {
        return;
    }


    const width =
        figureArea.clientWidth;


    const height =
        figureArea.clientHeight;


    if (
        width <= 0 ||
        height <= 0
    ) {
        return;
    }


    renderer.setSize(
        width,
        height,
        false
    );


    camera.aspect =
        width / height;


    camera.updateProjectionMatrix();

}


function getCurrentOrderTotal(activeState) {

    const rushFee = getCommissionRushFee();

    if (!activeState || !activeState.model) {
        return rushFee;
    }

    return getActiveStateTotal(activeState) +
        getProductSizePrice() +
        getProductDetailsAddon() +
        rushFee;

}


function updatePriceDisplay() {

    if (!dressUpTotalPrice) {
        return;
    }


    const activeState =
        getActiveState();


    const total =
        getCurrentOrderTotal(
            activeState
        );

    dressUpTotalPrice.textContent =
        `₱${total}`;

    refreshOptionPriceLabels();

    renderPreviewSummary();

}


function buildFigureDetailRows(activeState, categoryValue) {

    const rows = [];
    // "group" = anong column sa finalsummary.php: "figure" (default) o "box".
    // Iisang listahan lang sa commission.php sidebar, kaya hindi ito ginagamit doon.
    const addRow = (header, value, price = null, group = "figure") => {
        if (!header) {
            return;
        }

        rows.push({ header, value: value || "Not selected", price, group });
    };

    // Bawat row (Figure Style, Product Type, Gender, Skin Tone, Size, Hair, Top,
    // Bottom, Shoes) lumalabas lang kapag may aktwal na napili na, hindi "Not selected" placeholder.
    if (categoryValue) {
        addRow("Figure Style", categoryValue);
    }

    // Personalization fields (Box Name/Number/Color, atbp.) ipinapakita sa Box/Add-ons
    // column kahit walang presyo. Figure Name may ₱50 fee kaya nasa Figure Details pa rin.
    if (
        (state.currentCategory === "funko" || state.currentCategory === "chibi") &&
        productDetails.figureName.trim()
    ) {
        addRow("Figure Name", productDetails.figureName.trim(), 50);
    }

    if (state.currentCategory === "funko") {
        if (productDetails.boxName.trim()) {
            addRow("Box Name", productDetails.boxName.trim(), null, "box");
        }
        if (productDetails.boxNumber.trim()) {
            addRow("Box Number", productDetails.boxNumber.trim(), null, "box");
        }
        if (productDetails.boxColor.trim()) {
            addRow("Box Color", productDetails.boxColor.trim(), null, "box");
        }
    }

    // Sariling label bawat slot (kasama Gender/Skin). *Color slots (Hair Color,
    // Top Color, atbp.) tinatanggal — pangalan ng item na lang ang nagsasabi ano napili.
    if (state.currentCategory && activeState) {
        const slotLabels = {
            model: "Gender",
            skin: "Skin Tone",
            hair: "Hair",
            girlHair: "Hair",
            hairColor: "Hair Color",
            girlHairColor: "Hair Color",
            top: "Top",
            girlTop: "Top",
            outfit: "Top",
            topColor: "Top Color",
            girlTopColor: "Top Color",
            outfitColor: "Top Color",
            bottom: "Bottom",
            girlBottom: "Bottom",
            pants: "Bottom",
            bottomColor: "Pants Color",
            girlBottomColor: "Pants Color",
            pantsColor: "Pants Color",
            shoes: "Shoes",
            shoesColor: "Shoes Color",
            keychainHair: "Hirono Keychain Hair",
            keychainHairColor: "Hirono Keychain Hair Color",
            keychainHat: "Hat"
        };

        // Product Type row: Hirono/Chibi lang, kaagad pafter Figure Style, bago Gender/Skin.
        // Funko walang Product Type step kaya wala itong row.
        const productTypeLabels = {
            standee: "Full Body Standee",
            keychain: "Full Body Keychain",
            headKeychain: "Head Keychain"
        };

        if (state.currentCategory === "hirono") {
            const productTypeLabel = productTypeLabels[state.hirono.mode];
            if (productTypeLabel) {
                addRow("Product Type", productTypeLabel);
            }
        } else if (state.currentCategory === "chibi") {
            const productTypeLabel = productTypeLabels[state.chibi.productType];
            if (productTypeLabel) {
                addRow("Product Type", productTypeLabel);
            }
        }

        // I-splice ang Size row kaagad after Skin Tone (bago Hair/Top/Bottom/Shoes) para
        // sumunod sa aktwal na order ng steps, sa halip na palaging pinakababa.
        // Walang Size row kung walang Size step ang variant (hal. Hirono Head Keychain).
        const productKey = getProductDetailsKey();
        const knownConfig = PRODUCT_DETAIL_CONFIGS[productKey] || null;
        const sizeStepApplies = !knownConfig || knownConfig.sizes.length > 0;

        let skinRowIndex = -1;

        getActiveSlotOrder()
            .forEach(slot => {

                if (slot.toLowerCase().endsWith("color")) {
                    return;
                }

                const item = activeState[slot];

                if (!item) {
                    return;
                }

                const slotLabel = slotLabels[slot] || getDesignStepLabel(slot);
                const itemPrice = getItemPrice(item);
                addRow(slotLabel, item.name, itemPrice > 0 ? itemPrice : null);

                if (slot === "skin") {
                    skinRowIndex = rows.length - 1;
                }
            });

        if (sizeStepApplies && productDetails.size) {
            const sizePrice = getProductSizePrice();
            const sizeRow = {
                header: "Size",
                value: productDetails.size,
                price: sizePrice || null
            };

            if (skinRowIndex >= 0) {
                rows.splice(skinRowIndex + 1, 0, sizeRow);
            } else {
                rows.push(sizeRow);
            }
        }
    }

    if (state.currentCategory === "hirono") {
        if (productDetails.blindBoxSelected && productDetails.blindBox === "set") {
            addRow("Blind Box Type", "Blind Box Set", 350, "box");
        } else if (productDetails.blindBoxSelected && productDetails.blindBox === "regular") {
            addRow("Blind Box Type", "Regular Blind Box", 150, "box");
        }

        const addonOptions = {
            tearPaper: { label: "Tear Blind Paper", price: 50 },
            pouch: { label: "Pouch", price: 50 },
            digitalArt: { label: "Digital Art (Soft Copy) w/ Photo Card", price: 150 }
        };

        productDetails.hironoAddons.forEach(addon => {
            const selectedAddon = addonOptions[addon];
            if (selectedAddon) {
                addRow("Add-on", selectedAddon.label, selectedAddon.price, "box");
            }
        });

        // Box Design + Hirono personalization fields, makikita sa Box/Add-ons column, walang presyo
        if (productDetails.boxDesignSelected) {
            const boxDesignLabels = {
                checkered: "Checkered",
                peek: "Hirono Peek"
            };
            addRow(
                "Box Design",
                boxDesignLabels[productDetails.boxDesign] || productDetails.boxDesign,
                null,
                "box"
            );
        }

        if (productDetails.boxColor.trim()) {
            addRow("Box Color", productDetails.boxColor.trim(), null, "box");
        }

        if (productDetails.boxNickname.trim()) {
            addRow("Nickname", productDetails.boxNickname.trim(), null, "box");
        }

        if (productDetails.boxLetter.trim()) {
            addRow("Letter", productDetails.boxLetter.trim(), null, "box");
        }

        if (productDetails.boxDateYmd) {
            const formattedDate = formatMonthDay(productDetails.boxDateYmd);
            if (formattedDate) {
                addRow("Date", formattedDate, null, "box");
            }
        }

        const boxDateMonth = document.getElementById("boxDateMonth");
        const boxDateDay = document.getElementById("boxDateDay");
        const updateBoxDate = () => {
            productDetails.boxDateYmd = boxDateMonth?.value && boxDateDay?.value
                ? `${boxDateMonth.value}/${boxDateDay.value}`
                : "";
            saveProductDetails();
        };
        boxDateMonth?.addEventListener("change", updateBoxDate);
        boxDateDay?.addEventListener("change", updateBoxDate);
    }

    const config = getProductDetailsConfig();
    const selectedBox = config?.boxes?.find(box => box.id === productDetails.box);
    if (selectedBox) {
        addRow("Box", selectedBox.label, selectedBox.price, "box");
    }


    return rows;

}


function renderRowsIntoContainer(container, rows, emptyMessage = "No customization selected yet.") {

    if (!container) {
        return;
    }

    // Laging linisin muna ang container bago mag-render, kung hindi dumodoble ang laman
    container.innerHTML = "";

    if (!rows.length) {
        container.innerHTML = `<div class="receipt-empty">${emptyMessage}</div>`;
        return;
    }

    rows.forEach(({ header, value, price }) => {
        const hasPrice = price !== null && price !== undefined && Number(price) > 0;

        const item = document.createElement("div");
        item.className = hasPrice
            ? "figure-summary-item"
            : "figure-summary-item summary-no-price";

        const info = document.createElement("div");
        info.className = "figure-summary-info";

        // Header word muna (bold, sa itaas) — tapos ang pinili sa
        // ibaba, hindi bold.
        const headerElement = document.createElement("div");
        headerElement.className = "figure-summary-name";
        headerElement.textContent = header;
        info.appendChild(headerElement);

        if (value) {
            const valueElement = document.createElement("div");
            valueElement.className = "figure-summary-description";
            valueElement.textContent = value;
            info.appendChild(valueElement);
        }

        item.appendChild(info);

        const priceElement = document.createElement("div");
        priceElement.className = "figure-summary-price";
        priceElement.textContent = hasPrice ? formatMoney(price) : "";
        item.appendChild(priceElement);

        container.appendChild(item);
    });

}


function renderPreviewSummary() {

    const data =
        getCustomizationState();

    const activeState =
        getActiveState();

    // Sarili nang id ("dressUpSummary...") dahil ginagamit na ng Image Submission's
    // Order Summary ang "summaryOrderType"/"summaryBookingDate", at dapat unique ang id sa page.
    const orderType =
        document.getElementById(
            "dressUpSummaryOrderType"
        );

    // Kaparehong "order-type-price" (hal. "₱500" kung Rush Order) sa Image Submission side
    const orderTypePrice =
        document.getElementById(
            "dressUpSummaryOrderTypePrice"
        );

    const bookingDate =
        document.getElementById(
            "dressUpSummaryBookingDate"
        );

    // Kaparehong "Choose a date from the calendar" hint sa Image Submission side —
    // iisa lang ang aktwal na calendar (STEP 01, shared)
    const bookingStatus =
        document.getElementById(
            "dressUpSummaryBookingStatus"
        );

    const receiptItems =
        document.getElementById(
            "summaryReceiptItems"
        );

    // Kung walang currentCategory ngayon, walang Figure Style — hindi babalik sa
    // naka-save na localStorage value (para hindi mukhang may selected agad kahit wala pa)
    const categoryValue =
        state.currentCategory
            ? getFigureLabel(state.currentCategory)
            : "";

    // Laging live ang kinukuha (getCommissionOrderTypeLabel), hindi ang naka-save na
    // localStorage snapshot — yun kasi ang dahilan kung bakit stale minsan ang "⚡ Rush Order" label.
    const orderTypeValue =
        getCommissionOrderTypeLabel();

    const bookingDateValue =
        data?.bookingDateLabel ||
        getCommissionBookingDateLabel();

    if (orderType) {
        orderType.textContent =
            orderTypeValue;
    }

    if (orderTypePrice) {
        orderTypePrice.textContent =
            formatMoney(
                getCommissionRushFee()
            );
    }

    if (bookingDate) {
        bookingDate.textContent =
            bookingDateValue;
    }

    if (bookingStatus) {
        bookingStatus.textContent =
            getCommissionBookingStatusLabel();
    }

    if (!receiptItems) {
        return;
    }

    const rows =
        buildFigureDetailRows(
            activeState,
            categoryValue
        );

    // "summaryBoxItems" nasa finalsummary.php lang (Box/Add-ons column) — sa
    // commission.php sidebar, iisang listahan pa rin (figure + box magkasama)
    const boxItems =
        document.getElementById(
            "summaryBoxItems"
        );

    if (boxItems) {
        const figureRows = rows.filter(row => row.group !== "box");
        const boxRows = rows.filter(row => row.group === "box");

        renderRowsIntoContainer(
            receiptItems,
            figureRows
        );

        renderRowsIntoContainer(
            boxItems,
            boxRows,
            "No box selected yet."
        );
    } else {
        renderRowsIntoContainer(
            receiptItems,
            rows
        );
    }

}


function formatMonthDay(value) {
    if (!value) {
        return "";
    }

    if (/^\d{1,2}\/\d{1,2}$/.test(value)) {
        const [month, day] = value.split("/").map(Number);
        return `${String(month).padStart(2, "0")}/${String(day).padStart(2, "0")}`;
    }

    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const parsed = new Date(value);
        if (!Number.isNaN(parsed.getTime())) {
            return `${String(parsed.getMonth() + 1).padStart(2, "0")}/${String(parsed.getDate()).padStart(2, "0")}`;
        }
        return value;
    }

    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return `${String(date.getMonth() + 1).padStart(2, "0")}/${String(date.getDate()).padStart(2, "0")}`;
}


function collectClothingColors(activeState) {

    const colors = {};

    if (!activeState) {
        return colors;
    }

    Object.entries(activeState).forEach(
        function([key, item]) {
            if (!key.endsWith("Color") || !item) {
                return;
            }

            colors[key] = {
                name: item.name || "",
                color: item.color || ""
            };
        }
    );

    return colors;

}


function updateSelectedItemsUI() {

    if (!selectedList) {
        return;
    }


    selectedList.innerHTML = "";


    const activeState =
        getActiveState();


    if (!activeState || !state.currentCategory) {
        const emptyText =
            document.createElement(
                "span"
            );

        emptyText.className =
            "empty-text";

        emptyText.textContent =
            "No customization selected yet.";

        selectedList.appendChild(
            emptyText
        );

        return;
    }


    const items =
        getActiveSlotOrder()
        .filter(slot => slot !== "skin" && !slot.endsWith("Color"))
        .map(
            function(slot) {
                return activeState[slot];
            }
        ).filter(
            item => item && item.name !== "Custom Color"
        );

    const detailItems = [];
    const config = getProductDetailsConfig();
    const selectedBox = config?.boxes?.find(box => box.id === productDetails.box);

    if (productDetails.size) {
        detailItems.push(
            `Size: ${productDetails.size} (PHP ${getProductSizePrice()})`
        );
    }

    if (selectedBox) {
        detailItems.push(selectedBox.label);
    }

    if (productDetails.figureName.trim()) {
        detailItems.push(`Figure name: ${productDetails.figureName.trim()}`);
    }

    if (productDetails.boxName.trim()) {
        detailItems.push(`Box name: ${productDetails.boxName.trim()}`);
    }

    if (productDetails.boxNumber.trim()) {
        detailItems.push(`Box number: ${productDetails.boxNumber.trim()}`);
    }

    if (productDetails.boxColor.trim()) {
        detailItems.push(`Box color: ${productDetails.boxColor.trim()}`);
    }

    if (productDetails.blindBox === "set") {
        detailItems.push("Blind Box Set");
    }

    if (Array.isArray(productDetails.hironoAddons)) {
        const addonLabels = {
            tearPaper: "Tear Paper",
            pouch: "Pouch",
            digitalArt: "Digital Art"
        };
        detailItems.push(...productDetails.hironoAddons.map(addon => addonLabels[addon] || addon));
    }


    if (!items.length && !detailItems.length) {
        const emptyText =
            document.createElement(
                "span"
            );

        emptyText.className =
            "empty-text";

        emptyText.textContent =
            "No customization selected yet.";

        selectedList.appendChild(
            emptyText
        );

        return;
    }


    items.forEach(
        function(item) {

            const tag =
                document.createElement(
                    "span"
                );


            tag.className =
                "selected-tag";


            const tagPrice = getItemPrice(item);

            if (tagPrice <= 0) {
                tag.textContent = item.name;
            }
            else {
                tag.textContent =
                    `${item.name} + ₱${tagPrice}`;
            }


            selectedList.appendChild(
                tag
            );

        }
    );

    detailItems.forEach(label => {
        const tag = document.createElement("span");
        tag.className = "selected-tag";
        tag.textContent = label;
        selectedList.appendChild(tag);
    });

}


function updateSectionVisibility() {

    const activeCategory =
        state.currentCategory;


    Object.entries(
        categoryPanels
    ).forEach(
        function([category, panel]) {
            if (panel) {
                panel.hidden =
                    category !== activeCategory;
            }
        }
    );


    if (isDetailsPage) {
        // The details page is a focused review step: keep the preview and
        // order summary, but remove all figure-style/category controls.
        Object.values(categoryPanels).forEach(panel => {
            if (panel) {
                panel.hidden = true;
            }
        });

        if (figureDetailsContainer) {
            figureDetailsContainer.hidden = true;
        }

        if (!activeCategory) {
            if (productDetailsPanel) {
                productDetailsPanel.hidden = true;
            }
            return;
        }

        if (productDetailsPanel) {
            productDetailsPanel.hidden = false;
        }

        const navigation = document.getElementById("designStepNavigation");
        if (navigation) {
            navigation.hidden = true;
        }

        if (continueBtn) {
            continueBtn.hidden = false;
        }

        return;
    }


    if (!activeCategory) {
        if (continueBtn) {
            continueBtn.hidden = true;
        }

        if (productDetailsPanel) {
            productDetailsPanel.hidden = true;
        }

        // Size ay shared section, wala sa reset loop ng `sectionIds` sa ibaba (at
        // may `return` dito bago maabot yun) — i-reset dito para hindi manatiling
        // visible pagbalik sa Figure Style dropdown habang nasa Size step.
        setVisibleById("figureSizeSection", false);

        // No style chosen yet (or user came back to change it) — show the
        // Figure Style picker and keep every category panel hidden so it
        // occupies the same spot instead of stacking on top of one.
        if (figureStyleSection) {
            figureStyleSection.hidden = false;
        }

        const navigation = document.getElementById("designStepNavigation");
        if (navigation) {
            navigation.hidden = true;
        }

        return;
    }

    if (figureDetailsContainer) {
        figureDetailsContainer.hidden = false;
    }

    // A style is active — swap the Figure Style picker out for that
    // category's panel in the same spot, instead of leaving it visible
    // above the panel.
    if (figureStyleSection) {
        figureStyleSection.hidden = true;
    }

    // The model picker is the entry point for every category. Keep it visible
    // even though the dependent option sections are hidden until a model is
    // selected.
    [
        "funkoModelSection",
        "hironoModelSection",
        "chibiModelSection"
    ].forEach(
        function(id) {
            setVisibleById(
                id,
                id === `${activeCategory}ModelSection`
            );
        }
    );


    const activeState =
        state[activeCategory];

    if (continueBtn) {
        continueBtn.hidden = true;
    }

    Object.values(
        sectionIds
    ).forEach(
        function(categorySections) {
            Object.values(
                categorySections
            ).forEach(
                function(id) {
                    setVisibleById(
                        id,
                        false
                    );
                }
            );
        }
    );


    // The reset loop above only walks the per-style `sectionIds` map, which
    // doesn't include the shared Size section (it lives outside all three
    // style panels). Without this, Size stays visible after the user moves
    // past it to Hair/Top/etc. instead of being hidden like every other step.
    setVisibleById("figureSizeSection", false);


    const showSections = function(ids) {
        ids.forEach(
            function(id) {
                setVisibleById(
                    id,
                    true
                );
            }
        );
    };


    let sequentialSlots = null;

    if (activeCategory === "funko") {
        sequentialSlots = activeState.model && isFunkoGirlModel(activeState.model)
            ? ["model", "skin", "size", "girlHair", "girlTop", "girlBottom", "shoes"]
            : ["model", "skin", "size", "hair", "top", "bottom", "shoes"];
    }

    if (activeCategory === "chibi") {
        // Keychain: walang Hair step (kasama na sa keychain model, gaya ng DressUp)
        sequentialSlots = !activeState.productType
            ? ["productType"]
            : activeState.productType === "keychain"
                ? activeState.model && isChibiGirlModel(activeState.model)
                    ? ["productType", "model", "skin", "size", "girlTop", "girlBottom", "shoes"]
                    : ["productType", "model", "skin", "size", "top", "bottom", "shoes"]
                : activeState.model && isChibiGirlModel(activeState.model)
                    ? ["productType", "model", "skin", "size", "girlHair", "girlTop", "girlBottom", "shoes"]
                    : ["productType", "model", "skin", "size", "hair", "top", "bottom", "shoes"];
    }

    if (activeCategory === "hirono") {
        if (!activeState.mode) {
            sequentialSlots = ["mode"];
        }
        else {
            const hironoMode = getHironoMode();
            sequentialSlots = hironoMode === "headKeychain"
                ? ["mode", "model", "skin", "size", "keychainHair", "keychainHat"]
                : ["mode", "model", "skin", "size", "hair", "outfit", "pants", "shoes"];
        }
    }

    if (sequentialSlots) {
        const sequentialSections = {
            productType: sectionIds.chibi.type,
            mode: sectionIds.hirono.type,
            model: `${activeCategory}ModelSection`,
            skin: sectionIds[activeCategory].skin,
            size: "figureSizeSection",
            hair: sectionIds.funko.hair,
            hairColor: sectionIds.funko.hairColor,
            top: sectionIds.funko.top,
            topColor: sectionIds.funko.topColor,
            bottom: sectionIds.funko.bottom,
            pantsColor: sectionIds.funko.pantsColor,
            girlHair: sectionIds.funko.girlHair,
            girlHairColor: sectionIds.funko.hairColor,
            girlTop: sectionIds.funko.girlTop,
            girlTopColor: sectionIds.funko.girlTopColor,
            girlBottom: sectionIds.funko.girlBottom,
            girlBottomColor: sectionIds.funko.girlBottomColor,
            keychainHair: sectionIds.hirono.keychainHair,
            keychainHairColor: sectionIds.hirono.keychainHairColor,
            keychainHat: sectionIds.hirono.keychainHat,
            outfit: sectionIds.hirono.outfit,
            outfitColor: sectionIds.hirono.outfitColor,
            pants: sectionIds.hirono.pants,
            shoes: sectionIds.funko.shoes,
            shoesColor: sectionIds.hirono.shoesColor
        };

        if (activeCategory === "chibi") {
            Object.assign(sequentialSections, {
                skin: sectionIds.chibi.skin,
                hair: sectionIds.chibi.hair,
                hairColor: sectionIds.chibi.hairColor,
                girlHair: sectionIds.chibi.girlHair,
                girlHairColor: sectionIds.chibi.girlHairColor,
                top: sectionIds.chibi.top,
                girlTop: sectionIds.chibi.girlTop,
                bottom: sectionIds.chibi.bottom,
                girlBottom: sectionIds.chibi.girlBottom,
                shoes: sectionIds.chibi.shoes
            });

            if (activeState.productType === "keychain") {
                Object.assign(sequentialSections, {
                    top: sectionIds.chibi.keychainBoyTop,
                    bottom: sectionIds.chibi.keychainBoyBottom,
                    girlTop: sectionIds.chibi.keychainGirlTop,
                    girlBottom: sectionIds.chibi.keychainGirlBottom
                });
            }
        }

        if (activeCategory === "hirono") {
            Object.assign(sequentialSections, {
                hair: sectionIds.hirono.hair,
                hairColor: sectionIds.hirono.hairColor,
                outfit: sectionIds.hirono.outfit,
                pants: sectionIds.hirono.pants,
                pantsColor: sectionIds.hirono.pantsColor,
                shoes: sectionIds.hirono.shoes,
                shoesColor: sectionIds.hirono.shoesColor,
                keychainHair: sectionIds.hirono.keychainHair,
                keychainHairColor: sectionIds.hirono.keychainHairColor,
                keychainHat: sectionIds.hirono.keychainHat,
                outfitColor: sectionIds.hirono.outfitColor
            });
        }

        const designSlotByColor = activeCategory === "funko"
            ? {
                hairColor: isFunkoGirlModel(activeState.model) ? "girlHair" : "hair",
                topColor: isFunkoGirlModel(activeState.model) ? "girlTop" : "top",
                bottomColor: "girlBottom",
                pantsColor: "bottom"
            }
            : activeCategory === "chibi"
                ? {
                    hairColor: isChibiGirlModel(activeState.model) ? "girlHair" : "hair",
                    girlHairColor: "girlHair"
                }
                : {
                    hairColor: "hair",
                    outfitColor: "outfit",
                    pantsColor: "pants",
                    shoesColor: "shoes",
                    keychainHairColor: "keychainHair"
                };
        const requestedSlot = designSlotByColor[navigationSlot] || navigationSlot;
        const nextSlot = sequentialSlots.find(slot => !activeState[slot]);
        const slotToShow = requestedSlot && sequentialSlots.includes(requestedSlot)
            ? requestedSlot
            : nextSlot || sequentialSlots[sequentialSlots.length - 1];
        const slotBefore = slotToShow === "skin" || slotToShow.endsWith("Color")
            ? sequentialSlots[sequentialSlots.indexOf(slotToShow) - 1]
            : null;
        const colorSlotByDesign = {
            hair: "hairColor",
            girlHair: "girlHairColor",
            top: "topColor",
            girlTop: "girlTopColor",
            bottom: "pantsColor",
            girlBottom: "girlBottomColor",
            pants: "pantsColor",
            shoes: "shoesColor",
            outfit: "outfitColor",
            keychainHair: "keychainHairColor"
        };
        const slotAfter = colorSlotByDesign[slotToShow];

        // Gender at Skin dapat magkasama once shown, hindi kailangan mag-Gender muna
        // para makita ang skin options. Applies sa Funko/Hirono/Chibi Gender step.
        const modelPairedSlot = (activeCategory === "funko" || activeCategory === "hirono" || activeCategory === "chibi") &&
            slotToShow === "model" &&
            sequentialSlots.includes("skin")
            ? "skin"
            : null;

        const designsComplete = !nextSlot;
        const detailsStep = navigationSlot === "productDetails";

        renderSizeSection();

        if (continueBtn) {
            continueBtn.hidden = !detailsStep;
            continueBtn.textContent = "Next";
        }

        if (productDetailsPanel) {
            productDetailsPanel.hidden = !detailsStep;
        }

        if (figureDetailsContainer) {
            figureDetailsContainer.hidden = detailsStep;
        }

        if (detailsStep) {
            Object.values(sequentialSections).forEach(id => {
                setVisibleById(id, false);
            });

            renderProductDetailsPanel();
            renderDesignNavigation(
                sequentialSlots,
                sequentialSections,
                activeState,
                sequentialSlots[sequentialSlots.length - 1]
            );

            return;
        }

        setVisibleById(
            sequentialSections.model,
            slotToShow === "model"
        );

        setVisibleById(
            sequentialSections[slotBefore],
            Boolean(slotBefore)
        );

        setVisibleById(
            sequentialSections[slotToShow],
            Boolean(slotToShow)
        );

        setVisibleById(
            sequentialSections[slotAfter],
            Boolean(slotAfter)
        );

        setVisibleById(
            sequentialSections[modelPairedSlot],
            Boolean(modelPairedSlot)
        );

        if (designsComplete) {
            setVisibleById(
                sequentialSections[slotToShow],
                true
            );
        }

        renderDesignNavigation(
            sequentialSlots,
            sequentialSections,
            activeState,
            slotToShow,
            modelPairedSlot
        );

        return;
    }


    if (activeCategory === "funko") {

        if (activeState.model) {
            setVisibleById(
                sectionIds.funko.skin,
                true
            );
        }


        if (!activeState.skin) {
            return;
        }


        if (isFunkoGirlModel(activeState.model)) {
            showSections(
                [
                    sectionIds.funko.girlHair,
                    sectionIds.funko.hairColor,
                    sectionIds.funko.girlTop,
                    sectionIds.funko.girlTopColor,
                    sectionIds.funko.girlBottom,
                    sectionIds.funko.girlBottomColor
                ]
            );
        }
        else {
            showSections(
                [
                    sectionIds.funko.hair,
                    sectionIds.funko.hairColor,
                    sectionIds.funko.top,
                    sectionIds.funko.topColor,
                    sectionIds.funko.bottom,
                    sectionIds.funko.pantsColor
                ]
            );
        }

        return;

    }


    const hironoMode =
        getHironoMode();


    if (activeCategory === "hirono") {

        const hironoSequence =
            hironoMode === "headKeychain"
                ? ["model", "skin", "keychainHair", "keychainHairColor", "keychainHat"]
                : ["model", "skin", "hair", "hairColor", "outfit", "pants", "pantsColor", "shoes", "shoesColor"];

        const hironoSections = {
            model: "hironoModelSection",
            skin: sectionIds.hirono.skin,
            hair: sectionIds.hirono.hair,
            hairColor: sectionIds.hirono.hairColor,
            outfit: sectionIds.hirono.outfit,
            pants: sectionIds.hirono.pants,
            pantsColor: sectionIds.hirono.pantsColor,
            shoes: sectionIds.hirono.shoes,
            shoesColor: sectionIds.hirono.shoesColor,
            keychainHair: sectionIds.hirono.keychainHair,
            keychainHairColor: sectionIds.hirono.keychainHairColor,
            keychainHat: sectionIds.hirono.keychainHat
        };

        const nextSlot =
            hironoSequence.find(slot => !activeState[slot]);

        const designsComplete = !nextSlot;

        if (productDetailsPanel) {
            productDetailsPanel.hidden = !designsComplete;
        }

        if (figureDetailsContainer) {
            figureDetailsContainer.hidden = designsComplete;
        }

        setVisibleById(
            hironoSections.model,
            nextSlot === "model"
        );

        setVisibleById(
            hironoSections[nextSlot],
            Boolean(nextSlot)
        );

        return;
    }


    if (hironoMode === "headKeychain") {

        if (activeState.model) {
            setVisibleById(
                sectionIds.hirono.skin,
                true
            );
        }


        if (!activeState.skin) {
            return;
        }


        showSections(
            [
                sectionIds.hirono.keychainHair,
                sectionIds.hirono.keychainHairColor,
                sectionIds.hirono.keychainHat
            ]
        );
        return;

    }


    if (hironoMode === "keychain") {

        if (activeState.model) {
            setVisibleById(
                sectionIds.hirono.skin,
                true
            );
        }


        if (!activeState.skin) {
            return;
        }


        showSections(
            [
                sectionIds.hirono.hair,
                sectionIds.hirono.hairColor,
                sectionIds.hirono.outfit,
                sectionIds.hirono.pants,
                sectionIds.hirono.pantsColor,
                sectionIds.hirono.shoes,
                sectionIds.hirono.shoesColor
            ]
        );

        return;

    }


    if (activeState.model) {
        setVisibleById(
            sectionIds.hirono.skin,
            true
        );
    }


    if (!activeState.skin) {
        return;
    }


    if (hironoMode === "keychain") {
        showSections(
            [
                sectionIds.hirono.keychainHair,
                sectionIds.hirono.keychainHairColor,
                sectionIds.hirono.keychainHat
            ]
        );
        return;
    }


    showSections(
        [
            sectionIds.hirono.hair,
            sectionIds.hirono.hairColor,
            sectionIds.hirono.outfit,
            sectionIds.hirono.pants,
            sectionIds.hirono.pantsColor,
            sectionIds.hirono.shoes,
            sectionIds.hirono.shoesColor
        ]
    );

}


function clearCategoryState(category) {

    Object.assign(
        state[category],
        createCategoryState()
    );


    const selector =
        `[data-figure="${category}"][data-slot]`;


    clearSelectedClasses(
        selector
    );

}


function clearAccessoryObjects() {

    Object.keys(
        currentObjects
    ).forEach(
        function(slot) {
            removeFromScene(
                currentObjects[slot]
            );
            currentObjects[slot] = null;
        }
    );

}


function getPrice(category, slot, name) {

    const categoryPrices =
        PRICE_FALLBACKS[category];


    if (!categoryPrices) {
        return 0;
    }


    const slotPrices =
        categoryPrices[slot];


    if (typeof slotPrices === "number") {
        return slotPrices;
    }


    if (slotPrices && typeof slotPrices === "object") {
        return Number(
            slotPrices[name] || 0
        );
    }


    return 0;

}


function isPlaceholderModelPath(path) {

    return !path || String(path).startsWith("placeholder:");

}


// Kapag pumili ng Gender (model), auto-select agad ang mga "Default" na item
// (Default Hair, Sando/Tank Top, Shorts, Shoes) para may laman na ang bawat
// step at makapag-Next agad. Puwede pa rin nilang palitan anytime.
function applyDefaultSelections(category) {

    const stateForCategory =
        state[category];

    if (!stateForCategory || !stateForCategory.model) {
        return;
    }

    getActiveSlotOrder().forEach(
        function(slot) {

            if (
                slot === "model" ||
                slot === "skin" ||
                slot.endsWith("Color") ||
                stateForCategory[slot]
            ) {
                return;
            }

            // Chibi keychain may sariling top/bottom sections — doon kunin ang Default card
            const keychainSectionId =
                category === "chibi" && stateForCategory.productType === "keychain"
                    ? {
                        top: sectionIds.chibi.keychainBoyTop,
                        bottom: sectionIds.chibi.keychainBoyBottom,
                        girlTop: sectionIds.chibi.keychainGirlTop,
                        girlBottom: sectionIds.chibi.keychainGirlBottom
                    }[slot]
                    : null;

            const defaultCard =
                ((keychainSectionId && document.getElementById(keychainSectionId)) || document).querySelector(
                    `[data-figure="${category}"][data-slot="${slot}"][data-default="true"]`
                );

            if (!defaultCard) {
                return;
            }

            stateForCategory[slot] =
                buildItemFromCard(
                    defaultCard
                );

            markSelectedCard(
                defaultCard
            );

        }
    );

}


function buildItemFromCard(card) {

    return {
        name: card.dataset.name || "",
        model: card.dataset.model || "",
        color: card.dataset.color || "",
        price: Number(card.dataset.price || 0),
        billable: card.dataset.billable !== "false"
    };

}


function getColorAccessorySlot(category, slot) {

    let accessorySlot = slot.replace("Color", "");


    if (category === "funko" && slot === "pantsColor") {
        accessorySlot = "bottom";
    }
    else if (
        category === "funko" &&
        slot === "hairColor" &&
        isFunkoGirlModel(state[category].model)
    ) {
        accessorySlot = "girlHair";
    }
    else if (
        category === "funko" &&
        slot === "topColor" &&
        isFunkoGirlModel(state[category].model)
    ) {
        accessorySlot = "girlTop";
    }
    else if (
        category === "funko" &&
        slot === "bottomColor" &&
        isFunkoGirlModel(state[category].model)
    ) {
        accessorySlot = "girlBottom";
    }


    return accessorySlot;

}


function applyCustomColor(category, slot, color, input) {

    const stateForCategory = state[category];
    const accessorySlot = getColorAccessorySlot(category, slot);
    const currentObject = getLoadedObject(accessorySlot);


    stateForCategory[slot] = {
        name: "Custom Color",
        model: "",
        color,
        price: 0,
        billable: false
    };


    if (currentObject) {
        if (
            category === "funko" &&
            (accessorySlot === "bottom" || slot === "bottomColor")
        ) {
            applyBottomPartColors(currentObject, color);
        }
        else {
            applyColorToModel(currentObject, color, {
                clearTextureMaps: true,
                skipMaterialFragments: ["design", "logo", "print"]
            });
        }
    }


    state.currentCategory = category;
    navigationSlot = getColorAccessorySlot(category, slot);
    syncCustomColorPickers();
    updateSectionVisibility();
    updateSelectedItemsUI();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);

}


const customColorDesignSectionIds = {
    funkoHairColorSection: ["funkoHairSection", "funkoGirlHairSection"],
    funkoGirlTopColorSection: ["funkoGirlTopSection"],
    funkoTopColorSection: ["funkoTopSection"],
    funkoGirlBottomColorSection: ["funkoGirlBottomSection"],
    funkoBottomPantsColorSection: ["funkoBottomSection"],
    hironoHairColorSection: ["hironoHairSection"],
    hironoOutfitColorSection: ["hironoOutfitSection"],
    hironoPantsColorSection: ["hironoPantsSection"],
    hironoShoesColorSection: ["hironoShoesSection"],
    hironoKeychainHairColorSection: ["hironoKeychainHairSection"],
    funkoShoesColorSection: ["funkoShoesSection"],
    chibiHairColorSection: ["chibiHairSection"],
    chibiGirlHairColorSection: ["chibiGirlHairSection"],
    chibiTopColorSection: ["chibiTopSection", "chibiBoyKeychainTopSection"],
    chibiGirlTopColorSection: ["chibiGirlTopSection", "chibiGirlKeychainTopSection"],
    chibiBottomColorSection: ["chibiBottomSection", "chibiBoyKeychainBottomSection"],
    chibiGirlBottomColorSection: ["chibiGirlBottomSection", "chibiGirlKeychainBottomSection"],
    chibiShoesColorSection: ["chibiShoesSection"]
};


function getCustomColorMounts(section) {
    const siblings = Array.from(section.parentElement.children);
    const designSectionIds = customColorDesignSectionIds[section.id];

    if (designSectionIds) {
        return designSectionIds
            .map(id => document.getElementById(id))
            .filter(Boolean)
            .map(designSection => designSection.querySelector(".section-title"))
            .filter(Boolean);
    }

    const designSection = siblings
        .slice(0, siblings.indexOf(section))
        .reverse()
        .find(candidate => candidate.classList.contains("option-section"));

    const mount = designSection?.querySelector(".section-title");

    return mount ? [mount] : [section];
}


function initializeCustomColorPickers() {

    document.querySelectorAll(
        ".top-color-picker, .custom-color-only-section"
    ).forEach(
        function(section) {
            const mounts = getCustomColorMounts(section);

            if (mounts.every(mount => mount.querySelector(".custom-color-control"))) {
                return;
            }


            const slotCard = section.querySelector("[data-slot]");
            const slot =
                (slotCard && slotCard.dataset.slot) ||
                section.dataset.slot;

            const category =
                (slotCard && slotCard.dataset.figure) ||
                section.dataset.figure;


            if (!slot || !category) {
                return;
            }


            mounts.forEach(mount => {
                if (mount.querySelector(".custom-color-control")) {
                    return;
                }

                const label = document.createElement("label");
                label.className = "custom-color-control";
                label.innerHTML = `<span class="custom-color-wheel" aria-hidden="true"></span><span class="custom-color-label">Custom color</span><input type="color" value="#ffffff" aria-label="Choose custom ${slot} color">`;

                mount.appendChild(label);

                const input = label.querySelector("input");
                const wheel = label.querySelector(".custom-color-wheel");
                input.addEventListener(
                    "input",
                    function() {
                        if (wheel) {
                            wheel.style.background = input.value;
                        }
                        applyCustomColor(category, slot, input.value, input);
                    }
                );
            });

        }
    );

}


function syncCustomColorPickers() {

    document.querySelectorAll(
        ".top-color-picker, .custom-color-only-section"
    ).forEach(
        function(section) {
            const slotCard =
                section.querySelector("[data-slot]");

            const slot =
                (slotCard && slotCard.dataset.slot) ||
                section.dataset.slot;

            const category =
                (slotCard && slotCard.dataset.figure) ||
                section.dataset.figure;

            const inputs = getCustomColorMounts(section)
                .map(mount => mount.querySelector(
                    ".custom-color-control input[type='color']"
                ))
                .filter(Boolean);

            const savedColor =
                category && slot && state[category] && state[category][slot]
                    ? state[category][slot].color
                    : "";

            if (/^#[0-9a-f]{6}$/i.test(savedColor || "")) {
                inputs.forEach(input => {
                    input.value = savedColor;

                    const wheel = input
                        .closest(".custom-color-control")
                        ?.querySelector(".custom-color-wheel");

                    if (wheel) {
                        wheel.style.background = savedColor;
                    }
                });
            }
        }
    );

}


function getFigureLabel(category) {

    if (category === "funko") {
        return "Funko Pop";
    }


    if (category === "hirono") {
        return "Hirono";
    }


    if (category === "chibi") {
        return "Chibi";
    }


    return category || "";

}


function escapeSelectorValue(value) {

    const stringValue =
        String(value || "");


    if (
        window.CSS &&
        typeof window.CSS.escape === "function"
    ) {
        return window.CSS.escape(stringValue);
    }


    return stringValue.replace(
        /["\\]/g,
        "\\$&"
    );

}


// ---------------------------------------------------------------------
// PRESYO NG ITEM BASE SA SIZE
// Ang data-price ng bawat card ay presyo sa pinakamaliit na size. Tumataas
// ito kada size: 1st = 100%, 2nd = 125%, 3rd = 150%, 4th = 175% (naka-round
// sa ₱5). Kapareho ito ng kuwenta sa server (Commission/submit_order.php).
// ---------------------------------------------------------------------

const DRESSUP_SIZE_PRICE_PERCENTS = [100, 125, 150, 175];


function getDressUpSizePercent() {

    const config = getProductDetailsConfig();

    if (!config || !config.sizes || !config.sizes.length || !productDetails.size) {
        return 100;
    }

    const index = config.sizes.indexOf(productDetails.size);

    if (index < 0) {
        return 100;
    }

    return DRESSUP_SIZE_PRICE_PERCENTS[Math.min(index, DRESSUP_SIZE_PRICE_PERCENTS.length - 1)];

}


function scaleDressUpPrice(basePrice) {

    const base = Number(basePrice || 0);

    if (base <= 0) {
        return 0;
    }

    return Math.round((base * getDressUpSizePercent()) / 500) * 5;

}


function getItemPrice(item) {

    if (!item || item.billable === false) {
        return 0;
    }

    return scaleDressUpPrice(item.price);

}


// presyo sa ilalim ng bawat card (nagbabago kapag pinalitan ang size)
function refreshOptionPriceLabels() {

    document.querySelectorAll("[data-slot] .option-price[data-base-price]").forEach(label => {

        const card = label.closest("[data-slot]");

        if (!card) {
            return;
        }

        const billable = card.dataset.billable !== "false";
        const price = billable ? scaleDressUpPrice(card.dataset.price) : 0;

        label.textContent = price > 0 ? `+ ₱${price}` : "";

    });

}


// kapag may lumang draft (bago nagbago ang presyo), kunin ulit ang presyo
// mula sa mismong card para tugma sa server
function syncItemPricesFromCards() {

    Object.keys(categoryPanels).forEach(category => {

        const stateForCategory = state[category];

        if (!stateForCategory) {
            return;
        }

        Object.keys(stateForCategory).forEach(slot => {

            const item = stateForCategory[slot];

            if (
                !item ||
                typeof item !== "object" ||
                !item.model ||
                slot === "model" ||
                slot.endsWith("Color")
            ) {
                return;
            }

            const card = document.querySelector(
                `[data-figure="${category}"][data-slot="${slot}"][data-model="${escapeSelectorValue(item.model)}"]`
            );

            if (card) {
                item.price = Number(card.dataset.price || 0);
                item.billable = card.dataset.billable !== "false";
            }

        });

    });

}


function getActiveStateTotal(activeState) {

    if (!activeState) {
        return 0;
    }


    return getActiveSlotOrder().reduce(
        function(total, slot) {

            const item =
                activeState[slot];


            if (
                !item ||
                item.billable === false
            ) {
                return total;
            }


            return total + getItemPrice(item);

        },
        0
    );

}


function getSelectedItemNames(activeState) {

    if (!activeState) {
        return [];
    }


    return getActiveSlotOrder().map(
        function(slot) {
            const item =
                activeState[slot];

            return item ? item.name : null;
        }
    ).filter(Boolean);

}


function getSelectionDetails(activeState) {

    if (!activeState) {
        return [];
    }

    const labels = {
        model: "Figure type",
        skin: "Skin Tone",
        hair: "Hair",
        girlHair: "Hair",
        keychainHair: "Hair",
        top: "Top",
        girlTop: "Top",
        outfit: "Top",
        bottom: "Bottom",
        girlBottom: "Bottom",
        pants: "Bottom",
        shoes: "Shoes",
        hat: "Hat",
        accessory: "Accessory"
    };

    return getActiveSlotOrder().reduce((details, slot) => {
        if (slot === "skin" || slot.endsWith("Color")) {
            return details;
        }

        const item = activeState[slot];

        if (!item || !labels[slot]) {
            return details;
        }

        const value = getSummarySelectionValue(
            slot,
            item,
            activeState
        );

        details.push({
            label: labels[slot],
            value
        });

        return details;
    }, []);

}


function getAccessoryNames(activeState) {

    if (!activeState) {
        return [];
    }


    return getActiveSlotOrder().filter(
        function(slot) {
            return slot !== "model" && slot !== "skin";
        }
    ).map(
        function(slot) {
            const item =
                activeState[slot];

            return item ? item.name : null;
        }
    ).filter(Boolean);

}


function getPrimarySelectionName(activeState) {

    if (!activeState || !activeState.model) {
        return "";
    }


    return activeState.model.name || "";

}


function toSummarySlug(value) {
    return String(value || "")
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/-([0-9]+)/g, "$1")
        .replace(/^-+|-+$/g, "");
}


function getFileBaseName(value) {
    return String(value || "")
        .split(/[\\/]/)
        .pop()
        .replace(/\.[^.]+$/, "");
}


function getSummaryToken(item) {
    if (!item) {
        return "";
    }

    return toSummarySlug(
        getFileBaseName(item.model || item.name || item.color || "")
    );
}


function getSummaryColorToken(item) {
    if (!item) {
        return "";
    }

    return toSummarySlug(
        item.name || item.color || ""
    );
}


function getFigureTypeSummaryValue(activeState) {
    const category = toSummarySlug(getFigureLabel(state.currentCategory));
    const model = getSummaryToken(activeState?.model);
    const modelName = toSummarySlug(activeState?.model?.name);

    if (!category) {
        return model;
    }

    let suffix = model;

    if (category === "funko-pop") {
        suffix = suffix
            .replace(/^funko-pop-?/, "")
            .replace(/^funko-?/, "")
            .replace(/^pop-?/, "");

        if (suffix === "default") {
            suffix = "boy";
        }
    }
    else if (category === "hirono") {
        return modelName || (suffix ? `${category}-${suffix.replace(/^hirono-?/, "")}` : category);
    }
    else if (category === "chibi") {
        return modelName || (suffix ? `${category}-${suffix.replace(/^chibi-?/, "")}` : category);
    }

    return suffix ? `${category}-${suffix}` : category;
}


function getSummarySelectionValue(slot, item, activeState) {
    if (!item) {
        return "";
    }

    if (slot === "model") {
        return getFigureTypeSummaryValue(activeState);
    }

    if (slot === "skin") {
        return getSummaryColorToken(item);
    }

    const colorSlot = {
        hair: "hairColor",
        girlHair: "girlHairColor",
        keychainHair: "keychainHairColor",
        top: "topColor",
        girlTop: "girlTopColor",
        bottom: "pantsColor",
        girlBottom: "girlBottomColor",
        pants: "pantsColor",
        outfit: "outfitColor",
        shoes: "shoesColor",
        hat: "hatColor",
        accessory: "accessoryColor"
    }[slot];

    const color =
        colorSlot ? activeState?.[colorSlot] : null;

    const itemToken =
        getSummaryToken(item);

    const colorToken =
        color && color.name !== "Custom Color"
            ? getSummaryColorToken(color)
            : "";

    return colorToken
        ? `${itemToken}-${colorToken}`
        : itemToken;
}


function syncSelectedCardsFromState() {

    clearSelectedClasses(
        ".style-card, .figure-model-card, .skin-card, .option-card, .top-color-card"
    );


    if (!state.currentCategory) {
        return;
    }


    const activeState =
        getActiveState();


    if (!activeState) {
        return;
    }


    const activeCategory =
        state.currentCategory;


    const styleCard =
        document.querySelector(
            `[data-tab="figure"][data-figure="${activeCategory}"]`
        );


    if (styleCard) {
        styleCard.classList.add("selected");
    }


    if (activeState.model) {
        const modelCard =
            document.querySelector(
                `[data-figure="${activeCategory}"][data-slot="model"][data-model="${escapeSelectorValue(activeState.model.model)}"]`
            );


        if (modelCard) {
            modelCard.classList.add("selected");
        }
    }


    if (activeState.skin) {
        const skinCard =
            document.querySelector(
                `[data-figure="${activeCategory}"][data-slot="skin"][data-color="${escapeSelectorValue(activeState.skin.color)}"]`
            );


        if (skinCard) {
            skinCard.classList.add("selected");
        }
    }


    getActiveSlotOrder().forEach(
        function(slot) {

            const item =
                activeState[slot];


            if (!item || slot === "model" || slot === "skin") {
                return;
            }


            // querySelectorAll: iisang model minsan nasa standee at keychain section
            const cards =
                item.color
                    ? document.querySelectorAll(
                        `[data-figure="${activeCategory}"][data-slot="${slot}"][data-color="${escapeSelectorValue(item.color)}"]`
                    )
                    : document.querySelectorAll(
                        `[data-figure="${activeCategory}"][data-slot="${slot}"][data-model="${escapeSelectorValue(item.model)}"]`
                    );


            cards.forEach(card => card.classList.add("selected"));

        }
    );

}


function buildCustomizationSnapshot(isCompleted, isConfirmed) {

    const activeState =
        getActiveState();


    if (!activeState || !state.currentCategory) {
        return null;
    }


    const selectedItems =
        getSelectedItemNames(activeState);

    const orderTypeValue =
        getCommissionOrderTypeValue();

    const bookingDateValue =
        getCommissionBookingDateValue();

    const estimatedPrice =
        getCurrentOrderTotal(activeState);


    return {
        creationMethod: "create",
        customizationCompleted: Boolean(isCompleted),
        customizationConfirmed: Boolean(isConfirmed),
        currentCategory: state.currentCategory,
        figureCategory: getFigureLabel(state.currentCategory),
        orderType: getCommissionOrderTypeLabel(),
        orderTypeValue: orderTypeValue || "",
        bookingDate: getCommissionBookingDateLabel(),
        bookingDateValue: bookingDateValue || "",
        figureModel: getPrimarySelectionName(activeState),
        skin: activeState.skin ? activeState.skin.name : "",
        hair: activeState.hair ? activeState.hair.name : (activeState.girlHair ? activeState.girlHair.name : (activeState.keychainHair ? activeState.keychainHair.name : "")),
        top: activeState.top ? activeState.top.name : (activeState.girlTop ? activeState.girlTop.name : (activeState.outfit ? activeState.outfit.name : "")),
        bottom: activeState.bottom ? activeState.bottom.name : (activeState.girlBottom ? activeState.girlBottom.name : (activeState.pants ? activeState.pants.name : "")),
        shoes: activeState.shoes ? activeState.shoes.name : "",
        accessories: getAccessoryNames(activeState),
        selectedItems,
        selectionDetails: getSelectionDetails(activeState),
        previewImage: canvas ? canvas.toDataURL("image/png") : "",
        estimatedPrice,
        productKey: productDetails.productKey,
        figureSize: productDetails.size,
        figureSizeSelected: Boolean(productDetails.sizeSelected),
        figureName: productDetails.figureName,
        boxType: productDetails.box,
        boxSelected: Boolean(productDetails.boxSelected),
        boxName: productDetails.boxName,
        boxNumber: productDetails.boxNumber,
        boxColor: productDetails.boxColor,
        blindBox: productDetails.blindBox,
        blindBoxSelected: Boolean(productDetails.blindBoxSelected),
        hironoAddons: productDetails.hironoAddons,
        boxDesign: productDetails.boxDesign,
        boxDesignSelected: Boolean(productDetails.boxDesignSelected),
        boxNickname: productDetails.boxNickname,
        boxLetter: productDetails.boxLetter,
        boxDateYmd: productDetails.boxDateYmd,
        rushFee: getCommissionRushFee(),
        clothingColors: collectClothingColors(activeState),
        funko: state.funko,
        hirono: state.hirono,
        chibi: state.chibi
    };

}


function saveCustomizationSnapshot(isCompleted, isConfirmed = false) {

    const snapshot =
        buildCustomizationSnapshot(
            isCompleted,
            isConfirmed
        );


    if (!snapshot) {
        return;
    }


    try {

        localStorage.setItem(
            CUSTOMIZATION_STORAGE_KEY,
            JSON.stringify(
                snapshot
            )
        );

    }
    catch (error) {
        console.warn(
            "Unable to save customization:",
            error
        );
    }

}


function clearCustomizationSnapshot() {

    try {
        localStorage.removeItem(
            CUSTOMIZATION_STORAGE_KEY
        );
    }
    catch (error) {
        console.warn(
            "Unable to clear customization:",
            error
        );
    }

}


function restoreCustomizationFromStorage() {

    const saved =
        (() => {
            try {
                const raw =
                    localStorage.getItem(
                        CUSTOMIZATION_STORAGE_KEY
                    );

                return raw ? JSON.parse(raw) : null;
            }
            catch (error) {
                console.warn(
                    "Unable to restore customization:",
                    error
                );
                return null;
            }
        })();


    if (!saved) {
        return false;
    }


    const selectedCommissionCategory =
        getCommissionFigureCategoryValue();


    // A new category selected on the commission page must not be replaced
    // by an older customization saved for another category.
    if (
        selectedCommissionCategory &&
        selectedCommissionCategory !== saved.currentCategory
    ) {
        return false;
    }

    productDetails = {
        productKey: saved.productKey || "",
        size: saved.figureSizeSelected ? (saved.figureSize || "") : "",
        sizeSelected: Boolean(saved.figureSizeSelected),
        figureName: "", // walang Figure Name sa Dress Up
        box: saved.boxSelected ? (saved.boxType || "") : "",
        boxSelected: Boolean(saved.boxSelected),
        boxName: saved.boxName || "",
        boxNumber: saved.boxNumber || "",
        boxColor: saved.boxColor || "",
        blindBox: saved.blindBoxSelected ? (saved.blindBox || "regular") : "regular",
        blindBoxSelected: Boolean(saved.blindBoxSelected),
        hironoAddons: Array.isArray(saved.hironoAddons) ? saved.hironoAddons : [],
        boxDesign: saved.boxDesignSelected ? (saved.boxDesign || "checkered") : "checkered",
        boxDesignSelected: Boolean(saved.boxDesignSelected),
        boxNickname: saved.boxNickname || "",
        boxLetter: saved.boxLetter || "",
        boxDateYmd: saved.boxDateYmd || ""
    };


    Object.keys(
        categoryPanels
    ).forEach(
        function(category) {
            if (saved[category]) {
                state[category] = Object.assign(
                    createCategoryState(),
                    saved[category]
                );
            }
        }
    );


    state.currentCategory =
        saved.currentCategory ||
        Object.keys(
            categoryPanels
        ).find(
            function(category) {
                return saved[category] && saved[category].model;
            }
        ) ||
        null;


    syncItemPricesFromCards();

    syncCustomColorPickers();


    if (!state.currentCategory) {
        return true;
    }

    if (isEditPage) {
        if (state.currentCategory === "funko") {
            navigationSlot = isFunkoGirlModel(state.funko.model) ? "girlHair" : "hair";
        }
        else if (state.currentCategory === "chibi") {
            // Keychain: walang Hair step, Top agad
            navigationSlot = state.chibi.productType === "keychain"
                ? (isChibiGirlModel(state.chibi.model) ? "girlTop" : "top")
                : (isChibiGirlModel(state.chibi.model) ? "girlHair" : "hair");
        }
        else if (getHironoMode() === "headKeychain") {
            navigationSlot = "keychainHair";
        }
        else {
            navigationSlot = "hair";
        }
    }
    else if (isResumePage) {
        // Bumalik sa Box/Add-ons kung meron nito ang style (Funko/Hirono), kung wala
        // (Chibi) sa "shoes" — laging ito ang huling design step
        const detailsConfig = getProductDetailsConfig();
        const hasDetailsStep = Boolean(
            detailsConfig && (detailsConfig.hirono || detailsConfig.boxes?.length)
        );
        navigationSlot = hasDetailsStep ? "productDetails" : "shoes";
    }

    if (state.currentCategory === "hirono") {
        syncHironoGenderOptions();
    }

    syncSelectedCardsFromState();


    updateSectionVisibility();
    updateSelectedItemsUI();
    updatePriceDisplay();
    renderProductDetailsPanel();


    void renderCurrentCategory();


    return true;

}


async function loadBodyModel(path, token) {

    const requestId =
        ++loadTokens.body;


    removeFromScene(
        bodyModel
    );


    bodyModel = null;


    let gltf =
        await loadGLTF(
            path
        );


    if (
        !hasRenderableMeshes(gltf) &&
        /Funko pop-girl\.glb$/i.test(path)
    ) {
        console.warn(
            "Funko Girl GLB is empty, falling back to Funko Pop Default."
        );


        disposeObject3D(
            gltf
        );


        gltf =
            await loadGLTF(
                "dressup-assets/GLB_files/Funko pop-default.glb"
            );
    }


    if (token !== renderToken) {
        disposeObject3D(
            gltf
        );
        return null;
    }


    if (requestId !== loadTokens.body) {
        disposeObject3D(
            gltf
        );
        return null;
    }


    bodyModel = gltf;

    bodyModel.name = "BodyModel";


    configureModel(
        bodyModel
    );


    scene.add(
        bodyModel
    );


    return bodyModel;

}


async function loadAccessory(slot, path, colorValue, token) {

    const requestId =
        ++loadTokens[slot];


    removeFromScene(
        currentObjects[slot]
    );


    currentObjects[slot] = null;


    // Default / placeholder items (Sando, Shorts, Shoes, Default Hair, etc.)
    // wala pang 3D file — naka-select lang sila, walang ilo-load sa viewer.
    if (isPlaceholderModelPath(path)) {
        return null;
    }


    const gltf =
        await loadGLTF(
            path
        );


    if (token !== renderToken) {
        disposeObject3D(
            gltf
        );
        return null;
    }


    if (requestId !== loadTokens[slot]) {
        disposeObject3D(
            gltf
        );
        return null;
    }


    gltf.name =
        `Selected_${slot}`;


    configureModel(
        gltf
    );


    scene.add(
        gltf
    );


    currentObjects[slot] =
        gltf;


    if (colorValue) {
        applyColorToModel(
            gltf,
            colorValue,
            {
                clearTextureMaps: true,
                skipMaterialFragments: [
                    "design",
                    "logo",
                    "print"
                ]
            }
        );
    }


    return gltf;

}


function applySkinColor(color) {

    if (!bodyModel) {
        return;
    }


    const isHirono = state.currentCategory === "hirono";

    applyColorToModel(
        bodyModel,
        color,
        {
            clearTextureMaps: false,
            skipMaterialFragments: isHirono
                ? ["stand", "pedestal", "support", "shoe", "foot", "sole", "clear_acrylic"]
                : [],
            skipNodeFragments: isHirono
                ? ["acrylic_stand", "stand", "pedestal", "support", "shoes", "foot"]
                : []
        }
    );

}


async function renderCurrentCategory() {

    const activeCategory =
        state.currentCategory;


    const token =
        ++renderToken;


    clearCurrentScene();


    if (!activeCategory) {
        setFigurePromptVisible(
            true
        );
        updateSectionVisibility();
        updateSelectedItemsUI();
        updatePriceDisplay();
        return;
    }


    const activeState =
        state[activeCategory];


    if (!activeState.model) {
        setFigurePromptVisible(
            true
        );
        updateSectionVisibility();
        updateSelectedItemsUI();
        updatePriceDisplay();
        return;
    }


    setFigurePromptVisible(
        true
    );


    try {

        const bodyPath =
            activeState.model.model;


        if (!bodyPath) {
            return;
        }


        await loadBodyModel(
            bodyPath,
            token
        );


        if (token !== renderToken) {
            return;
        }

        // Frame the base figure first so the preview stays visible even if
        // an optional accessory asset cannot be loaded.
        frameFigure();

        setFigurePromptVisible(
            false
        );


        if (activeState.skin && (activeCategory === "hirono" || activeCategory === "chibi") && activeState.skin.name === "Light") {
            // Light means the original GLB materials: do not apply a tint.
        }
        else if (activeState.skin) {
            applySkinColor(
                activeState.skin.color
            );
        }
        else if (activeCategory === "hirono" || activeCategory === "chibi") {
            // Hirono and Chibi start neutral grey until the customer chooses a skin tone.
            applySkinColor("#808080");
        }


        const order =
            getActiveSlotOrder();


        for (const slot of order) {

            const item =
                activeState[slot];


            if (!item) {
                continue;
            }


            const colorSlot =
                getSlotColorSlot(
                    slot
                );


            const colorItem =
                colorSlot
                    ? activeState[colorSlot]
                    : null;


            try {
                await loadAccessory(
                    slot,
                    item.model,
                    slot === "bottom" && activeCategory === "funko"
                        ? null
                        : (colorItem ? colorItem.color : null),
                    token
                );
            }
            catch (error) {
                console.warn(
                    `Unable to load ${slot} accessory in the preview:`,
                    error
                );
            }


            if (token !== renderToken) {
                return;
            }


            if (
                activeCategory === "funko" &&
                slot === "bottom"
            ) {
                applyBottomPartColors(
                    currentObjects.bottom,
                    activeState.pantsColor && activeState.pantsColor.color
                );
            }

        }


        frameFigure();

    }
    catch (error) {

        console.error(
            "Failed to render figure:",
            error
        );

        setFigurePromptVisible(
            true
        );

    }


    updateSectionVisibility();
    updateSelectedItemsUI();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);

}


function getDesignStepLabel(slot) {
    if (slot === "hair" || slot === "girlHair") {
        return "Hair";
    }

    if (slot === "keychainHair") {
        return "Hirono Keychain Hair";
    }

    const labels = {
        productType: "Product Type",
        mode: "Product Type",
        // Gender/Model and Skin Tone are shown on the same page, so the
        // jump dropdown lists them as a single combined entry.
        model: "Gender / Skin",
        skin: "Gender / Skin",
        top: "Top",
        girlTop: "Top",
        outfit: "Top",
        bottom: "Bottom",
        girlBottom: "Bottom",
        pants: "Bottom",
        shoes: "Shoes",
        keychainHat: "Hat"
    };

    return labels[slot] || slot
        .replace(/([A-Z])/g, " $1")
        .replace(/^./, character => character.toUpperCase());
}


function placeDesignStepPicker(currentSlot, sections) {
    const jump = document.querySelector(".design-step-jump");
    const navigation = document.getElementById("designStepNavigation");

    if (!jump || !navigation) {
        return;
    }

    // Box/Add-ons step (Funko/Hirono, hindi kailanman ma-reach ng Chibi): walang
    // "DESIGN SECTION" jump dropdown, Previous/Next na lang. Inline style dahil may
    // CSS rule na mas mataas ang specificity kaysa default [hidden].
    if (navigationSlot === "productDetails") {
        navigation.prepend(jump);
        jump.hidden = true;
        jump.style.display = "none";
        return;
    }

    // Every other design step (Hair, Top, Bottom, Shoes, etc.): keep the
    // dropdown in its original spot — first/leftmost in the navigation
    // bar, before Previous / Next — not moved up into the section title.
    navigation.prepend(jump);
    jump.hidden = false;
    jump.style.display = "";
}


// balik sa itaas ang scroll ng Figure Details tuwing lilipat ng step
function resetDressUpScroll() {
    const scrollArea = document.getElementById("dressUpRightScroll");

    if (scrollArea) {
        scrollArea.scrollTop = 0;
    }
}


function renderDesignNavigation(sequence, sections, activeState, currentSlot, pairedSlot) {

    const navigation = document.getElementById("designStepNavigation");
    const previousButton = document.getElementById("previousDesignStep");
    const nextButton = document.getElementById("nextDesignStep");
    const stepPicker = document.getElementById("designStepPicker");
    const detailsStep = navigationSlot === "productDetails";

    if (!navigation || !previousButton || !nextButton || !sequence?.length || !currentSlot) {
        if (navigation) {
            navigation.hidden = true;
        }
        return;
    }

    const currentIndex = Math.max(
        0,
        sequence.indexOf(currentSlot)
    );
    const isLastDesignStep = currentIndex >= sequence.length - 1;

    if (stepPicker) {
        stepPicker.replaceChildren();

        const figureStyleOption = document.createElement("option");
        figureStyleOption.value = "figureStyle";
        figureStyleOption.textContent = "Figure Style";
        stepPicker.appendChild(figureStyleOption);

        // "model" (Gender) and "skin" (Skin Tone) render on one and the
        // same page, so they must not appear as two separate entries in
        // this dropdown. Drop the "skin" entry and let the single
        // "Gender / Skin" entry cover both; when the user is currently on
        // the skin slot, that same entry is the one shown as selected.
        const mergesSkinIntoModel = sequence.includes("model") && sequence.includes("skin");

        sequence
            .slice(0, currentIndex + 1)
            .filter(slot => !(mergesSkinIntoModel && slot === "skin"))
            .forEach(slot => {
            const option = document.createElement("option");
            option.value = slot;
            option.textContent = getDesignStepLabel(slot);
            option.selected = slot === currentSlot ||
                (mergesSkinIntoModel && slot === "model" && currentSlot === "skin");
            stepPicker.appendChild(option);
            });

        stepPicker.onchange = () => {
            if (stepPicker.value === "figureStyle") {
                goToFigureStyle();
                return;
            }

            navigationSlot = stepPicker.value;
            updateSectionVisibility();
        resetDressUpScroll();
        };
    }

    navigation.hidden = false;
    placeDesignStepPicker(currentSlot, sections);
    previousButton.disabled = false;

    // Last design step (e.g. Shoes for Chibi) goes straight to
    // finalsummary.php if walang Box/Add-ons. Kung meron (Funko/Hirono),
    // doon muna bago dito.
    const detailsConfig = getProductDetailsConfig();
    const hasDetailsStep = Boolean(
        detailsConfig && (detailsConfig.hirono || detailsConfig.boxes?.length)
    );

    // Itago ang Previous/Next pag nasa Box/Add-ons (detailsStep) na — continueBtn na ang "Next"
    nextButton.hidden = detailsStep;
    // Inline display dahil may CSS rule na puwedeng manalo laban sa default [hidden]
    nextButton.style.display = detailsStep ? "none" : "";
    nextButton.textContent = "Next";
    const canSkipCurrentSlot = !["model", "skin", "productType", "mode"].includes(currentSlot);

    // Size tracked separately (productDetails.size), hindi sa activeState — kaya sariling check
    const sizeStepConfig = currentSlot === "size" ? getProductDetailsConfig() : null;
    const sizeStepRequired = currentSlot === "size" && Boolean(sizeStepConfig?.sizes?.length);
    const sizeStepSatisfied = !sizeStepRequired || Boolean(productDetails.sizeSelected);
    // When a slot is shown paired with another one (e.g. Gender shown
    // together with Skin Tone), both must be selected before the user
    // can move on — picking only one of the two should not unlock Next.
    const pairedSlotSatisfied = !pairedSlot || Boolean(activeState[pairedSlot]);
    nextButton.disabled =
        detailsStep ||
        (!activeState[currentSlot] && !canSkipCurrentSlot) ||
        !sizeStepSatisfied ||
        !pairedSlotSatisfied;

    previousButton.onclick = () => {
        if (detailsStep) {
            navigationSlot = sequence[sequence.length - 1];
            updateSectionVisibility();
        resetDressUpScroll();
            return;
        }

        if (currentIndex <= 0) {
            goToFigureStyle();
            return;
        }

        navigationSlot = sequence[currentIndex - 1];
        updateSectionVisibility();
        resetDressUpScroll();
    };

    nextButton.onclick = async () => {
        if (!activeState[currentSlot] && !canSkipCurrentSlot) {
            return;
        }

        if (!sizeStepSatisfied) {
            return;
        }

        if (!pairedSlotSatisfied) {
            return;
        }

        if (isLastDesignStep) {

            saveCustomizationSnapshot(true, false);

            // May Box/Add-ons pa (Funko/Hirono) — doon muna, nasa
            // parehong page pa rin. Kung wala (Chibi), tapos na ang
            // design steps — dito na papunta sa sarili nang Final
            // Order Summary page (finalsummary.php) sa halip na isang
            // section na lang sa loob ng page ring ito.
            if (!hasDetailsStep) {
                window.location.href = "finalsummary.php";
                return;
            }

            navigationSlot = "productDetails";
            updateSectionVisibility();
        resetDressUpScroll();
            return;
        }

        navigationSlot = sequence[currentIndex + 1];
        updateSectionVisibility();
        resetDressUpScroll();
    };

}


function goToFigureStyle() {

    state.currentCategory = null;
    navigationSlot = null;

    resetFigureStyleSelection();

    updateSectionVisibility();
    updateSelectedItemsUI();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);

}


function selectPendingFigureStyle(card) {

    const category =
        card.dataset.figure;

    if (!categoryPanels[category]) {
        return;
    }

    pendingFigureCategory =
        category;

    styleCards.forEach(
        function(styleCard) {
            styleCard.classList.toggle(
                "selected",
                styleCard.dataset.figure === category
            );
        }
    );

    const nextButton =
        document.getElementById("nextFigureStyle");

    if (nextButton) {
        nextButton.disabled = false;
    }

}


function resetFigureStyleSelection() {

    pendingFigureCategory = null;

    clearSelectedClasses(
        '[data-tab="figure"]'
    );

    const nextButton =
        document.getElementById("nextFigureStyle");

    if (nextButton) {
        nextButton.disabled = true;
    }

}


function selectCategory(category) {

    if (!categoryPanels[category]) {
        return;
    }

    pendingFigureCategory = null;

    try {
        localStorage.setItem(
            COMMISSION_FIGURE_CATEGORY_KEY,
            category
        );
    }
    catch (error) {
        console.warn(
            "Unable to persist figure category:",
            error
        );
    }


    // Choosing a style starts that category's flow from the beginning.
    // This prevents an old model selection from skipping the required type
    // choice (Funko boy/girl, Hirono standee/keychain, or Chibi product type).
    clearCategoryState(category);

    state.currentCategory =
        category;
    navigationSlot = null;

    syncCustomColorPickers();


    styleCards.forEach(
        function(card) {
            card.classList.toggle(
                "selected",
                card.dataset.figure === category
            );
        }
    );


    updateSectionVisibility();
    updateSelectedItemsUI();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);


    void renderCurrentCategory();

}


function selectModel(card) {

    const category =
        card.dataset.figure;


    const stateForCategory =
        state[category];


    const item =
        buildItemFromCard(
            card
        );


    // Hirono's Standee/Keychain mode is chosen in its own Product Type
    // step (selectHironoType) before Gender is shown, so it should carry
    // over as-is when a Gender card is picked here.
    const preservedHironoMode = category === "hirono"
        ? stateForCategory.mode
        : null;

    if (
        stateForCategory.model &&
        stateForCategory.model.model === item.model &&
        stateForCategory.model.name === item.name
    ) {
        return;
    }


    clearCategoryState(
        category
    );


    if (category === "hirono") {
        stateForCategory.mode =
            preservedHironoMode;
    }

    if (category === "chibi") {
        stateForCategory.productType =
            card.dataset.productType || "standee";
    }


    stateForCategory.model = item;


    markSelectedCard(
        card
    );


    state.currentCategory =
        category;
    navigationSlot = "model";


    if (category === "hirono") {
        syncHironoGenderOptions();
    }


    applyDefaultSelections(
        category
    );


    syncCustomColorPickers();

    updateSectionVisibility();
    updateSelectedItemsUI();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);


    void renderCurrentCategory();

}


function selectChibiType(card) {

    const type =
        card.dataset.productType || "standee";

    clearCategoryState("chibi");
    state.chibi.productType = type;
    state.currentCategory = "chibi";
    // Stay on the Product Type step after picking it — advancing to
    // Gender only happens when the required "Next" button is pressed.
    navigationSlot = "productType";

    document
        .querySelectorAll('[data-figure="chibi"][data-slot="model"]')
        .forEach(modelCard => {
            modelCard.hidden = modelCard.dataset.productType !== type;
        });

    markSelectedCard(card);
    updateSectionVisibility();
    updateSelectedItemsUI();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);
}


function selectHironoType(card) {

    const mode =
        card.dataset.mode || "standee";

    clearCategoryState("hirono");
    state.hirono.mode = mode;
    state.currentCategory = "hirono";
    // Stay on the Product Type step after picking it — advancing to
    // Gender only happens when the required "Next" button is pressed.
    navigationSlot = "mode";

    syncHironoGenderOptions();
    markSelectedCard(card);
    updateSectionVisibility();
    updateSelectedItemsUI();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);
}


// Hirono (galing sa DressUp): magkaiba ang body ng Boy/Girl at ng Standee/Keychain.
// Ipakita lang ang Gender cards ng napiling Product Type, at ang hair / top /
// bottom / shoes na para sa napiling gender (data-hirono-gender).
function syncHironoGenderOptions() {

    const cardMode =
        getHironoMode() === "keychain" ? "keychain" : "standee";

    const model =
        state.hirono.model;

    const selectedGender = model
        ? (/girl/i.test(model.name || "") ? "girl" : "boy")
        : null;

    document
        .querySelectorAll('[data-figure="hirono"][data-slot="model"]')
        .forEach(card => {
            card.hidden = (card.dataset.mode || "standee") !== cardMode;
            // Inline display dahil may CSS rule (display:flex) na mananalo laban sa [hidden]
            card.style.display = card.hidden ? "none" : "";
        });

    document
        .querySelectorAll('[data-figure="hirono"][data-hirono-gender]')
        .forEach(card => {
            card.hidden = Boolean(selectedGender) && card.dataset.hironoGender !== selectedGender;
        });

}


function selectSkin(card) {

    const category =
        card.dataset.figure;


    const stateForCategory =
        state[category];


    const item =
        buildItemFromCard(
            card
        );


    if (
        stateForCategory.skin &&
        stateForCategory.skin.color === item.color
    ) {
        return;
    }


    stateForCategory.skin = item;


    markSelectedCard(
        card
    );


    state.currentCategory =
        category;
    navigationSlot = "skin";


    if (category === "hirono" || category === "chibi") {
        // Reload from the original GLB so choosing Light restores the model's
        // original skin material after the neutral-grey default.
        void renderCurrentCategory();
    }
    else {
        applySkinColor(item.color);
    }


    updateSectionVisibility();
    updateSelectedItemsUI();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);

}


async function selectAccessory(card) {

    const category =
        card.dataset.figure;


    const slot =
        card.dataset.slot;

    navigationSlot = slot;


    const item =
        buildItemFromCard(
            card
        );


    const stateForCategory =
        state[category];


    const currentItem =
        stateForCategory[slot];


    if (
        currentItem &&
        currentItem.model === item.model &&
        currentObjects[slot]
    ) {
        if (slot === "bottom" && category === "funko") {
            applyBottomPartColors(
                currentObjects.bottom,
                stateForCategory.pantsColor && stateForCategory.pantsColor.color
            );
        }
        else if (item.color) {
            const colorSlot =
                getSlotColorSlot(
                    slot
                );

            const colorItem =
                colorSlot
                    ? stateForCategory[colorSlot]
                    : null;

            if (colorItem) {
                applyColorToModel(
                    currentObjects[slot],
                    colorItem.color,
                    {
                        clearTextureMaps: true,
                        skipMaterialFragments: [
                            "design",
                            "logo",
                            "print"
                        ]
                    }
                );
            }
        }

        return;
    }


    stateForCategory[slot] =
        item;


    state.currentCategory =
        category;


    markSelectedCard(
        card
    );


    const colorSlot =
        getSlotColorSlot(
            slot
        );


    const colorItem =
        colorSlot
            ? stateForCategory[colorSlot]
            : null;


    try {
        const loaded =
            await loadAccessory(
                slot,
                item.model,
                colorItem ? colorItem.color : null,
                renderToken
            );


        if (loaded) {
            frameFigure();
        }
    }
    catch (error) {
        console.error(
            `Failed to load ${slot}:`,
            error
        );
    }


    updateSectionVisibility();
    updateSelectedItemsUI();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);

}


function selectColor(card) {

    const category =
        card.dataset.figure;


    const slot =
        card.dataset.slot;


    const item =
        buildItemFromCard(
            card
        );


    const stateForCategory =
        state[category];


    const currentItem =
        stateForCategory[slot];


    if (
        currentItem &&
        currentItem.color === item.color
    ) {
        return;
    }


    stateForCategory[slot] =
        item;


    markSelectedCard(
        card
    );


    state.currentCategory =
        category;
    navigationSlot = slot;


    let accessorySlot =
        slot.replace(
            "Color",
            ""
        );

    if (category === "funko" && slot === "pantsColor") {
        accessorySlot = "bottom";
    }
    else if (
        category === "funko" &&
        slot === "bottomColor" &&
        isFunkoGirlModel(stateForCategory.model)
    ) {
        accessorySlot = "girlBottom";
    }


    const currentObject =
        getLoadedObject(
            accessorySlot
        );


    if (currentObject) {
        if (
            category === "funko" &&
            (accessorySlot === "bottom" || slot === "bottomColor")
        ) {
            applyBottomPartColors(
                currentObject,
                item.color
            );
        }
        else {
            applyColorToModel(
                currentObject,
                item.color,
                {
                    clearTextureMaps: true,
                    skipMaterialFragments: [
                        "design",
                        "logo",
                        "print"
                    ]
                }
            );
        }
    }


    updateSectionVisibility();
    updateSelectedItemsUI();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);

}


function handleCardClick(card) {

    navigationSlot = null;

    if (card.dataset.tab === "figure") {
        // Only mark the style as chosen here. The actual switch into
        // that category's flow happens when the required "Next" button
        // in the Figure Style section is pressed, so clicking a card
        // never skips straight ahead on its own.
        selectPendingFigureStyle(
            card
        );
        return;
    }


    const slot =
        card.dataset.slot;


    if (slot === "chibiType") {
        selectChibiType(
            card
        );
        return;
    }


    if (slot === "hironoType") {
        selectHironoType(
            card
        );
        return;
    }


    if (slot === "model") {
        selectModel(
            card
        );
        return;
    }


    if (slot === "skin") {
        selectSkin(
            card
        );
        return;
    }


    if (slot.endsWith("Color")) {
        selectColor(
            card
        );
        return;
    }


    void selectAccessory(
        card
    );

}


// ---------------------------------------------------------------------
// DRESS UP ORDER SUBMISSION (finalsummary.php -> submit_order.php -> payment)
// ---------------------------------------------------------------------

const DRESSUP_SLOT_LABELS = {
    hair: "Hair",
    girlHair: "Hair",
    keychainHair: "Hair",
    top: "Top",
    girlTop: "Top",
    outfit: "Top",
    bottom: "Bottom",
    girlBottom: "Bottom",
    pants: "Bottom",
    shoes: "Shoes",
    keychainHat: "Hat"
};


function showDressUpNotice(message, type) {

    let container =
        document.getElementById("noticeContainer");

    if (!container) {
        container = document.createElement("div");
        container.id = "noticeContainer";
        container.className = "notice-container";
        document.body.appendChild(container);
    }

    container.innerHTML = "";

    const noticeType = type || "error";

    const notice = document.createElement("div");
    notice.className = "notice-toast notice-" + noticeType;
    notice.innerHTML =
        '<div class="notice-icon">' +
        (noticeType === "success" ? "✓" : (noticeType === "info" ? "✦" : "!")) +
        '</div><div class="notice-message"></div>';
    notice.querySelector(".notice-message").textContent = message;

    container.appendChild(notice);

    requestAnimationFrame(() => notice.classList.add("notice-show"));

    setTimeout(() => {
        notice.classList.remove("notice-show");
        notice.classList.add("notice-hide");
        setTimeout(() => notice.remove(), 250);
    }, 5000);

}


function getDressUpProductTypeLabel() {

    if (state.currentCategory === "hirono") {
        const mode = getHironoMode();

        if (mode === "headKeychain") {
            return "Head Only Keychain";
        }

        return mode === "keychain"
            ? "Full Body Keychain"
            : "Full Body Standee";
    }

    if (state.currentCategory === "chibi") {
        return state.chibi.productType === "keychain"
            ? "Full Body Keychain"
            : "Full Body Standee";
    }

    return "Full Body Standee";

}


function getDressUpGenderLabel(activeState) {

    const modelName =
        activeState && activeState.model ? activeState.model.name : "";

    if (state.currentCategory === "funko") {
        return isFunkoGirlModel(activeState.model) ? "Funko Girl" : "Funko Boy";
    }

    if (state.currentCategory === "chibi") {
        return isChibiGirlModel(activeState.model) ? "Chibi Girl" : "Chibi Boy";
    }

    return modelName || "Hirono";

}


function cloneDressUpItem(item) {

    if (!item) {
        return null;
    }

    return {
        name: item.name || "",
        model: item.model || "",
        color: item.color || "",
        price: Number(item.price || 0),
        billable: item.billable !== false
    };

}


// buong design na kailangan para maipakita ulit ang 3D figure sa My Orders /
// staff / owner pages (tingnan ang Shared/dressup-viewer.js). Kapareho ng
// pagkakasunod at kulay na ginagamit ng renderCurrentCategory().
function buildDressUpDesignData(activeState) {

    const category = state.currentCategory;
    const slots = [];

    getActiveSlotOrder().forEach(slot => {

        if (
            slot === "model" ||
            slot === "skin" ||
            slot.toLowerCase().endsWith("color")
        ) {
            return;
        }

        const item = activeState[slot];

        if (!item) {
            return;
        }

        const isFunkoBottom =
            category === "funko" && slot === "bottom";

        let colorSlot = isFunkoBottom
            ? "pantsColor"
            : getSlotColorSlot(slot);

        // kung walang kulay sa inaasahang key (hal. shoesColor ng Hirono, o
        // hairColor na ginagamit din ng Funko Girl), hanapin ang color slot
        // na tumuturo sa accessory na ito
        if (!colorSlot || !activeState[colorSlot]) {
            const matchingColorSlot = Object.keys(activeState).find(key =>
                key.endsWith("Color") &&
                activeState[key] &&
                activeState[key].color &&
                getColorAccessorySlot(category, key) === slot
            );

            if (matchingColorSlot) {
                colorSlot = matchingColorSlot;
            }
        }

        const colorItem =
            colorSlot ? activeState[colorSlot] : null;

        const pricedItem = cloneDressUpItem(item);
        pricedItem.price = getItemPrice(item);
        pricedItem.billable = pricedItem.price > 0;

        slots.push({
            slot,
            label: DRESSUP_SLOT_LABELS[slot] || getDesignStepLabel(slot),
            item: pricedItem,
            colorName: colorItem ? (colorItem.name || "") : "",
            colorValue: colorItem ? (colorItem.color || "") : "",
            isBottom: isFunkoBottom
        });

    });

    return {
        category,
        productKey: getProductDetailsKey(),
        productType: getDressUpProductTypeLabel(),
        genderLabel: getDressUpGenderLabel(activeState),
        model: cloneDressUpItem(activeState.model),
        skin: cloneDressUpItem(activeState.skin),
        slots,
        size: productDetails.size || "",
        sizePrice: getProductSizePrice(),
        nameFee: productDetails.figureName && productDetails.figureName.trim() ? 50 : 0,
        designTotal: getActiveStateTotal(activeState)
    };

}


// box / add-ons -> parehong format na tinatanggap ng submit_order.php
// (kapareho ng Image Submission), para gumana agad ang "Custom Box" card
function buildDressUpBoxDetails() {

    const config = getProductDetailsConfig();

    if (!config) {
        return { boxDetails: null, boxAddonPrice: 0 };
    }

    if (config.hirono) {

        if (!productDetails.blindBoxSelected) {
            return { boxDetails: null, boxAddonPrice: 0 };
        }

        const blindType =
            productDetails.blindBox === "set" ? "set" : "regular";

        const blindPrice = blindType === "set" ? 350 : 150;

        const addonMap = {
            tearPaper: ["tear_blind_paper", 50],
            pouch: ["pouch", 50],
            digitalArt: ["digital_art", 150]
        };

        const blindItems = {};
        let itemsTotal = 0;

        (productDetails.hironoAddons || []).forEach(addon => {
            if (addonMap[addon]) {
                blindItems[addonMap[addon][0]] = addonMap[addon][1];
                itemsTotal += addonMap[addon][1];
            }
        });

        const dateParts =
            String(productDetails.boxDateYmd || "").split("-");

        return {
            boxDetails: {
                addonType: "hirono_blind_box",
                blindType,
                boxDesign: productDetails.boxDesignSelected ? (productDetails.boxDesign || "") : "",
                boxColor: productDetails.boxColor || "",
                letter: productDetails.boxLetter || "",
                nickname: productDetails.boxNickname || "",
                dateMonth: dateParts.length === 3 ? dateParts[1] : "",
                dateDay: dateParts.length === 3 ? dateParts[2] : "",
                blindItems
            },
            boxAddonPrice: blindPrice + itemsTotal
        };

    }

    const selectedBox =
        (config.boxes || []).find(box => box.id === productDetails.box);

    if (!selectedBox) {
        return { boxDetails: null, boxAddonPrice: 0 };
    }

    return {
        boxDetails: {
            addonType: "funko_box",
            boxType: selectedBox.id,
            boxName: productDetails.boxName || "",
            boxNumber: productDetails.boxNumber || "",
            boxColor: productDetails.boxColor || ""
        },
        boxAddonPrice: selectedBox.price
    };

}


let dressUpOrderSubmitting = false;


async function submitDressUpOrder(button) {

    if (dressUpOrderSubmitting) {
        return;
    }

    const activeState = getActiveState();
    const orderType = getCommissionOrderTypeValue();
    const bookingDate = getCommissionBookingDateValue();

    if (!orderType && !bookingDate) {
        showDressUpNotice("Please choose an Order Type and Booking Date first before continuing to payment.");
        return;
    }

    if (!orderType) {
        showDressUpNotice("Please choose an Order Type first before continuing to payment.");
        return;
    }

    if (!bookingDate) {
        showDressUpNotice("Please choose a Booking Date first before continuing to payment.");
        return;
    }

    if (!activeState || !activeState.model) {
        showDressUpNotice("Please finish designing your figure first.");
        return;
    }

    const config = getProductDetailsConfig();

    if (config && config.sizes && config.sizes.length && !productDetails.size) {
        showDressUpNotice("Please choose a figure size first.");
        return;
    }

    if (
        config &&
        config.hirono &&
        (productDetails.hironoAddons || []).length &&
        !productDetails.blindBoxSelected
    ) {
        showDressUpNotice("Please choose a Blind Box type (Regular or Set) for your add-ons, or remove the add-ons.");
        return;
    }

    const design = buildDressUpDesignData(activeState);
    const box = buildDressUpBoxDetails();

    // presyo ng figure mismo (walang rush fee — sa server kinukuwenta iyon,
    // pareho ng Image Submission)
    const figureTotal =
        design.designTotal + design.sizePrice + design.nameFee + box.boxAddonPrice;

    const figureName =
        (productDetails.figureName || "").trim() ||
        (getFigureLabel(state.currentCategory) + " Dress Up");

    const figurePayload = {
        style: getFigureLabel(state.currentCategory),
        product: design.productType,
        size: productDetails.size || "",
        sizeLabel: productDetails.size || "",
        hasSizes: Boolean(config && config.sizes && config.sizes.length),
        name: figureName,
        notes: "",
        productPrice: design.sizePrice,
        nameFee: design.nameFee,
        boxAddonPrice: box.boxAddonPrice,
        boxDetails: box.boxDetails,
        total: figureTotal,
        dressup: design
    };

    const formData = new FormData();
    formData.append("order_type", orderType);
    formData.append("order_method", "create_style");
    formData.append("booking_date", bookingDate);
    formData.append("figures", JSON.stringify([figurePayload]));

    dressUpOrderSubmitting = true;

    let orderPlaced = false;

    const originalText = button ? button.textContent : "";

    if (button) {
        button.classList.add("is-loading");
        button.setAttribute("aria-disabled", "true");
        button.textContent = "Placing order…";
    }

    try {

        const response = await fetch("submit_order.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        });

        const rawText = await response.text();

        let data = null;

        try {
            data = JSON.parse(rawText);
        }
        catch (parseError) {
            console.error("submit_order.php raw response:", rawText);
        }

        if (!data || !data.success) {
            const message =
                (data && data.message) ||
                "Something went wrong while placing your order. Please try again.";

            // sa localhost lang ibinibigay ng server ang totoong dahilan —
            // ipakita na rin para madaling ma-trace habang dine-develop
            if (data && data.debug_reason) {
                console.error("Dress Up order failed:", data.debug_reason);
                showDressUpNotice(message + " (Details: " + data.debug_reason + ")");
                return;
            }

            showDressUpNotice(message);
            return;
        }

        // naka-save na ang order — linisin ang draft para hindi maulit ang order,
        // at huwag nang i-enable ulit ang button habang nagre-redirect
        orderPlaced = true;
        clearCustomizationSnapshot();

        showDressUpNotice(
            "Order #" + data.order_id + " placed! Redirecting you to payment…",
            "success"
        );

        setTimeout(() => {
            window.location.href =
                data.payment_url ||
                ("../my-order/payment-shipping.php?order_id=" + data.order_id);
        }, 900);

    }
    catch (error) {

        console.error("Dress Up order request failed:", error);
        showDressUpNotice("Could not reach the server. Please check your connection and try again.");

    }
    finally {

        if (orderPlaced) {
            return;
        }

        dressUpOrderSubmitting = false;

        if (button) {
            button.classList.remove("is-loading");
            button.removeAttribute("aria-disabled");
            button.textContent = originalText || "Continue to Payment";
        }

    }

}


function initializeInteractions() {

    initializeCustomColorPickers();

    const nextFigureStyleButton =
        document.getElementById("nextFigureStyle");

    if (nextFigureStyleButton) {
        nextFigureStyleButton.addEventListener(
            "click",
            function() {
                if (!pendingFigureCategory) {
                    return;
                }

                selectCategory(
                    pendingFigureCategory
                );
            }
        );
    }

    styleCards.forEach(
        function(card) {
            card.addEventListener(
                "click",
                function() {
                    handleCardClick(
                        card
                    );
                }
            );
        }
    );


    itemCards.forEach(
        function(card) {
            card.addEventListener(
                "click",
                function() {
                    handleCardClick(
                        card
                    );
                }
            );
        }
    );


    if (resetBtn) {
        resetBtn.addEventListener(
            "click",
            function() {
                const resetCategory = state.currentCategory;

                state.currentCategory = resetCategory;
                navigationSlot = null;
                state.funko = createCategoryState();
                state.hirono = createCategoryState();
                state.chibi = createCategoryState();
                productDetails = {
                    productKey: "",
                    size: "",
                    sizeSelected: false,
                    figureName: "",
                    box: "",
                    boxSelected: false,
                    boxName: "",
                    boxNumber: "",
                    boxColor: "",
                    blindBox: "regular",
                    blindBoxSelected: false,
                    hironoAddons: [],
                    boxDesign: "checkered",
                    boxDesignSelected: false,
                    boxNickname: "",
                    boxLetter: "",
                    boxDateYmd: ""
                };

                clearCurrentScene();
                clearCustomizationSnapshot();

                styleCards.forEach(
                    function(card) {
                        card.classList.toggle(
                            "selected",
                            card.dataset.figure === resetCategory
                        );
                    }
                );

                updateSectionVisibility();
                updateSelectedItemsUI();
                updatePriceDisplay();
                renderProductDetailsPanel();
                setFigurePromptVisible(true);

                void renderCurrentCategory();
            }
        );
    }

    [
        [figureNameInput, "figureName"],
        [boxNameInput, "boxName"],
        [boxNumberInput, "boxNumber"],
        [boxColorInput, "boxColor"]
    ].forEach(([input, key]) => {
        if (!input) {
            return;
        }

        input.addEventListener("input", () => {
            productDetails[key] = input.value;
            renderProductDetailsPanel();
            saveProductDetails();
        });
    });


    if (continueBtn) {
        continueBtn.addEventListener(
            "click",
            async function(event) {
                const activeState =
                    getActiveState();

                if (!activeState || !activeState.model) {
                    event.preventDefault();
                    alert(
                        "Please choose at least a figure model before continuing."
                    );
                    return;
                }


                event.preventDefault();

                await renderCurrentCategory();

                saveCustomizationSnapshot(
                    true,
                    false
                );

                // Habang nasa Box/Add-ons pa lang (Funko/Hirono), ang
                // "Next" na ito ay pumupunta na sa sarili nang page:
                // finalsummary.php — hindi na isang section lang sa
                // parehong page.
                window.location.href = "finalsummary.php";

            }
        );
    }

    // "Continue to Payment" — isinusumite na ang Dress Up order (diretso
    // "Awaiting Payment", walang quotation), tapos papunta sa Payment page.
    const finalSummaryContinueBtn =
        document.getElementById(
            "finalSummaryContinueBtn"
        );

    if (finalSummaryContinueBtn) {
        finalSummaryContinueBtn.addEventListener(
            "click",
            function(event) {
                event.preventDefault();
                void submitDressUpOrder(finalSummaryContinueBtn);
            }
        );
    }

}


function init3D() {

    if (!canvas) {
        console.error(
            "[Figurify 3D] Canvas #figureViewer was not found."
        );
        return;
    }


    scene = new THREE.Scene();
    figureShadow = null;

    camera = new THREE.PerspectiveCamera(
        35,
        1,
        0.1,
        1000
    );

    camera.position.set(
        0,
        1.1,
        4.5
    );

    try {
        renderer = new THREE.WebGLRenderer(
            {
                canvas,
                alpha: true,
                antialias: true,
                preserveDrawingBuffer: true
            }
        );
    }
    catch (error) {
        console.error(
            "[Figurify 3D] WebGL initialization failed.",
            error
        );
        return;
    }

    renderer.outputColorSpace =
        THREE.SRGBColorSpace;

    renderer.setPixelRatio(
        Math.min(
            window.devicePixelRatio || 1,
            2
        )
    );

    renderer.setClearColor(
        0x000000,
        0
    );

    renderer.shadowMap.enabled = true;

    controls = new OrbitControls(
        camera,
        renderer.domElement
    );

    controls.enableDamping = true;
    controls.dampingFactor = 0.08;
    controls.enablePan = false;
    controls.minDistance = 1.5;
    controls.maxDistance = 12;

    scene.add(
        new THREE.AmbientLight(
            0xffffff,
            1.6
        )
    );

    const keyLight =
        new THREE.DirectionalLight(
            0xffffff,
            1.3
        );

    keyLight.position.set(
        4,
        6,
        6
    );
    scene.add(
        keyLight
    );

    const fillLight =
        new THREE.DirectionalLight(
            0xfff0f7,
            0.8
        );

    fillLight.position.set(
        -4,
        3,
        4
    );
    scene.add(
        fillLight
    );

    const rimLight =
        new THREE.DirectionalLight(
            0xffffff,
            0.45
        );

    rimLight.position.set(
        0,
        4,
        -5
    );
    scene.add(
        rimLight
    );

    resize3D();

    if (figureResizeObserver) {
        figureResizeObserver.disconnect();
    }

    if (
        figureArea &&
        typeof ResizeObserver !== "undefined"
    ) {
        figureResizeObserver =
            new ResizeObserver(
                function() {
                    resize3D();
                }
            );

        figureResizeObserver.observe(
            figureArea
        );
    }

    requestAnimationFrame(
        resize3D
    );

    window.addEventListener(
        "resize",
        resize3D
    );

}


function animate() {

    requestAnimationFrame(
        animate
    );


    if (controls) {
        controls.update();
    }


    if (
        renderer &&
        scene &&
        camera
    ) {
        renderer.render(
            scene,
            camera
        );
    }

}


// bootstrap

refreshOptionPriceLabels();
initializeInteractions();
updateSectionVisibility();
updateSelectedItemsUI();
updatePriceDisplay();
setFigurePromptVisible(
    true
);
init3D();

// The editor must always start with no selected style. Saved customization is
// only restored by the preview/details pages, where an existing design is
// intentionally being reviewed.
const restoredCustomization = (isPreviewPage || isDetailsPage || isFinalSummaryPage || isEditPage || isResumePage)
    ? restoreCustomizationFromStorage()
    : false;

if (
    (isPreviewPage || isDetailsPage || isFinalSummaryPage) &&
    !restoredCustomization
) {

    // Walang naka-save na customization — ibalik sa commission.php (ang aktwal na editor page)
    window.location.replace(
        "commission.php"
    );

}
else {

    // The editor always starts at FIGURE STYLE. Do not restore the category
    // saved by the commission page here, otherwise FIGURE TYPE is shown
    // before the user chooses a style in this session.

    animate();

}

// dressup.js ay type="module" kaya hindi global ang functions dito by default —
// i-expose explicit para magamit ng commission.js pag nag-refresh ng Order Summary.
window.updatePriceDisplay = updatePriceDisplay;
window.renderPreviewSummary = renderPreviewSummary;
