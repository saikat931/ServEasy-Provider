<?php

$bridgeFile = file_exists(__DIR__ . '/common/ApiBridge.php')
    ? __DIR__ . '/common/ApiBridge.php'
    : (file_exists(__DIR__ . '/../common/ApiBridge.php') ? __DIR__ . '/../common/ApiBridge.php' : null);
if ($bridgeFile) {
    require_once $bridgeFile;
    $otpConfig = \Frontend\ApiBridge::getOtpConfig();
} else {
    $otpConfig = [
        'mode' => 'both',
        'label' => 'Email or Phone Number',
        'placeholder' => 'Please enter your phone number or email',
        'validation_message' => 'Please enter a valid email or 10-digit mobile number.',
        'input_type' => 'text'
    ];
}

$otpMode = $otpConfig['mode'] ?? 'both';
$otpLabel = $otpConfig['label'] ?? 'Email or Phone Number';
$otpPlaceholder = $otpConfig['placeholder'] ?? 'Please enter your phone number or email';
$otpValidationMsg = $otpConfig['validation_message'] ?? 'Please enter a valid email or 10-digit mobile number.';
$otpInputType = $otpConfig['input_type'] ?? 'text';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1']) || str_starts_with($host, 'localhost:');
$backHomeUrl = $isLocalHost ? '../frontend-web/index.php' : ($_ENV['WEB_PORTAL_URL'] ?? 'https://silver-penguin-156353.hostingersite.com/');
session_start();

if (isset($_SESSION['dealer'])) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <script src="./config.js?v=20261118v2"></script>
  <script src="./serveasy-api-bridge.js?v=20261118v2"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Provider Login | ServEasy</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">

    <!-- Ionicons -->
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <link rel="stylesheet" href="css/notify.css">
    <style>
        :root {
            --emerald: hsl(152, 95%, 39%);
            --emerald-dark: hsl(152, 95%, 31%);
            --mint-cream: hsl(96, 36%, 95%);
            --russian-violet: hsl(245, 97%, 13%);
            --gunmetal-2: hsl(206, 21%, 15%);
            --white: hsl(0, 0%, 100%);
            --black_50: hsla(0, 0%, 0%, 0.5);
            --shadow-1: 0 12px 25px -8px hsla(0, 0%, 0%, 0.2);
            --transition-2: 0.25s ease;
            --ff-playfair: 'Playfair Display', serif;
            --ff-roboto: 'Roboto', sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--ff-roboto);
            background: url('./img/hero-banner.png');
            background-size: cover;
            background-position: center;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(8px);
            z-index: 1;
        }

        .login-card {
            position: relative;
            z-index: 2;
            background: var(--white);
            padding: 40px;
            border-radius: 20px;
            box-shadow: var(--shadow-1);
            width: 100%;
            max-width: 420px;
            text-align: center;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            font-family: var(--ff-playfair);
            font-size: 3.2rem;
            color: var(--gunmetal-2);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .logo .span {
            color: var(--emerald);
        }

        .logo ion-icon {
            color: var(--emerald);
        }

        .badge {
            background: var(--mint-cream);
            color: var(--emerald);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .subtitle {
            color: var(--black_50);
            font-size: 1.1rem;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gunmetal-2);
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper ion-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.4rem;
            color: var(--emerald);
        }

        input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid var(--mint-cream);
            background: var(--mint-cream);
            border-radius: 12px;
            font-size: 1rem;
            color: var(--gunmetal-2);
            transition: var(--transition-2);
            outline: none;
        }

        input:focus {
            border-color: var(--emerald);
            background: var(--white);
        }

        .btn {
            width: 100%;
            padding: 15px;
            background: var(--emerald);
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition-2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }

        .btn:hover {
            background: var(--emerald-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px hsla(152, 95%, 39%, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        #otpBox {
            display: none;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid var(--mint-cream);
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .error {
            color: #e74c3c;
            font-size: 0.85rem;
            margin-top: 5px;
            display: none;
        }

        .success {
            color: var(--emerald);
            font-size: 0.9rem;
            margin-bottom: 15px;
            display: none;
            font-weight: 500;
        }

        .back-home {
            margin-top: 25px;
            display: inline-block;
            color: var(--emerald);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition-2);
        }

        .back-home:hover {
            color: var(--emerald-dark);
            text-decoration: underline;
        }

        .spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>

    <div class="login-card">
        <div class="logo">
            <ion-icon name="leaf-outline"></ion-icon>
            ServEasy<span class="span">.</span>
        </div>
        <div class="badge">Service Provider Portal</div>
        <p class="subtitle">Secure access to your Service Provider dashboard.</p>

        <div id="successMsg" class="success"></div>

        <div id="identitySection">
            <div class="form-group">
                <label for="identity" id="identityLabel"><?= htmlspecialchars($otpLabel) ?></label>
                <div class="input-wrapper">
                    <ion-icon name="<?= $otpMode === 'phone' ? 'call-outline' : ($otpMode === 'email' ? 'mail-outline' : 'person-outline') ?>" id="identityIcon"></ion-icon>
                    <input type="<?= htmlspecialchars($otpInputType) ?>" id="identity" placeholder="<?= htmlspecialchars($otpPlaceholder) ?>" oninput="validateIdentity()">
                </div>
                <div class="error" id="errorMsg"><?= htmlspecialchars($otpValidationMsg) ?></div>
            </div>

            <button class="btn" onclick="sendOTP()" id="sendBtn">
                <span>Send OTP</span>
                <ion-icon name="arrow-forward-outline"></ion-icon>
            </button>
        </div>

        <div id="otpBox">
            <div class="form-group">
                <label for="otp">Enter Verification Code</label>
                <div class="input-wrapper">
                    <ion-icon name="shield-checkmark-outline"></ion-icon>
                    <input type="text" id="otp" placeholder="Enter 6-digit OTP" maxlength="6">
                </div>
            </div>
            <button class="btn" onclick="verifyOTP()" id="verifyBtn">
                <span>Verify & Login</span>
                <ion-icon name="checkmark-circle-outline"></ion-icon>
            </button>
            <p style="margin-top: 15px; font-size: 0.85rem; color: var(--black_50);">
                Didn't get the code? <a href="#" onclick="sendOTP()"
                    style="color: var(--emerald); font-weight: 600;">Resend</a>
            </p>
        </div>

        <a href="<?= htmlspecialchars($backHomeUrl) ?>" class="back-home">
            <ion-icon name="chevron-back-outline" style="display: inline-block; vertical-align: middle;"></ion-icon>
            Back to Home
        </a>
    </div>

    <script src="js/notify.js"></script>
    <script>

        var OTP_CONFIG = <?= json_encode($otpConfig) ?>;

        function refreshOtpConfig() {
            (window.serveasyFetch ? window.serveasyFetch("platform-settings.php?action=get_otp_config") : fetch((window.SERVEASY_API_BASE || "../../backend/api/") + "platform-settings.php?action=get_otp_config"))
                .then(res => res.json())
                .then(res => {
                    if (res && res.status === "success" && res.data) {
                        OTP_CONFIG = res.data;
                        var lbl = document.getElementById("identityLabel");
                        var inp = document.getElementById("identity");
                        var icn = document.getElementById("identityIcon");
                        if (lbl) lbl.innerText = OTP_CONFIG.label;
                        if (inp) {
                            inp.placeholder = OTP_CONFIG.placeholder;
                            inp.type = OTP_CONFIG.input_type || "text";
                        }
                        if (icn) {
                            if (OTP_CONFIG.mode === "phone") icn.setAttribute("name", "call-outline");
                            else if (OTP_CONFIG.mode === "email") icn.setAttribute("name", "mail-outline");
                            else icn.setAttribute("name", "person-outline");
                        }
                    }
                })
                .catch(() => {});
        }

        window.addEventListener("focus", refreshOtpConfig);
        window.addEventListener("DOMContentLoaded", function () {
            refreshOtpConfig();
            const idEl = document.getElementById("identity");
            const otpEl = document.getElementById("otp");
            if (idEl) {
                idEl.addEventListener("keydown", function (e) {
                    if (e.key === "Enter") { e.preventDefault(); sendOTP(); }
                });
            }
            if (otpEl) {
                otpEl.addEventListener("keydown", function (e) {
                    if (e.key === "Enter") { e.preventDefault(); verifyOTP(); }
                });
            }
        });

        function validateIdentity() {
            let identity = document.getElementById("identity").value.trim();
            let errorMsg = document.getElementById("errorMsg");

            let emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            let cleanPhone = identity.replace(/[\s\-]/g, "");
            let phonePattern = /^\+?\d{10,13}$/;

            if (identity === "") {
                errorMsg.style.display = "none";
                return false;
            }

            if (OTP_CONFIG.mode === "email") {
                if (/^\d+$/.test(cleanPhone)) {
                    errorMsg.innerText = "Phone number login is closed. Please enter your email.";
                    errorMsg.style.display = "block";
                    return false;
                }
                if (!emailPattern.test(identity)) {
                    errorMsg.innerText = "Please enter a valid email address.";
                    errorMsg.style.display = "block";
                    return false;
                }
            } else if (OTP_CONFIG.mode === "phone") {
                if (identity.includes("@")) {
                    errorMsg.innerText = "Email login is closed. Please enter your phone number.";
                    errorMsg.style.display = "block";
                    return false;
                }
                if (!phonePattern.test(cleanPhone)) {
                    errorMsg.innerText = "Please enter a valid 10-digit mobile number.";
                    errorMsg.style.display = "block";
                    return false;
                }
            } else {
                if (!emailPattern.test(identity) && !phonePattern.test(cleanPhone)) {
                    errorMsg.innerText = "Please enter a valid email or 10-digit mobile number.";
                    errorMsg.style.display = "block";
                    return false;
                }
            }

            errorMsg.style.display = "none";
            return true;
        }

        function sendOTP() {
            if (!validateIdentity()) {
                return;
            }

            let identity = document.getElementById("identity").value.trim();
            let sendBtn = document.getElementById("sendBtn");

            sendBtn.innerHTML = '<span>Sending...</span><ion-icon name="sync-outline" class="spin"></ion-icon>';
            sendBtn.disabled = true;

            const dealerApiUrl = (window.serveasyApiUrl ? window.serveasyApiUrl('dealer-auth-api.php') : ((window.SERVEASY_API_BASE || '../../backend/api/') + 'dealer-auth-api.php'));
            fetch(dealerApiUrl, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "action=send_otp&identity=" + encodeURIComponent(identity)
            })
                .then(res => res.json())
                .then(data => {
                    sendBtn.innerHTML = '<span>Send OTP</span><ion-icon name="arrow-forward-outline"></ion-icon>';
                    sendBtn.disabled = false;

                    if (data.status == "success") {
                        let successText = data.message || "Verification code sent to your email/phone.";
                        document.getElementById("successMsg").innerHTML = successText;
                        document.getElementById("successMsg").style.display = "block";
                        document.getElementById("otpBox").style.display = "block";
                        document.getElementById("identitySection").style.opacity = "0.6";
                        document.getElementById("identity").disabled = true;

                        if (data.otp) {
                            Notify.info("Your OTP is: " + data.otp, { title: "Login OTP" });
                        } else {
                            Notify.success(successText);
                        }
                    } else {
                        Notify.error(data.message || "Failed to send OTP");
                    }
                })
                .catch(err => {
                    console.error("Error:", err);
                    sendBtn.innerHTML = '<span>Send OTP</span><ion-icon name="arrow-forward-outline"></ion-icon>';
                    sendBtn.disabled = false;
                    Notify.error("Server error");
                });
        }

        function verifyOTP() {
            let identity = document.getElementById("identity").value.trim();
            let otp = document.getElementById("otp").value.trim();
            let verifyBtn = document.getElementById("verifyBtn");

            if (otp == "") {
                Notify.warning("Enter OTP");
                return;
            }

            verifyBtn.innerHTML = '<span>Verifying...</span><ion-icon name="sync-outline" class="spin"></ion-icon>';
            verifyBtn.disabled = true;

            const dealerApiUrl = (window.serveasyApiUrl ? window.serveasyApiUrl('dealer-auth-api.php') : ((window.SERVEASY_API_BASE || '../../backend/api/') + 'dealer-auth-api.php'));
            fetch(dealerApiUrl, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "action=verify_otp&identity=" + encodeURIComponent(identity) + "&otp=" + encodeURIComponent(otp)
            })
                .then(res => res.json())
                .then(async data => {
                    if (data.status == "success") {
                        try {
                            if (data.access_token) localStorage.setItem("provider_access_token", data.access_token);
                            if (data.user) localStorage.setItem("provider_user", JSON.stringify(data.user));
                        } catch (e) {}

                        // Sync PHP session on this Provider domain (required for split-domain Hostinger deployments)
                        if (data.access_token) {
                            try {
                                const syncRes = await fetch("./session-sync.php", {
                                    method: "POST",
                                    credentials: "same-origin",
                                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                                    body: "access_token=" + encodeURIComponent(data.access_token)
                                });
                                const syncData = await syncRes.json();
                                if (!syncData || syncData.status !== "success") {
                                    verifyBtn.innerHTML = '<span>Verify & Login</span><ion-icon name="checkmark-circle-outline"></ion-icon>';
                                    verifyBtn.disabled = false;
                                    Notify.error((syncData && syncData.message) || "Failed to initialize portal session");
                                    return;
                                }
                            } catch (syncErr) {
                                console.error("Session sync error:", syncErr);
                                verifyBtn.innerHTML = '<span>Verify & Login</span><ion-icon name="checkmark-circle-outline"></ion-icon>';
                                verifyBtn.disabled = false;
                                Notify.error("Could not establish portal session. Please try again.");
                                return;
                            }
                        }

                        window.location = "index.php";
                    } else {
                        verifyBtn.innerHTML = '<span>Verify & Login</span><ion-icon name="checkmark-circle-outline"></ion-icon>';
                        verifyBtn.disabled = false;
                        Notify.error(data.message);
                    }
                })
                .catch(err => {
                    console.error("Error:", err);
                    verifyBtn.innerHTML = '<span>Verify & Login</span><ion-icon name="checkmark-circle-outline"></ion-icon>';
                    verifyBtn.disabled = false;
                    Notify.error("Server error");
                });
        }
    </script>
</body>

</html>
