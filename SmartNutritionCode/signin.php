<?php
// C:\xampp\htdocs\smartnutrition\signin.php
declare(strict_types=1);
session_start();

error_reporting(E_ALL);
ini_set('display_errors', '1');

/* ========== DB CONFIG ========== */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'smart_nutrition';
const DB_USER = 'root';
const DB_PASS = '';

require_once __DIR__ . '/sendEmail.php';   // PHPMailer helper with sendOtpMail()

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * Ensure password_resets table exists
 * Columns expected: id, user_id, email, otp_code, expires_at, used, created_at
 */
function ensurePasswordResetsTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            email VARCHAR(255) NOT NULL,
            otp_code VARCHAR(10) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");
}

/* ========== CSRF TOKEN ========== */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

/* If already logged in, go to dashboard */
if (!empty($_SESSION['user_id'])) {
    header('Location: Index.php');
    exit;
}

/* ========== HANDLE FORMS ========== */
$errors          = [];
$infoMsg         = '';
$showResetPanel  = false;   // whole orange box
$showOtpStep     = false;   // OTP + new password fields

$storedResetEmail = $_SESSION['reset_email'] ?? '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {

    // CSRF check
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $errors[] = 'Security token mismatch. Please refresh and try again.';
    } else {
        $pdo  = db();
        $mode = $_POST['mode'] ?? 'login';

        /* ---------- LOGIN MODE ---------- */
        if ($mode === 'login') {
            $identifier = trim((string)($_POST['identifier'] ?? '')); // email or username
            $password   = (string)($_POST['password'] ?? '');

            if ($identifier === '') {
                $errors[] = 'Please enter your email or username.';
            }
            if ($password === '') {
                $errors[] = 'Password is required.';
            }

            if (!$errors) {
                $isEmail = (bool)filter_var($identifier, FILTER_VALIDATE_EMAIL);

                if ($isEmail) {
                    $st = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ? LIMIT 1');
                } else {
                    $st = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE name = ? LIMIT 1');
                }
                $st->execute([$identifier]);
                $user = $st->fetch();

                if ($user) {
                    $hash = (string)($user['password_hash'] ?? '');
                    $ok   = $hash && password_verify($password, $hash);

                    // Optional legacy plain-text compatibility
                    if (!$ok && $hash === $password) {
                        $ok = true;
                    }

                    if ($ok) {
                        $_SESSION['user_id']   = (int)$user['id'];
                        $_SESSION['user_name'] = (string)$user['name'];

                        header('Location: Index.php');
                        exit;
                    }
                }

                $errors[] = 'Invalid credentials. Please try again.';
            }

        /* ---------- SEND OTP (STEP 1) ---------- */
        } elseif ($mode === 'send_otp') {
            $showResetPanel = true;          // show the orange box
            $showOtpStep    = false;         // only show email field first
            ensurePasswordResetsTable($pdo);

            $email = trim((string)($_POST['reset_email'] ?? ''));

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            }

            if (!$errors) {
                $st = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $st->execute([$email]);
                $user = $st->fetch();

                if (!$user) {
                    $errors[] = 'No account found with that email.';
                } else {
                    $otp     = (string)random_int(100000, 999999);
                    $expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes

                    // Clear any previous OTPs for this email
                    $pdo->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);

                    // Store OTP
                    $ins = $pdo->prepare('
                        INSERT INTO password_resets (user_id, email, otp_code, expires_at)
                        VALUES (?, ?, ?, ?)
                    ');
                    $ins->execute([(int)$user['id'], $email, $otp, $expires]);

                    if (sendOtpMail($email, $otp)) {
                        $infoMsg        = 'We sent a 6-digit OTP to your email. It is valid for 10 minutes.';
                        $showOtpStep    = true;        // NOW reveal OTP + password fields
                        $_SESSION['reset_email'] = $email;
                        $storedResetEmail       = $email;
                    } else {
                        $errors[] = 'OTP generated but email sending failed. Check your PHPMailer / SMTP config.';
                    }
                }
            }

        /* ---------- VERIFY OTP (STEP 2) ---------- */
        } elseif ($mode === 'verify_otp') {
            $showResetPanel = true;
            $showOtpStep    = true;     // keep OTP fields visible
            ensurePasswordResetsTable($pdo);

            // Email comes from hidden field (pre-filled from session)
            $email    = trim((string)($_POST['otp_email'] ?? ''));
            $otp      = trim((string)($_POST['otp_code'] ?? ''));
            $newPass1 = (string)($_POST['new_password'] ?? '');
            $newPass2 = (string)($_POST['new_password_confirm'] ?? '');

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email for OTP verification.';
            }
            if ($otp === '' || !preg_match('/^[0-9]{6}$/', $otp)) {
                $errors[] = 'Please enter the 6-digit OTP sent to your email.';
            }
            if ($newPass1 === '' || strlen($newPass1) < 6) {
                $errors[] = 'New password must be at least 6 characters.';
            }
            if ($newPass1 !== $newPass2) {
                $errors[] = 'New password and confirmation do not match.';
            }

            if (!$errors) {
                $st = $pdo->prepare('
                    SELECT id, otp_code, expires_at, used
                    FROM password_resets
                    WHERE email = ?
                    ORDER BY id DESC
                    LIMIT 1
                ');
                $st->execute([$email]);
                $resetRow = $st->fetch();

                if (!$resetRow || (int)$resetRow['used'] === 1) {
                    $errors[] = 'No active OTP found for this email. Please request a new one.';
                } else {
                    $storedOtp = (string)$resetRow['otp_code'];
                    $expiresAt = $resetRow['expires_at'];
                    $now       = time();
                    $expTime   = $expiresAt ? strtotime($expiresAt) : 0;

                    if ($storedOtp === '' || !$expTime || $expTime < $now) {
                        $errors[] = 'OTP is invalid or has expired. Please request a new one.';
                    } elseif (!hash_equals($storedOtp, $otp)) {
                        $errors[] = 'Incorrect OTP. Please check your email and try again.';
                    } else {
                        // Update user password
                        $u = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                        $u->execute([$email]);
                        $user = $u->fetch();

                        if (!$user) {
                            $errors[] = 'No account found with that email.';
                        } else {
                            $hash = password_hash($newPass1, PASSWORD_DEFAULT);
                            $up   = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                            $up->execute([$hash, $user['id']]);

                            // Mark OTPs used & clean session email
                            $pdo->prepare('UPDATE password_resets SET used = 1 WHERE email = ?')->execute([$email]);
                            unset($_SESSION['reset_email']);
                            $storedResetEmail = '';

                            // Hide panel after success
                            $showResetPanel = false;
                            $showOtpStep    = false;
                            $infoMsg        = 'Your password has been reset. Please sign in with your new password.';
                        }
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sign In — Smart Nutrition</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
        --bg: #ffffff;
        --text: #1a1a1a;
        --muted: #555;
        --accent: #ff7a00;
        --accent-dark: #e56a00;
        --radius: 10px;
        --shadow: 0 6px 30px rgba(0,0,0,.08);
        --border: #e1e1e1;
    }
    body {
        margin: 0;
        font-family: "Inter", system-ui, sans-serif;
        background: var(--bg);
        color: var(--text);
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
    }
    .login-container {
        width: 100%;
        max-width: 430px;
        background: #fff;
        padding: 2rem 2.5rem;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        text-align: center;
    }
    .login-logo img {
        width: 72px;
        height: 72px;
        border-radius: var(--radius);
        margin-bottom: 1rem;
    }
    h2 {
        font-size: 1.7rem;
        font-weight: 900;
        margin-bottom: 0.3rem;
    }
    p.sub {
        color: var(--muted);
        font-size: 0.9rem;
        margin-bottom: 1.2rem;
    }
    input {
        width: 100%;
        padding: 0.75rem;
        margin-top: 0.6rem;
        border-radius: var(--radius);
        border: 1.4px solid var(--border);
        background: #fafafa;
        font-size: 1rem;
        transition: .25s;
    }
    input:focus {
        outline: none;
        border-color: var(--accent);
        background: #fff;
    }
    .btn-login {
        width: 100%;
        padding: 0.9rem;
        border-radius: var(--radius);
        border: none;
        background: var(--accent);
        color: #fff;
        font-weight: 800;
        font-size: 1rem;
        margin-top: 1rem;
        cursor: pointer;
        transition: 0.25s;
        box-shadow: var(--shadow);
    }
    .btn-login:hover {
        background: var(--accent-dark);
        transform: translateY(-1px);
    }
    .link {
        color: var(--accent-dark);
        font-weight: 700;
        text-decoration: none;
        margin-left: .25rem;
    }
    .link:hover { text-decoration: underline; }
    .error {
        background: rgba(255, 90, 90, .15);
        color: #b60000;
        padding: 0.6rem;
        border-radius: var(--radius);
        margin-bottom: 1rem;
        font-size: 0.88rem;
        text-align: left;
    }
    ul.errs { margin: .4rem 0 0 1.1rem; padding: 0; }
    .success {
        background: rgba(34,197,94,.12);
        color: #166534;
        padding: 0.6rem;
        border-radius: var(--radius);
        margin-bottom: 1rem;
        font-size: 0.88rem;
        text-align: left;
    }
    .forgot-wrap {
        margin-top: 0.6rem;
        font-size: 0.85rem;
        text-align: right;
    }
    .forgot-link {
        color: var(--accent-dark);
        cursor: pointer;
        text-decoration: underline;
    }
    .reset-panel {
        margin-top: 1rem;
        padding: 0.9rem;
        border-radius: var(--radius);
        background: #fff7ed;
        border: 1px solid #ffedd5;
        text-align: left;
    }
    .reset-panel h3 {
        margin: 0 0 .3rem;
        font-size: 0.98rem;
        font-weight: 800;
        color: #92400e;
    }
    .reset-panel p {
        margin: 0 0 .4rem;
        font-size: 0.8rem;
        color: #92400e;
    }
    .reset-panel input {
        background: #fff;
    }
    .reset-panel button {
        margin-top: 0.4rem;
        width: 100%;
        padding: 0.6rem;
        border-radius: var(--radius);
        border: none;
        font-weight: 800;
        cursor: pointer;
        background: #fdba74;
        color: #7c2d12;
    }
    .reset-panel button:hover {
        background: #fb923c;
    }
    .reset-row {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.4rem;
    }
  </style>
</head>
<body>
<div class="login-container">
    <div class="login-logo">
        <img src="assets/img/smart_nutrition_logo.png" alt="Logo">
    </div>

    <h2>Welcome Back</h2>
    <p class="sub">Sign in with your email or username.</p>

    <?php if ($errors): ?>
      <div class="error">
        <strong>There was a problem:</strong>
        <ul class="errs">
          <?php foreach ($errors as $er): ?>
            <li><?= e($er) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($infoMsg && !$errors): ?>
      <div class="success">
        <?= e($infoMsg) ?>
      </div>
    <?php endif; ?>

    <!-- LOGIN FORM -->
    <form method="post" action="">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="mode" value="login">
        <input type="text" name="identifier" placeholder="Email or Username" required>
        <input type="password" name="password" placeholder="Password" required>

        <div class="forgot-wrap">
            <span class="forgot-link" onclick="toggleReset()">Forgot password?</span>
        </div>

        <button type="submit" class="btn-login">Sign In</button>
    </form>

    <!-- RESET PASSWORD PANEL -->
    <div id="resetPanel"
         class="reset-panel"
         style="<?= $showResetPanel ? 'display:block;' : 'display:none;' ?>">
        <h3>Reset your password</h3>
       <!-- <p>Step 1: request an OTP. Step 2: verify it and set a new password.</p> -->

        <!-- STEP 1: SEND OTP -->
        <form method="post" action="" style="margin-bottom:.6rem;">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="mode" value="send_otp">
            <input type="email" name="reset_email"
                   placeholder="Registered email"
                   value="<?= e($storedResetEmail) ?>"
                   required>
            <button type="submit">Send OTP</button>
        </form>

        <!-- STEP 2: VERIFY OTP + SET NEW PASSWORD -->
        <div id="otpStep" style="<?= $showOtpStep ? 'display:block;' : 'display:none;' ?>">
            <form method="post" action="">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                <input type="hidden" name="mode" value="verify_otp">
                <!-- keep email hidden (we already validated it in step 1) -->
                <input type="hidden" name="otp_email" value="<?= e($storedResetEmail) ?>">

                <div class="reset-row">
                    <input type="text" name="otp_code" placeholder="6-digit OTP" required>
                    <input type="password" name="new_password" placeholder="New password (min 6 chars)" required>
                    <input type="password" name="new_password_confirm" placeholder="Confirm new password" required>
                </div>
                <button type="submit">Verify OTP &amp; Update Password</button>
            </form>
        </div>
    </div>

    <p class="sub">
        Don't have an account?
        <a href="signup.php" class="link">Create one</a>
    </p>
</div>

<script>
  function toggleReset() {
    const panel = document.getElementById('resetPanel');
    if (!panel) return;
    panel.style.display = (panel.style.display === 'block') ? 'none' : 'block';
  }
</script>
</body>
</html>
