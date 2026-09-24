<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

/* DATABASE CONNECTION */
$conn = new mysqli("localhost", "root", "", "figurify_db");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

require_once __DIR__ . "/../helpers/booking_helper.php";

$bookedDates = figurify_get_booked_dates($conn);

/* Rush/Non-Rush booking limits, ginagamit ng calendar sa commission.js
   para i-enforce ang daily/monthly caps ng Rush Order. */
$rushDayCounts        = figurify_get_rush_day_counts($conn);
$nonrushDateCounts    = figurify_get_nonrush_date_counts($conn);

/* DEFAULT CUSTOMER INFORMATION */
$customer_name = "";
$customer_email = "";
$profile_picture = "";  

/* GET LOGGED-IN CUSTOMER */
if (isset($_SESSION["user_id"])) {

    $user_id = $_SESSION["user_id"];

    $stmt = $conn->prepare("
        SELECT full_name, email, profile_picture
        FROM users
        WHERE user_id = ?
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $customer_name = $user["full_name"];
        $customer_email = $user["email"];
        $profile_picture = $user["profile_picture"];
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="../Image/logoclay.png">

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Clay and Stuff — Commission</title>

  <link rel="stylesheet" href="commission.css?v=30" />

</head>
<body>

<!-- navbar -->
<?php include "../Shared/navbar.php"; ?>

<!-- hero -->

<header class="hero">

    <div class="hero-content">

        <span class="hero-small">
            LET'S MAKE SOMETHING PERSONAL
        </span>

        <h1>
            Commission a Figure,
            <br>
            Made Just for You
        </h1>

        <p>
            Turn your ideas, memories, and favorite people
            into a little clay masterpiece.
        </p>

    </div>

</header>


<!-- main -->

<main class="page">


<!-- STEP 01 — order schedule -->

<section>

    <div class="section-header">

        <div class="section-icon">
            ✧
        </div>

        <div>

            <h2>
                When Do You Need Your Figure?
            </h2>

            <p>
                Pick your order type and choose a booking date
                before deciding how you'd like to create your
                figure.
            </p>

        </div>

    </div>


    <div class="form-shell schedule-shell">

        <div class="schedule-center">

                <!-- RUSH / NON-RUSH -->

                <div class="order-type">


                    <!-- RUSH -->

                    <button
                        type="button"
                        class="order-card"
                        id="rushCard"
                        onclick="selectOrderType('rush')"
                    >

                        <div class="check">
                            ✓
                        </div>

                        <div class="order-icon">
                            ⚡
                        </div>

                        <h4>
                            Rush Order
                        </h4>

                        <p>
                            Earliest booking date is 7 days
                            from the date of order.
                        </p>

                        <div class="order-fee">
                            + Rush Fee (based on product type per figure)
                        </div>

                    </button>


                    <!-- NON RUSH -->

                    <button
                        type="button"
                        class="order-card"
                        id="nonrushCard"
                        onclick="selectOrderType('nonrush')"
                    >

                        <div class="check">
                            ✓
                        </div>

                        <div class="order-icon">
                            🌷
                        </div>

                        <h4>
                            Non-Rush Order
                        </h4>

                        <p>
                            Booking is scheduled on the last
                            day of the selected month.
                        </p>

                        <div class="order-fee">
                            No additional fee
                        </div>

                    </button>

                </div>


                <!-- calendar -->

                <div class="calendar-box">

                    <div class="form-title">

                        <span>
                            BOOKING DATE
                        </span>

                        <h3>
                            Choose Your Date
                        </h3>

                    </div>


                    <div class="calendar-header">

                        <button
                            type="button"
                            onclick="changeMonth(-1)"
                            id="prevMonth"
                            class="calendar-arrow-btn"
                            aria-label="Previous month"
                        >
                            <svg class="calendar-arrow-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                        <h3 id="calendarTitle">
                            Calendar
                        </h3>

                        <button
                            type="button"
                            onclick="changeMonth(1)"
                            id="nextMonth"
                            class="calendar-arrow-btn"
                            aria-label="Next month"
                        >
                            <svg class="calendar-arrow-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                    </div>


                    <div class="weekdays">

                        <span>SUN</span>
                        <span>MON</span>
                        <span>TUE</span>
                        <span>WED</span>
                        <span>THU</span>
                        <span>FRI</span>
                        <span>SAT</span>

                    </div>


                    <div
                        class="days"
                        id="calendarDays"
                    ></div>


                    <!-- selected date — nasa taas ng legend, dikit sa ibaba ng date buttons -->

                    <div
                        class="selected-date"
                        id="selectedDate"
                    ></div>


                    <!-- legend, part na ng calendar card mismo -->

                    <div class="calendar-legend">

                        <div class="calendar-legend-item">
                            <span class="calendar-legend-dot legend-available"></span>
                            <span>Available</span>
                        </div>

                        <div class="calendar-legend-item">
                            <span class="calendar-legend-dot legend-booked"></span>
                            <span>Booked</span>
                        </div>

                        <div class="calendar-legend-item">
                            <span class="calendar-legend-dot legend-unavailable"></span>
                            <span>Unavailable</span>
                        </div>

                        <div class="calendar-legend-item">
                            <span class="calendar-legend-dot legend-selected"></span>
                            <span>Selected</span>
                        </div>

                        <div class="calendar-legend-item">
                            <span class="calendar-legend-dot legend-today"></span>
                            <span>Today</span>
                        </div>

                    </div>

                </div>

                <!-- notes — static content, laging visible, hindi dependent sa Rush/Non-Rush -->

                <div
                    class="calendar-note"
                    id="calendarNote"
                >
                    <strong>Notes</strong>

                    <ul>
                        <li>
                            <strong>Rush Order:</strong>
                            may additional processing time rules
                            (7 days minimum).
                        </li>
                        <li>
                            <strong>Non-Rush Order:</strong>
                            only the last day of the selected
                            month is available. Booking must be
                            made at least one week before that
                            date.
                        </li>
                    </ul>
                </div>

            </div>

        </div>

</section>


<!-- STEP 02 -->

<section>

    <div class="section-header">

        <div class="section-icon">
            ✦
        </div>

        <div>

            <h2>
                How Would You Like To Create It?
            </h2>

            <p>
                Choose how you want us to create your figure.
            </p>

        </div>

    </div>


    <div class="method-grid">


        <!-- IMAGE SUBMISSION -->

        <button
            type="button"
            class="method-card"
            id="referenceCard"
            onclick="selectMethod('reference')"
        >

            <div class="method-picture">
                📷
            </div>

            <div class="method-content">

                <span class="method-label">
                    STAFF QUOTATION
                </span>

                <h3>
                    Image Submission
                </h3>

                <p>
                    Upload your reference images and tell us
                    what you want us to create. Our staff will
                    review your request and provide the final
                    quotation. You may add multiple figures
                    to a single order.
                </p>

                <strong>
                    Build your custom order →
                </strong>

            </div>

        </button>


        <!-- CREATE STYLE -->

        <button
            type="button"
            class="method-card"
            id="styleCard"
            onclick="openDressUp()"
        >

            <div class="method-picture">
                ✨
            </div>

            <div class="method-content">

                <span class="method-label">
                    CREATE YOUR DESIGN
                </span>

                <h3>
                    Dress Up
                </h3>

                <p>
                    Choose a style and customize your own
                    figure using our design tools.
                </p>

                <strong>
                    Open Dress Up →
                </strong>

            </div>

        </button>

    </div>

</section>


<!-- image submission form -->

<section
    class="order-form"
    id="customReferenceForm"
>

    <div class="section-header">

        <div class="section-icon">
            ♡
        </div>

        <div>

            <h2>
                Build Your Image Submission Order
            </h2>

            <p>
                Add one or more figures to your order using
                your reference images and notes.
            </p>

        </div>

    </div>


    <div class="form-shell">


        <div class="order-workspace">


            <!-- left side -->

            <div class="order-left">


                <!-- figure details (draft figure — add multiple) -->

                <div class="details-box">

                    <div class="details-box-heading">

                        <div class="details-box-heading-left">

                            <h3>
                                Figure Details
                            </h3>

                            <span
                                class="figure-index-badge"
                                id="figureIndexBadge"
                            >
                                FIGURE #1
                            </span>

                        </div>

                        <div class="figure-header-actions">

                            <button
                                type="button"
                                class="add-figure-button"
                                id="addFigureButton"
                                onclick="addFigureToCart()"
                            >
                                + Add Another Order
                            </button>

                            <button
                                type="button"
                                class="clear-figure-button"
                                onclick="cancelEditFigure()"
                            >
                                Clear / Cancel
                            </button>

                        </div>

                    </div>

                    <p>
                        Choose the style, product type, size,
                        and name of your figure. You can add
                        as many figures as you want to this
                        single order.
                    </p>


                    <div
                        class="editing-note"
                        id="editingNote"
                    >
                        ✎ You are editing a figure already in
                        your order. Saving will update it in
                        place.
                    </div>


                    <!-- FIGURE STYLE -->

                    <div class="field-group">

                        <label>
                            Figure Style
                        </label>

                        <div class="option-grid">


                            <button
                                type="button"
                                class="image-option style-option"
                                data-style="Chibi"
                                onclick="selectFigureStyle(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/chibi.jpg"
                                    alt="Chibi"
                                >

                                <span class="image-option-name">
                                    Chibi
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option style-option"
                                data-style="Funko Pop"
                                onclick="selectFigureStyle(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/funko.jpg"
                                    alt="Funko Pop"
                                >

                                <span class="image-option-name">
                                    Funko Pop
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option style-option"
                                data-style="Hirono"
                                onclick="selectFigureStyle(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/hirono.jpg"
                                    alt="Hirono"
                                >

                                <span class="image-option-name">
                                    Hirono
                                </span>

                            </button>

                        </div>

                    </div>




                    <!-- PRODUCT TYPE -->

                    <div class="field-group" id="productTypeFieldGroup">

                        <label>
                            Product Type
                        </label>


                        <!-- CHIBI product type grid -->

                        <div
                            class="product-grid product-style-group"
                            data-style-group="Chibi"
                        >

                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Full Body Standee"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/ch-fbs.jpg"
                                    alt="Full Body Standee"
                                >

                                <span class="image-option-name">
                                    Full Body Standee
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Full Body Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/ch-fbk.jpg"
                                    alt="Full Body Keychain"
                                >

                                <span class="image-option-name">
                                    Full Body Keychain
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Half Body Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/ch-hbk.jpg"
                                    alt="Half Body Keychain"
                                >

                                <span class="image-option-name">
                                    Half Body Keychain
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Head Only Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/ch-ho.jpg"
                                    alt="Head Only Keychain"
                                >

                                <span class="image-option-name">
                                    Head Only Keychain
                                </span>

                            </button>

                        </div>


                        <!-- FUNKO POP product type grid -->

                        <div
                            class="product-grid product-style-group"
                            data-style-group="Funko Pop"
                            style="display:none;"
                        >

                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Full Body Standee"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/funko-fbs.jpg"
                                    alt="Full Body Standee"
                                >

                                <span class="image-option-name">
                                    Full Body Standee
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Full Body Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/funko-fbk.jpg"
                                    alt="Full Body Keychain"
                                >

                                <span class="image-option-name">
                                    Full Body Keychain
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Half Body Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/funko-hbk.jpg"
                                    alt="Half Body Keychain"
                                >

                                <span class="image-option-name">
                                    Half Body Keychain
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Head Only Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/funko-ho.jpg"
                                    alt="Head Only Keychain"
                                >

                                <span class="image-option-name">
                                    Head Only Keychain
                                </span>

                            </button>

                        </div>


                        <!-- HIRONO product type grid -->

                        <div
                            class="product-grid product-style-group"
                            data-style-group="Hirono"
                            style="display:none;"
                        >

                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Full Body Standee"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/hirono-fbs.jpg"
                                    alt="Full Body Standee"
                                >

                                <span class="image-option-name">
                                    Full Body Standee
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Full Body Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/hirono-fbk.jpg"
                                    alt="Full Body Keychain"
                                >

                                <span class="image-option-name">
                                    Full Body Keychain
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Half Body Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/hirono-hbk.jpg"
                                    alt="Half Body Keychain"
                                >

                                <span class="image-option-name">
                                    Half Body Keychain
                                </span>

                            </button>


                            <button
                                type="button"
                                class="image-option product-option"
                                data-product="Head Only Keychain"
                                onclick="selectProductType(this)"
                            >

                                <span class="image-check">
                                    ✓
                                </span>

                                <img
                                    class="image-option-image"
                                    src="../Image/hirono-ho.jpg"
                                    alt="Head Only Keychain"
                                >

                                <span class="image-option-name">
                                    Head Only Keychain
                                </span>

                            </button>

                        </div>

                    </div>


                    <!-- SIZE -->

                    <div class="field-group" id="sizeFieldGroup">

                        <label>
                            Size
                        </label>

                        <div
                            class="size-grid"
                            id="sizeGrid"
                        >

                            <button
                                type="button"
                                class="size-option"
                                data-size="2"
                                onclick="selectSize(this)"
                            >
                                2"
                            </button>

                            <button
                                type="button"
                                class="size-option"
                                data-size="3"
                                onclick="selectSize(this)"
                            >
                                3"
                            </button>

                            <button
                                type="button"
                                class="size-option"
                                data-size="3.5"
                                onclick="selectSize(this)"
                            >
                                3.5"
                            </button>

                            <button
                                type="button"
                                class="size-option"
                                data-size="4"
                                onclick="selectSize(this)"
                            >
                                4"
                            </button>

                            <button
                                type="button"
                                class="size-option"
                                data-size="5"
                                onclick="selectSize(this)"
                            >
                                5"
                            </button>

                        </div>


                        <div
                            class="no-size-message"
                            id="noSizeMessage"
                        >
                            ✓ No size required for this product.
                        </div>

                    </div>


                    <!-- FIGURE NAME -->

                    <div class="field-group">

                        <label for="figureName">
                            Figure Name — ₱50
                        </label>

                        <input
                            class="field figure-name-field"
                            type="text"
                            id="figureName"
                            maxlength="50"
                            placeholder="Enter your Figure name"
                            oninput="updateFigureName()"
                        >

                    </div>

                    <!-- REFERENCE IMAGES -->

                    <div class="field-group">

                        <label>
                            Reference Images
                            <span class="upload-limit-inline" id="uploadLimitText">
                                (Maximum 5 images)
                            </span>
                        </label>

                        <div class="image-upload-area">

                            <div
                                class="upload-dropzone"
                                id="uploadDropzone"
                            >

                                <input
                                    type="file"
                                    id="referenceImages"
                                    class="upload-input"
                                    accept="image/*"
                                    multiple
                                >

                                <div
                                    class="image-count-badge"
                                    id="imageCount"
                                >
                                    0 / 5
                                </div>

                                <!-- buong box na ang clickable/droppable para sa file picker, kaya wala nang hiwalay na "+ Add Images" button -->


                                <div class="image-preview-wrap">

                                    <div
                                        class="upload-subtitle"
                                        id="uploadDragText"
                                    >
                                        Drag and drop your images here
                                    </div>

                                    <div
                                        class="image-preview-grid"
                                        id="imagePreviewGrid"
                                    ></div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- NOTES -->

                    <div class="field-group notes-box">

                        <label for="notes">
                            Notes
                        </label>

                        <textarea
                            id="notes"
                            placeholder="Please include any important details about your requested figure."
                        ></textarea>

                    </div>

                </div>
                <!-- /.details-box (figure info) -->


                <!-- box/add-ons container, shows Funko Box or Hirono Blind section depende sa style -->

                <div class="box-addon-box">

                    <div class="box-addon-box-heading">

                        <h3>
                            Box / Add-ons
                        </h3>

                    </div>

                    <!-- custom funko box, shows only if style = Funko Pop -->

                    <div
                        class="field-group style-addon-section"
                        id="funkoBoxSection"
                    >

                        <label>
                            Custom Funko Box
                        </label>

                        <p class="addon-hint">
                            Funko Pop figures come with a custom
                            box. Choose Solo or Couple Box, and
                            fill in the box details below.
                        </p>

                        <div class="box-type-grid">

                            <button
                                type="button"
                                class="box-type-option"
                                data-box-type="solo"
                                data-box-price="500"
                                onclick="selectBoxType(this)"
                            >
                                <span class="image-check">✓</span>
                                <div
                                    class="box-type-image"
                                    style="background:linear-gradient(145deg,#ffe0ef,#f6c6de);"
                                ></div>
                                <div class="box-type-info">
                                    <span class="box-type-name">Solo Box</span>
                                    <span class="box-type-price">₱500</span>
                                </div>
                            </button>

                            <button
                                type="button"
                                class="box-type-option"
                                data-box-type="couple"
                                data-box-price="800"
                                onclick="selectBoxType(this)"
                            >
                                <span class="image-check">✓</span>
                                <div
                                    class="box-type-image"
                                    style="background:linear-gradient(145deg,#e8ddff,#d5c4f7);"
                                ></div>
                                <div class="box-type-info">
                                    <span class="box-type-name">Couple Box</span>
                                    <span class="box-type-price">₱800</span>
                                </div>
                            </button>

                        </div>

                        <div
                            class="box-locked-message hide"
                            id="boxLockedMessage"
                        >
                            Please select a box type above to
                            unlock the box details form.
                        </div>

                        <div
                            class="box-detail-fields show"
                            id="funkoBoxFields"
                        >

                            <div class="field-group">
                                <label for="funkoBoxName">Name (to print on box) <span class="optional-tag">(optional)</span></label>
                                <input
                                    class="field"
                                    type="text"
                                    id="funkoBoxName"
                                    maxlength="50"
                                    placeholder="Example: Omar"
                                    oninput="updateAllSummary()"
                                >
                            </div>

                            <div class="field-group">
                                <label for="funkoBoxNumber">Box Number <span class="optional-tag">(optional)</span></label>
                                <input
                                    class="field"
                                    type="text"
                                    id="funkoBoxNumber"
                                    maxlength="20"
                                    placeholder="Example: 001"
                                    oninput="updateAllSummary()"
                                >
                            </div>

                            <div class="field-group">
                                <label for="funkoBoxColor">Box Color <span class="optional-tag">(optional)</span></label>
                                <input
                                    class="field"
                                    type="text"
                                    id="funkoBoxColor"
                                    maxlength="30"
                                    placeholder="Example: Pastel Pink"
                                    oninput="updateAllSummary()"
                                >
                            </div>

                        </div>

                    </div>


                    <!-- hirono blind box, shows only if style = Hirono -->

                    <div
                        class="field-group style-addon-section"
                        id="hironoBlindSection"
                    >

                        <label>
                            Blind Box Type
                        </label>

                        <p class="addon-hint">
                            Hirono figures are offered as a blind
                            box. Choose Regular or Set, then fill
                            in the details and any optional extras
                            below.
                        </p>

                        <div class="box-type-grid">

                            <button
                                type="button"
                                class="box-type-option"
                                data-blind-type="regular"
                                data-blind-price="150"
                                onclick="selectBlindType(this)"
                            >
                                <span class="image-check">✓</span>
                                <img
                                    class="box-type-image"
                                    src="../Image/Hirono.jpg"
                                    alt="Regular Blind Box"
                                >
                                <div class="box-type-info">
                                    <span class="box-type-name">Regular Blind Box</span>
                                    <span class="box-type-price">₱150</span>
                                </div>
                            </button>

                            <button
                                type="button"
                                class="box-type-option"
                                data-blind-type="set"
                                data-blind-price="350"
                                onclick="selectBlindType(this)"
                            >
                                <span class="image-check">✓</span>
                                <img
                                    class="box-type-image"
                                    src="../Image/blindboxset.jpg"
                                    alt="Blind Box Set"
                                >
                                <div class="box-type-info">
                                    <span class="box-type-name">Blind Box Set</span>
                                    <span class="box-type-price">₱350</span>
                                    <span class="box-type-note">Includes: Tear Blind Paper &amp; Digital Art only</span>
                                </div>
                            </button>

                        </div>

                        <!-- optional extras: individually selectable, hindi na naka-tie sa Regular vs Set,
                             customer picks kung ano lang gusto niya at may sariling add-on price bawat isa -->

                        <div
                            class="blind-set-contents show"
                            id="blindSetContents"
                        >

                            <div class="blind-set-title" id="blindSetTitle">
                                Optional Extras (Tap To Add)
                            </div>

                            <div class="blind-set-grid">

                                <button
                                    type="button"
                                    class="blind-set-item"
                                    data-blind-item="tear_blind_paper"
                                    data-blind-item-price="50"
                                    onclick="selectBlindSetItem(this)"
                                >
                                    <span class="image-check">✓</span>
                                    <img
                                        src="../Image/tear.jpg"
                                        alt="Tear Blind Paper"
                                    >
                                    <span class="blind-set-item-name">Tear Blind Paper</span>
                                    <span class="blind-set-item-price">₱50</span>
                                </button>

                                <button
                                    type="button"
                                    class="blind-set-item"
                                    data-blind-item="pouch"
                                    data-blind-item-price="120"
                                    onclick="selectBlindSetItem(this)"
                                >
                                    <span class="image-check">✓</span>
                                    <img
                                        src="../Image/pouch.jpg"
                                        alt="Pouch"
                                    >
                                    <span class="blind-set-item-name">Pouch</span>
                                    <span class="blind-set-item-price">₱120</span>
                                </button>

                                <button
                                    type="button"
                                    class="blind-set-item"
                                    data-blind-item="digital_art"
                                    data-blind-item-price="150"
                                    onclick="selectBlindSetItem(this)"
                                >
                                    <span class="image-check">✓</span>
                                    <img
                                        src="../Image/photocard.jpg"
                                        alt="Digital Art w/ Photo Card"
                                    >
                                    <span class="blind-set-item-name">Digital Art w/ Photo Card</span>
                                    <span class="blind-set-item-price">₱150</span>
                                </button>

                            </div>

                        </div>


                        <div
                            class="box-detail-fields"
                            id="hironoBlindFields"
                        >

                            <div class="field-group" style="grid-column:1 / -1;">

                                <label>Box Design</label>

                                <p class="addon-hint">
                                    Choose a box design for your
                                    Blind Box.
                                </p>

                                <div class="box-type-grid">

                                    <button
                                        type="button"
                                        class="box-type-option"
                                        data-box-design="checkered"
                                        onclick="selectBoxDesign(this)"
                                    >
                                        <span class="image-check">✓</span>
                                        <img
                                            class="box-type-image"
                                            src="../Image/checkered.jpg"
                                            alt="Checkered"
                                        >
                                        <div class="box-type-info">
                                            <span class="box-type-name">Checkered</span>
                                        </div>
                                    </button>

                                    <button
                                        type="button"
                                        class="box-type-option"
                                        data-box-design="hirono_peek"
                                        onclick="selectBoxDesign(this)"
                                    >
                                        <span class="image-check">✓</span>
                                        <img
                                            class="box-type-image"
                                            src="../Image/peek.jpg"
                                            alt="Hirono Peek"
                                        >
                                        <div class="box-type-info">
                                            <span class="box-type-name">Hirono Peek</span>
                                        </div>
                                    </button>

                                </div>

                            </div>

                            <div class="field-group" id="hironoBoxColorGroup">
                                <label for="hironoBoxColor">Box Color</label>
                                <div id="hironoBoxColorWrap">
                                    <input
                                        class="field"
                                        type="text"
                                        id="hironoBoxColor"
                                        maxlength="30"
                                        placeholder="Example: Cream White"
                                        oninput="updateAllSummary()"
                                    >
                                </div>
                            </div>

                            <div class="field-group">
                                <label for="hironoNickname">Nickname</label>
                                <input
                                    class="field"
                                    type="text"
                                    id="hironoNickname"
                                    maxlength="50"
                                    placeholder="Example: Bubbles"
                                    oninput="updateAllSummary()"
                                >
                            </div>

                            <div class="field-group" style="grid-column:1 / -1;">
                                <label for="hironoLetter">Letter (Short Love Letter)</label>
                                <textarea
                                    class="field"
                                    id="hironoLetter"
                                    maxlength="300"
                                    rows="4"
                                    placeholder="Write a short love letter/message here..."
                                    oninput="updateAllSummary()"
                                ></textarea>
                            </div>

                            <div class="field-group">
                                <label for="hironoDateMonth">Date (Month)</label>
                                <select
                                    class="field"
                                    id="hironoDateMonth"
                                    onchange="updateAllSummary()"
                                >
                                    <option value="">Month</option>
                                    <option value="01">January</option>
                                    <option value="02">February</option>
                                    <option value="03">March</option>
                                    <option value="04">April</option>
                                    <option value="05">May</option>
                                    <option value="06">June</option>
                                    <option value="07">July</option>
                                    <option value="08">August</option>
                                    <option value="09">September</option>
                                    <option value="10">October</option>
                                    <option value="11">November</option>
                                    <option value="12">December</option>
                                </select>
                            </div>

                            <div class="field-group">
                                <label for="hironoDateDay">Date (Day)</label>
                                <select
                                    class="field"
                                    id="hironoDateDay"
                                    onchange="updateAllSummary()"
                                >
                                    <option value="">Day</option>
                                    <?php for($d = 1; $d <= 31; $d++): ?>
                                    <option value="<?php echo str_pad($d, 2, "0", STR_PAD_LEFT); ?>">
                                        <?php echo $d; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>


                            <!-- BOX REFERENCE IMAGES
                                 Limit depende sa napiling Box Design:
                                 Checkered -> 5 images
                                 Hirono Peek -> 3 images -->

                            <div class="field-group" style="grid-column:1 / -1;">

                                <label>
                                    Box Reference Images
                                </label>

                                <div class="image-upload-area">

                                    <div
                                        class="upload-dropzone"
                                        id="hironoImageDropzone"
                                    >

                                        <input
                                            type="file"
                                            id="hironoImages"
                                            class="upload-input"
                                            accept="image/*"
                                            multiple
                                        >

                                        <div class="upload-placeholder">

                                            <div class="upload-icon">
                                                📷
                                            </div>

                                            <div class="upload-title">
                                                Add Box Reference Images
                                            </div>

                                            <div class="upload-subtitle">
                                                Drag &amp; drop your images anywhere here
                                            </div>

                                            <button
                                                type="button"
                                                class="upload-add-button"
                                                id="hironoUploadAddButton"
                                            >
                                                + Add Images
                                            </button>

                                            <div
                                                class="upload-limit"
                                                id="hironoImageLimitText"
                                            >
                                                Select a Box Design above first
                                            </div>

                                        </div>


                                        <div
                                            class="image-preview-grid"
                                            id="hironoImagePreviewGrid"
                                        ></div>


                                        <div
                                            class="image-empty"
                                            id="hironoImageEmpty"
                                        >
                                            No box reference images added yet.
                                        </div>

                                    </div>


                                    <div class="image-counter">

                                        <span>
                                            Box reference images
                                        </span>

                                        <strong id="hironoImageCount">
                                            0 / 0
                                        </strong>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>
                <!-- /.box-addon-box -->

            </div>


            <!-- right side — order summary -->

            <aside class="summary-column">

                <div class="receipt">


                    <div class="receipt-header">

                        <h3>
                            Order Summary
                        </h3>

                        <div class="receipt-icon">
                            ₱
                        </div>

                    </div>


                    <p class="receipt-subtitle">
                        Your selected order details
                    </p>


                    <!-- ORDER TYPE -->

                    <div class="selected-order-box">

                        <div class="order-type-summary">

                            <div class="order-type-summary-left">

                                <div class="selected-order-label">
                                    ORDER TYPE
                                </div>

                                <div
                                    class="selected-order-value"
                                    id="summaryOrderType"
                                >
                                    Not selected
                                </div>

                            </div>

                            <div
                                class="order-type-price"
                                id="orderTypePrice"
                            >
                                ₱0
                            </div>

                        </div>

                    </div>


                    <!-- BOOKING DATE -->

                    <div class="selected-order-box">

                        <div class="selected-order-label">
                            BOOKING DATE
                        </div>

                        <div
                            class="selected-order-value"
                            id="summaryBookingDate"
                        >
                            Not selected
                        </div>

                        <div
                            class="selected-order-date"
                            id="summaryBookingStatus"
                        >
                            Choose a date from the calendar
                        </div>

                    </div>


                    <!-- figures in this order (multi-figure cart) -->

                    <div class="figure-cart-box">

                        <div class="figure-cart-title">

                            <span>
                                FIGURES IN THIS ORDER
                            </span>

                            <span
                                class="figure-cart-count"
                                id="figureCartCount"
                            >
                                0 figures
                            </span>

                        </div>


                        <div id="figureCartList"></div>


                        <div
                            class="cart-empty"
                            id="cartEmptyMessage"
                        >
                            No figures added yet.
                        </div>


                        <div class="cart-subtotal-row">

                            <span>
                                Figures Subtotal
                            </span>

                            <span id="cartSubtotalPrice">
                                ₱0
                            </span>

                        </div>

                    </div>


                    <!-- CURRENT DRAFT FIGURE (NOT YET ADDED) -->

                    <div class="figure-summary-box">

                        <div class="figure-summary-title">
                            CURRENT FIGURE (UNSAVED)
                        </div>


                        <!-- FIGURE STYLE -->

                        <div class="figure-summary-item summary-no-price">

                            <div class="figure-summary-info">

                                <div
                                    class="figure-summary-name"
                                    id="summaryStyle"
                                >
                                    Not selected
                                </div>

                                <div class="figure-summary-description">
                                    Figure Style
                                </div>

                            </div>

                            <div class="figure-summary-price">
                                ₱0
                            </div>

                        </div>


                        <!-- PRODUCT TYPE -->

                        <div class="figure-summary-item">

                            <div class="figure-summary-info">

                                <div
                                    class="figure-summary-name"
                                    id="summaryProduct"
                                >
                                    Not selected
                                </div>

                                <div class="figure-summary-description">
                                    Product Type
                                </div>

                            </div>

                            <div
                                class="figure-summary-price"
                                id="productPrice"
                            >
                                ₱0
                            </div>

                        </div>


                        <!-- SIZE -->

                        <div class="figure-summary-item">

                            <div class="figure-summary-info">

                                <div
                                    class="figure-summary-name"
                                    id="summarySize"
                                >
                                    Not selected
                                </div>

                                <div class="figure-summary-description">
                                    Size
                                </div>

                            </div>

                            <div
                                class="figure-summary-price"
                                id="sizePrice"
                            >
                                ₱0
                            </div>

                        </div>


                        <!-- FIGURE NAME — kapareho ng pattern ng Style/Product/Size rows sa taas -->

                        <div class="figure-summary-item">

                            <div class="figure-summary-info">

                                <div
                                    class="figure-summary-name"
                                    id="summaryFigureName"
                                >
                                    Not entered
                                </div>

                                <div class="figure-summary-description">
                                    Figure Name
                                </div>

                            </div>

                            <div
                                class="figure-summary-price"
                                id="nameFee"
                            >
                                ₱0
                            </div>

                        </div>


                        <!-- CUSTOM BOX / BLIND BOX ADD-ON -->

                        <div
                            class="figure-name-summary box-addon-summary-row"
                            id="boxAddonSummaryRow"
                            style="margin-top:6px;"
                        >

                            <span id="boxAddonLabel">
                                Box Add-on
                            </span>

                            <strong id="boxAddonFee">
                                ₱0
                            </strong>

                        </div>


                        <!-- box add-on sub-details: selected extras + box design, one per line -->

                        <div
                            class="box-addon-sub-details"
                            id="boxAddonSubDetails"
                        ></div>

                    </div>


                    <!-- TOTAL -->

                    <div class="summary-total">

                        <span>
                            Estimated Total
                        </span>

                        <strong id="totalPrice">
                            ₱0
                        </strong>

                    </div>


                    <!-- NOTE -->

                    <div class="receipt-note">

                        <strong>
                            Estimated Price Only
                        </strong>

                        <br><br>

                        This is only an estimated amount.
                        The Rush Fee (if applicable) is charged
                        once per order, not per figure. The
                        final quotation will be determined by
                        our staff after reviewing your reference
                        images and requested details.

                    </div>

                </div>

            </aside>

        </div>


        <!-- SUBMIT -->

        <div class="submit-area">

            <button
                type="button"
                class="submit-button"
                onclick="submitOrder()"
            >
                SUBMIT ORDER
                →
            </button>

        </div>

    </div>

</section>


<!-- dress up module (merged from Figurify DressUp tool) -->

<div id="dressUpModule" style="display:none">

    <div class="section-header">

        <div class="section-icon">
            ✨
        </div>

        <div>

            <h2>
                Build Your Dress Up Order
            </h2>

            <p>
                Customize your own figure using our design
                tools, then add it to your order.
            </p>

        </div>

    </div>


    <div class="form-shell">

        <div class="dressup-workspace">

            <div class="dressup-left">


            <div class="preview-header">

                <h2>Your Figure</h2>


                <button
                    type="button"
                    id="resetBtn"
                >
                    Reset Design
                </button>

            </div>


            <!-- 3D figure preview -->

            <div class="figure-area">

                <canvas
                    id="figureViewer"
                    class="figure-viewer"
                ></canvas>

            </div>


            <div class="preview-summary">

                <!-- same layout as Image Submission's order summary, pero own "dressUpSummary..." ids
                     since ids need to be unique per page (Image Submission already uses summaryOrderType etc) -->

                <div class="receipt-header">
                    <h3>Order Summary</h3>
                    <div class="receipt-icon">₱</div>
                </div>

                <p class="receipt-subtitle">
                    Your selected figure details
                </p>


                <!-- ORDER TYPE -->

                <div class="selected-order-box">
                    <div class="order-type-summary">
                        <div class="order-type-summary-left">
                            <div class="selected-order-label">ORDER TYPE</div>
                            <div class="selected-order-value" id="dressUpSummaryOrderType">Not selected</div>
                        </div>
                        <div class="order-type-price" id="dressUpSummaryOrderTypePrice">₱0</div>
                    </div>
                </div>


                <!-- BOOKING DATE -->

                <div class="selected-order-box">
                    <div class="selected-order-label">BOOKING DATE</div>
                    <div class="selected-order-value" id="dressUpSummaryBookingDate">Not selected</div>
                    <div class="selected-order-date" id="dressUpSummaryBookingStatus">
                        Choose a date from the calendar
                    </div>
                </div>


                <!-- FIGURE DETAILS + CUSTOMIZATION PICKS -->

                <div class="figure-summary-box">

                    <div class="figure-summary-title">YOUR FIGURE</div>

                    <div id="summaryReceiptItems">
                        <div class="receipt-empty">No customization selected yet.</div>
                    </div>

                </div>


                <!-- TOTAL -->

                <div class="summary-total">
                    <span>Total Price</span>
                    <strong id="dressUpTotalPrice">₱0</strong>
                </div>

            </div>

            </div>


            <div class="dressup-right">

            <!-- scroll area: pare-pareho ang laki ng Figure Details container; kapag dumami
                 ang laman (hal. maraming Tops), dito na lang mag-i-scroll, nasa baba pa rin ang Back/Next -->
            <div class="dressup-right-scroll" id="dressUpRightScroll">


            <!-- figure details: from Figure Style up to the last option (e.g. shoes), then Box/Add-ons na kung meron -->

            <div
                class="option-section figure-details-heading"
                id="figureDetailsContainer"
            >

                <h2>Figure Details</h2>


                <!-- figure style -->

                <div
                    class="option-section figure-style-section"
                    id="figureStyleSection"
                >

                    <span class="figure-style-kicker">FIGURE STYLE</span>

                    <div class="figure-style-grid">

                        <button
                            type="button"
                            class="figure-style-card"
                            data-tab="figure"
                            data-figure="funko"
                        >
                            <img src="../Image/funko.jpg" alt="Funko Pop">
                            <span>Funko Pop</span>
                        </button>

                        <button
                            type="button"
                            class="figure-style-card"
                            data-tab="figure"
                            data-figure="hirono"
                        >
                            <img src="../Image/Hirono.jpg" alt="Hirono">
                            <span>Hirono</span>
                        </button>

                        <button
                            type="button"
                            class="figure-style-card"
                            data-tab="figure"
                            data-figure="chibi"
                        >
                            <img src="../Image/chibi.jpg" alt="Chibi">
                            <span>Chibi</span>
                        </button>

                    </div>

                    <div
                        class="figure-style-navigation"
                        id="figureStyleNavigation"
                    >
                        <button
                            type="button"
                            class="continue-btn step-navigation-button"
                            id="nextFigureStyle"
                            disabled
                        >
                            Next
                        </button>
                    </div>

                </div>


                <!-- funko pop panel -->

                <div
                    class="category-panel"
                    id="funkoPanel"
                    hidden
                >

                    <div
                    class="option-section"
                    id="funkoModelSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>FUNKO POP GENDER</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="figure-model-grid">

                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="funko"
                            data-slot="model"
                            data-name="Funko Pop Default"
                            data-model="dressup-assets/GLB_files/Funko pop-default.glb"
                            data-image="dressup-assets/images/Funko pop-default.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko pop-default.png"
                                    alt="Funko Pop Default"
                                >
                            </div>
                            <span class="figure-model-name">Funko Boy</span>
                        </button>


                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="funko"
                            data-slot="model"
                            data-name="Funko Pop-Girl"
                            data-model="dressup-assets/GLB_files/Funko pop-girl.glb"
                            data-image="dressup-assets/images/Funko pop-girl.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko pop-girl.png"
                                    alt="Funko Pop Girl"
                                >
                            </div>
                            <span class="figure-model-name">Funko Girl</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section skin-section"
                    id="funkoSkinSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>Skin Tone</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="skin-grid">

                        <button
                            type="button"
                            class="skin-card"
                            data-figure="funko"
                            data-slot="skin"
                            data-name="Light"
                            data-color="#F2CCB7"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="skin-swatch skin-light"></span>
                        </button>


                        <button
                            type="button"
                            class="skin-card"
                            data-figure="funko"
                            data-slot="skin"
                            data-name="Dark"
                            data-color="#D19477"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="skin-swatch skin-dark"></span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section funko-step"
                    id="funkoGirlHairSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>FUNKO POP HAIR</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="funko"
                            data-slot="girlHair"
                            data-name="Default Hair"
                            data-model="placeholder:funko-girlHair-default-hair"
                            data-price="20"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">💇</span>
                            </div>
                            <span>Default Hair</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="girlHair"
                            data-name="Hair 01"
                            data-model="dressup-assets/GLB_files/Funko GirlHair01.glb"
                            data-image="dressup-assets/images/Funko GirlHair01.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko GirlHair01.png"
                                    alt="Hair 01"
                                >
                            </div>
                            <span>Hair 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="girlHair"
                            data-name="Hair 02"
                            data-model="dressup-assets/GLB_files/Funko GirlHair02.glb"
                            data-image="dressup-assets/images/Funko GirlHair02.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko GirlHair02.png"
                                    alt="Hair 02"
                                >
                            </div>
                            <span>Hair 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="girlHair"
                            data-name="Hair 03"
                            data-model="dressup-assets/GLB_files/Funko GirlHair03.glb"
                            data-image="dressup-assets/images/Funko GirlHair03.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko GirlHair03.png"
                                    alt="Hair 03"
                                >
                            </div>
                            <span>Hair 03</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section top-section"
                    id="funkoGirlTopSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>FUNKO POP TOP</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="funko"
                            data-slot="girlTop"
                            data-name="Tank Top"
                            data-model="placeholder:funko-girlTop-tank-top"
                            data-price="25"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🎽</span>
                            </div>
                            <span>Tank Top</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="girlTop"
                            data-name="Girl Shirt 01"
                            data-model="dressup-assets/GLB_files/Funko Girl T-Shirt.glb"
                            data-image="dressup-assets/images/GirlShirt01.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/GirlShirt01.png"
                                    alt="Girl Shirt 01"
                                >
                            </div>
                            <span>Girl Shirt 01</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="girlTop"
                            data-name="Girl Shirt 02"
                            data-model="dressup-assets/GLB_files/Funko Girl T-Shirt1.glb"
                            data-image="dressup-assets/images/Girl Shirt02.png"
                            data-price="100"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Girl Shirt02.png"
                                    alt="Girl Shirt 02"
                                >
                            </div>
                            <span>Girl Shirt 02</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="girlTop"
                            data-name="Girl Shirt 03"
                            data-model="dressup-assets/GLB_files/Funko Girl T-Shirt2.glb"
                            data-image="dressup-assets/images/GirlShirt03.png"
                            data-price="100"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/GirlShirt03.png"
                                    alt="Girl Shirt 03"
                                >
                            </div>
                            <span>Girl Shirt 03</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card placeholder-card is-coming-soon"
                            data-figure="funko"
                            data-slot="girlTop"
                            data-name="Jacket"
                            data-model="placeholder:funko-girlTop-jacket"
                            data-price="0"
                            data-billable="false"
                            disabled
                            aria-disabled="true"
                            title="Coming soon"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🧥</span>
                            </div>
                            <span>Jacket</span>
                            <small class="coming-soon-badge">Coming soon</small>
                        </button>

                        <button
                            type="button"
                            class="option-card placeholder-card is-coming-soon"
                            data-figure="funko"
                            data-slot="girlTop"
                            data-name="Long Sleeve"
                            data-model="placeholder:funko-girlTop-long-sleeve"
                            data-price="0"
                            data-billable="false"
                            disabled
                            aria-disabled="true"
                            title="Coming soon"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">👔</span>
                            </div>
                            <span>Long Sleeve</span>
                            <small class="coming-soon-badge">Coming soon</small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker funko-step"
                    id="funkoGirlTopColorSection"
                    data-figure="funko"
                    data-slot="girlTopColor"
                    hidden
                >

                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="girlTopColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="girlTopColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="girlTopColor"
                            data-name="Red"
                            data-color="#D94B4B"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-red"></span>
                            <span class="top-color-name">Red</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="girlTopColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="funkoGirlBottomSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>FUNKO POP BOTTOM</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="funko"
                            data-slot="girlBottom"
                            data-name="Shorts"
                            data-model="placeholder:funko-girlBottom-shorts"
                            data-price="25"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🩳</span>
                            </div>
                            <span>Shorts</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="girlBottom"
                            data-name="Girl Bottom"
                            data-model="dressup-assets/GLB_files/Funko Girl Bottom.glb"
                            data-image="dressup-assets/images/Funko Girl Bottom.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Girl Bottom.png"
                                    alt="Girl Bottom"
                                >
                            </div>
                            <span>Girl Bottom</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="girlBottom"
                            data-name="Girl Bottom 1"
                            data-model="dressup-assets/GLB_files/Funko Girl Bottom1.glb"
                            data-image="dressup-assets/images/Funko Girl Bottom1.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Funko Girl Bottom1.png"
                                    alt="Girl Bottom 1"
                                >
                            </div>
                            <span>Girl Bottom 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker pants-color-picker funko-step"
                    id="funkoGirlBottomColorSection"
                    hidden
                >
                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="girlBottomColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="girlBottomColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="girlBottomColor"
                            data-name="Brown"
                            data-color="#8B5A3C"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-brown"></span>
                            <span class="top-color-name">Brown</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="girlBottomColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section funko-step"
                    id="funkoHairSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>FUNKO POP HAIR</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="funko"
                            data-slot="hair"
                            data-name="Default Hair"
                            data-model="placeholder:funko-hair-default-hair"
                            data-price="20"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">💇</span>
                            </div>
                            <span>Default Hair</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="hair"
                            data-name="Hair 01"
                            data-model="dressup-assets/GLB_files/Hair_01.glb"
                            data-image="dressup-assets/images/Hair01.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hair01.png"
                                    alt="Hair 01"
                                >
                            </div>
                            <span>Hair 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="hair"
                            data-name="Hair 02"
                            data-model="dressup-assets/GLB_files/Hair02.glb"
                            data-image="dressup-assets/images/Hair_02.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hair_02.png"
                                    alt="Hair 02"
                                >
                            </div>
                            <span>Hair 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker hair-color-picker funko-step"
                    id="funkoHairColorSection"
                    hidden
                >



                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="hairColor"
                            data-name="Brown"
                            data-color="#5C3A2E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-brown"></span>
                            <span class="top-color-name">Brown</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="hairColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="hairColor"
                            data-name="Blonde"
                            data-color="#D8BE7A"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">Blonde</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="hairColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section top-section"
                    id="funkoTopSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>FUNKO POP TOPS</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="funko"
                            data-slot="top"
                            data-name="Sando"
                            data-model="placeholder:funko-top-sando"
                            data-price="25"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🎽</span>
                            </div>
                            <span>Sando</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="top"
                            data-name="T-Shirt"
                            data-model="dressup-assets/GLB_files/T-Shirt.glb"
                            data-image="dressup-assets/images/T-shirt.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/T-shirt.png"
                                    alt="T-Shirt"
                                >
                            </div>
                            <span>T-Shirt</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="top"
                            data-name="Hoodie"
                            data-model="dressup-assets/GLB_files/Hoodie.glb"
                            data-image="dressup-assets/images/hoodie.png"
                            data-price="100"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/hoodie.png"
                                    alt="Hoodie"
                                >
                            </div>
                            <span>Hoodie</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card placeholder-card is-coming-soon"
                            data-figure="funko"
                            data-slot="top"
                            data-name="Jacket"
                            data-model="placeholder:funko-top-jacket"
                            data-price="0"
                            data-billable="false"
                            disabled
                            aria-disabled="true"
                            title="Coming soon"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🧥</span>
                            </div>
                            <span>Jacket</span>
                            <small class="coming-soon-badge">Coming soon</small>
                        </button>

                        <button
                            type="button"
                            class="option-card placeholder-card is-coming-soon"
                            data-figure="funko"
                            data-slot="top"
                            data-name="Long Sleeve"
                            data-model="placeholder:funko-top-long-sleeve"
                            data-price="0"
                            data-billable="false"
                            disabled
                            aria-disabled="true"
                            title="Coming soon"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">👔</span>
                            </div>
                            <span>Long Sleeve</span>
                            <small class="coming-soon-badge">Coming soon</small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker shirt-color-picker funko-step"
                    id="funkoTopColorSection"
                    hidden
                >



                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="topColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="topColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="topColor"
                            data-name="Red"
                            data-color="#D94B4B"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-red"></span>
                            <span class="top-color-name">Red</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="topColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="funkoBottomSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>FUNKO POP BOTTOMS</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="funko"
                            data-slot="bottom"
                            data-name="Shorts"
                            data-model="placeholder:funko-bottom-shorts"
                            data-price="25"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🩳</span>
                            </div>
                            <span>Shorts</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="funko"
                            data-slot="bottom"
                            data-name="Pants + Shoes"
                            data-model="dressup-assets/GLB_files/Pants with Shoes.glb"
                            data-image="dressup-assets/images/pants with shoes.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/pants with shoes.png"
                                    alt="Pants + Shoes"
                                >
                            </div>
                            <span>Pants + Shoes</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker pants-color-picker funko-step"
                    id="funkoBottomPantsColorSection"
                    hidden
                >

                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="pantsColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="pantsColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="pantsColor"
                            data-name="Brown"
                            data-color="#8B5A3C"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-brown"></span>
                            <span class="top-color-name">Brown</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="funko"
                            data-slot="pantsColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="funkoShoesSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>FUNKO POP SHOES</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="funko"
                            data-slot="shoes"
                            data-name="Shoes"
                            data-model="placeholder:funko-shoes-shoes"
                            data-price="20"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">👟</span>
                            </div>
                            <span>Shoes</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker custom-color-only-section"
                    id="funkoShoesColorSection"
                    data-figure="funko"
                    data-slot="shoesColor"
                    hidden
                >

                </div>

            </div>


            <!-- chibi panel -->

            <div
                class="category-panel"
                id="chibiPanel"
                hidden
            >

                <div
                    class="option-section"
                    id="chibiTypeSection"
                >

                    <div class="section-title">
                        <h2>PRODUCT TYPE</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card chibi-type-card"
                            data-figure="chibi"
                            data-slot="chibiType"
                            data-product-type="standee"
                            data-name="Full Body Standee"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img src="../Image/ch-fbs.jpg" alt="Chibi Full Body Standee">
                            </div>
                            <span>Full Body Standee</span>
                        </button>


                        <button
                            type="button"
                            class="option-card chibi-type-card"
                            data-figure="chibi"
                            data-slot="chibiType"
                            data-product-type="keychain"
                            data-name="Full Body Keychain"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img src="../Image/ch-fbk.jpg" alt="Chibi Full Body Keychain">
                            </div>
                            <span>Full Body Keychain</span>
                        </button>

                    </div>

                </div>

                <div
                    class="option-section"
                    id="chibiModelSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI GENDER</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="figure-model-grid">

                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="chibi"
                            data-slot="model"
                            data-product-type="standee"
                            data-name="Chibi Boy"
                            data-model="dressup-assets/GLB_files/ChibiFigure.glb"
                            data-image="dressup-assets/images/Chibi Boy.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Boy.png"
                                    alt="Chibi Boy"
                                >
                            </div>
                            <span class="figure-model-name">Chibi Boy</span>
                        </button>


                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="chibi"
                            data-slot="model"
                            data-product-type="standee"
                            data-name="Chibi Girl"
                            data-model="dressup-assets/GLB_files/Chibi Girl.glb"
                            data-image="dressup-assets/images/Chibi Girl.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Girl.png"
                                    alt="Chibi Girl"
                                >
                            </div>
                            <span class="figure-model-name">Chibi Girl</span>
                        </button>


                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="chibi"
                            data-slot="model"
                            data-product-type="keychain"
                            data-name="Chibi Boy Keychain"
                            data-model="dressup-assets/GLB_files/Chibi Boy Keychain.glb"
                            data-image="dressup-assets/images/Chibi Boy Keychain.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img src="dressup-assets/images/Chibi Boy Keychain.png" alt="Chibi Boy Keychain">
                            </div>
                            <span class="figure-model-name">Chibi Boy</span>
                        </button>


                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="chibi"
                            data-slot="model"
                            data-product-type="keychain"
                            data-name="Chibi Girl Keychain"
                            data-model="dressup-assets/GLB_files/Chibi Girl Keychain.glb"
                            data-image="dressup-assets/images/Chibi Girl Keychain.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img src="dressup-assets/images/Chibi Girl Keychain.png" alt="Chibi Girl Keychain">
                            </div>
                            <span class="figure-model-name">Chibi Girl</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section skin-section"
                    id="chibiSkinSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>Skin Tone</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="skin-grid">

                        <button
                            type="button"
                            class="skin-card"
                            data-figure="chibi"
                            data-slot="skin"
                            data-name="Light"
                            data-color="#F2CCB7"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="skin-swatch skin-light"></span>
                        </button>


                        <button
                            type="button"
                            class="skin-card"
                            data-figure="chibi"
                            data-slot="skin"
                            data-name="Dark"
                            data-color="#D19477"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="skin-swatch skin-dark"></span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiHairSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI HAIR</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="hair"
                            data-name="Default Hair"
                            data-model="placeholder:chibi-hair-default-hair"
                            data-price="20"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">💇</span>
                            </div>
                            <span>Default Hair</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="hair"
                            data-name="Hair 1"
                            data-model="dressup-assets/GLB_files/Chibi Hair1.glb"
                            data-image="dressup-assets/images/Chibi Hair1.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Hair1.png"
                                    alt="Hair 1"
                                >
                            </div>
                            <span>Hair 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="hair"
                            data-name="Hair 2"
                            data-model="dressup-assets/GLB_files/Chibi Hair2.glb"
                            data-image="dressup-assets/images/Chibi Hair2.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Hair2.png"
                                    alt="Hair 2"
                                >
                            </div>
                            <span>Hair 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="hair"
                            data-name="Hair 3"
                            data-model="dressup-assets/GLB_files/Chibi Hair3.glb"
                            data-image="dressup-assets/images/Chibi Hair3.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Hair3.png"
                                    alt="Hair 3"
                                >
                            </div>
                            <span>Hair 3</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiGirlHairSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI GIRL HAIR</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="girlHair"
                            data-name="Default Hair"
                            data-model="placeholder:chibi-girlHair-default-hair"
                            data-price="20"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">💇</span>
                            </div>
                            <span>Default Hair</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlHair"
                            data-name="Girl Hair 1"
                            data-model="dressup-assets/GLB_files/Chibi GirlHair1.glb"
                            data-image="dressup-assets/images/Chibi GirlHair1.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi GirlHair1.png"
                                    alt="Girl Hair 1"
                                >
                            </div>
                            <span>Girl Hair 1</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlHair"
                            data-name="Girl Hair 2"
                            data-model="dressup-assets/GLB_files/Chibi GirlHair2.glb"
                            data-image="dressup-assets/images/Chibi GirlHair2.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi GirlHair2.png"
                                    alt="Girl Hair 2"
                                >
                            </div>
                            <span>Girl Hair 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker"
                    id="chibiHairColorSection"
                    data-figure="chibi"
                    data-slot="hairColor"
                    hidden
                >


                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="chibi"
                            data-slot="hairColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="chibi"
                            data-slot="hairColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="chibi"
                            data-slot="hairColor"
                            data-name="Brown"
                            data-color="#8B5A3C"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-brown"></span>
                            <span class="top-color-name">Brown</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="chibi"
                            data-slot="hairColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker"
                    id="chibiGirlHairColorSection"
                    data-figure="chibi"
                    data-slot="girlHairColor"
                    hidden
                >
                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="chibi"
                            data-slot="girlHairColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="chibi"
                            data-slot="girlHairColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="chibi"
                            data-slot="girlHairColor"
                            data-name="Brown"
                            data-color="#8B5A3C"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-brown"></span>
                            <span class="top-color-name">Brown</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="chibi"
                            data-slot="girlHairColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiTopSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI TOP</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="Sando"
                            data-model="placeholder:chibi-top-sando"
                            data-price="25"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🎽</span>
                            </div>
                            <span>Sando</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="Chibi T-Shirt"
                            data-model="dressup-assets/GLB_files/Chibi T-Shirt.glb"
                            data-image="dressup-assets/images/Chibi T-Shirt.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi T-Shirt.png"
                                    alt="Chibi T-Shirt"
                                >
                            </div>
                            <span>Chibi T-Shirt</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="Chibi Polo"
                            data-model="dressup-assets/GLB_files/Chibi Polo.glb"
                            data-image="dressup-assets/images/Chibi Polo.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Polo.png"
                                    alt="Chibi Polo"
                                >
                            </div>
                            <span>Chibi Polo</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card placeholder-card is-coming-soon"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="Jacket"
                            data-model="placeholder:chibi-top-jacket"
                            data-price="0"
                            data-billable="false"
                            disabled
                            aria-disabled="true"
                            title="Coming soon"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🧥</span>
                            </div>
                            <span>Jacket</span>
                            <small class="coming-soon-badge">Coming soon</small>
                        </button>

                        <button
                            type="button"
                            class="option-card placeholder-card is-coming-soon"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="Long Sleeve"
                            data-model="placeholder:chibi-top-long-sleeve"
                            data-price="0"
                            data-billable="false"
                            disabled
                            aria-disabled="true"
                            title="Coming soon"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">👔</span>
                            </div>
                            <span>Long Sleeve</span>
                            <small class="coming-soon-badge">Coming soon</small>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiGirlTopSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI GIRL TOP</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Tank Top"
                            data-model="placeholder:chibi-girlTop-tank-top"
                            data-price="25"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🎽</span>
                            </div>
                            <span>Tank Top</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Chibi Girl Hoodie"
                            data-model="dressup-assets/GLB_files/Chibi Girl Hoodie.glb"
                            data-image="dressup-assets/images/Chibi Girl Hoodie.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Girl Hoodie.png"
                                    alt="Chibi Girl Hoodie"
                                >
                            </div>
                            <span>Chibi Girl Hoodie</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Chibi Girl T-Shirt"
                            data-model="dressup-assets/GLB_files/Chibi Girl T-Shirt.glb"
                            data-image="dressup-assets/images/Chibi Girl T-Shirt.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Girl T-Shirt.png"
                                    alt="Chibi Girl T-Shirt"
                                >
                            </div>
                            <span>Chibi Girl T-Shirt</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Chibi Girl Coat"
                            data-model="dressup-assets/GLB_files/Chibi Girl Coat.glb"
                            data-image="dressup-assets/images/Chibi Girl Coat.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Girl Coat.png"
                                    alt="Chibi Girl Coat"
                                >
                            </div>
                            <span>Chibi Girl Coat</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Chibi Girl Sweater"
                            data-model="dressup-assets/GLB_files/Chibi Girl Sweater.glb"
                            data-image="dressup-assets/images/Chibi Girl Sweater.jpg"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Girl Sweater.jpg"
                                    alt="Chibi Girl Sweater"
                                >
                            </div>
                            <span>Chibi Girl Sweater</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card placeholder-card is-coming-soon"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Jacket"
                            data-model="placeholder:chibi-girlTop-jacket"
                            data-price="0"
                            data-billable="false"
                            disabled
                            aria-disabled="true"
                            title="Coming soon"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🧥</span>
                            </div>
                            <span>Jacket</span>
                            <small class="coming-soon-badge">Coming soon</small>
                        </button>

                        <button
                            type="button"
                            class="option-card placeholder-card is-coming-soon"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Long Sleeve"
                            data-model="placeholder:chibi-girlTop-long-sleeve"
                            data-price="0"
                            data-billable="false"
                            disabled
                            aria-disabled="true"
                            title="Coming soon"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">👔</span>
                            </div>
                            <span>Long Sleeve</span>
                            <small class="coming-soon-badge">Coming soon</small>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiBottomSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI BOTTOM</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Shorts"
                            data-model="placeholder:chibi-bottom-shorts"
                            data-price="25"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🩳</span>
                            </div>
                            <span>Shorts</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Chibi Shorts"
                            data-model="dressup-assets/GLB_files/Chibi Shorts.glb"
                            data-image="dressup-assets/images/Chibi Shorts.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Shorts.png"
                                    alt="Chibi Shorts"
                                >
                            </div>
                            <span>Chibi Shorts</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Chibi Pants"
                            data-model="dressup-assets/GLB_files/Chibi Pants.glb"
                            data-image="dressup-assets/images/Chibi Pants.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Pants.png"
                                    alt="Chibi Pants"
                                >
                            </div>
                            <span>Chibi Pants</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiGirlBottomSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI GIRL BOTTOM</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Shorts"
                            data-model="placeholder:chibi-girlBottom-shorts"
                            data-price="25"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🩳</span>
                            </div>
                            <span>Shorts</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card placeholder-card is-coming-soon"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Skirt"
                            data-model="placeholder:chibi-girlBottom-skirt"
                            data-price="0"
                            data-billable="false"
                            disabled
                            aria-disabled="true"
                            title="Coming soon"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">👗</span>
                            </div>
                            <span>Skirt</span>
                            <small class="coming-soon-badge">Coming soon</small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Chibi Girl Pants"
                            data-model="dressup-assets/GLB_files/Chibi Girl Pants.glb"
                            data-image="dressup-assets/images/Chibi Girl Pants.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Girl Pants.png"
                                    alt="Chibi Girl Pants"
                                >
                            </div>
                            <span>Chibi Girl Pants</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Chibi Girl Pants2"
                            data-model="dressup-assets/GLB_files/Chibi Girl Pants2.glb"
                            data-image="dressup-assets/images/Chibi Girl Pants2.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Girl Pants2.png"
                                    alt="Chibi Girl Pants 2"
                                >
                            </div>
                            <span>Chibi Girl Pants 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiShoesSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI SHOES</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="shoes"
                            data-name="Shoes"
                            data-model="placeholder:chibi-shoes-shoes"
                            data-price="20"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">👟</span>
                            </div>
                            <span>Shoes</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <!-- Chibi keychain tops / bottoms (galing sa DressUp — sariling models ng keychain) -->

                <div
                    class="option-section"
                    id="chibiBoyKeychainTopSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI BOY KEYCHAIN TOP</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="Sando"
                            data-model="placeholder:chibi-top-sando"
                            data-price="25"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🎽</span>
                            </div>
                            <span>Sando</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="Chibi T-Shirt Keychain"
                            data-model="dressup-assets/GLB_files/Chibi T-Shirt Keychain.glb"
                            data-image="dressup-assets/images/Chibi T-Shirt.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi T-Shirt.png"
                                    alt="Chibi T-Shirt"
                                >
                            </div>
                            <span>Chibi T-Shirt</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="top"
                            data-name="Chibi Polo Keychain"
                            data-model="dressup-assets/GLB_files/Chibi Polo Keychain.glb"
                            data-image="dressup-assets/images/Chibi Polo.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Polo.png"
                                    alt="Chibi Polo"
                                >
                            </div>
                            <span>Chibi Polo</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiBoyKeychainBottomSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI BOY KEYCHAIN BOTTOM</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Shorts"
                            data-model="placeholder:chibi-bottom-shorts"
                            data-price="25"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🩳</span>
                            </div>
                            <span>Shorts</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Chibi Shorts Keychain"
                            data-model="dressup-assets/GLB_files/Chibi Shorts Keychain.glb"
                            data-image="dressup-assets/images/Chibi Shorts.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Shorts.png"
                                    alt="Chibi Shorts"
                                >
                            </div>
                            <span>Chibi Shorts</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Chibi Pants Keychain"
                            data-model="dressup-assets/GLB_files/Chibi Pants Keychain.glb"
                            data-image="dressup-assets/images/Chibi Pants.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Pants.png"
                                    alt="Chibi Pants"
                                >
                            </div>
                            <span>Chibi Pants</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="bottom"
                            data-name="Chibi Pants Keychain1"
                            data-model="dressup-assets/GLB_files/Chibi Pants Keychain1.glb"
                            data-image="dressup-assets/images/Chibi Girl Pants2.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Girl Pants2.png"
                                    alt="Chibi Pants 2"
                                >
                            </div>
                            <span>Chibi Pants 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiGirlKeychainTopSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI GIRL KEYCHAIN TOP</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Tank Top"
                            data-model="placeholder:chibi-girlTop-tank-top"
                            data-price="25"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🎽</span>
                            </div>
                            <span>Tank Top</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Chibi Girl Hoodie"
                            data-model="dressup-assets/GLB_files/Chibi Girl Hoodie.glb"
                            data-image="dressup-assets/images/Chibi Girl Hoodie.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Girl Hoodie.png"
                                    alt="Chibi Girl Hoodie"
                                >
                            </div>
                            <span>Chibi Girl Hoodie</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Chibi Girl T-Shirt"
                            data-model="dressup-assets/GLB_files/Chibi Girl T-Shirt.glb"
                            data-image="dressup-assets/images/Chibi Girl T-Shirt.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Girl T-Shirt.png"
                                    alt="Chibi Girl T-Shirt"
                                >
                            </div>
                            <span>Chibi Girl T-Shirt</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Chibi Girl Coat"
                            data-model="dressup-assets/GLB_files/Chibi Girl Coat.glb"
                            data-image="dressup-assets/images/Chibi Girl Coat.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Girl Coat.png"
                                    alt="Chibi Girl Coat"
                                >
                            </div>
                            <span>Chibi Girl Coat</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlTop"
                            data-name="Chibi Girl Sweater"
                            data-model="dressup-assets/GLB_files/Chibi Girl Sweater.glb"
                            data-image="dressup-assets/images/Chibi Girl Sweater.jpg"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Girl Sweater.jpg"
                                    alt="Chibi Girl Sweater"
                                >
                            </div>
                            <span>Chibi Girl Sweater</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="chibiGirlKeychainBottomSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>CHIBI GIRL KEYCHAIN BOTTOM</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Shorts"
                            data-model="placeholder:chibi-girlBottom-shorts"
                            data-price="25"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🩳</span>
                            </div>
                            <span>Shorts</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Chibi Girl Pants"
                            data-model="dressup-assets/GLB_files/Chibi Girl Pants.glb"
                            data-image="dressup-assets/images/Chibi Girl Pants.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Girl Pants.png"
                                    alt="Chibi Girl Pants"
                                >
                            </div>
                            <span>Chibi Girl Pants</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="chibi"
                            data-slot="girlBottom"
                            data-name="Chibi Girl Pants2"
                            data-model="dressup-assets/GLB_files/Chibi Girl Pants2.glb"
                            data-image="dressup-assets/images/Chibi Girl Pants2.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Chibi Girl Pants2.png"
                                    alt="Chibi Girl Pants 2"
                                >
                            </div>
                            <span>Chibi Girl Pants 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>

                <!-- custom color ng Chibi top / bottom / shoes — naka-mount sa title ng design section -->
                <div
                    class="top-color-picker custom-color-only-section"
                    id="chibiTopColorSection"
                    data-figure="chibi"
                    data-slot="topColor"
                    hidden
                ></div>

                <div
                    class="top-color-picker custom-color-only-section"
                    id="chibiGirlTopColorSection"
                    data-figure="chibi"
                    data-slot="girlTopColor"
                    hidden
                ></div>

                <div
                    class="top-color-picker custom-color-only-section"
                    id="chibiBottomColorSection"
                    data-figure="chibi"
                    data-slot="bottomColor"
                    hidden
                ></div>

                <div
                    class="top-color-picker custom-color-only-section"
                    id="chibiGirlBottomColorSection"
                    data-figure="chibi"
                    data-slot="girlBottomColor"
                    hidden
                ></div>

                <div
                    class="top-color-picker custom-color-only-section"
                    id="chibiShoesColorSection"
                    data-figure="chibi"
                    data-slot="shoesColor"
                    hidden
                ></div>

            </div>


            <!-- hirono panel -->

            <div
                class="category-panel"
                id="hironoPanel"
                hidden
            >

                <div
                    class="option-section"
                    id="hironoTypeSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>PRODUCT TYPE</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="figure-model-grid">

                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="hirono"
                            data-slot="hironoType"
                            data-mode="standee"
                            data-name="Full Body Standee"
                            data-model="dressup-assets/GLB_files/Hirono default.glb"
                            data-image="dressup-assets/images/Hirono Standee.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee.png"
                                    alt="Full Body Standee"
                                >
                            </div>
                            <span class="figure-model-name">Full Body Standee</span>
                        </button>


                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="hirono"
                            data-slot="hironoType"
                            data-mode="keychain"
                            data-name="Full Body Keychain"
                            data-model="dressup-assets/GLB_files/Hirono Keychain.glb"
                            data-image="dressup-assets/images/Hirono Keychain.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Keychain.png"
                                    alt="Full Body Keychain"
                                >
                            </div>
                            <span class="figure-model-name">Full Body Keychain</span>
                        </button>


                    </div>

                </div>


                <div
                    class="option-section"
                    id="hironoModelSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>HIRONO GENDER</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="figure-model-grid">

                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="hirono"
                            data-slot="model"
                            data-mode="standee"
                            data-gender="boy"
                            data-name="Hirono Boy"
                            data-model="dressup-assets/GLB_files/Hirono default.glb"
                            data-image="dressup-assets/images/Hirono Standee.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Standee.png"
                                    alt="Hirono Boy"
                                >
                            </div>
                            <span class="figure-model-name">Hirono Boy</span>
                        </button>

                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="hirono"
                            data-slot="model"
                            data-mode="keychain"
                            data-gender="boy"
                            data-name="Hirono Boy"
                            data-model="dressup-assets/GLB_files/Hirono Keychain.glb"
                            data-image="dressup-assets/images/Hirono Keychain.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Keychain.png"
                                    alt="Hirono Boy"
                                >
                            </div>
                            <span class="figure-model-name">Hirono Boy</span>
                        </button>


                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="hirono"
                            data-slot="model"
                            data-mode="standee"
                            data-gender="girl"
                            data-name="Hirono Girl"
                            data-model="dressup-assets/GLB_files/Hirono Girl default.glb"
                            data-image="dressup-assets/images/Hirono Girl Standee.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Girl Standee.png"
                                    alt="Hirono Girl"
                                >
                            </div>
                            <span class="figure-model-name">Hirono Girl</span>
                        </button>

                        <button
                            type="button"
                            class="figure-model-card"
                            data-figure="hirono"
                            data-slot="model"
                            data-mode="keychain"
                            data-gender="girl"
                            data-name="Hirono Girl"
                            data-model="dressup-assets/GLB_files/Hirono Girl Keychain.glb"
                            data-image="dressup-assets/images/Hirono Girl Keychain.png"
                            data-price="0"
                            data-billable="false"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Girl Keychain.png"
                                    alt="Hirono Girl"
                                >
                            </div>
                            <span class="figure-model-name">Hirono Girl</span>
                        </button>


                    </div>

                </div>


                <div
                    class="option-section skin-section"
                    id="hironoSkinSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>Skin Tone</h2>
                        <span>Choose one</span>
                    </div>

                    <div class="skin-grid">

                        <button
                            type="button"
                            class="skin-card"
                            data-figure="hirono"
                            data-slot="skin"
                            data-name="Light"
                            data-color="#F2CCB7"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="skin-swatch skin-light"></span>
                        </button>


                        <button
                            type="button"
                            class="skin-card"
                            data-figure="hirono"
                            data-slot="skin"
                            data-name="Dark"
                            data-color="#D19477"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="skin-swatch skin-dark"></span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="hironoHairSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>HIRONO HAIR</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="hirono"
                            data-slot="hair"
                            data-name="Default Hair"
                            data-model="placeholder:hirono-hair-default-hair"
                            data-price="20"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">💇</span>
                            </div>
                            <span>Default Hair</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-hirono-gender="boy"
                            data-slot="hair"
                            data-name="Hirono Hair"
                            data-model="dressup-assets/GLB_files/Hirono Hair01.glb"
                            data-image="dressup-assets/images/Hirono hair.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono hair.png"
                                    alt="Hirono Hair"
                                >
                            </div>
                            <span>Hirono Hair</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="hair"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Hair"
                            data-model="dressup-assets/GLB_files/Hirono Girl Hair.glb"
                            data-image="dressup-assets/images/Hirono Girl Hair.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Girl Hair.png"
                                    alt="Hirono Girl Hair"
                                >
                            </div>
                            <span>Hirono Girl Hair</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="hair"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Hair1"
                            data-model="dressup-assets/GLB_files/Hirono Girl Hair1.glb"
                            data-image="dressup-assets/images/Hirono Girl Hair1.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Girl Hair1.png"
                                    alt="Hirono Girl Hair1"
                                >
                            </div>
                            <span>Hirono Girl Hair1</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="hair"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Hair2"
                            data-model="dressup-assets/GLB_files/Hirono Girl Hair2.glb"
                            data-image="dressup-assets/images/Hirono Girl Hair2.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Girl Hair2.png"
                                    alt="Hirono Girl Hair2"
                                >
                            </div>
                            <span>Hirono Girl Hair2</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="hair"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Hair3"
                            data-model="dressup-assets/GLB_files/Hirono Girl Hair3.glb"
                            data-image="dressup-assets/images/Hirono Girl Hair3.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Girl Hair3.png"
                                    alt="Hirono Girl Hair3"
                                >
                            </div>
                            <span>Hirono Girl Hair3</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker hirono-hair-color-picker"
                    id="hironoHairColorSection"
                    hidden
                >

                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="hairColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="hairColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="hairColor"
                            data-name="Brown"
                            data-color="#8B5A3C"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-brown"></span>
                            <span class="top-color-name">Brown</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="hairColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="hironoOutfitSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>HIRONO TOPS</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="hirono"
                            data-slot="outfit"
                            data-name="Sando"
                            data-model="placeholder:hirono-outfit-sando"
                            data-price="25"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🎽</span>
                            </div>
                            <span>Sando</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-hirono-gender="boy"
                            data-slot="outfit"
                            data-name="Hirono Polo"
                            data-model="dressup-assets/GLB_files/Hirono Polo.glb"
                            data-image="dressup-assets/images/Hirono Polo.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Polo.png"
                                    alt="Hirono Polo"
                                >
                            </div>
                            <span>Hirono Polo</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-hirono-gender="boy"
                            data-slot="outfit"
                            data-name="Hirono Jersey"
                            data-model="dressup-assets/GLB_files/Hirono Jersey.glb"
                            data-image="dressup-assets/images/Hirono Jersey.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Jersey.png"
                                    alt="Hirono Jersey"
                                >
                            </div>
                            <span>Hirono Jersey</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="outfit"
                            data-hirono-gender="boy"
                            data-name="Hirono Coat"
                            data-model="dressup-assets/GLB_files/Hirono Coat.glb"
                            data-image="dressup-assets/images/Hirono Coat.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Coat.png"
                                    alt="Hirono Coat"
                                >
                            </div>
                            <span>Hirono Coat</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="outfit"
                            data-hirono-gender="boy"
                            data-name="Hirono Polo1"
                            data-model="dressup-assets/GLB_files/Hirono Polo1.glb"
                            data-image="dressup-assets/images/Hirono Polo1.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Polo1.png"
                                    alt="Hirono Polo1"
                                >
                            </div>
                            <span>Hirono Polo1</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="outfit"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Coat"
                            data-model="dressup-assets/GLB_files/Hirono Girl Coat.glb"
                            data-image="dressup-assets/images/Hirono Girl Coat.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Girl Coat.png"
                                    alt="Hirono Girl Coat"
                                >
                            </div>
                            <span>Hirono Girl Coat</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="outfit"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Dress"
                            data-model="dressup-assets/GLB_files/Hirono Girl Dress.glb"
                            data-image="dressup-assets/images/Hirono Girl Dress.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Girl Dress.png"
                                    alt="Hirono Girl Dress"
                                >
                            </div>
                            <span>Hirono Girl Dress</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card placeholder-card is-coming-soon"
                            data-figure="hirono"
                            data-slot="outfit"
                            data-name="Jacket"
                            data-model="placeholder:hirono-outfit-jacket"
                            data-price="0"
                            data-billable="false"
                            disabled
                            aria-disabled="true"
                            title="Coming soon"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🧥</span>
                            </div>
                            <span>Jacket</span>
                            <small class="coming-soon-badge">Coming soon</small>
                        </button>

                        <button
                            type="button"
                            class="option-card placeholder-card is-coming-soon"
                            data-figure="hirono"
                            data-slot="outfit"
                            data-name="Long Sleeve"
                            data-model="placeholder:hirono-outfit-long-sleeve"
                            data-price="0"
                            data-billable="false"
                            disabled
                            aria-disabled="true"
                            title="Coming soon"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">👔</span>
                            </div>
                            <span>Long Sleeve</span>
                            <small class="coming-soon-badge">Coming soon</small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker custom-color-only-section"
                    id="hironoOutfitColorSection"
                    data-figure="hirono"
                    data-slot="outfitColor"
                    hidden
                >

                </div>


                <div
                    class="option-section"
                    id="hironoPantsSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>HIRONO BOTTOM</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="hirono"
                            data-slot="pants"
                            data-name="Shorts"
                            data-model="placeholder:hirono-pants-shorts"
                            data-price="25"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">🩳</span>
                            </div>
                            <span>Shorts</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-hirono-gender="boy"
                            data-slot="pants"
                            data-name="Hirono Pants"
                            data-model="dressup-assets/GLB_files/Hirono Pants1.glb"
                            data-image="dressup-assets/images/Hirono Pants1.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Pants1.png"
                                    alt="Hirono Pants"
                                >
                            </div>
                            <span>Hirono Pants</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="pants"
                            data-hirono-gender="boy"
                            data-name="Hirono Pants 2"
                            data-model="dressup-assets/GLB_files/Hirono Pants.glb"
                            data-image="dressup-assets/images/Hirono Pants.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Pants.png"
                                    alt="Hirono Pants 2"
                                >
                            </div>
                            <span>Hirono Pants 2</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="pants"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Pants"
                            data-model="dressup-assets/GLB_files/Hirono Girl Pants.glb"
                            data-image="dressup-assets/images/Hirono Girl Pants.png"
                            data-price="90"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Girl Pants.png"
                                    alt="Hirono Girl Pants"
                                >
                            </div>
                            <span>Hirono Girl Pants</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker hirono-pants-color-picker"
                    id="hironoPantsColorSection"
                    hidden
                >


                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="pantsColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="pantsColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="pantsColor"
                            data-name="Brown"
                            data-color="#8B5A3C"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-brown"></span>
                            <span class="top-color-name">Brown</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="pantsColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="hironoShoesSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>HIRONO SHOES</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="hirono"
                            data-slot="shoes"
                            data-name="Shoes"
                            data-model="placeholder:hirono-shoes-shoes"
                            data-price="20"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">👟</span>
                            </div>
                            <span>Shoes</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-hirono-gender="boy"
                            data-slot="shoes"
                            data-name="Hirono Shoes"
                            data-model="dressup-assets/GLB_files/Hirono Shoes.glb"
                            data-image="dressup-assets/images/shoes.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/shoes.png"
                                    alt="Hirono Shoes"
                                >
                            </div>
                            <span>Hirono Shoes</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="shoes"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Shoes"
                            data-model="dressup-assets/GLB_files/Hirono Girl Shoes.glb"
                            data-image="dressup-assets/images/shoes.png"
                            data-price="80"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/shoes.png"
                                    alt="Hirono Girl Shoes"
                                >
                            </div>
                            <span>Hirono Girl Shoes</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker shoes-color-picker"
                    id="hironoShoesColorSection"
                    hidden
                >

                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="shoesColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="shoesColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="shoesColor"
                            data-name="Red"
                            data-color="#D94B4B"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-red"></span>
                            <span class="top-color-name">Red</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="shoesColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="hironoKeychainHairSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>HIRONO KEYCHAIN HAIR</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card placeholder-card is-default"
                            data-figure="hirono"
                            data-slot="keychainHair"
                            data-name="Default Hair"
                            data-model="placeholder:hirono-keychainHair-default-hair"
                            data-price="20"
                            data-billable="true"
                            data-default="true"
                        >
                            <div class="option-image placeholder-image">
                                <span class="placeholder-icon">💇</span>
                            </div>
                            <span>Default Hair</span>
                            <small class="default-badge">Default</small>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-hirono-gender="boy"
                            data-slot="keychainHair"
                            data-name="Hirono Hair"
                            data-model="dressup-assets/GLB_files/Hirono Hair01.glb"
                            data-image="dressup-assets/images/Hirono hair.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono hair.png"
                                    alt="Hirono Hair"
                                >
                            </div>
                            <span>Hirono Hair</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="keychainHair"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Hair"
                            data-model="dressup-assets/GLB_files/Hirono Girl Hair.glb"
                            data-image="dressup-assets/images/Hirono Girl Hair.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Girl Hair.png"
                                    alt="Hirono Girl Hair"
                                >
                            </div>
                            <span>Hirono Girl Hair</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="keychainHair"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Hair1"
                            data-model="dressup-assets/GLB_files/Hirono Girl Hair1.glb"
                            data-image="dressup-assets/images/Hirono Girl Hair1.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Girl Hair1.png"
                                    alt="Hirono Girl Hair1"
                                >
                            </div>
                            <span>Hirono Girl Hair1</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="keychainHair"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Hair2"
                            data-model="dressup-assets/GLB_files/Hirono Girl Hair2.glb"
                            data-image="dressup-assets/images/Hirono Girl Hair2.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Girl Hair2.png"
                                    alt="Hirono Girl Hair2"
                                >
                            </div>
                            <span>Hirono Girl Hair2</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="keychainHair"
                            data-hirono-gender="girl"
                            data-name="Hirono Girl Hair3"
                            data-model="dressup-assets/GLB_files/Hirono Girl Hair3.glb"
                            data-image="dressup-assets/images/Hirono Girl Hair3.png"
                            data-price="50"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono Girl Hair3.png"
                                    alt="Hirono Girl Hair3"
                                >
                            </div>
                            <span>Hirono Girl Hair3</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>


                <div
                    class="top-color-picker hirono-hair-color-picker"
                    id="hironoKeychainHairColorSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>Keychain Hair Color</h2>
                        <span>Choose a color</span>
                    </div>


                    <div class="top-color-grid">

                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="keychainHairColor"
                            data-name="White"
                            data-color="#FFFFFF"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-white"></span>
                            <span class="top-color-name">White</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="keychainHairColor"
                            data-name="Black"
                            data-color="#1E1E1E"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-black"></span>
                            <span class="top-color-name">Black</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="keychainHairColor"
                            data-name="Brown"
                            data-color="#8B5A3C"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-brown"></span>
                            <span class="top-color-name">Brown</span>
                        </button>


                        <button
                            type="button"
                            class="top-color-card"
                            data-figure="hirono"
                            data-slot="keychainHairColor"
                            data-name="Blue"
                            data-color="#4E7DF0"
                            data-price="0"
                            data-billable="false"
                        >
                            <span class="top-color-swatch top-color-blue"></span>
                            <span class="top-color-name">Blue</span>
                        </button>

                    </div>

                </div>


                <div
                    class="option-section"
                    id="hironoKeychainHatSection"
                    hidden
                >

                    <div class="section-title">
                        <h2>HIRONO HAT</h2>
                        <span>Choose one</span>
                    </div>


                    <div class="option-grid">

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="keychainHat"
                            data-name="KeyChain Cap"
                            data-model="dressup-assets/GLB_files/Hirono KeyChain Cap.glb"
                            data-image="dressup-assets/images/Hirono KeyChain Cap.png"
                            data-price="40"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/Hirono KeyChain Cap.png"
                                    alt="KeyChain Cap"
                                >
                            </div>
                            <span>KeyChain Cap</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="keychainHat"
                            data-name="Spider-Man Hat"
                            data-model="dressup-assets/GLB_files/Hirono SpidermanHat.glb"
                            data-image="dressup-assets/images/hirono spiderman.png"
                            data-price="60"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/hirono spiderman.png"
                                    alt="Spider-Man Hat"
                                >
                            </div>
                            <span>Spider-Man Hat</span>
                            <small class="option-price" data-base-price></small>
                        </button>


                        <button
                            type="button"
                            class="option-card"
                            data-figure="hirono"
                            data-slot="keychainHat"
                            data-name="Spider-Venom Hat"
                            data-model="dressup-assets/GLB_files/Hirono VenomxSpidermanHat.glb"
                            data-image="dressup-assets/images/hirono spiderxvenom.png"
                            data-price="60"
                            data-billable="true"
                        >
                            <div class="option-image">
                                <img
                                    src="dressup-assets/images/hirono spiderxvenom.png"
                                    alt="Spider-Venom Hat"
                                >
                            </div>
                            <span>Spider-Venom Hat</span>
                            <small class="option-price" data-base-price></small>
                        </button>

                    </div>

                </div>

            </div>


            <!-- SIZE — shared step after Gender/Skin, para sa kahit anong Figure Style -->

            <div
                class="option-section size-section"
                id="figureSizeSection"
                hidden
            >

                <div class="section-title">
                    <h2>Size</h2>
                    <span>Choose one</span>
                </div>

                <div class="size-choice-grid" id="sizeChoiceGrid"></div>

            </div>

            </div>


            <section
                class="product-details-panel"
                id="productDetailsPanel"
                hidden
            >

                <!-- one container lang, becomes "Box / Add-ons" kung Funko/Hirono, "Figure Details" pa rin kung Chibi -->

                <div class="section-title">
                    <div>
                        <span class="details-kicker" id="productDetailsKicker">FIGURE DETAILS</span>
                        <h2 id="productDetailsTitle">Choose Your Figure Details</h2>
                        <p id="productDetailsDescription">Choose the box for your selected product.</p>
                    </div>
                </div>

                <label class="product-detail-group" id="figureNameField" hidden>
                    <span class="detail-label">Figure Name <small>(optional)</small></span>
                    <input class="product-detail-input" id="figureNameInput" type="text" maxlength="40" placeholder="Example: Omar123">
                    <small class="detail-hint">PHP 50 if you add a name</small>
                </label>

                <div class="product-detail-group" id="boxChoiceGroup">
                    <span class="detail-label">Box</span>
                    <p class="detail-hint" id="boxChoiceHint">
                        Funko Pop figures come with a custom box. Choose Solo or Couple Box, and fill in the box details below.
                    </p>
                    <div class="box-choice-grid" id="boxChoiceGrid"></div>
                    <p class="detail-hint" id="boxLockedHint" hidden>
                        Please select a box type above to unlock the box details form.
                    </p>
                </div>

                <div class="box-detail-fields" id="boxDetailFields" hidden>
                    <label>
                        <span class="detail-label">Name to print on box <small>(optional)</small></span>
                        <input class="product-detail-input" id="boxNameInput" data-detail-field="boxName" type="text" maxlength="40" placeholder="Example: Omar">
                    </label>
                    <label>
                        <span class="detail-label">Box Number <small>(optional)</small></span>
                        <input class="product-detail-input" id="boxNumberInput" data-detail-field="boxNumber" type="text" maxlength="20" placeholder="Example: 001">
                    </label>
                    <label>
                        <span class="detail-label">Box Color <small>(optional)</small></span>
                        <input class="product-detail-input" id="boxColorInput" data-detail-field="boxColor" type="text" maxlength="30" placeholder="Example: Pastel Pink">
                    </label>
                </div>

                <div class="hirono-detail-fields" id="hironoDetailFields" hidden>
                    <div class="product-detail-group">
                        <span class="detail-label">Blind Box Type</span>
                        <div class="box-choice-grid" id="hironoBlindBoxGrid"></div>
                    </div>
                    <div class="product-detail-group">
                        <span class="detail-label">Optional Extras</span>
                        <div class="box-choice-grid" id="hironoAddonGrid"></div>
                    </div>
                    <div class="product-detail-group">
                        <span class="detail-label">Box Design</span>
                        <div class="box-choice-grid" id="hironoBoxDesignGrid"></div>
                    </div>
                    <div class="box-detail-fields" id="hironoBoxFields"></div>
                </div>

            </section>


            </div>
            <!-- /.dressup-right-scroll -->

            <!-- no summary panel here, redirects to finalsummary.php na (see dressup.js) -->


            <div
                class="design-step-navigation"
                id="designStepNavigation"
                hidden
            >
                <label class="design-step-jump" for="designStepPicker">
                    <span>DESIGN SECTION</span>
                    <select id="designStepPicker" aria-label="Jump to design section"></select>
                </label>
                <button
                    type="button"
                    class="step-navigation-button"
                    id="previousDesignStep"
                >
                    Back
                </button>
                <button
                    type="button"
                    class="continue-btn step-navigation-button"
                    id="nextDesignStep"
                >
                    Next
                </button>
                <a
                    href="finalsummary.php"
                    class="continue-btn"
                    id="continueBtn"
                    hidden
                >
                    Next
                </a>
            </div>

            </div>

        </div>

    </div>

</div>

</main>


<!-- image modal -->

<div
    class="image-modal"
    id="imageModal"
    onclick="closeImageModal(event)"
>

    <div class="modal-content">

        <button
            type="button"
            class="modal-close"
            onclick="closeImageModal()"
        >
            ×
        </button>

        <img
            id="modalImage"
            src=""
            alt="Large reference preview"
        >

        <div
            class="modal-label"
            id="modalLabel"
        >
            Reference Image
        </div>

    </div>

</div>

<script>
    const figurifyBookedDates = <?php echo json_encode($bookedDates); ?>;

    /* Rush: { "YYYY-MM-DD": count } — para sa daily/monthly rush cap (JS-computed) */
    const figurifyRushDayCounts = <?php echo json_encode($rushDayCounts); ?>;

    /* Non-Rush: { "YYYY-MM-DD": count } — para sa 15-order-per-date cap */
    const figurifyNonrushDateCounts = <?php echo json_encode($nonrushDateCounts); ?>;

    const FIGURIFY_RUSH_DAILY_CAP    = <?php echo (int) FIGURIFY_RUSH_DAILY_CAP; ?>;
    const FIGURIFY_RUSH_MONTHLY_CAP  = <?php echo (int) FIGURIFY_RUSH_MONTHLY_CAP; ?>;
    const FIGURIFY_NONRUSH_DATE_CAP  = <?php echo (int) FIGURIFY_NONRUSH_DATE_CAP; ?>;
</script>

<script src="commission.js?v=2"></script>

<!-- importmap needed since dressup.js imports three.js directly, module fails silently without it -->

<script type="importmap">
{
    "imports": {
        "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
        "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
    }
}
</script>

<script type="module" src="dressup.js?v=18"></script>


<!-- footer -->

<?php include "../Shared/footer.php"; ?>
</body>
</html>
