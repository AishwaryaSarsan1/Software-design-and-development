<?php
// C:\xampp\htdocs\smartnutrition\profile.php
declare(strict_types=1);
session_start();

/* Require login */
if (empty($_SESSION['user_id'])) {
  header('Location: signin.php');
  exit;
}

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

/* CSRF */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$pdo    = db();
$userId = (int)$_SESSION['user_id'];
$errors = [];
$updated = false;

/**
 * Ensure extra profile columns exist on users table:
 *  - height_cm, weight_kg (you already had)
 *  - gender, activity_level, auto_cal_goal, cal_goal
 */
function ensureUserProfileColumns(PDO $pdo): void {
    $check = $pdo->prepare("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME = ?
    ");
    $addIfMissing = function(string $col, string $definition) use ($pdo, $check) {
        $check->execute([DB_NAME, $col]);
        if ((int)$check->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN $col $definition");
        }
    };

    $addIfMissing('height_cm',      'DECIMAL(5,2) NULL');
    $addIfMissing('weight_kg',      'DECIMAL(5,2) NULL');
    $addIfMissing('gender',         "ENUM('male','female') NULL");
    $addIfMissing('activity_level', "ENUM('sedentary','light','moderate','active','very_active') NULL");
    $addIfMissing('auto_cal_goal',  'TINYINT(1) NOT NULL DEFAULT 1');
    $addIfMissing('cal_goal',       'INT NOT NULL DEFAULT 2000');
}
ensureUserProfileColumns($pdo);

/**
 * Calculate calorie goal based on BMR + activity + BMI
 */
function calculateCalorieGoal(
    float $heightCm,
    float $weightKg,
    string $gender,
    string $activity,
    ?int $age
): int {
    if ($heightCm < 120 || $weightKg < 35) {
        return 2000; // fallback
    }
    $age = $age && $age > 10 && $age < 100 ? $age : 25;

    // Mifflin–St Jeor BMR
    if ($gender === 'male') {
        $bmr = 10*$weightKg + 6.25*$heightCm - 5*$age + 5;
    } else { // default treat as female
        $bmr = 10*$weightKg + 6.25*$heightCm - 5*$age - 161;
    }

    // Activity multiplier
    $map = [
        'sedentary'    => 1.2,
        'light'        => 1.375,
        'moderate'     => 1.55,
        'active'       => 1.725,
        'very_active'  => 1.9,
    ];
    $mult = $map[$activity] ?? 1.375;
    $tdee = $bmr * $mult;

    // BMI adjustment
    $hM  = $heightCm / 100.0;
    $bmi = $weightKg / ($hM * $hM);
    if     ($bmi < 18.5) $adjust = +400;
    elseif ($bmi < 25)   $adjust = 0;
    elseif ($bmi < 30)   $adjust = -300;
    else                 $adjust = -600;

    return (int)round($tdee + $adjust);
}

/* Fetch user (current values) */
$st  = $pdo->prepare('
    SELECT id, name, email, age, height_cm, weight_kg, gender, activity_level,
           auto_cal_goal, cal_goal, created_at
      FROM users
     WHERE id = ?
     LIMIT 1
');
$st->execute([$userId]);
$user = $st->fetch();

if (!$user) {
  header('Location: signout.php');
  exit;
}

/* Handle profile update */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['update_profile'])) {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $errors[] = 'Security token mismatch. Please reload and try again.';
    } else {
        // Incoming raw values
        $height_cm   = trim($_POST['height_cm'] ?? '');
        $weight_kg   = trim($_POST['weight_kg'] ?? '');
        $gender      = trim($_POST['gender'] ?? '');
        $activity    = trim($_POST['activity_level'] ?? '');
        $autoFlag    = isset($_POST['auto_cal_goal']) ? 1 : 0;
        $manualGoal  = trim($_POST['manual_cal_goal'] ?? '');

        // Start from existing DB values
        $hVal  = $user['height_cm'] !== null ? (float)$user['height_cm'] : null;
        $wVal  = $user['weight_kg'] !== null ? (float)$user['weight_kg'] : null;
        $gVal  = $user['gender'] ?? null;
        $aVal  = $user['activity_level'] ?? null;
        $auto  = (int)($user['auto_cal_goal'] ?? 1);
        $cGoal = (int)($user['cal_goal'] ?? 2000);

        // Height
        if ($height_cm !== '') {
            if (!is_numeric($height_cm) || (float)$height_cm < 50 || (float)$height_cm > 300) {
                $errors[] = 'Height must be a number in centimeters (50–300).';
            } else {
                $hVal = (float)$height_cm;
            }
        }

        // Weight
        if ($weight_kg !== '') {
            if (!is_numeric($weight_kg) || (float)$weight_kg < 10 || (float)$weight_kg > 500) {
                $errors[] = 'Weight must be a number in kilograms (10–500).';
            } else {
                $wVal = (float)$weight_kg;
            }
        }

        // Gender
        if ($gender !== '') {
            if (!in_array($gender, ['male','female'], true)) {
                $errors[] = 'Gender must be male or female.';
            } else {
                $gVal = $gender;
            }
        }

        // Activity level
        $allowedAct = ['sedentary','light','moderate','active','very_active'];
        if ($activity !== '') {
            if (!in_array($activity, $allowedAct, true)) {
                $errors[] = 'Invalid activity level.';
            } else {
                $aVal = $activity;
            }
        }

        // Auto flag
        $auto = $autoFlag ? 1 : 0;

        // Manual calorie goal validation (if auto off)
        if ($auto === 0) {
            if ($manualGoal === '') {
                $errors[] = 'Please enter a daily calorie goal when auto mode is off.';
            } elseif (!ctype_digit($manualGoal)) {
                $errors[] = 'Calorie goal must be a whole number (kcal).';
            } else {
                $goalInt = (int)$manualGoal;
                if ($goalInt < 1000 || $goalInt > 5000) {
                    $errors[] = 'Calorie goal should be between 1000 and 5000 kcal.';
                } else {
                    $cGoal = $goalInt;
                }
            }
        }

        // If auto mode and we HAVE enough data, compute goal
        if ($auto === 1 && $hVal && $wVal && $gVal && $aVal) {
            $age = isset($user['age']) && $user['age'] !== null ? (int)$user['age'] : null;
            $cGoal = calculateCalorieGoal((float)$hVal, (float)$wVal, (string)$gVal, (string)$aVal, $age);
        }

        if (!$errors) {
            $st = $pdo->prepare('
                UPDATE users
                   SET height_cm = ?, weight_kg = ?, gender = ?, activity_level = ?,
                       auto_cal_goal = ?, cal_goal = ?
                 WHERE id = ?
                 LIMIT 1
            ');
            $st->execute([
                $hVal !== null ? number_format($hVal, 2, '.', '') : null,
                $wVal !== null ? number_format($wVal, 2, '.', '') : null,
                $gVal,
                $aVal,
                $auto,
                $cGoal,
                $userId
            ]);

            $updated = true;

            // Refresh local $user with latest values for display
            $st  = $pdo->prepare('
                SELECT id, name, email, age, height_cm, weight_kg, gender, activity_level,
                       auto_cal_goal, cal_goal, created_at
                  FROM users
                 WHERE id = ?
                 LIMIT 1
            ');
            $st->execute([$userId]);
            $user = $st->fetch();
        }
    }
}

/* Compute BMI */
$bmi = null;
$bmiLabel = 'N/A';
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

$autoCal  = (int)($user['auto_cal_goal'] ?? 1);
$calGoal  = (int)($user['cal_goal'] ?? 2000);
$gender   = (string)($user['gender'] ?? '');
$activity = (string)($user['activity_level'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Your Profile — Smart Nutrition</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{
      --bg:#ffffff;
      --text:#111827;
      --muted:#6b7280;
      --accent:#ff7a00;
      --accent2:#ff9a33;
      --panel:#f8f8f8;
      --border:#e5e7eb;
      --radius:12px;
      --shadow:0 4px 20px rgba(0,0,0,.06);
    }
    *{box-sizing:border-box;}
    body{
      margin:0;
      font-family:"Inter",system-ui,sans-serif;
      background:var(--bg);
      color:var(--text);
    }
    header{
      display:flex;
      justify-content:space-between;
      align-items:center;
      padding:1rem 2rem;
      background:#fff;
      box-shadow:0 2px 10px rgba(0,0,0,.04);
      position:sticky;
      top:0;
      z-index:10;
    }
    .logo-wrap{
      display:flex;
      align-items:center;
      gap:.6rem;
    }
    .logo-wrap img{
      width:40px;
      height:40px;
    }
    .logo-text{
      font-weight:900;
      font-size:1.2rem;
      color:var(--accent);
    }
    nav a{
      text-decoration:none;
      margin-left:.9rem;
      font-weight:700;
      font-size:.92rem;
      color:var(--text);
    }
    nav a:hover{color:var(--accent);}
    nav a.active{color:var(--accent);}
    .header-right{
      display:flex;
      align-items:center;
      gap:.6rem;
    }
    .btn-signout{
      padding:.4rem .85rem;
      border-radius:.7rem;
      border:none;
      background:linear-gradient(135deg,#f97316,#ef4444);
      color:#fff;
      font-weight:700;
      font-size:.85rem;
      cursor:pointer;
      box-shadow:0 3px 10px rgba(0,0,0,.16);
    }

    .container{
      max-width:900px;
      margin:2rem auto 2.5rem;
      padding:0 1rem;
    }
    .card{
      background:var(--panel);
      border-radius:var(--radius);
      border:1px solid var(--border);
      box-shadow:var(--shadow);
      padding:1.5rem 1.6rem 1.6rem;
    }
    h1{
      margin:0 0 1rem;
      font-size:1.7rem;
      font-weight:900;
      color:#111827;
    }
    h2{
      margin:.2rem 0 .6rem;
      font-size:1.25rem;
      font-weight:800;
      color:#111827;
    }
    .grid-2{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:.75rem;
    }
    @media(max-width:720px){
      .grid-2{grid-template-columns:1fr;}
      header{padding:.75rem 1rem;}
      nav a{margin-left:.6rem;}
    }

    .field{
      display:flex;
      justify-content:space-between;
      align-items:center;
      padding:.7rem .9rem;
      border-radius:.75rem;
      background:#ffffff;
      border:1px solid var(--border);
    }
    .field-label{
      font-size:.8rem;
      font-weight:600;
      color:var(--muted);
    }
    .field-value{
      font-size:.95rem;
      font-weight:700;
      color:#111827;
    }
    .bmi-pill{
      display:inline-block;
      margin-left:.35rem;
      padding:.15rem .55rem;
      border-radius:999px;
      font-size:.7rem;
      font-weight:600;
      border:1px solid #e5e7eb;
      color:#4b5563;
      background:#f9fafb;
    }
    .tag-pill{
      display:inline-block;
      margin-left:.35rem;
      padding:.12rem .5rem;
      border-radius:999px;
      font-size:.7rem;
      font-weight:600;
      border:1px solid #fee2e2;
      color:#b91c1c;
      background:#fef2f2;
    }

    .alert{
      padding:.55rem .8rem;
      border-radius:.6rem;
      font-size:.82rem;
      margin:0 0 .8rem 0;
    }
    .alert.ok{
      background:#ecfdf5;
      border:1px solid #bbf7d0;
      color:#166534;
    }
    .alert.err{
      background:#fef2f2;
      border:1px solid #fecaca;
      color:#991b1b;
    }
    .alert.err ul{
      margin:.15rem 0 0 1.1rem;
      padding:0;
    }

    .edit-card{
      margin-top:1.4rem;
      background:#ffffff;
      border-radius:var(--radius);
      border:1px solid var(--border);
      padding:1.1rem 1.2rem 1.2rem;
    }
    .edit-row{
      display:flex;
      align-items:center;
      gap:.75rem;
      margin-top:.55rem;
    }
    .edit-label{
      font-size:.8rem;
      font-weight:600;
      color:var(--muted);
      min-width:110px;
    }
    .edit-input{
      flex:1;
    }
    .edit-input input,
    .edit-input select{
      width:100%;
      padding:.55rem .6rem;
      border-radius:.6rem;
      border:1px solid var(--border);
      font-size:.9rem;
      color:var(--text);
      background:#fff;
    }
    .edit-input input:focus,
    .edit-input select:focus{
      outline:none;
      border-color:var(--accent);
      box-shadow:0 0 0 2px rgba(255,122,0,.12);
    }
    .actions{
      margin-top:.9rem;
      display:flex;
      flex-wrap:wrap;
      gap:.6rem;
    }
    .btn-save{
      padding:.65rem 1.25rem;
      border-radius:.7rem;
      border:none;
      cursor:pointer;
      background:linear-gradient(135deg,var(--accent),var(--accent2));
      color:#fff;
      font-weight:800;
      font-size:.9rem;
      box-shadow:var(--shadow);
    }
    .btn-back{
      padding:.6rem 1.1rem;
      border-radius:.7rem;
      border:1px solid var(--border);
      background:#ffffff;
      color:var(--muted);
      font-size:.85rem;
      font-weight:700;
      text-decoration:none;
      display:inline-flex;
      align-items:center;
      gap:.25rem;
    }
    .btn-back:hover{
      color:var(--accent);
      border-color:var(--accent);
    }
    .hint{
      font-size:.75rem;
      color:var(--muted);
      margin-top:.15rem;
    }
  </style>
</head>
<body>

<header>
  <div class="logo-wrap">
    <img src="assets/img/smart_nutrition_logo.png" alt="Smart Nutrition">
    <div class="logo-text">Smart Nutrition</div>
  </div>
  <nav>
    <a href="Index.php">Home</a>
    <a href="food_items.php">Pantry</a>
    <a href="meallogs.php">Meal Logs</a>
    <a href="recipes.php">Recipes</a>
    <a href="profile.php" class="active">Profile</a>
  </nav>
  <div class="header-right">
    <form method="post" action="signout.php" style="margin:0;">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <button type="submit" class="btn-signout">Sign Out</button>
    </form>
  </div>
</header>

<div class="container">
  <div class="card">
    <h1>Your Profile</h1>

    <?php if ($updated): ?>
      <div class="alert ok"><strong>Saved.</strong> Your profile and calorie goal were updated.</div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="alert err">
        <strong>We hit a snag:</strong>
        <ul>
          <?php foreach ($errors as $msg): ?>
            <li><?= e($msg) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Static details -->
    <div class="grid-2">
      <div class="field">
        <div class="field-label">Name</div>
        <div class="field-value"><?= e($user['name'] ?? '—') ?></div>
      </div>
      <div class="field">
        <div class="field-label">Email</div>
        <div class="field-value"><?= e($user['email'] ?? '—') ?></div>
      </div>
      <div class="field">
        <div class="field-label">Age</div>
        <div class="field-value">
          <?= isset($user['age']) && $user['age'] !== '' ? e((string)$user['age']) . ' yrs' : '—' ?>
        </div>
      </div>
      <div class="field">
        <div class="field-label">Member since</div>
        <div class="field-value">
          <?= !empty($user['created_at']) ? e(substr((string)$user['created_at'],0,10)) : '—' ?>
        </div>
      </div>
      <div class="field">
        <div class="field-label">Height</div>
        <div class="field-value">
          <?= isset($user['height_cm']) && $user['height_cm'] !== '' ? e((string)$user['height_cm']).' cm' : '—' ?>
        </div>
      </div>
      <div class="field">
        <div class="field-label">Weight</div>
        <div class="field-value">
          <?= isset($user['weight_kg']) && $user['weight_kg'] !== '' ? e((string)$user['weight_kg']).' kg' : '—' ?>
        </div>
      </div>
      <div class="field">
        <div class="field-label">BMI</div>
        <div class="field-value">
          <?php if ($bmi !== null): ?>
            <?= e(number_format($bmi, 2)) ?>
            <span class="bmi-pill"><?= e($bmiLabel) ?></span>
          <?php else: ?>
            —
          <?php endif; ?>
        </div>
      </div>
      <div class="field">
        <div class="field-label">Calorie Goal</div>
        <div class="field-value">
          <?= $calGoal ?> kcal
          <?php if ($autoCal): ?>
            <span class="tag-pill">Auto (BMI & activity)</span>
          <?php else: ?>
            <span class="tag-pill" style="background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8;">Manual</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Edit measurements & goal -->
    <form method="post" action="profile.php" class="edit-card">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <input type="hidden" name="update_profile" value="1">
      <h2>Update Measurements & Calorie Goal</h2>

      <div class="edit-row">
        <div class="edit-label">Height (cm)</div>
        <div class="edit-input">
          <input
            type="number"
            step="0.01"
            min="50"
            max="300"
            id="height_cm"
            name="height_cm"
            value="<?= e((string)($user['height_cm'] ?? '')) ?>"
            placeholder="e.g., 170.0"
          >
        </div>
      </div>

      <div class="edit-row">
        <div class="edit-label">Weight (kg)</div>
        <div class="edit-input">
          <input
            type="number"
            step="0.01"
            min="10"
            max="500"
            id="weight_kg"
            name="weight_kg"
            value="<?= e((string)($user['weight_kg'] ?? '')) ?>"
            placeholder="e.g., 65.5"
          >
        </div>
      </div>

      <div class="edit-row">
        <div class="edit-label">Gender</div>
        <div class="edit-input">
          <select name="gender" id="gender">
            <option value="">Select gender</option>
            <option value="male"   <?= $gender === 'male'   ? 'selected' : '' ?>>Male</option>
            <option value="female" <?= $gender === 'female' ? 'selected' : '' ?>>Female</option>
          </select>
          <div class="hint">Used for BMR & calorie goal calculation.</div>
        </div>
      </div>

      <div class="edit-row">
        <div class="edit-label">Activity level</div>
        <div class="edit-input">
          <select name="activity_level" id="activity_level">
            <option value="">Select activity</option>
            <option value="sedentary"    <?= $activity === 'sedentary'    ? 'selected' : '' ?>>Sedentary (little or no exercise)</option>
            <option value="light"        <?= $activity === 'light'        ? 'selected' : '' ?>>Light (1–3 days/week)</option>
            <option value="moderate"     <?= $activity === 'moderate'     ? 'selected' : '' ?>>Moderate (3–5 days/week)</option>
            <option value="active"       <?= $activity === 'active'       ? 'selected' : '' ?>>Active (6–7 days/week)</option>
            <option value="very_active"  <?= $activity === 'very_active'  ? 'selected' : '' ?>>Very active (hard exercise/physical job)</option>
          </select>
          <div class="hint">Higher activity → higher maintenance calories.</div>
        </div>
      </div>

      <div class="edit-row">
        <div class="edit-label">Goal mode</div>
        <div class="edit-input">
          <label style="font-size:.85rem;color:var(--muted);display:flex;align-items:center;gap:.45rem;">
            <input type="checkbox" name="auto_cal_goal" id="auto_cal_goal" value="1" <?= $autoCal ? 'checked' : '' ?>>
            Auto-calculate goal from BMI & activity
          </label>
          <div class="hint">
            When auto is ON, we compute a personalized goal from your height, weight, gender, age & activity.
          </div>
        </div>
      </div>

      <div class="edit-row" id="manualGoalRow" style="<?= $autoCal ? 'display:none;' : '' ?>">
        <div class="edit-label">Manual goal (kcal)</div>
        <div class="edit-input">
          <input
            type="number"
            name="manual_cal_goal"
            id="manual_cal_goal"
            min="1000"
            max="5000"
            step="10"
            value="<?= !$autoCal ? e((string)$calGoal) : '' ?>"
            placeholder="e.g., 1800"
          >
          <div class="hint">Only used when auto mode is OFF.</div>
        </div>
      </div>

      <div class="actions">
        <button type="submit" class="btn-save">Save changes</button>
        <a href="Index.php" class="btn-back">← Back to Dashboard</a>
      </div>
    </form>
  </div>
</div>

<script>
  // Toggle manual calorie goal row based on checkbox
  const autoCheckbox = document.getElementById('auto_cal_goal');
  const manualRow    = document.getElementById('manualGoalRow');
  if (autoCheckbox && manualRow) {
    autoCheckbox.addEventListener('change', function() {
      if (this.checked) {
        manualRow.style.display = 'none';
      } else {
        manualRow.style.display = 'flex';
      }
    });
  }
</script>

</body>
</html>
