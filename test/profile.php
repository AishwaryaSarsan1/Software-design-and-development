<?php
// C:\xampp\htdocs\smartnutrition\profile.php
declare(strict_types=1);
session_start();

/* Require login */
if (empty($_SESSION['user_id'])) {
  header('Location: signin.php');
  exit;
}

/* DB CONFIG (XAMPP defaults) */
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

/* CSRF */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$pdo = db();
$userId = (int)$_SESSION['user_id'];
$errors = [];
$updated = false;

/* Handle update (height/weight) */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['update_profile'])) {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $errors[] = 'Security token mismatch. Please reload and try again.';
    } else {
        $height_cm = trim($_POST['height_cm'] ?? '');
        $weight_kg = trim($_POST['weight_kg'] ?? '');

        $hVal = null; $wVal = null;

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
            // Only update provided fields; keep others as-is
            $fields = []; $params = [];
            if ($hVal !== null) { $fields[] = 'height_cm = ?'; $params[] = $hVal; }
            if ($wVal !== null) { $fields[] = 'weight_kg = ?'; $params[] = $wVal; }

            if ($fields) {
                $params[] = $userId;
                $sql = 'UPDATE users SET '.implode(', ', $fields).' WHERE id = ? LIMIT 1';
                $st = $pdo->prepare($sql);
                $st->execute($params);
                $updated = true;
            }
        }
    }
}

/* Fetch fresh user row */
$st  = $pdo->prepare('SELECT id, name, email, age, height_cm, weight_kg, created_at FROM users WHERE id = ? LIMIT 1');
$st->execute([$userId]);
$user = $st->fetch();

if (!$user) {
  header('Location: signout.php');
  exit;
}

/* Compute BMI */
$bmi = null; $bmiLabel = 'N/A';
if (!empty($user['height_cm']) && !empty($user['weight_kg']) && (float)$user['height_cm'] > 0) {
    $h_m = ((float)$user['height_cm']) / 100.0;
    if ($h_m > 0) {
        $bmi = (float)$user['weight_kg'] / ($h_m * $h_m);
        $bmi = round($bmi, 2);
        if ($bmi < 18.5)       $bmiLabel = 'Underweight';
        elseif ($bmi < 25)     $bmiLabel = 'Normal';
        elseif ($bmi < 30)     $bmiLabel = 'Overweight';
        else                   $bmiLabel = 'Obesity';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Your Profile — Smart Nutrition</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root { --bg:#0b1220; --card:#121a2d; --text:#e6ecff; --muted:#9bb0ff; --accent:#6aa1ff; --border:rgba(255,255,255,.08); }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,Segoe UI,Arial,sans-serif;min-height:100vh}
    header{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.2rem;background:rgba(255,255,255,.04);border-bottom:1px solid var(--border)}
    .brand a{color:var(--text);text-decoration:none;font-weight:900}
    .container{max-width:920px;margin:2rem auto;padding:0 1rem}
    .card{background:var(--card);border-radius:16px;padding:1.4rem;box-shadow:0 16px 40px rgba(0,0,0,.35);border:1px solid var(--border)}
    h1{margin:.2rem 0 1rem}
    .grid{display:grid;gap:1rem;grid-template-columns:1fr 1fr}
    @media (max-width:800px){ .grid{grid-template-columns:1fr} }
    .field, .edit-field{display:flex;justify-content:space-between;align-items:center;padding:.8rem 1rem;border:1px solid var(--border);border-radius:12px;background:#0f1627}
    .label{color:var(--muted);font-weight:600}
    .value{font-weight:800}
    .bmi-pill{display:inline-block;padding:.3rem .6rem;border-radius:999px;border:1px solid var(--border)}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
    @media (max-width:640px){ .row{grid-template-columns:1fr} }
    input[type="number"]{width:100%;margin-left:1rem;padding:.6rem .7rem;border-radius:.6rem;border:1px solid #223;background:#0b1526;color:var(--text)}
    .actions{margin-top:1rem;display:flex;gap:.6rem;flex-wrap:wrap}
    .btn{display:inline-block;text-decoration:none;color:white;background:linear-gradient(135deg,#6aa1ff,#22d3ee);padding:.7rem 1rem;border-radius:10px;font-weight:900;border:0;cursor:pointer}
    .btn-muted{color:var(--muted);background:transparent;border:1px solid var(--border)}
    .right-actions{display:flex;align-items:center;gap:.6rem}
    .right-actions form{margin:0}
    .signout{background:linear-gradient(135deg,#ff6a6a,#f97316);border:1px solid rgba(255,106,106,.4)}
    .alert{ padding:.8rem 1rem; border-radius:.6rem; margin:0 0 1rem 0 }
    .alert.ok{ background:rgba(34,197,94,.15); border:1px solid rgba(34,197,94,.35) }
    .alert.err{ background:rgba(255,106,106,.15); border:1px solid rgba(255,106,106,.35) }
  </style>
</head>
<body>
  <header>
    <div class="brand"><a href="Index.php" aria-label="Back to Dashboard">← Dashboard</a></div>
    <div class="right-actions">
      <form method="post" action="signout.php">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <button type="submit" class="btn signout">Sign Out</button>
      </form>
    </div>
  </header>

  <div class="container">
    <div class="card">
      <h1>Your Profile</h1>

      <?php if ($updated): ?>
        <div class="alert ok"><strong>Saved!</strong> Your details have been updated.</div>
      <?php endif; ?>
      <?php if ($errors): ?>
        <div class="alert err">
          <ul style="margin:.3rem 0 0 1rem">
            <?php foreach ($errors as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Display details -->
      <div class="grid" style="margin-bottom:1rem">
        <div class="field"><div class="label">Name</div><div class="value"><?= e($user['name'] ?? '—') ?></div></div>
        <div class="field"><div class="label">Email</div><div class="value"><?= e($user['email'] ?? '—') ?></div></div>
        <div class="field"><div class="label">Age</div><div class="value"><?= isset($user['age']) ? e((string)$user['age']).' yrs' : '—' ?></div></div>
        <div class="field"><div class="label">Height</div><div class="value"><?= isset($user['height_cm']) ? e((string)$user['height_cm']).' cm' : '—' ?></div></div>
        <div class="field"><div class="label">Weight</div><div class="value"><?= isset($user['weight_kg']) ? e((string)$user['weight_kg']).' kg' : '—' ?></div></div>
        <div class="field">
          <div class="label">BMI</div>
          <div class="value">
            <?= $bmi !== null ? e(number_format($bmi, 2)).' ' : '—' ?>
            <?php if ($bmi !== null): ?><span class="bmi-pill"><?= e($bmiLabel) ?></span><?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Edit form (Height & Weight) -->
      <form method="post" action="profile.php" class="card" style="background:#0f1627;border-color:#1b2440;">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="update_profile" value="1">
        <h2 style="margin-top:0">Update Measurements</h2>
        <div class="row">
          <div class="edit-field">
            <label class="label" for="height_cm">Height (cm)</label>
            <input type="number" step="0.01" min="50" max="300" id="height_cm" name="height_cm"
                   value="<?= e((string)($user['height_cm'] ?? '')) ?>" placeholder="e.g., 170.0">
          </div>
          <div class="edit-field">
            <label class="label" for="weight_kg">Weight (kg)</label>
            <input type="number" step="0.01" min="10" max="500" id="weight_kg" name="weight_kg"
                   value="<?= e((string)($user['weight_kg'] ?? '')) ?>" placeholder="e.g., 65.5">
          </div>
        </div>
        <div class="actions">
          <button class="btn" type="submit">Save changes</button>
          <a class="btn btn-muted" href="Index.php">Back</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
