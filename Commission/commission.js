// variables

    let orderType = "";

    let selectedDate = null;

    let calendarDate = new Date();

    let selectedFigureStyle = "";

    let selectedProductType = "";

    let selectedProductPrice = 0;

    let selectedSize = "";

    let selectedSizePrice = 0;


    // style add-on variables (Custom Funko Box / Hirono Blind Box)

    let selectedBoxType = "";

    let selectedBoxPrice = 0;

    let selectedBlindType = "";

    let selectedBlindPrice = 0;

    // box design for Hirono: "checkered" (any color) or "hirono_peek" (Wood or B&W only)
    let selectedBoxDesign = "";

    // optional Hirono extras (Tear Blind Paper, Pouch, Digital Art), each independently
    // selectable now. Shape: { itemKey: price, ... }
    let selectedBlindItems = {};


    // rush fee is per figure now, base sa product type (dati flat ₱500/order lang):
    // Head Only Keychain = ₱200/figure, lahat ng iba = ₱500/figure
    // IMPORTANT: dapat tugma ito sa helpers/booking_helper.php (figurify_get_rush_fee_for_product_type())

    function getRushFeeForProductType(productType){

        if(!productType){

            return 0;

        }

        const normalized =
            productType.toLowerCase().trim();

        if(normalized.indexOf("head only") !== -1){

            return 200;

        }

        return 500;

    }


    // Sinusuma ang rush fee ng bawat figure sa isang array ng figure objects (dapat may .product bawat isa).
    function calculateTotalRushFee(figures){

        return figures.reduce(function(sum,figureObject){

            return sum + getRushFeeForProductType(figureObject.product);

        },0);

    }


    // multiple image variables

    const MAX_IMAGES = 5;

    let uploadedImages = [];


    // box reference images for Hirono — limit depende sa Box Design (see getHironoBoxImageLimit())
    let hironoBoxImages = [];


    // multi order (figure cart) variables — para makapag-add ng ilang figure sa isang order

    let figuresCart = [];

    let editingIndex = -1;

    let cartCounter = 0;


    // product configuration

    const productConfig = {

        "Funko Pop": {

            "Full Body Standee": {

                sizes:{
                    "3":900,
                    "4":1200,
                    "5":1500
                }

            },

            "Full Body Keychain":null,
            "Half Body Keychain":null,
            "Head Only Keychain":null

        },


        "Hirono": {

            "Full Body Standee":{

                sizes:{
                    "2":600,
                    "3.5":950
                }

            },

            "Full Body Keychain":{

                sizes:{
                    "2":600
                }

            },

            "Half Body Keychain":{

                price:400,
                sizes:null

            },

            "Head Only Keychain":{

                price:300,
                sizes:null

            }

        },


        "Chibi":{

            "Full Body Standee":{

                sizes:{
                    "2":500,
                    "3":680,
                    "4":900,
                    "5":1200
                }

            },

            "Full Body Keychain":{

                sizes:{
                    "2":500
                }

            },

            "Half Body Keychain":{

                price:350,
                sizes:null

            },

            "Head Only Keychain":{

                price:250,
                sizes:null

            }

        }

    };


    // dress up

    function openDressUp(){

        // Opens the Dress Up module, which lives right on this page (#dressUpModule).

        const dressUpModule =
            document.getElementById("dressUpModule");

        if(!dressUpModule){
            return;
        }

        // hide Image Submission form first so Dress Up fully replaces it, not stacks under it

        const referenceCard =
            document.getElementById("referenceCard");

        const referenceForm =
            document.getElementById("customReferenceForm");

        if(referenceCard){
            referenceCard.classList.remove("selected");
        }

        if(referenceForm){
            referenceForm.classList.remove("show");
        }

        const styleCard =
            document.getElementById("styleCard");

        if(styleCard){
            styleCard.classList.add("selected");
        }

        dressUpModule.style.display = "block";

        setTimeout(function(){

            dressUpModule.scrollIntoView({
                behavior:"smooth",
                block:"start"
            });

        },50);

    }


    // Hides the Dress Up module and returns to the order method cards.
    window.__closeDressUp = function(){

        const dressUpModule =
            document.getElementById("dressUpModule");

        if(dressUpModule){
            dressUpModule.style.display = "none";
        }

        const styleCard =
            document.getElementById("styleCard");

        if(styleCard){
            styleCard.scrollIntoView({
                behavior:"smooth",
                block:"start"
            });
        }

    };


    // image submission

    function selectMethod(method){

        if(method !== "reference"){
            return;
        }

        const card =
            document.getElementById("referenceCard");

        const form =
            document.getElementById("customReferenceForm");

        const styleCard =
            document.getElementById("styleCard");

        const dressUpModule =
            document.getElementById("dressUpModule");

        if(styleCard){
            styleCard.classList.remove("selected");
        }

        if(dressUpModule){
            dressUpModule.style.display = "none";
        }

        card.classList.add("selected");

        form.classList.add("show");

        setTimeout(function(){

            form.scrollIntoView({
                behavior:"smooth",
                block:"start"
            });

        },100);

    }


    // order type

    function selectOrderType(type){

        orderType = type;

        document
            .getElementById("rushCard")
            .classList.remove("selected");

        document
            .getElementById("nonrushCard")
            .classList.remove("selected");


        if(type === "rush"){

            document
                .getElementById("rushCard")
                .classList.add("selected");

        }

        else{

            document
                .getElementById("nonrushCard")
                .classList.add("selected");

        }


        selectedDate = null;

        document
            .getElementById("selectedDate")
            .classList.remove("show");

        updateCalendarNote();

        updateAllSummary();

        renderCalendar();

    }


    // figure style

    function selectFigureStyle(button){

        document
            .querySelectorAll(".style-option")
            .forEach(function(item){

                item.classList.remove("selected");

            });


        button.classList.add("selected");

        selectedFigureStyle =
            button.dataset.style;

        selectedProductType = "";

        selectedProductPrice = 0;

        selectedSize = "";

        selectedSizePrice = 0;


        document
            .querySelectorAll(".product-option")
            .forEach(function(item){

                item.classList.remove("selected");

                item.classList.remove("disabled");

                item.disabled = false;

            });


        updateProductOptions();

        updateSizeOptions();

        resetBoxAddonState();

        updateStyleAddonSections();

        updateAllSummary();

    }


    // style add-on sections (Custom Funko Box / Hirono Blind Box) —
    // shows/hides the matching box depending on the selected Figure Style

    function updateStyleAddonSections(){

        const funkoSection =
            document.getElementById("funkoBoxSection");

        const hironoSection =
            document.getElementById("hironoBlindSection");

        const boxAddonBox =
            document.querySelector(".box-addon-box");

        const isFunko =
            selectedFigureStyle === "Funko Pop";

        const isHirono =
            selectedFigureStyle === "Hirono";

        if(funkoSection){

            funkoSection.classList.toggle("show", isFunko);

        }

        if(hironoSection){

            hironoSection.classList.toggle("show", isHirono);

        }

        /* Box / Add-ons container lumalabas lang kung Funko Pop o Hirono ang style */
        if(boxAddonBox){

            boxAddonBox.classList.toggle(
                "show",
                isFunko || isHirono
            );

        }

    }


    // reset style add-on state — called when Figure Style changes or draft is cleared

    function resetBoxAddonState(){

        selectedBoxType = "";

        selectedBoxPrice = 0;

        selectedBlindType = "";

        selectedBlindPrice = 0;

        selectedBlindItems = {};

        resetBoxDesignState();


        document
            .querySelectorAll(".box-type-option")
            .forEach(function(btn){

                btn.classList.remove("selected");

            });


        document
            .querySelectorAll(".blind-set-item")
            .forEach(function(btn){

                btn.classList.remove("selected");

            });


        /* Funko box fields laging naka-show; Hirono fields naka-hide hanggang mapili sa selectBlindType() */

        const funkoFields =
            document.getElementById("funkoBoxFields");

        if(funkoFields){

            funkoFields.classList.add("show");

        }

        const boxLockedMessage =
            document.getElementById("boxLockedMessage");

        if(boxLockedMessage){

            boxLockedMessage.classList.add("hide");

        }


        const hironoFields =
            document.getElementById("hironoBlindFields");

        if(hironoFields){

            hironoFields.classList.remove("show");

        }

        const blindSetContentsReset =
            document.getElementById("blindSetContents");

        if(blindSetContentsReset){

            // Optional Extras laging naka-show kahit wala pang Regular/Set — hiwalay
            // ito sa detail fields (Box Color/Nickname/etc.) na naka-lock hanggang may mapili.

            blindSetContentsReset.classList.add("show");

        }


        [
            "funkoBoxName",
            "funkoBoxNumber",
            "funkoBoxColor",
            "hironoBoxColor",
            "hironoLetter",
            "hironoNickname",
            "hironoDateMonth",
            "hironoDateDay"
        ].forEach(function(id){

            const el =
                document.getElementById(id);

            if(el){

                el.value = "";

            }

        });

    }


    // select Funko box type (Solo / Couple)

    function selectBoxType(button){

        document
            .querySelectorAll("[data-box-type]")
            .forEach(function(btn){

                btn.classList.remove("selected");

            });

        button.classList.add("selected");

        selectedBoxType =
            button.dataset.boxType;

        selectedBoxPrice =
            Number(button.dataset.boxPrice || 0);


        document
            .getElementById("boxLockedMessage")
            .classList.add("hide");

        document
            .getElementById("funkoBoxFields")
            .classList.add("show");


        updateAllSummary();

    }


    // show blind box optional extras section — shown for both Regular and Set,
    // customer clicks which extras (Tear Blind Paper, Pouch, Digital Art) to add

    function updateBlindSetContentsDisplay(blindType){

        const blindSetContents =
            document.getElementById("blindSetContents");

        if(!blindSetContents){
            return;
        }

        // optional extras laging naka-show na, hindi na depende sa Regular/Set —
        // "blindType" param hindi na ginagamit, naiwan lang para di masira ang callers

        blindSetContents.classList.add("show");

    }


    // select/unselect a blind box extra — tapping toggles it on/off with its own price,
    // no longer tied to Regular vs Set

    function selectBlindSetItem(button){

        const itemKey =
            button.dataset.blindItem;

        if(!itemKey){
            return;
        }

        const itemPrice =
            Number(button.dataset.blindItemPrice || 0);

        const isNowSelected =
            button.classList.toggle("selected");

        if(isNowSelected){

            selectedBlindItems[itemKey] = itemPrice;

        }

        else{

            delete selectedBlindItems[itemKey];

        }

        updateAllSummary();

    }


    // total price of currently selected blind box extras

    function getCurrentBlindItemsTotal(){

        return Object
            .keys(selectedBlindItems)
            .reduce(function(sum,key){

                return sum + Number(selectedBlindItems[key] || 0);

            },0);

    }


    // select Hirono blind box type (Regular / Set) — clicking toggles it,
    // clicking the same one again unselects it

    function selectBlindType(button){

        const isAlreadySelected =
            button.classList.contains("selected");

        document
            .querySelectorAll("[data-blind-type]")
            .forEach(function(btn){

                btn.classList.remove("selected");

            });


        if(isAlreadySelected){

            // unclick: walang napiling blind box type.

            selectedBlindType = "";

            selectedBlindPrice = 0;

            resetBoxDesignState();


            document
                .getElementById("hironoBlindFields")
                .classList.remove("show");


            updateAllSummary();

            return;

        }


        button.classList.add("selected");

        selectedBlindType =
            button.dataset.blindType;

        selectedBlindPrice =
            Number(button.dataset.blindPrice || 0);


        document
            .getElementById("hironoBlindFields")
            .classList.add("show");


        updateBlindSetContentsDisplay(selectedBlindType);


        updateAllSummary();

    }


    // box design (Checkered / Hirono Peek) — sets the image upload limit
    // (Checkered = 5, Hirono Peek = 3). Toggle rin ito, kagaya ng selectBlindType().

    function selectBoxDesign(button){

        const isAlreadySelected =
            button.classList.contains("selected");

        document
            .querySelectorAll("[data-box-design]")
            .forEach(function(btn){

                btn.classList.remove("selected");

            });


        if(isAlreadySelected){

            // unclick: walang napiling box design.

            resetBoxDesignState();

            updateAllSummary();

            return;

        }


        button.classList.add("selected");

        selectedBoxDesign =
            button.dataset.boxDesign;


        renderHironoBoxColorField(selectedBoxDesign);

        trimHironoImagesToLimit();

        updateHironoImageUploadUI();

        updateAllSummary();

    }


    // box color field for Hirono — Checkered is free text, Hirono Peek is a
    // Wood/Black & White dropdown. Same id="hironoBoxColor" both ways so other code still works.

    function renderHironoBoxColorField(design, presetValue){

        const wrap =
            document.getElementById("hironoBoxColorWrap");

        if(!wrap){

            return;

        }

        if(design === "hirono_peek"){

            wrap.innerHTML =
                '<select class="field" id="hironoBoxColor" onchange="updateAllSummary()">' +
                    '<option value="">Select Box Color</option>' +
                    '<option value="Wood">Wood</option>' +
                    '<option value="Black & White">Black &amp; White</option>' +
                '</select>';

        }
        else{

            wrap.innerHTML =
                '<input class="field" type="text" id="hironoBoxColor" maxlength="30" placeholder="Example: Cream White" oninput="updateAllSummary()">';

        }

        document.getElementById("hironoBoxColor").value =
            presetValue || "";

        updateAllSummary();

    }


    // reset box design state — clears uploaded Box Reference Images too since
    // their limit depends on the design

    function resetBoxDesignState(){

        selectedBoxDesign = "";

        document
            .querySelectorAll("[data-box-design]")
            .forEach(function(btn){

                btn.classList.remove("selected");

            });

        renderHironoBoxColorField("");

        clearHironoImages();

        updateHironoImageUploadUI();

    }


    // current box add-on price (draft figure): Funko = Solo/Couple box price,
    // Hirono = Regular/Set blind box price, Chibi = none

    function getCurrentBoxAddonPrice(){

        if(selectedFigureStyle === "Funko Pop"){

            return Number(selectedBoxPrice || 0);

        }

        if(selectedFigureStyle === "Hirono"){

            return (
                Number(selectedBlindPrice || 0) +
                getCurrentBlindItemsTotal()
            );

        }

        return 0;

    }


    // product type

    function selectProductType(button){

        if(button.disabled){
            return;
        }

        if(button.classList.contains("disabled")){
            return;
        }


        document
            .querySelectorAll(".product-option")
            .forEach(function(item){

                item.classList.remove("selected");

            });


        button.classList.add("selected");

        selectedProductType =
            button.dataset.product;

        selectedProductPrice = 0;

        selectedSize = "";

        selectedSizePrice = 0;


        updateSizeOptions();

        updateAllSummary();

    }


    // update product options

    // update product options — bawat Figure Style may sariling grid sa PHP
    // (".product-style-group"), dito lang ito ipinapakita/itinatago + disabled state

    function updateProductOptions(){

        const productTypeFieldGroup =
            document.getElementById("productTypeFieldGroup");

        productTypeFieldGroup.classList.toggle(
            "show",
            !!selectedFigureStyle
        );


        // ipakita lang ang grid ng napiling style

        document
            .querySelectorAll(".product-style-group")
            .forEach(function(group){

                const isMatch =
                    group.dataset.styleGroup === selectedFigureStyle;

                group.style.display =
                    isMatch ? "" : "none";

            });


        // i-enable/disable ang mga button base sa productConfig

        const products =
            document.querySelectorAll(".product-option");

        const config =
            productConfig[selectedFigureStyle];


        products.forEach(function(button){

            const product =
                button.dataset.product;


            button.classList.remove("disabled");

            button.disabled = false;


            if(!config || !config[product]){

                button.classList.add("disabled");

                button.disabled = true;

            }

        });

    }


    // update size options

    function updateSizeOptions(){

        const sizeButtons =
            document.querySelectorAll(".size-option");

        const sizeFieldGroup =
            document.getElementById("sizeFieldGroup");

        const noSizeMessage =
            document.getElementById("noSizeMessage");


        sizeButtons.forEach(function(button){

            button.classList.remove("selected");

            button.classList.remove("disabled");

            button.disabled = false;

            button.style.display = "none";

        });


        sizeFieldGroup.classList.remove("show");

        noSizeMessage.classList.remove("show");


        if(!selectedFigureStyle){

            selectedSize = "";

            selectedSizePrice = 0;

            selectedProductPrice = 0;

            updateAllSummary();

            return;

        }


        if(!selectedProductType){

            selectedSize = "";

            selectedSizePrice = 0;

            selectedProductPrice = 0;

            updateAllSummary();

            return;

        }


        const styleConfig =
            productConfig[selectedFigureStyle];


        if(!styleConfig){

            selectedSize = "";

            selectedSizePrice = 0;

            selectedProductPrice = 0;

            updateAllSummary();

            return;

        }


        const config =
            styleConfig[selectedProductType];


        if(!config){

            selectedSize = "";

            selectedSizePrice = 0;

            selectedProductPrice = 0;

            updateAllSummary();

            return;

        }


        if(!config.sizes){

            selectedSize = "";

            selectedSizePrice =
                Number(config.price || 0);

            selectedProductPrice =
                Number(config.price || 0);

            noSizeMessage.classList.add("show");

            sizeFieldGroup.classList.add("show");

            updateAllSummary();

            return;

        }


        const availableSizes =
            Object.keys(config.sizes);


        sizeButtons.forEach(function(button){

            const size =
                button.dataset.size;


            if(availableSizes.includes(size)){

                button.style.display = "block";

                button.disabled = false;

                button.classList.remove("disabled");

            }

        });


        sizeFieldGroup.classList.add("show");

        selectedSize = "";

        selectedSizePrice = 0;

        selectedProductPrice = 0;


        updateAllSummary();

    }


    // size

    function selectSize(button){

        if(button.disabled){
            return;
        }


        if(button.style.display === "none"){
            return;
        }


        document
            .querySelectorAll(".size-option")
            .forEach(function(item){

                item.classList.remove("selected");

            });


        button.classList.add("selected");

        selectedSize =
            button.dataset.size;


        const config =
            productConfig[selectedFigureStyle]
                [selectedProductType];


        if(
            config &&
            config.sizes &&
            config.sizes[selectedSize] !== undefined
        ){

            selectedSizePrice =
                Number(config.sizes[selectedSize]);

        }

        else{

            selectedSizePrice = 0;

        }


        selectedProductPrice = 0;

        updateAllSummary();

    }


    // figure name

    function updateFigureName(){

        const input =
            document.getElementById("figureName");

        const value =
            input.value;


        const validCharacters =
            value.match(/[A-Za-z0-9]/g);

        const count =
            validCharacters
                ? validCharacters.length
                : 0;


        const counterEl =
            document.getElementById("nameCounter");

        if(counterEl){

            counterEl.textContent =
                count +
                (
                    count === 1
                        ? " letter/number"
                        : " letters/numbers"
                );

        }


        updateAllSummary();

    }


    // calendar note

    function updateCalendarNote(){

        const note =
            document.getElementById("calendarNote");

        if(!note){

            return;

        }

        // static notes lang ito, pareho ang laman kahit anong order type ang napili

        note.innerHTML =
            "<strong>Notes</strong>" +
            "<ul>" +
                "<li>" +
                    "<strong>Rush Order:</strong> " +
                    "must be booked at least 7 days from today. " +
                    "Max 2 orders/day, max 10 orders/month. Once a " +
                    "day reaches 2 orders, that day closes — the " +
                    "very next day is already open again for new " +
                    "rush bookings." +
                "</li>" +
                "<li>" +
                    "<strong>Non-Rush Order:</strong> " +
                    "only the last day of the selected month " +
                    "is available. Booking must be made at " +
                    "least 7 days before that date, and up to 15 " +
                    "orders can share that date — once full, the " +
                    "next available date is the last day of the " +
                    "following month." +
                "</li>" +
            "</ul>";

    }


    // calendar init

    function initializeCalendar(){

        calendarDate = new Date();

        calendarDate.setDate(1);

        updateCalendarNote();

        renderCalendar();

    }


    // render calendar

    function renderCalendar(){

        const container =
            document.getElementById("calendarDays");

        const title =
            document.getElementById("calendarTitle");


        container.innerHTML = "";


        const year =
            calendarDate.getFullYear();

        const month =
            calendarDate.getMonth();


        title.textContent =
            new Date(year,month,1)
                .toLocaleDateString(
                    "en-PH",
                    {
                        month:"long",
                        year:"numeric"
                    }
                );


        const firstDay =
            new Date(
                year,
                month,
                1
            ).getDay();


        const daysInMonth =
            new Date(
                year,
                month + 1,
                0
            ).getDate();


        for(let i=0;i<firstDay;i++){

            const empty =
                document.createElement("div");

            empty.className = "day";

            empty.style.visibility = "hidden";

            container.appendChild(empty);

        }


        const today =
            new Date();

        today.setHours(
            0,
            0,
            0,
            0
        );


        const minimumRushDate =
            new Date(today);

        minimumRushDate.setDate(
            minimumRushDate.getDate() + 7
        );


        for(
            let day=1;
            day<=daysInMonth;
            day++
        ){

            const button =
                document.createElement("button");

            button.type = "button";

            button.className = "day";

            button.textContent = day;


            const date =
                new Date(
                    year,
                    month,
                    day
                );

            date.setHours(
                0,
                0,
                0,
                0
            );


            if(date.getTime() === today.getTime()){

                button.classList.add("today");

            }


            let unavailable = false;


            const dateString =
                year +
                "-" +
                String(month + 1).padStart(2, "0") +
                "-" +
                String(day).padStart(2, "0");


            // figurifyBookedDates para lang sa "booked" dot/tooltip — ang capacity caps
            // (rush: 2/day, non-rush: 15/date) ang totoong basehan kung unavailable na
            const hasAnyBooking =
                typeof figurifyBookedDates !== "undefined" &&
                figurifyBookedDates.includes(dateString);

            let isBooked = false;
            let unavailableReason = null;


            if(orderType === ""){

                unavailable = true;

            }


            if(orderType === "rush"){

                if(date < minimumRushDate){

                    unavailable = true;

                }


                // rush — daily cap (max 2 orders/day, puwedeng magkaibang customer)
                const rushDayCounts =
                    (typeof figurifyRushDayCounts !== "undefined")
                        ? figurifyRushDayCounts
                        : {};

                const rushDailyCap =
                    (typeof FIGURIFY_RUSH_DAILY_CAP !== "undefined")
                        ? FIGURIFY_RUSH_DAILY_CAP
                        : 2;

                const rushCountThisDay =
                    rushDayCounts[dateString] || 0;

                if(rushCountThisDay >= rushDailyCap){

                    unavailable = true;
                    isBooked = true;
                    unavailableReason = "Already has the maximum of " + rushDailyCap + " rush orders for this day";

                }


                /* RUSH — MONTHLY CAP (max 10 orders/month), kino-
                   compute mula sa parehong rushDayCounts (isinasama
                   ang lahat ng petsa sa parehong taon-buwan) */
                const rushMonthlyCap =
                    (typeof FIGURIFY_RUSH_MONTHLY_CAP !== "undefined")
                        ? FIGURIFY_RUSH_MONTHLY_CAP
                        : 10;

                const monthPrefix =
                    year + "-" + String(month + 1).padStart(2, "0");

                let rushMonthTotal = 0;

                for(const d in rushDayCounts){

                    if(d.indexOf(monthPrefix) === 0){

                        rushMonthTotal += rushDayCounts[d];

                    }

                }

                if(rushMonthTotal >= rushMonthlyCap){

                    unavailable = true;
                    unavailableReason = "Rush order slots for this month are already full (" + rushMonthlyCap + "/month)";

                }


            }


            if(orderType === "nonrush"){

                const lastDay =
                    new Date(
                        year,
                        month + 1,
                        0
                    ).getDate();


                if(day !== lastDay){

                    unavailable = true;

                }


                const bookingDeadline =
                    new Date(
                        year,
                        month,
                        lastDay - 7
                    );

                bookingDeadline.setHours(
                    0,
                    0,
                    0,
                    0
                );


                if(today > bookingDeadline){

                    unavailable = true;

                }


                /* Non-rush: max 15 order/last-day-of-month, block kapag puno. */
                if(day === lastDay){

                    const nonrushDateCounts =
                        (typeof figurifyNonrushDateCounts !== "undefined")
                            ? figurifyNonrushDateCounts
                            : {};

                    const nonrushCap =
                        (typeof FIGURIFY_NONRUSH_DATE_CAP !== "undefined")
                            ? FIGURIFY_NONRUSH_DATE_CAP
                            : 15;

                    const nonrushCountThisDate =
                        nonrushDateCounts[dateString] || 0;

                    if(nonrushCountThisDate >= nonrushCap){

                        unavailable = true;
                        isBooked = true;
                        unavailableReason = "This date is already fully booked (" + nonrushCap + " orders) — try next month";

                    }

                }

            }


            if(hasAnyBooking){

                isBooked = isBooked || true;

            }


            if(
                selectedDate &&
                sameDate(date,selectedDate)
            ){

                button.classList.add("selected");

            }


            if(unavailable){

                button.classList.add("unavailable");

                button.disabled = true;

                if(isBooked){

                    button.classList.add("booked");

                    button.title = unavailableReason || "Already booked";

                } else if(unavailableReason){

                    button.title = unavailableReason;

                }

            }

            else{

                button.classList.add("available");

                button.addEventListener(
                    "click",
                    function(){

                        selectDate(date);

                    }
                );

            }


            container.appendChild(button);

        }


        const currentMonth =
            new Date();

        currentMonth.setDate(1);

        currentMonth.setHours(
            0,
            0,
            0,
            0
        );


        document
            .getElementById("prevMonth")
            .disabled =
            calendarDate <= currentMonth;

    }


    // same date

    function sameDate(a,b){

        return(
            a.getFullYear() === b.getFullYear() &&
            a.getMonth() === b.getMonth() &&
            a.getDate() === b.getDate()
        );

    }


    // select date

    function selectDate(date){

        selectedDate =
            new Date(date);

        selectedDate.setHours(
            0,
            0,
            0,
            0
        );


        renderCalendar();


        const display =
            document.getElementById("selectedDate");


        const formatted =
            selectedDate.toLocaleDateString(
                "en-PH",
                {
                    weekday:"long",
                    month:"long",
                    day:"numeric",
                    year:"numeric"
                }
            );


        display.textContent =
            "✓ Selected: " + formatted;


        display.classList.add("show");


        updateSummary();

    }


    // change month

    function changeMonth(amount){

        calendarDate.setMonth(
            calendarDate.getMonth() + amount
        );

        renderCalendar();

    }


    // get current product price

    function getCurrentProductPrice(){

        if(
            !selectedFigureStyle ||
            !selectedProductType
        ){

            return 0;

        }


        const config =
            productConfig[selectedFigureStyle]
                [selectedProductType];


        if(!config){

            return 0;

        }


        if(config.sizes){

            return Number(
                selectedSizePrice || 0
            );

        }


        return Number(
            config.price || 0
        );

    }


    // current figure name fee (draft figure)

    function getCurrentNameFee(){

        const figureNameInput =
            document.getElementById("figureName");

        const figureName =
            figureNameInput
                ? figureNameInput.value.trim()
                : "";

        const hasFigureName =
            /[A-Za-z0-9]/.test(figureName);

        return hasFigureName ? 50 : 0;

    }


    // price calculation — draft panel shows rush fee only kapag walang laman pa ang cart;
    // once may figure na, nasa cart subtotal/grand total na ang rush fee

    function calculatePrice(){

        const currentProductPrice =
            getCurrentProductPrice();


        let hasSizePrice = false;


        if(
            selectedFigureStyle &&
            selectedProductType &&
            productConfig[selectedFigureStyle] &&
            productConfig[selectedFigureStyle][selectedProductType] &&
            productConfig[selectedFigureStyle][selectedProductType].sizes
        ){

            hasSizePrice = true;

        }


        const nameFee =
            getCurrentNameFee();


        const boxAddonPrice =
            getCurrentBoxAddonPrice();


        const boxAddonRow =
            document.getElementById("boxAddonSummaryRow");

        const boxAddonLabel =
            document.getElementById("boxAddonLabel");

        const boxAddonFee =
            document.getElementById("boxAddonFee");

        const boxAddonSubDetails =
            document.getElementById("boxAddonSubDetails");


        if(boxAddonRow && boxAddonLabel && boxAddonFee){

            if(selectedFigureStyle === "Funko Pop"){

                boxAddonRow.classList.add("show");

                boxAddonLabel.textContent =
                    "Custom Funko Box" +
                    (
                        selectedBoxType
                            ? " (" + (selectedBoxType === "solo" ? "Solo" : "Couple") + ")"
                            : ""
                    );

                boxAddonFee.textContent =
                    money(boxAddonPrice);


                if(boxAddonSubDetails){

                    boxAddonSubDetails.innerHTML = "";

                    boxAddonSubDetails.classList.remove("show");

                }

            }

            else if(selectedFigureStyle === "Hirono"){

                boxAddonRow.classList.add("show");

                boxAddonLabel.textContent =
                    "Blind Box" +
                    (
                        selectedBlindType
                            ? " (" + (selectedBlindType === "regular" ? "Regular" : "Set") + ")"
                            : ""
                    );

                boxAddonFee.textContent =
                    money(boxAddonPrice);


                // ipakita bawat extra isa-isa (with price) + box design,
                // sa halip na "+2 extras" na lang

                if(boxAddonSubDetails){

                    const subLines = [];

                    Object
                        .keys(selectedBlindItems)
                        .forEach(function(itemKey){

                            subLines.push(
                                "<div class=\"box-addon-sub-item\">" +
                                    "<span>+ " +
                                        (BLIND_ITEM_LABELS[itemKey] || itemKey) +
                                    "</span>" +
                                    "<span>" +
                                        money(selectedBlindItems[itemKey]) +
                                    "</span>" +
                                "</div>"
                            );

                        });

                    if(selectedBoxDesign){

                        subLines.push(
                            "<div class=\"box-addon-sub-item\">" +
                                "<span>Box Design</span>" +
                                "<span>" +
                                    (
                                        BOX_DESIGN_LABELS[selectedBoxDesign] ||
                                        selectedBoxDesign
                                    ) +
                                "</span>" +
                            "</div>"
                        );

                    }

                    if(subLines.length > 0){

                        boxAddonSubDetails.innerHTML =
                            subLines.join("");

                        boxAddonSubDetails.classList.add("show");

                    }

                    else{

                        boxAddonSubDetails.innerHTML = "";

                        boxAddonSubDetails.classList.remove("show");

                    }

                }

            }

            else{

                boxAddonRow.classList.remove("show");

                boxAddonFee.textContent =
                    money(0);


                if(boxAddonSubDetails){

                    boxAddonSubDetails.innerHTML = "";

                    boxAddonSubDetails.classList.remove("show");

                }

            }

        }


        // product / size price

        if(hasSizePrice){

            document
                .getElementById("productPrice")
                .textContent =
                money(0);


            document
                .getElementById("sizePrice")
                .textContent =
                money(currentProductPrice);

        }

        else{

            document
                .getElementById("productPrice")
                .textContent =
                money(currentProductPrice);


            document
                .getElementById("sizePrice")
                .textContent =
                "—";

        }


        // total rush fee (per figure) — sum ng cart figures (skip ang ini-edit) + draft figure

        const rushFeeFromCart =
            orderType === "rush"
                ? figuresCart.reduce(function(sum,item,itemIndex){

                    if(itemIndex === editingIndex){

                        return sum;

                    }

                    return sum + getRushFeeForProductType(item.product);

                },0)
                : 0;

        const rushFeeFromDraft =
            (orderType === "rush" && selectedProductType)
                ? getRushFeeForProductType(selectedProductType)
                : 0;

        const totalRushFee =
            rushFeeFromCart + rushFeeFromDraft;

        document
            .getElementById("orderTypePrice")
            .textContent =
            money(totalRushFee);


        // name fee display (current draft figure)

        document
            .getElementById("nameFee")
            .textContent =
            money(nameFee);


        // cart subtotal — skip the figure currently being edited (editingIndex),
        // its live value is already in draftTotal below, para di ito ma-double count

        const cartSubtotal =
            figuresCart.reduce(function(sum,item,itemIndex){

                if(itemIndex === editingIndex){

                    return sum;

                }

                return sum + item.total;

            },0);


        const subtotalRow =
            document.getElementById("cartSubtotalPrice");

        if(subtotalRow){

            subtotalRow.textContent =
                money(cartSubtotal);

        }


        // final total = saved figures + current draft figure + total rush fee

        const draftTotal =
            currentProductPrice +
            nameFee +
            boxAddonPrice;


        const total =
            cartSubtotal +
            draftTotal +
            totalRushFee;


        document
            .getElementById("totalPrice")
            .textContent =
            money(total);


        return total;

    }


    // update summary

    function updateSummary(){

        // persist order type / booking date to localStorage kasi dressup.js
        // nagbabasa doon — na-save tuwing selectOrderType()/selectDate() -> updateSummary()

        try{

            localStorage.setItem(
                "figurifyCommissionOrderType",
                orderType || ""
            );

            localStorage.setItem(
                "figurifyCommissionBookingDate",
                selectedDate
                    ? (selectedDate.getFullYear() + "-" +
                        String(selectedDate.getMonth() + 1).padStart(2,"0") + "-" +
                        String(selectedDate.getDate()).padStart(2,"0"))
                    : ""
            );

        }
        catch(error){

            console.warn("Unable to persist order type/date:", error);

        }

        // I-refresh din ang Dress Up module's Order Summary. dressup.js ay type="module"
        // kaya hindi global ang functions dito (e.g. renderPreviewSummary) — gamitin na
        // lang ang window.updatePriceDisplay na inilagay ni dressup.js sa window mismo.
        if(typeof window.updatePriceDisplay === "function"){

            window.updatePriceDisplay();

        }


        // order type

        const orderTypeText =
            document.getElementById(
                "summaryOrderType"
            );


        if(orderType === "rush"){

            orderTypeText.textContent =
                "⚡ Rush Order";

        }

        else if(orderType === "nonrush"){

            orderTypeText.textContent =
                "🌷 Non-Rush Order";

        }

        else{

            orderTypeText.textContent =
                "Not selected";

        }


        // booking date

        const bookingDate =
            document.getElementById(
                "summaryBookingDate"
            );

        const bookingStatus =
            document.getElementById(
                "summaryBookingStatus"
            );


        if(selectedDate){

            bookingDate.textContent =
                selectedDate.toLocaleDateString(
                    "en-PH",
                    {
                        month:"long",
                        day:"numeric",
                        year:"numeric"
                    }
                );


            bookingStatus.textContent =
                selectedDate.toLocaleDateString(
                    "en-PH",
                    {
                        weekday:"long"
                    }
                );

        }

        else{

            bookingDate.textContent =
                "Not selected";

            bookingStatus.textContent =
                "Choose a date from the calendar";

        }


        // figure style

        document
            .getElementById("summaryStyle")
            .textContent =
            selectedFigureStyle ||
            "Not selected";


        // product type

        document
            .getElementById("summaryProduct")
            .textContent =
            selectedProductType ||
            "Not selected";


        // size

        let sizeText = "Not selected";


        if(
            selectedFigureStyle &&
            selectedProductType
        ){

            const config =
                productConfig[selectedFigureStyle] &&
                productConfig[selectedFigureStyle][selectedProductType];


            if(config){

                if(config.sizes){

                    sizeText =
                        selectedSize
                            ? selectedSize + '"'
                            : "Choose size";

                }

                else{

                    sizeText =
                        "No size required";

                }

            }

        }


        document
            .getElementById("summarySize")
            .textContent =
            sizeText;


        // figure name

        const figureName =
            document
                .getElementById("figureName")
                .value
                .trim();


        document
            .getElementById("summaryFigureName")
            .textContent =
            figureName ||
            "Not entered";


        // editing note + badge

        updateEditingIndicators();


        calculatePrice();

    }


    // update everything

    function updateAllSummary(){

        updateSummary();

    }


    // money

    function money(value){

        return "₱" +
            Number(value)
                .toLocaleString("en-PH");

    }


    // styled notice/toast — ginamit ito sa halip ng alert() para di na "localhost says..."
    // ang lumabas. type: "error" (default), "success", or "info"

    function showNotice(message,type){

        type = type || "error";

        let container =
            document.getElementById("noticeContainer");

        if(!container){

            container =
                document.createElement("div");

            container.id = "noticeContainer";

            container.className = "notice-container";

            document.body.appendChild(container);

        }


        // clear any showing notice (and its auto-dismiss timer) so the new one
        // appears in the same spot instead of stacking
        container.innerHTML = "";


        const notice =
            document.createElement("div");

        notice.className =
            "notice-toast notice-" + type;

        const icon =
            type === "success"
                ? "✓"
                : (type === "info" ? "✦" : "!");

        notice.innerHTML =
            '<div class="notice-icon">' +
                icon +
            '</div>' +
            '<div class="notice-message"></div>';

        notice.querySelector(".notice-message").textContent =
            message;


        function removeNotice(){

            notice.classList.remove("notice-show");

            notice.classList.add("notice-hide");

            setTimeout(function(){

                if(notice.parentNode){

                    notice.parentNode.removeChild(notice);

                }

            },250);

        }


        container.appendChild(notice);

        requestAnimationFrame(function(){

            notice.classList.add("notice-show");

        });

        setTimeout(
            removeNotice,
            5000
        );

    }


    // multi order (figure cart) logic


    // validate current (draft) figure — returns an error message, or "" if valid

    function validateDraftFigure(){

        if(!selectedFigureStyle){

            return "Please select a Figure Style.";

        }


        if(!selectedProductType){

            return "Please select a Product Type.";

        }


        const config =
            productConfig[selectedFigureStyle]
                [selectedProductType];


        if(!config){

            return "This product is not available for the selected figure style.";

        }


        if(config.sizes && !selectedSize){

            return "Please select a Size.";

        }


        // Figure Name ay optional na — hindi na required.


        if(uploadedImages.length === 0){

            return "Please upload at least 1 reference image for this figure.";

        }


        if(selectedFigureStyle === "Funko Pop"){

            // Custom Funko Box fields optional na — puwedeng walang piliin ang customer

        }


        if(selectedFigureStyle === "Hirono"){

            // Blind Box fields optional pa rin kung untouched, pero minsang may
            // nafill-upan na, dapat kumpletuhin lahat — di pwede kalahati lang

            const hironoBoxColorField =
                document.getElementById("hironoBoxColor");

            const hironoBoxColorVal =
                hironoBoxColorField
                    ? hironoBoxColorField.value.trim()
                    : "";

            const hironoNicknameVal =
                document.getElementById("hironoNickname").value.trim();

            const hironoLetterVal =
                document.getElementById("hironoLetter").value.trim();

            const hironoDateMonthVal =
                document.getElementById("hironoDateMonth").value;

            const hironoDateDayVal =
                document.getElementById("hironoDateDay").value;

            const hironoBoxEngaged =
                !!selectedBlindType ||
                !!selectedBoxDesign ||
                !!hironoBoxColorVal ||
                !!hironoNicknameVal ||
                !!hironoLetterVal ||
                !!hironoDateMonthVal ||
                !!hironoDateDayVal;

            if(hironoBoxEngaged){

                if(!selectedBlindType){

                    return "You started filling out the Blind Box — please select a Blind Box type (Regular or Set), or clear all Blind Box fields to skip it.";

                }

                if(!selectedBoxDesign){

                    return "You started filling out the Blind Box — please select a Box Design (Checkered or Hirono Peek), or clear all Blind Box fields to skip it.";

                }

                if(!hironoBoxColorVal){

                    return "You started filling out the Blind Box — please choose a Box Color, or clear all Blind Box fields to skip it.";

                }

                if(!hironoNicknameVal){

                    return "You started filling out the Blind Box — please enter a Nickname, or clear all Blind Box fields to skip it.";

                }

                if(!hironoLetterVal){

                    return "You started filling out the Blind Box — please write a Letter/Message, or clear all Blind Box fields to skip it.";

                }

                if(!hironoDateMonthVal || !hironoDateDayVal){

                    return "You started filling out the Blind Box — please select a complete Date (Month and Day), or clear all Blind Box fields to skip it.";

                }

            }

        }


        const notesValue =
            (document.getElementById("notes")
                ? document.getElementById("notes").value.trim()
                : "");

        if(!notesValue){

            return "Please add a note with any important details about your requested figure.";

        }


        return "";

    }


    // build a figure object from current draft fields

    function buildFigureFromDraft(){

        const figureName =
            document
                .getElementById("figureName")
                .value
                .trim();


        const notes =
            document
                .getElementById("notes")
                .value
                .trim();


        const config =
            productConfig[selectedFigureStyle]
                [selectedProductType];


        const productPrice =
            getCurrentProductPrice();


        const nameFee =
            getCurrentNameFee();


        const boxAddonPrice =
            getCurrentBoxAddonPrice();


        let boxDetails = null;

        if(selectedFigureStyle === "Funko Pop"){

            boxDetails = {

                addonType:"funko_box",

                boxType:selectedBoxType,

                boxPrice:selectedBoxPrice,

                boxName:
                    document.getElementById("funkoBoxName").value.trim(),

                boxNumber:
                    document.getElementById("funkoBoxNumber").value.trim(),

                boxColor:
                    document.getElementById("funkoBoxColor").value.trim()

            };

        }

        else if(selectedFigureStyle === "Hirono"){

            boxDetails = {

                addonType:"hirono_blind_box",

                blindType:selectedBlindType,

                blindPrice:selectedBlindPrice,

                blindItems:
                    Object.assign({},selectedBlindItems),

                blindItemsTotal:
                    getCurrentBlindItemsTotal(),

                boxDesign:selectedBoxDesign,

                boxColor:
                    document.getElementById("hironoBoxColor").value.trim(),

                letter:
                    document.getElementById("hironoLetter").value.trim(),

                nickname:
                    document.getElementById("hironoNickname").value.trim(),

                dateMonth:
                    document.getElementById("hironoDateMonth").value,

                dateDay:
                    document.getElementById("hironoDateDay").value,

                boxImages:
                    hironoBoxImages.map(function(imageObject){

                        return {
                            file:imageObject.file,
                            url:imageObject.url
                        };

                    })

            };

        }


        const total =
            productPrice +
            nameFee +
            boxAddonPrice;


        let sizeLabel = "No size required";

        if(config && config.sizes){

            sizeLabel =
                selectedSize
                    ? selectedSize + '"'
                    : "";

        }


        return {

            style:selectedFigureStyle,
            product:selectedProductType,
            size:selectedSize,
            sizeLabel:sizeLabel,
            hasSizes: !!(config && config.sizes),
            name:figureName,
            notes:notes,
            productPrice:productPrice,
            nameFee:nameFee,
            boxAddonPrice:boxAddonPrice,
            boxDetails:boxDetails,
            total:total,

            images:
                uploadedImages.map(function(imageObject){

                    return {
                        file:imageObject.file,
                        url:imageObject.url
                    };

                })

        };

    }


    // reset draft figure fields (after adding to cart, or cancel-edit)

    function resetDraftFigureFields(){

        selectedFigureStyle = "";

        selectedProductType = "";

        selectedProductPrice = 0;

        selectedSize = "";

        selectedSizePrice = 0;


        document
            .querySelectorAll(".style-option")
            .forEach(function(item){

                item.classList.remove("selected");

            });


        document
            .querySelectorAll(".product-option")
            .forEach(function(item){

                item.classList.remove("selected");

                item.classList.remove("disabled");

                item.disabled = false;

            });


        document
            .querySelectorAll(".size-option")
            .forEach(function(item){

                item.classList.remove("selected");

                item.style.display = "none";

            });


        document
            .getElementById("noSizeMessage")
            .classList.remove("show");


        resetBoxAddonState();

        updateStyleAddonSections();


        const nameInput =
            document.getElementById("figureName");

        nameInput.value = "";

        const nameCounterReset =
            document.getElementById("nameCounter");

        if(nameCounterReset){

            nameCounterReset.textContent =
                "0 letters/numbers";

        }


        document
            .getElementById("notes")
            .value = "";


        uploadedImages.forEach(function(imageObject){

            if(imageObject.url){

                URL.revokeObjectURL(
                    imageObject.url
                );

            }

        });

        uploadedImages = [];

        renderImagePreviews();

        updateImageUploadUI();


        updateProductOptions();

        updateAllSummary();

    }


    // add (or save edit of) current figure to the cart

    function addFigureToCart(){

        const error =
            validateDraftFigure();


        if(error){

            showNotice(error);

            return;

        }


        const figureObject =
            buildFigureFromDraft();


        if(editingIndex === -1){

            cartCounter += 1;

            figureObject.cartId =
                "fig_" + cartCounter;

            figuresCart.push(figureObject);

        }

        else{

            const existing =
                figuresCart[editingIndex];

            figureObject.cartId =
                existing
                    ? existing.cartId
                    : "fig_" + (++cartCounter);

            figuresCart[editingIndex] =
                figureObject;

            editingIndex = -1;

        }


        resetDraftFigureFields();

        renderFigureCart();

        updateAllSummary();


        document
            .querySelector(".details-box")
            .scrollIntoView({
                behavior:"smooth",
                block:"start"
            });

    }


    // edit a figure from the cart — loads its values back into the draft fields

    function editFigureInCart(index){

        const figureObject =
            figuresCart[index];

        if(!figureObject){
            return;
        }


        editingIndex = index;


        // style

        selectedFigureStyle =
            figureObject.style;

        document
            .querySelectorAll(".style-option")
            .forEach(function(item){

                item.classList.toggle(
                    "selected",
                    item.dataset.style === figureObject.style
                );

            });


        updateProductOptions();

        updateStyleAddonSections();


        // custom Funko box / Hirono blind box

        resetBoxAddonState();

        if(figureObject.boxDetails){

            if(figureObject.boxDetails.addonType === "funko_box"){

                selectedBoxType =
                    figureObject.boxDetails.boxType;

                selectedBoxPrice =
                    Number(figureObject.boxDetails.boxPrice || 0);

                document
                    .querySelectorAll("[data-box-type]")
                    .forEach(function(btn){

                        btn.classList.toggle(
                            "selected",
                            btn.dataset.boxType === selectedBoxType
                        );

                    });

                document
                    .getElementById("boxLockedMessage")
                    .classList.add("hide");

                document
                    .getElementById("funkoBoxFields")
                    .classList.add("show");

                document.getElementById("funkoBoxName").value =
                    figureObject.boxDetails.boxName || "";

                document.getElementById("funkoBoxNumber").value =
                    figureObject.boxDetails.boxNumber || "";

                document.getElementById("funkoBoxColor").value =
                    figureObject.boxDetails.boxColor || "";

            }

            else if(figureObject.boxDetails.addonType === "hirono_blind_box"){

                selectedBlindType =
                    figureObject.boxDetails.blindType;

                selectedBlindPrice =
                    Number(figureObject.boxDetails.blindPrice || 0);

                document
                    .querySelectorAll("[data-blind-type]")
                    .forEach(function(btn){

                        btn.classList.toggle(
                            "selected",
                            btn.dataset.blindType === selectedBlindType
                        );

                    });

                document
                    .getElementById("hironoBlindFields")
                    .classList.add("show");

                updateBlindSetContentsDisplay(selectedBlindType);


                // restore selected blind box extras

                selectedBlindItems =
                    Object.assign(
                        {},
                        figureObject.boxDetails.blindItems || {}
                    );

                document
                    .querySelectorAll(".blind-set-item")
                    .forEach(function(btn){

                        btn.classList.toggle(
                            "selected",
                            Object.prototype.hasOwnProperty.call(
                                selectedBlindItems,
                                btn.dataset.blindItem
                            )
                        );

                    });


                // restore box design (checkered / Hirono peek)

                resetBoxDesignState();

                selectedBoxDesign =
                    figureObject.boxDetails.boxDesign || "";

                if(selectedBoxDesign){

                    document
                        .querySelectorAll("[data-box-design]")
                        .forEach(function(btn){

                            btn.classList.toggle(
                                "selected",
                                btn.dataset.boxDesign === selectedBoxDesign
                            );

                        });

                }


                // restore box reference images

                hironoBoxImages =
                    (figureObject.boxDetails.boxImages || [])
                        .map(function(imageObject){

                            return {

                                id:
                                    Date.now() +
                                    "_" +
                                    Math.random()
                                        .toString(36)
                                        .substring(2),

                                file:imageObject.file,

                                url:imageObject.url

                            };

                        });

                renderHironoImagePreviews();

                updateHironoImageUploadUI();


                renderHironoBoxColorField(
                    selectedBoxDesign,
                    figureObject.boxDetails.boxColor || ""
                );

                document.getElementById("hironoLetter").value =
                    figureObject.boxDetails.letter || "";

                document.getElementById("hironoNickname").value =
                    figureObject.boxDetails.nickname || "";

                document.getElementById("hironoDateMonth").value =
                    figureObject.boxDetails.dateMonth || "";

                document.getElementById("hironoDateDay").value =
                    figureObject.boxDetails.dateDay || "";

            }

        }


        // product

        selectedProductType =
            figureObject.product;

        document
            .querySelectorAll(".product-option")
            .forEach(function(item){

                item.classList.toggle(
                    "selected",
                    item.dataset.product === figureObject.product
                );

            });


        updateSizeOptions();


        // size

        if(figureObject.hasSizes && figureObject.size){

            selectedSize =
                figureObject.size;


            document
                .querySelectorAll(".size-option")
                .forEach(function(item){

                    if(item.dataset.size === figureObject.size){

                        item.classList.add("selected");

                    }

                });


            const config =
                productConfig[selectedFigureStyle]
                    [selectedProductType];

            if(
                config &&
                config.sizes &&
                config.sizes[selectedSize] !== undefined
            ){

                selectedSizePrice =
                    Number(config.sizes[selectedSize]);

            }

        }


        // name

        const nameInput =
            document.getElementById("figureName");

        nameInput.value =
            figureObject.name;

        updateFigureName();


        // notes

        document
            .getElementById("notes")
            .value =
            figureObject.notes || "";


        // images

        uploadedImages.forEach(function(imageObject){

            if(imageObject.url){

                URL.revokeObjectURL(
                    imageObject.url
                );

            }

        });

        uploadedImages =
            figureObject.images.map(function(image){

                return {

                    id:
                        Date.now() +
                        "_" +
                        Math.random()
                            .toString(36)
                            .substring(2),

                    file:image.file,

                    url:
                        image.file
                            ? URL.createObjectURL(image.file)
                            : image.url

                };

            });

        renderImagePreviews();

        updateImageUploadUI();


        updateAllSummary();


        document
            .querySelector(".details-box")
            .scrollIntoView({
                behavior:"smooth",
                block:"start"
            });

    }


    // remove a figure from the cart

    function removeFigureFromCart(index){

        if(!figuresCart[index]){
            return;
        }


        const confirmed =
            confirm(
                "Remove this figure from your order?"
            );

        if(!confirmed){
            return;
        }


        figuresCart[index].images.forEach(function(image){

            if(image.url){

                URL.revokeObjectURL(
                    image.url
                );

            }

        });


        figuresCart.splice(index,1);


        if(editingIndex === index){

            editingIndex = -1;

            resetDraftFigureFields();

        }

        else if(editingIndex > index){

            editingIndex -= 1;

        }


        renderFigureCart();

        updateAllSummary();

    }


    // cancel edit mode (keep figure unchanged in cart)

    function cancelEditFigure(){

        editingIndex = -1;

        resetDraftFigureFields();

        renderFigureCart();

    }


    // update editing indicators (badge + notice while editing an existing figure)

    function updateEditingIndicators(){

        const badge =
            document.getElementById("figureIndexBadge");

        const note =
            document.getElementById("editingNote");

        const addButton =
            document.getElementById("addFigureButton");


        if(!badge || !note || !addButton){
            return;
        }


        if(editingIndex === -1){

            badge.textContent =
                "FIGURE #" + (figuresCart.length + 1);

            note.classList.remove("show");

            addButton.textContent =
                "+ Add Another Order";

        }

        else{

            badge.textContent =
                "EDITING FIGURE #" + (editingIndex + 1);

            note.classList.add("show");

            addButton.textContent =
                "✓ Save Changes To This Figure";

        }

    }


    // label lookups (for the receipt breakdown)

    const BLIND_ITEM_LABELS = {
        tear_blind_paper:"Tear Blind Paper",
        pouch:"Pouch",
        digital_art:"Digital Art (Soft Copy) w/ Photo Card"
    };

    const BOX_DESIGN_LABELS = {
        checkered:"Checkered",
        hirono_peek:"Hirono Peek"
    };

    const MONTH_LABELS = {
        "01":"January","02":"February","03":"March","04":"April",
        "05":"May","06":"June","07":"July","08":"August",
        "09":"September","10":"October","11":"November","12":"December"
    };


    // build the receipt lines for one figure (Figure Details + Custom Box),
    // each field gets its own line instead of one summary sentence

    function buildFigureReceiptLines(figureObject){

        const lines = [];


        // figure details

        lines.push({
            section:"Figure Details",
            label:"Figure Style",
            value:figureObject.style
        });

        lines.push({
            label:"Product Type",
            value:figureObject.product,
            price:figureObject.productPrice
        });

        if(figureObject.hasSizes){

            lines.push({
                label:"Size",
                value:figureObject.sizeLabel || "Not selected"
            });

        }

        lines.push({
            label:"Figure Name",
            value:figureObject.name || "Not entered",
            price:
                figureObject.name
                    ? figureObject.nameFee
                    : null
        });


        // custom box (Funko Pop)

        if(
            figureObject.boxDetails &&
            figureObject.boxDetails.addonType === "funko_box"
        ){

            const box = figureObject.boxDetails;

            lines.push({
                section:"Custom Funko Box",
                label:
                    box.boxType === "solo"
                        ? "Solo Box"
                        : "Couple Box",
                price:box.boxPrice
            });

            if(box.boxName){
                lines.push({
                    label:"Name (on box)",
                    value:box.boxName
                });
            }

            if(box.boxNumber){
                lines.push({
                    label:"Box Number",
                    value:box.boxNumber
                });
            }

            if(box.boxColor){
                lines.push({
                    label:"Box Color",
                    value:box.boxColor
                });
            }

        }


        // custom box (Hirono blind box)

        else if(
            figureObject.boxDetails &&
            figureObject.boxDetails.addonType === "hirono_blind_box"
        ){

            const box = figureObject.boxDetails;

            lines.push({
                section:"Hirono Blind Box",
                label:
                    box.blindType === "regular"
                        ? "Regular Blind Box"
                        : "Blind Box Set",
                price:box.blindPrice
            });

            const blindItemKeys =
                box.blindItems
                    ? Object.keys(box.blindItems)
                    : [];

            blindItemKeys.forEach(function(itemKey){

                lines.push({
                    label:
                        "+ " +
                        (BLIND_ITEM_LABELS[itemKey] || itemKey),
                    price:box.blindItems[itemKey]
                });

            });

            if(box.boxDesign){
                lines.push({
                    label:"Box Design",
                    value:
                        BOX_DESIGN_LABELS[box.boxDesign] ||
                        box.boxDesign
                });
            }

            if(box.boxColor){
                lines.push({
                    label:"Box Color",
                    value:box.boxColor
                });
            }

            if(box.nickname){
                lines.push({
                    label:"Nickname",
                    value:box.nickname
                });
            }

            if(box.letter){
                lines.push({
                    label:"Letter/Message",
                    value:box.letter
                });
            }

            if(box.dateMonth && box.dateDay){
                lines.push({
                    label:"Date",
                    value:
                        (MONTH_LABELS[box.dateMonth] || box.dateMonth) +
                        " " + box.dateDay
                });
            }

            if(box.boxImages && box.boxImages.length > 0){
                lines.push({
                    label:"Box Reference Images",
                    value:
                        box.boxImages.length + " image" +
                        (box.boxImages.length === 1 ? "" : "s")
                });
            }

        }


        // reference images

        lines.push({
            section:"Reference Images",
            label:"Uploaded Images",
            value:
                figureObject.images.length + " image" +
                (figureObject.images.length === 1 ? "" : "s")
        });


        return lines;

    }


    // render the step-by-step lines as HTML (resibo style)

    function renderFigureReceiptLinesHtml(figureObject){

        const lines =
            buildFigureReceiptLines(figureObject);

        let html = "";

        lines.forEach(function(line){

            if(line.section){

                html +=
                    '<div class="cart-item-section-label">' +
                        escapeHtml(line.section) +
                    '</div>';

            }

            html +=
                '<div class="cart-item-line">' +
                    '<span class="cart-item-line-label">' +
                        escapeHtml(line.label) +
                        (
                            line.value
                                ? ': <span class="cart-item-line-value">' +
                                  escapeHtml(line.value) +
                                  '</span>'
                                : ""
                        ) +
                    '</span>' +
                    (
                        (line.price || line.price === 0)
                            ? '<span class="cart-item-line-price">' +
                              money(line.price) +
                              '</span>'
                            : ""
                    ) +
                '</div>';

        });

        return html;

    }


    // render figure cart (right summary panel)

    function renderFigureCart(){

        const list =
            document.getElementById("figureCartList");

        const emptyMessage =
            document.getElementById("cartEmptyMessage");

        const countBadge =
            document.getElementById("figureCartCount");


        if(!list){
            return;
        }


        list.innerHTML = "";


        countBadge.textContent =
            figuresCart.length + " figure" +
            (figuresCart.length === 1 ? "" : "s");


        if(figuresCart.length === 0){

            emptyMessage.style.display = "block";

            return;

        }


        emptyMessage.style.display = "none";


        // simple list lang, "Figure 1"/"Figure 2" atbp. + entered name as sub-label —
        // edit/remove na lang ang paraan para makita ang buong details

        figuresCart.forEach(function(figureObject,index){

            const item =
                document.createElement("div");

            item.className = "cart-item";


            const enteredName =
                (figureObject.name || "").trim();

            let nameHtml =
                '<span class="cart-item-badge">' +
                    "Figure " + (index + 1) +
                '</span>';

            if(enteredName){

                nameHtml +=
                    '<span class="cart-item-name-sub">' +
                        escapeHtml(enteredName) +
                    '</span>';

            }


            // kanya-kanyang rush fee bawat figure base sa product type, para malinaw sa customer
            const figureRushFee =
                orderType === "rush"
                    ? getRushFeeForProductType(figureObject.product)
                    : 0;

            const rushFeeHtml =
                figureRushFee > 0
                    ? '<div class="cart-item-rush-fee">' +
                        "+ " + money(figureRushFee) + " Rush Fee" +
                    '</div>'
                    : "";


            item.innerHTML =
                '<div class="cart-item-top">' +
                    '<div class="cart-item-name">' +
                        nameHtml +
                    '</div>' +
                    '<div class="cart-item-price">' +
                        money(figureObject.total) +
                    '</div>' +
                    '<div class="cart-item-actions"></div>' +
                '</div>' +
                rushFeeHtml;


            const actions =
                item.querySelector(".cart-item-actions");


            const editButton =
                document.createElement("button");

            editButton.type = "button";

            editButton.className = "cart-item-edit";

            editButton.title = "Edit this figure";

            editButton.textContent = "✎";

            editButton.addEventListener(
                "click",
                function(){

                    editFigureInCart(index);

                }
            );


            const removeButton =
                document.createElement("button");

            removeButton.type = "button";

            removeButton.className = "cart-item-remove";

            removeButton.title = "Remove this figure";

            removeButton.textContent = "×";

            removeButton.addEventListener(
                "click",
                function(){

                    removeFigureFromCart(index);

                }
            );


            actions.appendChild(editButton);

            actions.appendChild(removeButton);


            list.appendChild(item);

        });

    }


    // small HTML escape helper (for cart text)

    function escapeHtml(text){

        const div =
            document.createElement("div");

        div.textContent =
            text == null
                ? ""
                : String(text);

        return div.innerHTML;

    }


    // image upload init

    function initializeImageUpload(){

        const input =
            document.getElementById("referenceImages");

        const dropzone =
            document.getElementById("uploadDropzone");


        if(!input || !dropzone){
            return;
        }


        // walang hiwalay na "+ Add Images" button — buong dropzone box na mismo
        // ang pwedeng i-click, bukod sa drag & drop na gumagana rin dito
        dropzone.addEventListener(
            "click",
            function(){

                if(uploadedImages.length >= MAX_IMAGES){

                    showNotice(
                        "You can upload a maximum of 5 images."
                    );

                    return;

                }

                input.click();

            }
        );


        input.addEventListener(
            "change",
            function(event){

                addImageFiles(
                    Array.from(event.target.files)
                );

                input.value = "";

            }
        );


        dropzone.addEventListener(
            "dragover",
            function(event){

                event.preventDefault();

                if(uploadedImages.length >= MAX_IMAGES){
                    return;
                }

                dropzone.classList.add("dragover");

            }
        );


        dropzone.addEventListener(
            "dragleave",
            function(){

                dropzone.classList.remove("dragover");

            }
        );


        dropzone.addEventListener(
            "drop",
            function(event){

                event.preventDefault();

                dropzone.classList.remove("dragover");


                if(uploadedImages.length >= MAX_IMAGES){

                    showNotice(
                        "You can upload a maximum of 5 images."
                    );

                    return;

                }


                const files =
                    Array.from(
                        event.dataTransfer.files
                    );


                addImageFiles(files);

            }
        );


        updateImageUploadUI();

    }


    // add image files

    function addImageFiles(files){

        if(!files || !files.length){
            return;
        }


        const remainingSlots =
            MAX_IMAGES - uploadedImages.length;


        if(remainingSlots <= 0){

            showNotice(
                "You can upload a maximum of 5 images."
            );

            return;

        }


        const imageFiles =
            files.filter(function(file){

                return file.type &&
                    file.type.startsWith("image/");

            });


        if(!imageFiles.length){

            showNotice(
                "Please select image files only."
            );

            return;

        }


        const filesToAdd =
            imageFiles.slice(
                0,
                remainingSlots
            );


        if(imageFiles.length > remainingSlots){

            showNotice(
                "Only " +
                remainingSlots +
                " more image(s) can be added. Maximum is 5."
            );

        }


        filesToAdd.forEach(function(file){

            const imageObject = {

                id:
                    Date.now() +
                    "_" +
                    Math.random()
                        .toString(36)
                        .substring(2),

                file:file,

                url:
                    URL.createObjectURL(file)

            };


            uploadedImages.push(
                imageObject
            );

        });


        renderImagePreviews();

        updateImageUploadUI();

    }


    // render image previews

    function renderImagePreviews(){

        const grid =
            document.getElementById(
                "imagePreviewGrid"
            );


        grid.innerHTML = "";


        if(uploadedImages.length === 0){

            return;

        }


        uploadedImages.forEach(
            function(imageObject,index){

                const card =
                    document.createElement("div");

                card.className =
                    "uploaded-image-card";


                card.addEventListener(
                    "click",
                    function(){

                        openImageModal(
                            imageObject.url,
                            index + 1
                        );

                    }
                );


                const image =
                    document.createElement("img");

                image.src =
                    imageObject.url;

                image.alt =
                    "Reference Image " +
                    (index + 1);


                const number =
                    document.createElement("div");

                number.className =
                    "image-number";

                number.textContent =
                    index + 1;


                const deleteButton =
                    document.createElement("button");

                deleteButton.type =
                    "button";

                deleteButton.className =
                    "image-delete";

                deleteButton.textContent =
                    "×";

                deleteButton.title =
                    "Delete image";


                deleteButton.addEventListener(
                    "click",
                    function(event){

                        event.stopPropagation();

                        deleteImage(
                            imageObject.id
                        );

                    }
                );


                card.appendChild(image);

                card.appendChild(number);

                card.appendChild(deleteButton);

                grid.appendChild(card);

            }
        );

    }


    // delete image

    function deleteImage(id){

        const index =
            uploadedImages.findIndex(
                function(item){

                    return item.id === id;

                }
            );


        if(index === -1){
            return;
        }


        const imageObject =
            uploadedImages[index];


        if(imageObject.url){

            URL.revokeObjectURL(
                imageObject.url
            );

        }


        uploadedImages.splice(
            index,
            1
        );


        renderImagePreviews();

        updateImageUploadUI();

    }


    // update image upload UI

    function updateImageUploadUI(){

        const count =
            uploadedImages.length;


        const counter =
            document.getElementById(
                "imageCount"
            );


        const dropzone =
            document.getElementById(
                "uploadDropzone"
            );


        const uploadDragText =
            document.getElementById(
                "uploadDragText"
            );


        counter.textContent =
            count + " / " + MAX_IMAGES;


        if(count >= MAX_IMAGES){

            dropzone.classList.add(
                "disabled"
            );

        }

        else{

            dropzone.classList.remove(
                "disabled"
            );

        }


        // "Drag & drop" text shares the thumbnail spot — hide it once an image is added
        if(uploadDragText){

            uploadDragText.style.display =
                count === 0 ? "flex" : "none";

        }


        // Dropzone stays clickable kahit maxed out, para lumabas yung "maximum 5 images" notice sa halip na walang mangyari.

    }


    // box reference images (Hirono) — kapareho ng logic ng main uploader pero
    // hiwalay ang list (hironoBoxImages), limit depende sa Box Design

    function getHironoBoxImageLimit(){

        if(selectedBoxDesign === "checkered"){
            return 5;
        }

        if(selectedBoxDesign === "hirono_peek"){
            return 3;
        }

        return 0;

    }


    function initializeHironoImageUpload(){

        const input =
            document.getElementById("hironoImages");

        const dropzone =
            document.getElementById("hironoImageDropzone");

        const addButton =
            document.getElementById("hironoUploadAddButton");


        if(!input || !dropzone || !addButton){
            return;
        }


        addButton.addEventListener(
            "click",
            function(){

                const limit =
                    getHironoBoxImageLimit();

                if(limit === 0){

                    showNotice(
                        "Please select a Box Design (Checkered or Hirono Peek) first."
                    );

                    return;

                }

                if(hironoBoxImages.length >= limit){

                    return;

                }

                input.click();

            }
        );


        // buong dropzone box ang clickable, hindi lang ang button — same as main uploader
        dropzone.addEventListener(
            "click",
            function(event){

                if(addButton.contains(event.target)){
                    return;
                }

                const limit =
                    getHironoBoxImageLimit();

                if(limit === 0){

                    showNotice(
                        "Please select a Box Design (Checkered or Hirono Peek) first."
                    );

                    return;

                }

                if(hironoBoxImages.length >= limit){
                    return;
                }

                input.click();

            }
        );


        input.addEventListener(
            "change",
            function(event){

                addHironoImageFiles(
                    Array.from(event.target.files)
                );

                input.value = "";

            }
        );


        dropzone.addEventListener(
            "dragover",
            function(event){

                event.preventDefault();

                const limit =
                    getHironoBoxImageLimit();

                if(limit === 0 || hironoBoxImages.length >= limit){
                    return;
                }

                dropzone.classList.add("dragover");

            }
        );


        dropzone.addEventListener(
            "dragleave",
            function(){

                dropzone.classList.remove("dragover");

            }
        );


        dropzone.addEventListener(
            "drop",
            function(event){

                event.preventDefault();

                dropzone.classList.remove("dragover");


                const limit =
                    getHironoBoxImageLimit();

                if(limit === 0){

                    showNotice(
                        "Please select a Box Design (Checkered or Hirono Peek) first."
                    );

                    return;

                }

                if(hironoBoxImages.length >= limit){

                    showNotice(
                        "You can upload a maximum of " +
                        limit +
                        " image(s) for this Box Design."
                    );

                    return;

                }


                const files =
                    Array.from(
                        event.dataTransfer.files
                    );


                addHironoImageFiles(files);

            }
        );


        updateHironoImageUploadUI();

    }


    // add box reference image files

    function addHironoImageFiles(files){

        if(!files || !files.length){
            return;
        }


        const limit =
            getHironoBoxImageLimit();

        if(limit === 0){

            showNotice(
                "Please select a Box Design (Checkered or Hirono Peek) first."
            );

            return;

        }


        const remainingSlots =
            limit - hironoBoxImages.length;


        if(remainingSlots <= 0){

            showNotice(
                "You can upload a maximum of " +
                limit +
                " image(s) for this Box Design."
            );

            return;

        }


        const imageFiles =
            files.filter(function(file){

                return file.type &&
                    file.type.startsWith("image/");

            });


        if(!imageFiles.length){

            showNotice(
                "Please select image files only."
            );

            return;

        }


        const filesToAdd =
            imageFiles.slice(
                0,
                remainingSlots
            );


        if(imageFiles.length > remainingSlots){

            showNotice(
                "Only " +
                remainingSlots +
                " more image(s) can be added. Maximum is " +
                limit +
                "."
            );

        }


        filesToAdd.forEach(function(file){

            const imageObject = {

                id:
                    Date.now() +
                    "_" +
                    Math.random()
                        .toString(36)
                        .substring(2),

                file:file,

                url:
                    URL.createObjectURL(file)

            };


            hironoBoxImages.push(
                imageObject
            );

        });


        renderHironoImagePreviews();

        updateHironoImageUploadUI();

    }


    // render box reference image previews

    function renderHironoImagePreviews(){

        const grid =
            document.getElementById(
                "hironoImagePreviewGrid"
            );

        const empty =
            document.getElementById(
                "hironoImageEmpty"
            );

        if(!grid || !empty){
            return;
        }


        grid.innerHTML = "";


        if(hironoBoxImages.length === 0){

            empty.style.display = "block";

            return;

        }


        empty.style.display = "none";


        hironoBoxImages.forEach(
            function(imageObject,index){

                const card =
                    document.createElement("div");

                card.className =
                    "uploaded-image-card";


                card.addEventListener(
                    "click",
                    function(){

                        openImageModal(
                            imageObject.url,
                            index + 1
                        );

                    }
                );


                const image =
                    document.createElement("img");

                image.src =
                    imageObject.url;

                image.alt =
                    "Box Reference Image " +
                    (index + 1);


                const number =
                    document.createElement("div");

                number.className =
                    "image-number";

                number.textContent =
                    index + 1;


                const deleteButton =
                    document.createElement("button");

                deleteButton.type =
                    "button";

                deleteButton.className =
                    "image-delete";

                deleteButton.textContent =
                    "×";

                deleteButton.title =
                    "Delete image";


                deleteButton.addEventListener(
                    "click",
                    function(event){

                        event.stopPropagation();

                        deleteHironoImage(
                            imageObject.id
                        );

                    }
                );


                card.appendChild(image);

                card.appendChild(number);

                card.appendChild(deleteButton);

                grid.appendChild(card);

            }
        );

    }


    // delete a box reference image

    function deleteHironoImage(id){

        const index =
            hironoBoxImages.findIndex(
                function(item){

                    return item.id === id;

                }
            );


        if(index === -1){
            return;
        }


        const imageObject =
            hironoBoxImages[index];


        if(imageObject.url){

            URL.revokeObjectURL(
                imageObject.url
            );

        }


        hironoBoxImages.splice(
            index,
            1
        );


        renderHironoImagePreviews();

        updateHironoImageUploadUI();

    }


    // clear all box reference images (when Box Design is unselected/reset)

    function clearHironoImages(){

        hironoBoxImages.forEach(function(imageObject){

            if(imageObject.url){

                URL.revokeObjectURL(
                    imageObject.url
                );

            }

        });

        hironoBoxImages = [];

        renderHironoImagePreviews();

    }


    // trim box reference images to the new design's limit (e.g. Checkered 5 -> Hirono Peek 3)

    function trimHironoImagesToLimit(){

        const limit =
            getHironoBoxImageLimit();

        if(hironoBoxImages.length <= limit){
            return;
        }

        const removed =
            hironoBoxImages.splice(limit);

        removed.forEach(function(imageObject){

            if(imageObject.url){

                URL.revokeObjectURL(
                    imageObject.url
                );

            }

        });

        renderHironoImagePreviews();

    }


    // update box reference image upload UI

    function updateHironoImageUploadUI(){

        const limit =
            getHironoBoxImageLimit();

        const count =
            hironoBoxImages.length;


        const counter =
            document.getElementById(
                "hironoImageCount"
            );

        const dropzone =
            document.getElementById(
                "hironoImageDropzone"
            );

        const limitText =
            document.getElementById(
                "hironoImageLimitText"
            );

        if(!counter || !dropzone){
            return;
        }

        const uploadTitle =
            dropzone.querySelector(".upload-title");

        const uploadSubtitle =
            dropzone.querySelector(".upload-subtitle");

        const addButton =
            document.getElementById("hironoUploadAddButton");


        if(limit === 0){

            counter.textContent =
                count + " / 0";

            if(limitText){

                limitText.textContent =
                    "Select a Box Design above first";

            }

            dropzone.classList.add("disabled");

            if(uploadTitle){

                uploadTitle.textContent =
                    "Select a Box Design first";

            }

            if(uploadSubtitle){

                uploadSubtitle.textContent =
                    "Choose Checkered or Hirono Peek above to unlock uploads";

            }

            if(addButton){

                addButton.disabled = true;

            }

            return;

        }


        counter.textContent =
            count + " / " + limit;

        if(limitText){

            limitText.textContent =
                "Maximum " + limit + " images";

        }


        if(count >= limit){

            dropzone.classList.add("disabled");

            if(uploadTitle){

                uploadTitle.textContent =
                    "Maximum images reached";

            }

            if(uploadSubtitle){

                uploadSubtitle.textContent =
                    "Delete an image to upload another";

            }

            if(addButton){

                addButton.disabled = true;

            }

        }

        else{

            dropzone.classList.remove("disabled");

            if(uploadTitle){

                uploadTitle.textContent =
                    "Add Box Reference Images";

            }

            if(uploadSubtitle){

                uploadSubtitle.textContent =
                    "Drag & drop your images anywhere here";

            }

            if(addButton){

                addButton.disabled = false;

            }

        }

    }


    // image modal

    function openImageModal(
        imageUrl,
        imageNumber
    ){

        const modal =
            document.getElementById(
                "imageModal"
            );

        const image =
            document.getElementById(
                "modalImage"
            );

        const label =
            document.getElementById(
                "modalLabel"
            );


        image.src =
            imageUrl;


        label.textContent =
            "Reference Image " +
            imageNumber;


        modal.classList.add(
            "show"
        );


        document.body.style.overflow =
            "hidden";

    }


    // close image modal

    function closeImageModal(event){

        if(event){

            if(
                event.target &&
                !event.target.classList.contains(
                    "image-modal"
                )
            ){

                return;

            }

        }


        const modal =
            document.getElementById(
                "imageModal"
            );

        const image =
            document.getElementById(
                "modalImage"
            );


        modal.classList.remove(
            "show"
        );


        image.src = "";

        document.body.style.overflow =
            "";

    }


    // esc key closes modal

    document.addEventListener(
        "keydown",
        function(event){

            if(
                event.key === "Escape"
            ){

                closeImageModal();

            }

        }
    );


    // submit order — sends the whole cart to submit_order.php. Kung may na-fill-up
    // pero hindi pa na-"Add To Order", awtomatiko na itong isasali sa cart

    function submitOrder(){

        if(orderType === ""){

            showNotice(
                "Please select Rush or Non-Rush Order first."
            );

            return;

        }


        if(!selectedDate){

            showNotice(
                "Please select an available booking date."
            );

            return;

        }


        // awtomatikong idagdag ang draft figure kung complete na — walang popup, diretso na

        const draftHasData =
            selectedFigureStyle ||
            selectedProductType ||
            uploadedImages.length > 0 ||
            document.getElementById("figureName").value.trim() !== "";


        if(draftHasData){

            const error =
                validateDraftFigure();


            if(!error){

                // Complete na ang draft — idagdag agad, walang tanong.

                addFigureToCart();

            }

            else if(figuresCart.length === 0){

                /* Wala pang laman ang cart at hindi kumpleto ang draft — sabihin kung ano ang kulang */

                showNotice(error);

                return;

            }

            /* May laman na ang cart, hindi kumpletong draft: ituloy na lang ang submit gamit ang cart */

        }


        if(figuresCart.length === 0){

            showNotice(
                "Please add at least 1 figure to your order."
            );

            return;

        }


        // build FormData — metadata (JSON) + actual image files

        const submitButton =
            document.querySelector(".submit-button");

        if(submitButton){

            submitButton.disabled = true;

            submitButton.textContent =
                "Submitting...";

        }


        const formData =
            new FormData();

        formData.append(
            "order_type",
            orderType
        );

        formData.append(
            "order_method",
            "reference"
        );

        formData.append(
            "booking_date",
            formatDateForServer(selectedDate)
        );


        const figuresMeta =
            figuresCart.map(function(figureObject,index){

                figureObject.images.forEach(
                    function(imageObject){

                        if(imageObject.file){

                            formData.append(
                                "figure_" + index + "_images[]",
                                imageObject.file
                            );

                        }

                    }
                );


                // append Box Reference Images separately, at tanggalin ang raw File
                // objects sa metadata muna bago i-JSON.stringify (di ito valid JSON)

                let boxDetailsForMeta = null;

                if(figureObject.boxDetails){

                    boxDetailsForMeta =
                        Object.assign({},figureObject.boxDetails);

                    if(Array.isArray(boxDetailsForMeta.boxImages)){

                        boxDetailsForMeta.boxImages.forEach(
                            function(imageObject){

                                if(imageObject.file){

                                    formData.append(
                                        "figure_" + index + "_box_images[]",
                                        imageObject.file
                                    );

                                }

                            }
                        );

                        boxDetailsForMeta.boxImagesCount =
                            boxDetailsForMeta.boxImages.length;

                        delete boxDetailsForMeta.boxImages;

                    }

                }


                return {

                    style:figureObject.style,
                    product:figureObject.product,
                    size:figureObject.size,
                    sizeLabel:figureObject.sizeLabel,
                    hasSizes:figureObject.hasSizes,
                    name:figureObject.name,
                    notes:figureObject.notes,
                    productPrice:figureObject.productPrice,
                    nameFee:figureObject.nameFee,
                    boxAddonPrice:figureObject.boxAddonPrice || 0,
                    boxDetails:boxDetailsForMeta,
                    total:figureObject.total

                };

            });


        formData.append(
            "figures",
            JSON.stringify(figuresMeta)
        );


        // send to backend — basahin muna bilang text (hindi diretso .json()) para
        // kung may PHP error o mali ang path, makita ang totoong error sa console

        function restoreSubmitButton(){

            if(submitButton){

                submitButton.disabled = false;

                submitButton.textContent =
                    "SUBMIT ORDER →";

            }

        }


        fetch(
            "submit_order.php",
            {
                method:"POST",
                body:formData
            }
        )

        .then(function(response){

            return response.text().then(function(rawText){

                return {
                    status:response.status,
                    rawText:rawText
                };

            });

        })

        .then(function(result){

            let data;


            try{

                data = JSON.parse(result.rawText);

            }

            catch(parseError){

                console.error(
                    "Hindi JSON ang sagot ng server (HTTP " +
                    result.status +
                    "). Raw response:",
                    result.rawText
                );

                throw new Error(
                    "Hindi maayos na sumagot ang server. " +
                    "I-check na tama ang lokasyon ng " +
                    "submit_order.php (dapat kasabay ng " +
                    "commission.php sa Commission folder) " +
                    "at ang database connection. Tingnan " +
                    "din ang browser console (F12) para sa " +
                    "detalye."
                );

            }


            if(data.success){

                showNotice(
                    "Order submitted! Your order number is #" +
                    data.order_id +
                    ". You can track it in My Orders.",
                    "success"
                );

                setTimeout(function(){

                    window.location.href =
                        "../my-order/my-orders.php";

                },1300);

            }

            else{

                showNotice(
                    data.message ||
                    "Something went wrong while submitting your order."
                );

                restoreSubmitButton();

            }

        })

        .catch(function(error){

            console.error(error);

            showNotice(
                error.message ||
                "Could not submit your order. Please check your connection and try again."
            );

            restoreSubmitButton();

        });

    }


    // format a JS date as "YYYY-MM-DD" for the server (avoids timezone issues from .toISOString())

    function formatDateForServer(date){

        const year =
            date.getFullYear();

        const month =
            String(date.getMonth() + 1).padStart(2,"0");

        const day =
            String(date.getDate()).padStart(2,"0");

        return year + "-" + month + "-" + day;

    }


    // start

    document.addEventListener(
        "DOMContentLoaded",
        function(){

            initializeCalendar();

            updateProductOptions();

            updateSizeOptions();

            renderFigureCart();

            updateAllSummary();

            initializeImageUpload();

            initializeHironoImageUpload();

            if(window.location.hash === "#createStyleForm"){
                openDressUp();
            }else if(window.location.hash === "#customReferenceForm"){
                selectMethod("reference");
            }

        }
    );
