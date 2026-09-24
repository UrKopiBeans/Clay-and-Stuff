<?php
/* Site-wide login/signup popup, included once via Shared/navbar.php
   (walang sariling page). Tumatawag lang ito sa existing endpoints
   (Login.php, send_code.php, verify_code.php, signup.php) via fetch()
   — same backend, UI/popup na lang ang bago. Wala itong ilalabas kung
   naka-login na. */

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

if (isset($_SESSION["user_id"])) {
    return;
}

require_once __DIR__ . "/../helpers/csrf_helper.php";
$csAuthCsrfToken = figurify_csrf_token();
?>
<!-- ================= LOGIN / SIGN UP MODAL CSS ================= -->
<style>
/* Naka-scope lahat sa .cs-auth- prefix para hindi ma-clash
   sa CSS ng anumang page kung saan ito lumalabas. */

.cs-auth-overlay {
    position: fixed;
    inset: 0;
    z-index: 3000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(24, 14, 22, 0.6);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.22s ease, visibility 0.22s ease;
}

.cs-auth-overlay.active {
    opacity: 1;
    visibility: visible;
}

body.cs-auth-lock {
    overflow: hidden;
}

.cs-auth-modal {
    position: relative;
    width: 100%;
    max-width: 380px;
    max-height: min(660px, 94vh);
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border-radius: 24px;
    box-shadow: 0 30px 70px rgba(40, 12, 32, 0.35);
    font-family: 'Plus Jakarta Sans', Arial, sans-serif;
    transform: translateY(14px) scale(0.97);
    transition: transform 0.22s ease;
}

.cs-auth-overlay.active .cs-auth-modal {
    transform: translateY(0) scale(1);
}

.cs-auth-modal * {
    box-sizing: border-box;
    font-family: 'Plus Jakarta Sans', Arial, sans-serif;
}

/* Scrollable lang ang laman (forms), hindi ang buong modal — nakapirmi
   ang close button kahit anong scroll position. */
.cs-auth-scroll {
    overflow-y: auto;
    padding: 30px 26px 22px;
}

.cs-auth-close {
    position: absolute;
    top: 14px;
    right: 14px;
    z-index: 5;
    width: 36px;
    height: 36px;
    border: 1.5px solid #f6d9ea;
    border-radius: 50%;
    background: #ffffff;
    color: #831843;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(131, 24, 67, 0.18);
    transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}

.cs-auth-close svg {
    width: 16px;
    height: 16px;
    stroke: currentColor;
    stroke-width: 2.4;
    stroke-linecap: round;
}

.cs-auth-close:hover {
    background: #fdf1f7;
    border-color: #f6b3df;
    transform: scale(1.08);
    box-shadow: 0 6px 16px rgba(131, 24, 67, 0.26);
}

.cs-auth-close:active {
    transform: scale(0.96);
}

.cs-auth-header {
    text-align: center;
    margin-bottom: 12px;
}

.cs-auth-header .cs-auth-logo {
    width: 46px;
    height: 46px;
    margin: 0 auto 10px;
    display: block;
    object-fit: contain;
}

.cs-auth-header h2 {
    font-size: 19px;
    font-weight: 800;
    color: #4a1332;
    margin: 0 0 3px;
}

.cs-auth-header p {
    font-size: 12px;
    font-weight: 600;
    color: #8a5b78;
    margin: 0;
}

.cs-auth-field {
    margin-bottom: 10px;
}

.cs-auth-field label {
    display: block;
    font-size: 10px;
    font-weight: 800;
    color: #5c2a49;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.cs-auth-input-wrap {
    position: relative;
}

.cs-auth-input-wrap input {
    width: 100%;
    height: 40px;
    padding: 0 14px;
    border-radius: 11px;
    border: 1.5px solid #f0dbe8;
    background: #fdf8fb;
    font-size: 13px;
    font-weight: 600;
    color: #3d2035;
    outline: none;
    transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}

.cs-auth-input-wrap input::placeholder {
    color: #b791a7;
    font-weight: 500;
}

.cs-auth-input-wrap input:focus {
    border-color: #db2777;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(219, 39, 119, 0.14);
}

/* Naka-lock na field (hal. Password bago pa ma-verify ang code) —
   dapat maliwanag na hindi pa ito pwedeng gamitin. */
.cs-auth-input-wrap input:disabled {
    background: #f4eef1;
    border-color: #ece0e6;
    color: #b79aab;
    cursor: not-allowed;
}

.cs-auth-input-wrap input:disabled::placeholder {
    color: #c3aebb;
}

.cs-auth-verify-row input:disabled {
    background: #f4eef1;
    border-color: #ece0e6;
    color: #b79aab;
    cursor: not-allowed;
}

.cs-auth-has-eye input {
    padding-right: 44px;
}

.cs-auth-input-wrap .cs-auth-eye {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    padding: 0;
    color: #a1728f;
    transition: color 0.15s ease, background 0.15s ease;
}

.cs-auth-input-wrap .cs-auth-eye:hover {
    color: #db2777;
    background: #fdf1f7;
}

.cs-auth-eye svg {
    width: 19px;
    height: 19px;
    stroke: currentColor;
    fill: none;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.cs-auth-eye .cs-auth-eye-closed {
    display: none;
}

.cs-auth-eye.is-visible .cs-auth-eye-open {
    display: none;
}

.cs-auth-eye.is-visible .cs-auth-eye-closed {
    display: block;
}

.cs-auth-forgot {
    display: block;
    text-align: right;
    font-size: 11.5px;
    font-weight: 700;
    color: #831843;
    text-decoration: none;
    margin: -4px 0 10px;
}

.cs-auth-btn {
    width: 100%;
    height: 42px;
    border: none;
    border-radius: 21px;
    background: #db2777;
    color: #ffffff;
    font-size: 13.5px;
    font-weight: 800;
    letter-spacing: 0.2px;
    cursor: pointer;
    box-shadow: 0 10px 22px rgba(219, 39, 119, 0.32);
    transition: background 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
}

.cs-auth-btn:hover {
    background: #be123c;
    box-shadow: 0 12px 26px rgba(219, 39, 119, 0.4);
}

/* Ang Log In / Create Account / Reset Password submit buttons —
   hindi na sumasakop sa buong lapad ng card, sinukat na lang
   ayon sa laman nito at naka-center. */
.cs-auth-btn-submit {
    width: auto;
    min-width: 200px;
    max-width: 100%;
    padding: 0 30px;
    display: block;
    margin: 6px auto 0;
}

.cs-auth-btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
    transform: none;
}

.cs-auth-btn-secondary {
    background: #fdf1f7;
    color: #831843;
    box-shadow: none;
    margin-top: 4px;
}

.cs-auth-btn-secondary:hover {
    background: #fbdcec;
    color: #831843;
    box-shadow: none;
}

.cs-auth-verify-row {
    display: grid;
    grid-template-columns: 1.3fr 0.85fr;
    gap: 10px;
    align-items: stretch;
}

.cs-auth-verify-row input {
    width: 100%;
    height: 40px;
    padding: 0 10px;
    border-radius: 11px;
    border: 1.5px solid #f0dbe8;
    background: #fdf8fb;
    font-family: 'Plus Jakarta Sans', Arial, sans-serif;
    font-size: 13px;
    font-weight: 600;
    color: #3d2035;
    outline: none;
    text-align: center;
    letter-spacing: 3px;
    transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}

.cs-auth-verify-row input::placeholder {
    color: #b791a7;
    font-weight: 500;
    letter-spacing: normal;
}

.cs-auth-verify-row input:focus {
    border-color: #db2777;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(219, 39, 119, 0.14);
}

.cs-auth-verify-btn {
    height: 40px;
    border-radius: 11px;
    font-size: 12px !important;
    white-space: nowrap;
    padding: 0 10px;
}

.cs-auth-section-divider {
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid #f4e3ee;
}

.cs-auth-switch {
    text-align: center;
    font-size: 12px;
    font-weight: 600;
    color: #8a5b78;
    margin-top: 10px;
}

.cs-auth-switch a {
    color: #db2777;
    font-weight: 800;
    text-decoration: none;
    margin-left: 4px;
}

.cs-auth-switch a:hover {
    text-decoration: underline;
}

.cs-auth-terms {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    margin-top: 10px;
    margin-bottom: 4px;
    text-align: left;
}

.cs-auth-terms input[type="checkbox"] {
    width: 17px;
    height: 17px;
    margin-top: 2px;
    flex-shrink: 0;
    cursor: pointer;
    accent-color: #db2777;
}

.cs-auth-terms label {
    font-size: 11px;
    font-weight: 600;
    color: #8a5b78;
    line-height: 1.4;
    cursor: pointer;
}

.cs-auth-terms a {
    color: #831843;
    font-weight: 700;
    text-decoration: underline;
}

.cs-auth-hidden {
    display: none !important;
}

/* Styled notice/toast, same disenyo ng Commission page (kapalit ng
   browser alert()) — floating card sa itaas, nawawala sa sarili. */

.cs-auth-notice-container {
    position: fixed;
    top: 24px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 4200;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    max-width: 400px;
    width: calc(100% - 32px);
    pointer-events: none;
}

.cs-auth-notice-toast {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px 18px;
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid #f6d9ea;
    border-left: 4px solid #db2777;
    box-shadow: 0 14px 32px rgba(131, 24, 67, 0.22);
    opacity: 0;
    transform: translateY(-10px) scale(0.96);
    transition: opacity 0.2s ease, transform 0.2s ease;
    pointer-events: auto;
    font-family: 'Plus Jakarta Sans', Arial, sans-serif;
}

.cs-auth-notice-toast.cs-notice-show {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.cs-auth-notice-toast.cs-notice-hide {
    opacity: 0;
    transform: translateY(-6px) scale(0.97);
}

.cs-auth-notice-toast.cs-notice-success {
    border-left-color: #1f9d55;
}

.cs-auth-notice-icon {
    flex-shrink: 0;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 900;
    color: #ffffff;
    background: #db2777;
}

.cs-auth-notice-toast.cs-notice-success .cs-auth-notice-icon {
    background: #1f9d55;
}

.cs-auth-notice-message {
    flex: 1;
    color: #4a1332;
    font-size: 13.5px;
    font-weight: 600;
    line-height: 1.5;
    padding-top: 2px;
}

@media (max-width: 480px) {
    .cs-auth-modal {
        border-radius: 20px;
    }

    .cs-auth-scroll {
        padding: 28px 20px 18px;
    }
}
</style>

<!-- ================= LOGIN / SIGN UP MODAL MARKUP ================= -->

<div class="cs-auth-overlay" id="csAuthOverlay" aria-hidden="true">
    <div class="cs-auth-modal" role="dialog" aria-modal="true" aria-labelledby="csAuthHeading">

        <!-- CSRF token, isinasama sa bawat form submit -->
        <input type="hidden" id="csAuthCsrfToken" value="<?= htmlspecialchars($csAuthCsrfToken) ?>">

        <button type="button" class="cs-auth-close" id="csAuthClose" aria-label="Close">
            <svg viewBox="0 0 24 24" fill="none"><line x1="5" y1="5" x2="19" y2="19"/><line x1="19" y1="5" x2="5" y2="19"/></svg>
        </button>

        <div class="cs-auth-scroll">

        <!-- ================= LOGIN FORM ================= -->
        <form id="csAuthLoginForm" autocomplete="on">

            <div class="cs-auth-header">
                <h2 id="csAuthHeading">Welcome back</h2>
                <p>Log in to continue your Clay &amp; Stuff journey.</p>
            </div>

            <div class="cs-auth-field">
                <label for="csAuthEmail">Email</label>
                <div class="cs-auth-input-wrap">
                    <input type="email" id="csAuthEmail" name="email" placeholder="you@example.com" autocomplete="username" required>
                </div>
            </div>

            <div class="cs-auth-field">
                <label for="csAuthPassword">Password</label>
                <div class="cs-auth-input-wrap cs-auth-has-eye">
                    <input type="password" id="csAuthPassword" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                    <button type="button" class="cs-auth-eye" data-target="csAuthPassword" aria-label="Show password">
                        <svg class="cs-auth-eye-open" viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="cs-auth-eye-closed" viewBox="0 0 24 24"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.06 21.06 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.06 21.06 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <a href="#" class="cs-auth-forgot" id="csAuthGoForgot">Forgot password?</a>

            <button type="submit" class="cs-auth-btn cs-auth-btn-submit" id="csAuthLoginSubmit">Log In</button>

            <div class="cs-auth-switch">
                Don't have an account? <a href="#" id="csAuthGoSignup">Sign up</a>
            </div>
        </form>

        <!-- ================= SIGN UP FORM ================= -->
        <form id="csAuthSignupForm" class="cs-auth-hidden" autocomplete="off">

            <div class="cs-auth-header">
                <h2>Create your account</h2>
                <p>Join Figurify and start creating.</p>
            </div>

            <div class="cs-auth-field">
                <label for="csAuthFullName">Full name</label>
                <div class="cs-auth-input-wrap">
                    <input type="text" id="csAuthFullName" name="signup_full_name" placeholder="Your full name" autocomplete="off" required>
                </div>
            </div>

            <div class="cs-auth-field">
                <label for="csAuthSignupEmail">Gmail</label>
                <div class="cs-auth-input-wrap">
                    <input type="email" id="csAuthSignupEmail" name="signup_email" placeholder="you@gmail.com" autocomplete="off" required>
                </div>
            </div>

            <!-- Isang buong form na lang, walang paunti-unting
                 palabas — pero naka-disable ang Password/Confirm
                 Password hanggat hindi pa na-verify ang code, at
                 naka-disable ang Create Account hanggat hindi pa
                 naka-check ang Terms. Iisa lang ding button ang
                 "Send Code"/"Verify Code" — nagpapalit lang ng
                 mode/label ito, hindi na dalawang hiwalay na button. -->

            <div class="cs-auth-field">
                <label for="csAuthVerificationCode">Verification code</label>
                <div class="cs-auth-verify-row">
                    <input type="text" id="csAuthVerificationCode" maxlength="6" inputmode="numeric" placeholder="Enter 6-digit code" autocomplete="off" disabled>
                    <button type="button" id="csAuthSendCodeBtn" class="cs-auth-btn cs-auth-verify-btn" data-mode="send">Send Code</button>
                </div>
            </div>

            <div class="cs-auth-field">
                <label for="csAuthSignupPassword">Password</label>
                <div class="cs-auth-input-wrap cs-auth-has-eye">
                    <input type="password" id="csAuthSignupPassword" name="signup_password" placeholder="Verify your code first" autocomplete="new-password" disabled>
                    <button type="button" class="cs-auth-eye" data-target="csAuthSignupPassword" aria-label="Show password">
                        <svg class="cs-auth-eye-open" viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="cs-auth-eye-closed" viewBox="0 0 24 24"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.06 21.06 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.06 21.06 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <div class="cs-auth-field">
                <label for="csAuthConfirmPassword">Confirm password</label>
                <div class="cs-auth-input-wrap cs-auth-has-eye">
                    <input type="password" id="csAuthConfirmPassword" name="signup_confirm_password" placeholder="Verify your code first" autocomplete="new-password" disabled>
                    <button type="button" class="cs-auth-eye" data-target="csAuthConfirmPassword" aria-label="Show password">
                        <svg class="cs-auth-eye-open" viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="cs-auth-eye-closed" viewBox="0 0 24 24"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.06 21.06 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.06 21.06 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <div class="cs-auth-terms">
                <input type="checkbox" id="csAuthAgreeTerms" name="agree_terms">
                <label for="csAuthAgreeTerms">
                    I have read and agree to the
                    <a href="../Legal/terms-and-conditions.php" target="_blank" rel="noopener">Terms and Conditions</a>
                    and
                    <a href="../Legal/privacy-policy.php" target="_blank" rel="noopener">Privacy Policy</a>.
                </label>
            </div>

            <button type="submit" class="cs-auth-btn cs-auth-btn-submit" id="csAuthCreateAccountBtn" disabled>Create Account</button>

            <div class="cs-auth-switch">
                Already have an account? <a href="#" id="csAuthGoLogin">Log in</a>
            </div>
        </form>

        <!-- ================= FORGOT PASSWORD FORM ================= -->
        <form id="csAuthForgotForm" class="cs-auth-hidden" autocomplete="off">

            <div class="cs-auth-header">
                <h2>Reset your password</h2>
                <p>We'll send a verification code to your email.</p>
            </div>

            <div class="cs-auth-field">
                <label for="csAuthForgotEmail">Email</label>
                <div class="cs-auth-input-wrap">
                    <input type="email" id="csAuthForgotEmail" name="forgot_email" placeholder="you@example.com" autocomplete="username" required>
                </div>
            </div>

            <div class="cs-auth-field">
                <label for="csAuthForgotCode">Verification code</label>
                <div class="cs-auth-verify-row">
                    <input type="text" id="csAuthForgotCode" maxlength="6" inputmode="numeric" placeholder="Enter 6-digit code" autocomplete="off" disabled>
                    <button type="button" id="csAuthForgotSendBtn" class="cs-auth-btn cs-auth-verify-btn" data-mode="send">Send Code</button>
                </div>
            </div>

            <div class="cs-auth-field">
                <label for="csAuthNewPassword">New password</label>
                <div class="cs-auth-input-wrap cs-auth-has-eye">
                    <input type="password" id="csAuthNewPassword" name="new_password" placeholder="Verify your code first" autocomplete="new-password" disabled>
                    <button type="button" class="cs-auth-eye" data-target="csAuthNewPassword" aria-label="Show password">
                        <svg class="cs-auth-eye-open" viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="cs-auth-eye-closed" viewBox="0 0 24 24"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.06 21.06 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.06 21.06 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <div class="cs-auth-field">
                <label for="csAuthConfirmNewPassword">Confirm new password</label>
                <div class="cs-auth-input-wrap cs-auth-has-eye">
                    <input type="password" id="csAuthConfirmNewPassword" name="confirm_new_password" placeholder="Verify your code first" autocomplete="new-password" disabled>
                    <button type="button" class="cs-auth-eye" data-target="csAuthConfirmNewPassword" aria-label="Show password">
                        <svg class="cs-auth-eye-open" viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="cs-auth-eye-closed" viewBox="0 0 24 24"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.06 21.06 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.06 21.06 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="cs-auth-btn cs-auth-btn-submit" id="csAuthResetSubmit" disabled>Reset Password</button>

            <div class="cs-auth-switch">
                Remembered your password? <a href="#" id="csAuthGoLoginFromForgot">Log in</a>
            </div>
        </form>

        </div>
    </div>
</div>

<!-- ================= LOGIN / SIGN UP MODAL SCRIPT ================= -->

<script>
(function () {

    var overlay = document.getElementById('csAuthOverlay');
    if (!overlay) return;

    // Isinasama sa bawat form submit, tinitingnan ng backend (csrf_helper.php).
    function csAuthCsrfToken() {
        var input = document.getElementById('csAuthCsrfToken');
        return input ? input.value : '';
    }

    // Countdown timer (mm:ss) para sa login lockout at resend-code cooldown.
    function csAuthFormatTime(totalSeconds) {
        var minutes = Math.floor(totalSeconds / 60);
        var seconds = totalSeconds % 60;
        return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
    }

    function csAuthStartCountdown(seconds, onTick, onDone) {
        var secondsLeft = seconds;
        onTick(secondsLeft);

        var intervalId = setInterval(function () {
            secondsLeft--;

            if (secondsLeft <= 0) {
                clearInterval(intervalId);
                onDone();
                return;
            }

            onTick(secondsLeft);
        }, 1000);

        return intervalId;
    }

    /* Styled notice/toast, same ginagamit ng commission.js. type: "error" (default) o "success". */
    function csAuthShowNotice(message, type) {
        if (!message) return;

        type = type || 'error';

        var container = document.getElementById('csAuthNoticeContainer');

        if (!container) {
            container = document.createElement('div');
            container.id = 'csAuthNoticeContainer';
            container.className = 'cs-auth-notice-container';
            document.body.appendChild(container);
        }

        // Laging isa lang ang lumalabas sa parehong lugar, hindi
        // nagtatambak-tambak pababa.
        container.innerHTML = '';

        var notice = document.createElement('div');
        notice.className = 'cs-auth-notice-toast cs-notice-' + type;

        var icon = document.createElement('div');
        icon.className = 'cs-auth-notice-icon';
        icon.textContent = type === 'success' ? '✓' : '!';

        var text = document.createElement('div');
        text.className = 'cs-auth-notice-message';
        text.textContent = message;

        notice.appendChild(icon);
        notice.appendChild(text);
        container.appendChild(notice);

        requestAnimationFrame(function () {
            notice.classList.add('cs-notice-show');
        });

        setTimeout(function () {
            notice.classList.remove('cs-notice-show');
            notice.classList.add('cs-notice-hide');
            setTimeout(function () {
                if (notice.parentNode) {
                    notice.parentNode.removeChild(notice);
                }
            }, 250);
        }, 5000);
    }

    var modalBox = overlay.querySelector('.cs-auth-modal');
    var closeBtn = document.getElementById('csAuthClose');
    var loginForm = document.getElementById('csAuthLoginForm');
    var signupForm = document.getElementById('csAuthSignupForm');

    /* ================= OPEN / CLOSE ================= */

    function openAuthModal(showSignup) {
        overlay.classList.add('active');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('cs-auth-lock');

        if (showSignup) {
            showSignupForm();
        } else {
            showLoginForm();
        }
    }

    function closeAuthModal() {
        overlay.classList.remove('active');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('cs-auth-lock');
    }

    // Ginagawa itong global para magamit ng ibang script sa site
    // (hal. isang "Log In muna" prompt sa Commission page) kung
    // kailangan buksan ang modal mula sa ibang lugar.
    window.figurifyOpenAuthModal = openAuthModal;
    window.figurifyCloseAuthModal = closeAuthModal;

    /* "X" button lang ang pwedeng magsara ng popup (hindi click-outside o Escape). */
    if (closeBtn) {
        closeBtn.addEventListener('click', closeAuthModal);
    }

    /* Click sa "Log In" navbar button / [data-auth-open] = mag-popup na lang
       (walang JS = normal link pa rin papunta sa Login.php). */
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('.btn-login-only, [data-auth-open]');
        if (!trigger) return;

        event.preventDefault();
        openAuthModal(trigger.hasAttribute('data-auth-open-signup'));
    });

    /* ?auth=login sa URL (mula protected-page redirect, atbp.) = auto-open
       ang popup; ?auth_error= = ipapakita bilang error. Nililinis ang URL
       pagkatapos (history.replaceState) para hindi bumukas ulit sa refresh. */
    (function () {
        var params = new URLSearchParams(window.location.search);
        if (!params.has('auth')) return;

        var wantsSignup = params.get('auth') === 'signup';
        openAuthModal(wantsSignup);

        var authError = params.get('auth_error');
        if (authError && !wantsSignup) {
            setLoginError(authError);
        }

        params.delete('auth');
        params.delete('auth_error');

        var cleanQuery = params.toString();
        var cleanUrl = window.location.pathname + (cleanQuery ? '?' + cleanQuery : '') + window.location.hash;
        window.history.replaceState({}, document.title, cleanUrl);
    })();

    /* ================= SWITCH LOGIN / SIGN UP / FORGOT ================= */

    var forgotForm = document.getElementById('csAuthForgotForm');

    function showLoginForm() {
        signupForm.classList.add('cs-auth-hidden');
        if (forgotForm) forgotForm.classList.add('cs-auth-hidden');
        loginForm.classList.remove('cs-auth-hidden');
    }

    function showSignupForm() {
        loginForm.classList.add('cs-auth-hidden');
        if (forgotForm) forgotForm.classList.add('cs-auth-hidden');
        signupForm.classList.remove('cs-auth-hidden');
    }

    function showForgotForm() {
        loginForm.classList.add('cs-auth-hidden');
        signupForm.classList.add('cs-auth-hidden');
        if (forgotForm) forgotForm.classList.remove('cs-auth-hidden');
    }

    var goSignup = document.getElementById('csAuthGoSignup');
    var goLogin = document.getElementById('csAuthGoLogin');
    var goForgot = document.getElementById('csAuthGoForgot');
    var goLoginFromForgot = document.getElementById('csAuthGoLoginFromForgot');

    if (goSignup) {
        goSignup.addEventListener('click', function (e) {
            e.preventDefault();
            showSignupForm();
        });
    }

    if (goLogin) {
        goLogin.addEventListener('click', function (e) {
            e.preventDefault();
            showLoginForm();
        });
    }

    if (goForgot) {
        goForgot.addEventListener('click', function (e) {
            e.preventDefault();
            showForgotForm();
        });
    }

    if (goLoginFromForgot) {
        goLoginFromForgot.addEventListener('click', function (e) {
            e.preventDefault();
            showLoginForm();
        });
    }

    /* Generic show/hide-password handler para sa lahat ng eye buttons (SVG icon, hindi emoji). */

    overlay.addEventListener('click', function (event) {
        var eyeBtn = event.target.closest('.cs-auth-eye');
        if (!eyeBtn) return;

        var targetInput = document.getElementById(eyeBtn.getAttribute('data-target'));
        if (!targetInput) return;

        var nowVisible = targetInput.type === 'password';
        targetInput.type = nowVisible ? 'text' : 'password';
        eyeBtn.classList.toggle('is-visible', nowVisible);
        eyeBtn.setAttribute('aria-label', nowVisible ? 'Hide password' : 'Show password');
    });

    /* ================= LOGIN (AJAX, JSON response) ================= */

    function setLoginError(message) {
        csAuthShowNotice(message, 'error');
    }

    if (loginForm) {
        loginForm.addEventListener('submit', function (event) {
            event.preventDefault();
            setLoginError('');

            var email = document.getElementById('csAuthEmail').value.trim();
            var password = document.getElementById('csAuthPassword').value.trim();

            if (!email || !password) {
                setLoginError('Please enter your email and password.');
                return;
            }

            var submitBtn = document.getElementById('csAuthLoginSubmit');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Logging in...';

            var formData = new FormData();
            formData.append('action', 'login');
            formData.append('ajax', '1');
            formData.append('email', email);
            formData.append('password', password);
            formData.append('csrf_token', csAuthCsrfToken());

            fetch('../Login/Login.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.status === 'success') {
                        window.location.href = data.redirect || window.location.href;
                    } else if (data.retry_after) {
                        /* Naka-lock muna: ipakita ang live countdown sa
                           toast at sa button mismo, hanggang matapos. */
                        submitBtn.disabled = true;

                        csAuthStartCountdown(
                            data.retry_after,
                            function (secondsLeft) {
                                var timeText = csAuthFormatTime(secondsLeft);
                                csAuthShowNotice('Too many failed login attempts. Try again in ' + timeText + '.', 'error');
                                submitBtn.textContent = 'Try again in ' + timeText;
                            },
                            function () {
                                submitBtn.disabled = false;
                                submitBtn.textContent = 'Log In';
                                csAuthShowNotice('You can try logging in again now.', 'success');
                            }
                        );
                    } else {
                        setLoginError(data.message || 'Invalid email or password.');
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Log In';
                    }
                })
                .catch(function () {
                    setLoginError('Unable to log in right now. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Log In';
                });
        });
    }

    /* ================= SIGN UP ================= */
    /* Iisang button (csAuthSendCodeBtn) para sa Send Code/Verify Code,
       nagpapalit lang ng mode/label (data-mode: send -> verify -> verified).
       Naka-lock ang code/password fields hanggat hindi pa na-verify. */

    var sendCodeBtn = document.getElementById('csAuthSendCodeBtn');
    var verificationCodeInput = document.getElementById('csAuthVerificationCode');
    var signupPasswordInput = document.getElementById('csAuthSignupPassword');
    var confirmPasswordInput = document.getElementById('csAuthConfirmPassword');
    var createAccountBtn = document.getElementById('csAuthCreateAccountBtn');
    var agreeTermsInput = document.getElementById('csAuthAgreeTerms');

    function setSignupError(message) {
        csAuthShowNotice(message, 'error');
    }

    function setSignupSuccess(message) {
        csAuthShowNotice(message, 'success');
    }

    /* Ang Create Account ay dapat naka-enable lang kapag PAREHONG
       (1) na-verify na ang code (bukas na ang password fields) AT
       (2) na-check na ang Terms and Conditions checkbox. */
    function updateCreateAccountState() {
        if (!createAccountBtn) return;
        var verified = signupPasswordInput && !signupPasswordInput.disabled;
        var agreed = agreeTermsInput && agreeTermsInput.checked;
        createAccountBtn.disabled = !(verified && agreed);
    }

    if (agreeTermsInput) {
        agreeTermsInput.addEventListener('change', updateCreateAccountState);
    }

    /* Ibinabalik ang Sign Up form sa naka-lock na state pagkatapos ng successful signup. */
    function resetSignupLockState() {
        sendCodeBtn.setAttribute('data-mode', 'send');
        sendCodeBtn.disabled = false;
        sendCodeBtn.textContent = 'Send Code';
        verificationCodeInput.disabled = true;
        signupPasswordInput.disabled = true;
        confirmPasswordInput.disabled = true;
        signupPasswordInput.placeholder = 'Verify your code first';
        confirmPasswordInput.placeholder = 'Verify your code first';
        updateCreateAccountState();
    }

    /* -------- SEND CODE / VERIFY CODE (iisang button) -------- */

    if (sendCodeBtn) {
        sendCodeBtn.addEventListener('click', async function () {
            setSignupError('');

            var mode = sendCodeBtn.getAttribute('data-mode');

            if (mode === 'send') {

                var name = document.getElementById('csAuthFullName').value.trim();
                var email = document.getElementById('csAuthSignupEmail').value.trim();

                if (!name || !email) {
                    setSignupError('Please enter your name and Gmail.');
                    return;
                }

                var formData = new FormData();
                formData.append('full_name', name);
                formData.append('email', email);
                formData.append('csrf_token', csAuthCsrfToken());

                sendCodeBtn.disabled = true;
                sendCodeBtn.textContent = 'Sending...';

                try {
                    var response = await fetch('../Login/send_code.php', { method: 'POST', body: formData });
                    var result = await response.text();
                    result = result.trim();

                    if (result === 'success') {
                        setSignupSuccess('Verification code has been sent.');
                        verificationCodeInput.disabled = false;
                        verificationCodeInput.focus();
                        sendCodeBtn.setAttribute('data-mode', 'verify');
                        sendCodeBtn.textContent = 'Verify Code';
                        sendCodeBtn.disabled = false;
                    } else if (result.indexOf('COOLDOWN|') === 0) {
                        /* May naipadala nang code kamakailan lang -
                           ipakita ang live countdown sa button hanggang
                           puwede na ulit mag-send. */
                        var cooldownSeconds = parseInt(result.split('|')[1], 10) || 600;

                        sendCodeBtn.disabled = true;

                        csAuthStartCountdown(
                            cooldownSeconds,
                            function (secondsLeft) {
                                sendCodeBtn.textContent = 'Resend in ' + csAuthFormatTime(secondsLeft);
                            },
                            function () {
                                sendCodeBtn.disabled = false;
                                sendCodeBtn.textContent = 'Send Code';
                            }
                        );
                    } else {
                        setSignupError(result);
                        sendCodeBtn.textContent = 'Send Code';
                        sendCodeBtn.disabled = false;
                    }
                } catch (error) {
                    setSignupError('Unable to send verification code.');
                    sendCodeBtn.textContent = 'Send Code';
                    sendCodeBtn.disabled = false;
                }

            } else if (mode === 'verify') {

                var codeEmail = document.getElementById('csAuthSignupEmail').value.trim();
                var code = verificationCodeInput.value.trim();

                if (code.length !== 6) {
                    setSignupError('Please enter the 6-digit verification code.');
                    return;
                }

                var verifyFormData = new FormData();
                verifyFormData.append('email', codeEmail);
                verifyFormData.append('code', code);
                verifyFormData.append('csrf_token', csAuthCsrfToken());

                sendCodeBtn.disabled = true;
                sendCodeBtn.textContent = 'Verifying...';

                try {
                    var verifyResponse = await fetch('../Login/verify_code.php', { method: 'POST', body: verifyFormData });
                    var verifyResult = await verifyResponse.text();

                    if (verifyResult.trim() === 'success') {
                        setSignupSuccess('Email verified successfully! You can now set your password.');
                        verificationCodeInput.disabled = true;
                        signupPasswordInput.disabled = false;
                        confirmPasswordInput.disabled = false;
                        signupPasswordInput.placeholder = 'Create a password';
                        confirmPasswordInput.placeholder = 'Repeat your password';
                        signupPasswordInput.focus();
                        sendCodeBtn.setAttribute('data-mode', 'verified');
                        sendCodeBtn.textContent = 'Verified ✓';
                        sendCodeBtn.disabled = true;
                        updateCreateAccountState();
                    } else {
                        setSignupError('Invalid or expired verification code.');
                        sendCodeBtn.disabled = false;
                        sendCodeBtn.textContent = 'Verify Code';
                    }
                } catch (error) {
                    setSignupError('Unable to verify the code.');
                    sendCodeBtn.disabled = false;
                    sendCodeBtn.textContent = 'Verify Code';
                }
            }
        });
    }

    /* -------- CREATE ACCOUNT -------- */

    if (signupForm) {
        signupForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            setSignupError('');

            var name = document.getElementById('csAuthFullName').value.trim();
            var email = document.getElementById('csAuthSignupEmail').value.trim();
            var password = document.getElementById('csAuthSignupPassword').value;
            var confirmPassword = document.getElementById('csAuthConfirmPassword').value;
            var agreeTerms = document.getElementById('csAuthAgreeTerms');

            if (!name || !email || !password || !confirmPassword) {
                setSignupError('Please complete all fields.');
                return;
            }

            if (password.length < 8) {
                setSignupError('Password must be at least 8 characters.');
                return;
            }

            if (password !== confirmPassword) {
                setSignupError('Passwords do not match.');
                return;
            }

            if (!agreeTerms || !agreeTerms.checked) {
                setSignupError('Please agree to the Terms and Conditions and Privacy Policy before creating an account.');
                return;
            }

            var formData = new FormData();
            formData.append('full_name', name);
            formData.append('email', email);
            formData.append('password', password);
            formData.append('confirm_password', confirmPassword);
            formData.append('agree_terms', '1');
            formData.append('csrf_token', csAuthCsrfToken());

            try {
                var response = await fetch('../Login/signup.php', { method: 'POST', body: formData });
                var result = await response.text();

                if (result.trim() === 'success') {
                    setSignupSuccess('Account created successfully! You can now log in.');
                    setTimeout(function () {
                        signupForm.reset();
                        resetSignupLockState();
                        setSignupSuccess('');
                        showLoginForm();
                    }, 1400);
                } else {
                    setSignupError(result);
                }
            } catch (error) {
                setSignupError('Unable to create account.');
            }
        });
    }

    /* Forgot Password — same pattern ng Sign Up (data-mode button, naka-lock hanggat na-verify). */

    var forgotSendBtn = document.getElementById('csAuthForgotSendBtn');
    var forgotCodeInput = document.getElementById('csAuthForgotCode');
    var forgotEmailInput = document.getElementById('csAuthForgotEmail');
    var newPasswordInput = document.getElementById('csAuthNewPassword');
    var confirmNewPasswordInput = document.getElementById('csAuthConfirmNewPassword');
    var resetSubmitBtn = document.getElementById('csAuthResetSubmit');

    function updateResetSubmitState() {
        if (!resetSubmitBtn) return;
        var verified = newPasswordInput && !newPasswordInput.disabled;
        resetSubmitBtn.disabled = !verified;
    }

    function resetForgotLockState() {
        if (!forgotSendBtn) return;
        forgotSendBtn.setAttribute('data-mode', 'send');
        forgotSendBtn.disabled = false;
        forgotSendBtn.textContent = 'Send Code';
        forgotCodeInput.disabled = true;
        newPasswordInput.disabled = true;
        confirmNewPasswordInput.disabled = true;
        newPasswordInput.placeholder = 'Verify your code first';
        confirmNewPasswordInput.placeholder = 'Verify your code first';
        updateResetSubmitState();
    }

    if (forgotSendBtn) {
        forgotSendBtn.addEventListener('click', async function () {

            var mode = forgotSendBtn.getAttribute('data-mode');

            if (mode === 'send') {

                var email = forgotEmailInput.value.trim();

                if (!email) {
                    csAuthShowNotice('Please enter your email.', 'error');
                    return;
                }

                var formData = new FormData();
                formData.append('action', 'send');
                formData.append('email', email);
                formData.append('csrf_token', csAuthCsrfToken());

                forgotSendBtn.disabled = true;
                forgotSendBtn.textContent = 'Sending...';

                try {
                    var response = await fetch('../Login/forgot_password.php', { method: 'POST', body: formData });
                    var data = await response.json();

                    if (data.status === 'success') {
                        csAuthShowNotice(data.message || 'Verification code has been sent.', 'success');
                        forgotCodeInput.disabled = false;
                        forgotCodeInput.focus();
                        forgotSendBtn.setAttribute('data-mode', 'verify');
                        forgotSendBtn.textContent = 'Verify Code';
                        forgotSendBtn.disabled = false;
                    } else if (data.status === 'cooldown' && data.retry_after) {
                        /* May naipadala nang code kamakailan lang -
                           ipakita ang live countdown sa button. */
                        forgotSendBtn.disabled = true;

                        csAuthStartCountdown(
                            data.retry_after,
                            function (secondsLeft) {
                                forgotSendBtn.textContent = 'Resend in ' + csAuthFormatTime(secondsLeft);
                            },
                            function () {
                                forgotSendBtn.disabled = false;
                                forgotSendBtn.textContent = 'Send Code';
                            }
                        );
                    } else {
                        csAuthShowNotice(data.message || 'Unable to send verification code.', 'error');
                        forgotSendBtn.textContent = 'Send Code';
                        forgotSendBtn.disabled = false;
                    }
                } catch (error) {
                    csAuthShowNotice('Unable to send verification code.', 'error');
                    forgotSendBtn.textContent = 'Send Code';
                    forgotSendBtn.disabled = false;
                }

            } else if (mode === 'verify') {

                var codeEmail = forgotEmailInput.value.trim();
                var code = forgotCodeInput.value.trim();

                if (code.length !== 6) {
                    csAuthShowNotice('Please enter the 6-digit verification code.', 'error');
                    return;
                }

                var verifyFormData = new FormData();
                verifyFormData.append('action', 'verify');
                verifyFormData.append('email', codeEmail);
                verifyFormData.append('code', code);
                verifyFormData.append('csrf_token', csAuthCsrfToken());

                forgotSendBtn.disabled = true;
                forgotSendBtn.textContent = 'Verifying...';

                try {
                    var verifyResponse = await fetch('../Login/forgot_password.php', { method: 'POST', body: verifyFormData });
                    var verifyData = await verifyResponse.json();

                    if (verifyData.status === 'success') {
                        csAuthShowNotice('Email verified! You can now set a new password.', 'success');
                        forgotCodeInput.disabled = true;
                        newPasswordInput.disabled = false;
                        confirmNewPasswordInput.disabled = false;
                        newPasswordInput.placeholder = 'Create a new password';
                        confirmNewPasswordInput.placeholder = 'Repeat your new password';
                        newPasswordInput.focus();
                        forgotSendBtn.setAttribute('data-mode', 'verified');
                        forgotSendBtn.textContent = 'Verified ✓';
                        forgotSendBtn.disabled = true;
                        updateResetSubmitState();
                    } else {
                        csAuthShowNotice(verifyData.message || 'Invalid or expired verification code.', 'error');
                        forgotSendBtn.disabled = false;
                        forgotSendBtn.textContent = 'Verify Code';
                    }
                } catch (error) {
                    csAuthShowNotice('Unable to verify the code.', 'error');
                    forgotSendBtn.disabled = false;
                    forgotSendBtn.textContent = 'Verify Code';
                }
            }
        });
    }

    if (forgotForm) {
        forgotForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            var email = forgotEmailInput.value.trim();
            var password = newPasswordInput.value;
            var confirmPassword = confirmNewPasswordInput.value;

            if (newPasswordInput.disabled) {
                csAuthShowNotice('Please verify your email first.', 'error');
                return;
            }

            if (!password || !confirmPassword) {
                csAuthShowNotice('Please complete all fields.', 'error');
                return;
            }

            if (password.length < 8) {
                csAuthShowNotice('Password must be at least 8 characters.', 'error');
                return;
            }

            if (password !== confirmPassword) {
                csAuthShowNotice('Passwords do not match.', 'error');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'reset');
            formData.append('email', email);
            formData.append('password', password);
            formData.append('confirm_password', confirmPassword);
            formData.append('csrf_token', csAuthCsrfToken());

            try {
                var response = await fetch('../Login/forgot_password.php', { method: 'POST', body: formData });
                var data = await response.json();

                if (data.status === 'success') {
                    csAuthShowNotice(data.message || 'Your password has been reset. You can now log in.', 'success');
                    setTimeout(function () {
                        forgotForm.reset();
                        resetForgotLockState();
                        showLoginForm();
                    }, 1400);
                } else {
                    csAuthShowNotice(data.message || 'Unable to reset password.', 'error');
                }
            } catch (error) {
                csAuthShowNotice('Unable to reset password. Please try again.', 'error');
            }
        });
    }

})();
</script>
