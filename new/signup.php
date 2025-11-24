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

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

/* Ensure table exists with new columns (safe no-op if already there) */
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
    // Add columns if missing (no error if they already exist thanks to TRY/CATCH)
    try { $pdo->exec("ALTER TABLE users ADD COLUMN age TINYINT UNSIGNED NULL"); } catch(Throwable $__) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN height_cm DECIMAL(5,2) NULL"); } catch(Throwable $__) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN weight_kg DECIMAL(5,2) NULL"); } catch(Throwable $__) {}
} catch (Throwable $e) {
    die('<pre style="color:#f55;background:#222;padding:12px;border-radius:8px;">DB error: ' . e($e->getMessage()) . '</pre>');
}

/* Handle submit */
$errors = [];
$ok = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $errors[] = 'Security token mismatch. Please refresh and try again.';
    }

    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm'] ?? '';

    // Optional numeric fields
    $age       = trim($_POST['age'] ?? '');
    $height_cm = trim($_POST['height_cm'] ?? '');
    $weight_kg = trim($_POST['weight_kg'] ?? '');

    // Basic validation
    if ($name === '') $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    // Optional fields: validate only if provided
    $ageVal = null; $hVal = null; $wVal = null;
    if ($age !== '') {
        if (!ctype_digit($age) || (int)$age < 1 || (int)$age > 120) {
            $errors[] = 'Age must be a number between 1 and 120.';
        } else { $ageVal = (int)$age; }
    }
    if ($height_cm !== '') {
        if (!is_numeric($height_cm) || (float)$height_cm < 50 || (float)$height_cm > 300) {
            $errors[] = 'Height must be a number in centimeters (50–300).';
        } else { $hVal = number_format((float)$height_cm, 2, '.', ''); }
    }
    if ($weight_kg !== '') {
        if (!is_numeric($weight_kg) || (float)$weight_kg < 10 || (float)$weight_kg > 500) {
            $errors[] = 'Weight must be a number in kilograms (10–500).';
        } else { $wVal = number_format((float)$weight_kg, 2, '.', ''); }
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
                header('Refresh: 1.5; url=login.php');
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
  <title>Create Account — Smart Nutrition</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root { --bg:#0b1220; --card:#121a2d; --text:#e6ecff; --muted:#9bb0ff; --accent:#6aa1ff; }
    body{ margin:0; min-height:100vh; background:var(--bg); color:var(--text);
          font-family:system-ui,Segoe UI,Arial,sans-serif; display:grid; place-items:center; padding:2rem }
    .card{ width:min(760px, 92vw); background:var(--card); border-radius:1.25rem; padding:1.6rem 1.4rem;
           box-shadow:0 18px 50px rgba(0,0,0,.35); border:1px solid rgba(255,255,255,.06) }
    h1{ margin:0 0 .5rem 0 }
    p.muted{ color:var(--muted); margin:.25rem 0 1.2rem }
    form .grid{ display:grid; gap:1rem; grid-template-columns:1fr 1fr }
    @media (max-width:720px){ form .grid{ grid-template-columns:1fr } }
    label{ display:flex; flex-direction:column; font-size:.95rem; color:var(--muted) }
    input{ margin-top:.35rem; padding:.7rem .8rem; border-radius:.6rem; border:1px solid #223; background:#0f1627; color:var(--text) }
    .actions{ margin-top:1rem; display:flex; gap:.8rem; flex-wrap:wrap }
    .btn{ border:0; padding:.8rem 1.2rem; border-radius:.8rem; font-weight:800; cursor:pointer }
    .btn-primary{ background:linear-gradient(135deg, #6aa1ff, #22d3ee); color:#fff }
    .btn-muted{ background:transparent; color:var(--muted); border:1px solid rgba(155,176,255,.3); text-decoration:none; display:inline-block; line-height:2.2rem; padding:0 .9rem }
    .alert{ padding:.8rem 1rem; border-radius:.6rem; margin-bottom:1rem }
    .alert.error{ background:rgba(255,106,106,.15); border:1px solid rgba(255,106,106,.35) }
    .alert.ok{ background:rgba(34,197,94,.15); border:1px solid rgba(34,197,94,.35) }
    a{ color:#9bb0ff; text-decoration:none }
  </style>
</head>
<body>
  <div class="card">
    <h1>Create your account</h1>
    <p class="muted">Join Smart Nutrition to track your pantry, calories, and get smart recipe suggestions.</p>

    <?php if ($errors): ?>
      <div class="alert error">
        <strong>Couldn’t sign you up:</strong>
        <ul style="margin:.5rem 0 0 1rem">
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php elseif ($ok): ?>
      <div class="alert ok">
        <strong>Success!</strong> Your account was created. Redirecting to sign-in…
      </div>
    <?php endif; ?>

    <form method="post" action="signup.php" novalidate>
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

      <div class="grid">
        <label>
          Full name
          <input name="name" placeholder="e.g., Aishwarya Reddy" value="<?= e($_POST['name'] ?? '') ?>" required>
        </label>
        <label>
          Email
          <input type="email" name="email" placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>" required>
        </label>

        <label>
          Age (years)
          <input type="number" name="age" min="1" max="120" placeholder="e.g., 22" value="<?= e($_POST['age'] ?? '') ?>">
        </label>
        <label>
          Height (cm)
          <input type="number" name="height_cm" min="50" max="300" step="0.01" placeholder="e.g., 170.0" value="<?= e($_POST['height_cm'] ?? '') ?>">
        </label>
        <label>
          Weight (kg)
          <input type="number" name="weight_kg" min="10" max="500" step="0.01" placeholder="e.g., 65.5" value="<?= e($_POST['weight_kg'] ?? '') ?>">
        </label>

        <label>
          Password (min 6)
          <input type="password" name="password" minlength="6" required>
        </label>
        <label>
          Confirm password
          <input type="password" name="confirm" minlength="6" required>
        </label>
      </div>

      <div class="actions">
        <button class="btn btn-primary" type="submit">Create account</button>
        <a class="btn btn-muted" href="Home.php">Back to Home</a>
      </div>
    </form>
  </div>
</body>
</html>
