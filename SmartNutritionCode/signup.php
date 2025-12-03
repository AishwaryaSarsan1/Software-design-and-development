<?php
// C:\xampp\htdocs\smartnutrition\signup.php
declare(strict_types=1);
session_start();

error_reporting(E_ALL);
ini_set('display_errors', '1');

/* DB CONFIG */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'smart_nutrition';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

/* Ensure users table exists with extended columns (safe no-op if already there) */
try {
    $pdo = db();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
          id INT AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(100) NOT NULL,
          age TINYINT UNSIGNED NULL,
          height_cm DECIMAL(5,2) NULL,
          weight_kg DECIMAL(5,2) NULL,
          email VARCHAR(190) NOT NULL UNIQUE,
          password_hash VARCHAR(255) NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");
    // Try to add columns if missing (exceptions are ignored)
    try { $pdo->exec("ALTER TABLE users ADD COLUMN age TINYINT UNSIGNED NULL"); } catch(Throwable $__) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN height_cm DECIMAL(5,2) NULL"); } catch(Throwable $__) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN weight_kg DECIMAL(5,2) NULL"); } catch(Throwable $__) {}
} catch (Throwable $e) {
    die('<pre style="color:#f55;background:#222;padding:12px;border-radius:8px;">DB error: ' . e($e->getMessage()) . '</pre>');
}

/* Handle submit */
$errors = [];
$ok     = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $errors[] = 'Security token mismatch. Please refresh and try again.';
    }

    $name      = trim($_POST['name']      ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']       ?? '';
    $confirm   = $_POST['confirm']        ?? '';
    $age       = trim($_POST['age']       ?? '');
    $height_cm = trim($_POST['height_cm'] ?? '');
    $weight_kg = trim($_POST['weight_kg'] ?? '');

    // Basic validation
    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // Optional numeric fields: validate only if provided
    $ageVal = null; $hVal = null; $wVal = null;

    if ($age !== '') {
        if (!ctype_digit($age) || (int)$age < 1 || (int)$age > 120) {
            $errors[] = 'Age must be a number between 1 and 120.';
        } else {
            $ageVal = (int)$age;
        }
    }

    if ($height_cm !== '') {
        if (!is_numeric($height_cm) || (float)$height_cm < 50 || (float)$height_cm > 300) {
            $errors[] = 'Height must be a number in centimeters (50–300).';
        } else {
            $hVal = number_format((float)$height_cm, 2, '.', '');
        }
    }

    if ($weight_kg !== '') {
        if (!is_numeric($weight_kg) || (float)$weight_kg < 10 || (float)$weight_kg > 500) {
            $errors[] = 'Weight must be a number in kilograms (10–500).';
        } else {
            $wVal = number_format((float)$weight_kg, 2, '.', '');
        }
    }

    if (!$errors) {
        try {
            // Duplicate email?
            $st = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $st->execute([$email]);
            if ($st->fetch()) {
                $errors[] = 'That email is already registered.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins  = $pdo->prepare('
                    INSERT INTO users (name, age, height_cm, weight_kg, email, password_hash)
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                $ins->execute([$name, $ageVal, $hVal, $wVal, $email, $hash]);
                $ok = true;

                // Keep your existing redirect behavior
                header('Refresh: 1.5; url=Index.php');
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'That email is already registered.';
            } else {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sign Up — Smart Nutrition</title>
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
    .signup-container {
        width: 100%;
        max-width: 520px;
        background: #fff;
        padding: 2rem 2.4rem 2.2rem;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        text-align: center;
    }
    .signup-logo img {
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
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.8rem 0.9rem;
        text-align: left;
    }
    @media (max-width: 640px) {
        .signup-container {
            padding: 1.6rem 1.4rem 2rem;
            max-width: 100%;
            margin: 0 1rem;
        }
        .form-grid {
            grid-template-columns: 1fr;
        }
    }
    label {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--muted);
        display: block;
    }
    input {
        width: 100%;
        padding: 0.7rem 0.75rem;
        margin-top: 0.35rem;
        border-radius: var(--radius);
        border: 1.4px solid var(--border);
        background: #fafafa;
        font-size: 0.95rem;
        transition: .25s;
    }
    input:focus {
        outline: none;
        border-color: var(--accent);
        background: #fff;
    }
    .btn-signup {
        width: 100%;
        padding: 0.9rem;
        border-radius: var(--radius);
        border: none;
        background: var(--accent);
        color: #fff;
        font-weight: 800;
        font-size: 1rem;
        margin-top: 1.1rem;
        cursor: pointer;
        transition: 0.25s;
        box-shadow: var(--shadow);
    }
    .btn-signup:hover {
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
    .bottom-text {
        margin-top: 1rem;
        font-size: 0.88rem;
        color: var(--muted);
        text-align: center;
    }
  </style>
</head>
<body>
<div class="signup-container">
    <div class="signup-logo">
        <img src="assets/img/smart_nutrition_logo.png" alt="Logo">
    </div>

    <h2>Create Account</h2>
    <p class="sub">Join Smart Nutrition to track your pantry, meals, and personalized nutrition insights.</p>

    <?php if ($errors): ?>
      <div class="error">
        <strong>We couldn’t sign you up:</strong>
        <ul class="errs">
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php elseif ($ok): ?>
      <div class="success">
        <strong>Success!</strong> Your account was created. Redirecting…
      </div>
    <?php endif; ?>

    <form method="post" action="signup.php" novalidate>
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

        <div class="form-grid">
            <div>
                <label for="name">Full name</label>
                <input
                    id="name"
                    name="name"
                    placeholder="Enter Your name"
                    value="<?= e($_POST['name'] ?? '') ?>"
                    required
                >
            </div>
            <div>
                <label for="email">Email</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    placeholder="you@example.com"
                    value="<?= e($_POST['email'] ?? '') ?>"
                    required
                >
            </div>

            <div>
                <label for="age">Age (years)</label>
                <input
                    id="age"
                    type="number"
                    name="age"
                    min="1"
                    max="120"
                    placeholder="e.g., 22"
                    value="<?= e($_POST['age'] ?? '') ?>"
                >
            </div>
            <div>
                <label for="height_cm">Height (cm)</label>
                <input
                    id="height_cm"
                    type="number"
                    name="height_cm"
                    min="50"
                    max="300"
                    step="0.01"
                    placeholder="e.g., 170.0"
                    value="<?= e($_POST['height_cm'] ?? '') ?>"
                >
            </div>

            <div>
                <label for="weight_kg">Weight (kg)</label>
                <input
                    id="weight_kg"
                    type="number"
                    name="weight_kg"
                    min="10"
                    max="500"
                    step="0.01"
                    placeholder="e.g., 65.5"
                    value="<?= e($_POST['weight_kg'] ?? '') ?>"
                >
            </div>
            <div>
                <label for="password">Password (min 6)</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    minlength="6"
                    required
                >
            </div>

            <div>
                <label for="confirm">Confirm password</label>
                <input
                    id="confirm"
                    type="password"
                    name="confirm"
                    minlength="6"
                    required
                >
            </div>
        </div>

        <button type="submit" class="btn-signup">Create Account</button>
    </form>

    <p class="bottom-text">
        Already have an account?
        <a href="signin.php" class="link">Sign in</a>
    </p>
</div>
</body>
</html>
