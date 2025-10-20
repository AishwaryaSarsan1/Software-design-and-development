<?php
// C:\xampp\htdocs\smartnutrition\signin.php
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

/* Handle login */
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $errors[] = 'Security token mismatch. Please refresh and try again.';
    }

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }

    if (!$errors) {
        $pdo = db();
        $st = $pdo->prepare('SELECT id, name, password_hash FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $user = $st->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Success: set session
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['name'];

            // Redirect to home/dashboard
            header('Location: Index.php');
            exit;
        } else {
            $errors[] = 'Invalid email or password.';
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
    :root { --bg:#0b1220; --card:#121a2d; --text:#e6ecff; --muted:#9bb0ff; --accent:#6aa1ff; }
    body{ margin:0; min-height:100vh; background:var(--bg); color:var(--text);
          font-family:system-ui,Segoe UI,Arial,sans-serif; display:grid; place-items:center; padding:2rem }
    .card{ width:min(500px, 92vw); background:var(--card); border-radius:1.25rem; padding:1.6rem 1.4rem;
           box-shadow:0 18px 50px rgba(0,0,0,.35); border:1px solid rgba(255,255,255,.06) }
    h1{ margin:0 0 1rem 0 }
    label{ display:flex; flex-direction:column; font-size:.95rem; color:var(--muted); margin-bottom:1rem }
    input{ margin-top:.35rem; padding:.7rem .8rem; border-radius:.6rem; border:1px solid #223; background:#0f1627; color:var(--text) }
    .actions{ margin-top:1rem; display:flex; gap:.8rem; flex-wrap:wrap }
    .btn{ border:0; padding:.8rem 1.2rem; border-radius:.8rem; font-weight:800; cursor:pointer }
    .btn-primary{ background:linear-gradient(135deg, #6aa1ff, #22d3ee); color:#fff }
    .btn-muted{ background:transparent; color:var(--muted); border:1px solid rgba(155,176,255,.3); text-decoration:none; display:inline-block; line-height:2.2rem; padding:0 .9rem }
    .alert{ padding:.8rem 1rem; border-radius:.6rem; margin-bottom:1rem }
    .alert.error{ background:rgba(255,106,106,.15); border:1px solid rgba(255,106,106,.35) }
  </style>
</head>
<body>
  <div class="card">
    <h1>Sign in</h1>

    <?php if ($errors): ?>
      <div class="alert error">
        <ul style="margin:.5rem 0 0 1rem">
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="signin.php" novalidate>
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <label>
        Email
        <input type="email" name="email" required placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>">
      </label>
      <label>
        Password
        <input type="password" name="password" required>
      </label>
      <div class="actions">
        <button class="btn btn-primary" type="submit">Sign In</button>
        <a class="btn btn-muted" href="Home.php">Back to Home</a>
      </div>
    </form>
  </div>
</body>
</html>
