/* ==================================================
   LOGIN
================================================== */

const loginForm = document.getElementById("loginForm");

if (loginForm) {

    loginForm.addEventListener("submit", function(event) {

        const email =
            document.getElementById("email")
                .value
                .trim();

        const password =
            document.getElementById("password")
                .value
                .trim();


        if (!email || !password) {

            event.preventDefault();

            alert(
                "Please enter your email and password."
            );

            return;
        }

        /*
            DO NOT USE:

            event.preventDefault();

            The form will automatically POST
            back to this same page (Login.php).
        */

    });

}

/* ==================================================
   SIGN UP
================================================== */

const signupForm =
    document.getElementById("signupForm");


const sendCodeBtn =
    document.getElementById("sendCodeBtn");


const verifyCodeBtn =
    document.getElementById("verifyCodeBtn");


const verificationSection =
    document.getElementById("verificationSection");


const passwordSection =
    document.getElementById("passwordSection");


/* ==================================================
   SEND VERIFICATION CODE
================================================== */

if (sendCodeBtn) {

    sendCodeBtn.addEventListener(
        "click",
        async function() {

            const name =
                document
                    .getElementById("fullName")
                    .value
                    .trim();


            const email =
                document
                    .getElementById("signupEmail")
                    .value
                    .trim();


            if (!name || !email) {

                alert(
                    "Please enter your name and Gmail."
                );

                return;
            }


            const formData =
                new FormData();


            formData.append(
                "full_name",
                name
            );


            formData.append(
                "email",
                email
            );


            sendCodeBtn.disabled = true;

            sendCodeBtn.textContent =
                "Sending...";


            try {

                const response =
                    await fetch(
                        "send_code.php",
                        {
                            method: "POST",
                            body: formData
                        }
                    );


                const result =
                    await response.text();


                if (
                    result.trim() ===
                    "success"
                ) {

                    alert(
                        "Verification code has been sent."
                    );


                    verificationSection.style.display =
                        "block";


                } else {

                    alert(result);

                }


            } catch (error) {

                console.error(error);

                alert(
                    "Unable to send verification code."
                );

            }


            sendCodeBtn.disabled = false;

            sendCodeBtn.textContent =
                "Send Verification Code";

        }
    );

}


/* ==================================================
   VERIFY CODE
================================================== */

if (verifyCodeBtn) {

    verifyCodeBtn.addEventListener(
        "click",
        async function() {

            const email =
                document
                    .getElementById("signupEmail")
                    .value
                    .trim();


            const code =
                document
                    .getElementById("verificationCode")
                    .value
                    .trim();


            if (code.length !== 6) {

                alert(
                    "Please enter the 6-digit verification code."
                );

                return;
            }


            const formData =
                new FormData();


            formData.append(
                "email",
                email
            );


            formData.append(
                "code",
                code
            );


            verifyCodeBtn.disabled = true;

            verifyCodeBtn.textContent =
                "Verifying...";


            try {

                const response =
                    await fetch(
                        "verify_code.php",
                        {
                            method: "POST",
                            body: formData
                        }
                    );


                const result =
                    await response.text();


                if (
                    result.trim() ===
                    "success"
                ) {

                    alert(
                        "Email verified successfully!"
                    );


                    passwordSection.style.display =
                        "block";


                    verifyCodeBtn.textContent =
                        "Verified ✓";


                } else {

                    alert(
                        "Invalid or expired verification code."
                    );


                    verifyCodeBtn.disabled =
                        false;


                    verifyCodeBtn.textContent =
                        "Verify Code";

                }


            } catch (error) {

                console.error(error);

                alert(
                    "Unable to verify the code."
                );


                verifyCodeBtn.disabled =
                    false;


                verifyCodeBtn.textContent =
                    "Verify Code";

            }

        }
    );

}


/* ==================================================
   CREATE ACCOUNT
================================================== */

if (signupForm) {

    signupForm.addEventListener(
        "submit",
        async function(event) {

            event.preventDefault();


            const name =
                document
                    .getElementById("fullName")
                    .value
                    .trim();


            const email =
                document
                    .getElementById("signupEmail")
                    .value
                    .trim();


            const password =
                document
                    .getElementById("signupPassword")
                    .value;


            const confirmPassword =
                document
                    .getElementById("confirmPassword")
                    .value;


            if (
                !name ||
                !email ||
                !password ||
                !confirmPassword
            ) {

                alert(
                    "Please complete all fields."
                );

                return;
            }


            if (password.length < 8) {

                alert(
                    "Password must be at least 8 characters."
                );

                return;
            }


            if (password !== confirmPassword) {

                alert(
                    "Passwords do not match."
                );

                return;
            }


            const formData =
                new FormData();


            formData.append(
                "full_name",
                name
            );


            formData.append(
                "email",
                email
            );


            formData.append(
                "password",
                password
            );


            formData.append(
                "confirm_password",
                confirmPassword
            );


            try {

                const response =
                    await fetch(
                        "signup.php",
                        {
                            method: "POST",
                            body: formData
                        }
                    );


                const result =
                    await response.text();


                if (
                    result.trim() ===
                    "success"
                ) {

                    alert(
                        "Account created successfully!"
                    );


                    window.location.href =
                        "login.html";


                } else {

                    alert(result);

                }


            } catch (error) {

                console.error(error);

                alert(
                    "Unable to create account."
                );

            }

        }
    );

}


/* ==================================================
   SHOW / HIDE PASSWORD
================================================== */

const showPassword =
    document.getElementById("showPassword");


if (showPassword) {

    showPassword.addEventListener(
        "click",
        function() {

            const password =
                document.getElementById(
                    "password"
                );


            if (
                password.type ===
                "password"
            ) {

                password.type =
                    "text";

                showPassword.textContent =
                    "🙈";

            } else {

                password.type =
                    "password";

                showPassword.textContent =
                    "👁";

            }

        }
    );

}


/* ==================================================
   GO HOME
================================================== */

function goHome() {

    window.location.href =
        "../Home/Home.php";

}