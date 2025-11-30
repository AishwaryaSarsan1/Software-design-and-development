<?php
// C:\xampp\htdocs\smartnutrition\meallogs.php
declare(strict_types=1);
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

/* ---------------- DB CONFIG ---------------- */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'smart_nutrition';
const DB_USER = 'root';
const DB_PASS = '';

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

$pdo      = db();
$userId   = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? null;
if (!$userName) {
    $st = $pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $userName = $st->fetchColumn() ?: 'User';
    $_SESSION['user_name'] = $userName;
}
$avatarInitial = strtoupper(mb_substr(trim($userName), 0, 1));

/* ---------------- Notifications (same as Index.php) ---------------- */
$lowStockThreshold = 2;
$daysAhead         = 3;

$expiredStmt = $pdo->prepare("
  SELECT id, name, unit, quantity, expiry_date
  FROM food_items
  WHERE user_id = ?
    AND expiry_date IS NOT NULL
    AND expiry_date < CURDATE()
  ORDER BY expiry_date DESC
");
$expiredStmt->execute([$userId]);
$expiredItems = $expiredStmt->fetchAll();

$expiringStmt = $pdo->prepare("
  SELECT id, name, unit, quantity, expiry_date
  FROM food_items
  WHERE user_id = ?
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
  ORDER BY expiry_date ASC
  LIMIT 10
");
$expiringStmt->execute([$userId, $daysAhead]);
$expiringItems = $expiringStmt->fetchAll();

$lowStockStmt = $pdo->prepare("
  SELECT id, name, unit, quantity
  FROM food_items
  WHERE user_id = ?
    AND quantity <= ?
  ORDER BY quantity ASC
");
$lowStockStmt->execute([$userId, $lowStockThreshold]);
$lowStockItems = $lowStockStmt->fetchAll();

$notifCount = count($expiredItems) + count($expiringItems) + count($lowStockItems);

/* ---------------- Handle manual log POST ---------------- */
$msg = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['meal_type'])) {

    $meal   = in_array($_POST['meal_type'], ['breakfast','lunch','dinner','snack'], true)
                ? $_POST['meal_type'] : 'breakfast';

    $foodId = isset($_POST['food_item_id']) && $_POST['food_item_id'] !== ''
                ? (int)$_POST['food_item_id'] : null;

    $qty    = max(0.01, (float)($_POST['quantity'] ?? 1));
    $unit   = trim((string)($_POST['unit'] ?? 'serving'));

    // Nutrition defaults
    $cal = 0; $p = 0.0; $c = 0.0; $f = 0.0;
    $mealName = null;

    if ($foodId) {
        $st = $pdo->prepare("
            SELECT name, calories_per_unit, protein_per_unit, carbs_per_unit, fat_per_unit
            FROM food_items
            WHERE id = ? AND user_id = ?
        ");
        $st->execute([$foodId, $userId]);
        if ($fi = $st->fetch()) {
            $mealName = $fi['name'] ?? null;

            $cal = (int)round(($fi['calories_per_unit'] ?? 0) * $qty);
            $p   = round(($fi['protein_per_unit'] ?? 0) * $qty, 2);
            $c   = round(($fi['carbs_per_unit']   ?? 0) * $qty, 2);
            $f   = round(($fi['fat_per_unit']     ?? 0) * $qty, 2);
        }
    }

    if ($mealName === null) {
        // If no pantry item selected or name missing, fall back to meal type label.
        $mealName = ucfirst($meal) . ' item';
    }

    // Insert row – consumed_at has DEFAULT current_timestamp()
    $ins = $pdo->prepare("
        INSERT INTO meal_logs
        (user_id, food_item_id, recipe_id, recipe_title,
         quantity, unit, log_date, meal_name,
         calories, protein, carbs, fat, meal_type)
        VALUES
        (?, ?, NULL, NULL,
         ?, ?, CURDATE(), ?,
         ?, ?, ?, ?, ?)
    ");

    $ins->execute([
        $userId,
        $foodId,
        $qty,
        $unit,
        $mealName,
        $cal,
        $p,
        $c,
        $f,
        $meal
    ]);

    $msg = 'Meal logged.';
}

/* ---------------- Daily totals for donut ---------------- */
$tot = $pdo->prepare("
  SELECT
    COALESCE(SUM(calories),0) AS cal,
    COALESCE(SUM(protein),0)  AS protein,
    COALESCE(SUM(carbs),0)    AS carbs,
    COALESCE(SUM(fat),0)      AS fat
  FROM meal_logs
  WHERE user_id = ?
    AND DATE(consumed_at) = CURDATE()
");
$tot->execute([$userId]);
$totals   = $tot->fetch() ?: ['cal'=>0,'protein'=>0,'carbs'=>0,'fat'=>0];
$calToday = (int)$totals['cal'];

/* ---------------- Fetch recent history ---------------- */
$logs = $pdo->prepare("
  SELECT *
  FROM meal_logs
  WHERE user_id = ?
  ORDER BY consumed_at DESC
  LIMIT 50
");
$logs->execute([$userId]);
$rows = $logs->fetchAll();

/* For manual logging dropdown */
$fiStmt = $pdo->prepare("
  SELECT id, name
  FROM food_items
  WHERE user_id = ?
  ORDER BY name ASC
");
$fiStmt->execute([$userId]);
$pantry = $fiStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Meal Logs — Smart Nutrition</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<style>
:root{
  --bg:#ffffff; --text:#1a1a1a; --muted:#555;
  --accent:#ff7a00; --accent2:#ff9a33;
  --panel:#f8f8f8; --border:#e1e1e1;
  --radius:12px; --shadow:0 4px 20px rgba(0,0,0,.06);
}
*{box-sizing:border-box}
body{margin:0;font-family:"Inter",system-ui,sans-serif;background:var(--bg);color:var(--text)}

/* Header (same as Index.php) */
.header{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.2rem;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.05);position:sticky;top:0;z-index:10}
.brand{display:flex;align-items:center;gap:.6rem}
.brand img{width:40px;height:40px}
.brand a{color:var(--text);font-weight:900;font-size:1.2rem}
.brand, .brand *{text-decoration:none}
.nav{display:flex;align-items:center;gap:1rem}
.nav a{color:var(--text);text-decoration:none;font-weight:700}
.nav a:hover{color:var(--accent)}
.welcome{display:flex;align-items:center;gap:.35rem;color:#222;font-weight:700}
.iconbtn{position:relative;border:1px solid var(--border);background:#fff;border-radius:999px;width:38px;height:38px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
.badge{position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;border-radius:999px;padding:2px 6px;font-size:.72rem;font-weight:800}
.avatar{width:38px;height:38px;border-radius:999px;background:#ffe6d0;color:#b34d00;display:inline-flex;align-items:center;justify-content:center;font-weight:900;border:1px solid var(--border);cursor:pointer}

/* Dropdowns */
.profile{position:relative}
.dropdown{position:absolute;right:0;top:48px;width:260px;display:none;background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);overflow:hidden}
.dropdown .hdr{font-weight:900;padding:.7rem .9rem;border-bottom:1px solid var(--border)}
.dropdown .empty{padding:.8rem .9rem;color:var(--muted)}
.dropdown .item{padding:.65rem .9rem;border-bottom:1px solid var(--border)}
.dropdown .item:last-child{border-bottom:none}
.dropdown .item .t{font-weight:800}
.dropdown .item .s{color:var(--muted);font-size:.9rem}

/* Layout & cards */
.container{max-width:1200px;margin:2rem auto;padding:0 1rem}
.grid{display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(320px,1fr))}
.card{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.25rem;box-shadow:var(--shadow)}
.table-wrap{overflow:auto;margin-top:.5rem}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:var(--radius);overflow:hidden}
th,td{padding:.6rem;border-bottom:1px solid var(--border);text-align:left}
th{color:var(--muted);font-weight:700;font-size:.9rem}
td{font-size:.95rem}
.muted{color:var(--muted);font-size:.9rem}

/* Form controls */
label{display:block;color:var(--muted);font-weight:700;margin-bottom:.3rem}
input,select{
  width:100%;padding:.65rem .7rem;border-radius:10px;border:1px solid var(--border);
  background:#fafafa;color:var(--text);font-size:1rem
}
input:focus,select:focus{outline:none;border-color:#ffb15a;background:#fff}
button{
  background:linear-gradient(135deg,var(--accent),var(--accent2));border:0;border-radius:10px;color:#fff;
  padding:.75rem 1.2rem;font-weight:800;cursor:pointer;box-shadow:var(--shadow)
}
button:hover{opacity:.95}
h1,h2,h3{margin:.2rem 0 .8rem}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<!-- HEADER (same as Index.php) -->
<header class="header">
  <div class="brand">
    <img src="assets/img/smart_nutrition_logo.png" alt="Smart Nutrition">
    <a href="Index.php">Smart Nutrition</a>
  </div>

  <div class="nav" style="margin-left:auto;margin-right:auto;gap:1.25rem">
    <a href="Index.php">Home</a>
    <a href="food_items.php">Pantry</a>
    <a href="meallogs.php">Meal Logs</a>
    <a href="recipes.php">Recipes</a>
    <a href="profile.php">Profile</a>
  </div>

  <div style="display:flex;align-items:center;gap:.6rem">
    <div class="welcome" title="Signed in">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M7 11l2-2m2 0l2-2m2 2l-8 8a4 4 0 106 6l6-6a4 4 0 10-6-6"
              stroke="#ff7a00" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span>Welcome back, <?= e($userName) ?></span>
    </div>

    <!-- Bell -->
    <div class="profile">
      <button class="iconbtn" id="bellBtn" aria-haspopup="true" aria-expanded="false" title="Notifications">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M15 17h5l-1.4-1.4a2 2 0 0 1-.6-1.4V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5"
                stroke="#333" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <?php if ($notifCount > 0): ?>
          <span class="badge" id="notifCount"><?= $notifCount ?></span>
        <?php endif; ?>
      </button>
      <div id="notifMenu" class="dropdown" role="menu" aria-hidden="true">
        <div class="hdr">Expiring in next <?= (int)$daysAhead ?> day(s)</div>
        <?php if (!$expiringItems): ?>
          <div class="empty">No items expiring soon.</div>
        <?php else: foreach ($expiringItems as $it): ?>
          <div class="item">
            <div class="t"><?= e($it['name']) ?></div>
            <div class="s">
              Qty: <?= e((string)$it['quantity']).' '.e((string)($it['unit'] ?? '')) ?> ·
              Expires: <?= e((string)$it['expiry_date']) ?>
            </div>
          </div>
        <?php endforeach; endif; ?>

        <?php if ($expiredItems): ?>
          <div class="hdr">Already expired</div>
          <?php foreach ($expiredItems as $it): ?>
            <div class="item">
              <div class="t"><?= e($it['name']) ?></div>
              <div class="s">Expired: <?= e((string)$it['expiry_date']) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($lowStockItems): ?>
          <div class="hdr">Low stock (≤ <?= (int)$lowStockThreshold ?>)</div>
          <?php foreach ($lowStockItems as $it): ?>
            <div class="item">
              <div class="t"><?= e($it['name']) ?></div>
              <div class="s">Qty: <?= e((string)$it['quantity']).' '.e((string)($it['unit'] ?? '')) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Avatar -->
    <div class="profile">
      <button class="avatar" id="avatarBtn" aria-haspopup="true" aria-expanded="false" title="Account">
        <?= e($avatarInitial) ?>
      </button>
      <div id="profileMenu" class="dropdown" role="menu" aria-hidden="true">
        <div class="hdr"><?= e($userName) ?></div>
        <div class="item">
          <a href="profile.php" style="text-decoration:none;color:inherit"><span class="t">Profile</span></a>
        </div>
        <div class="item">
          <a href="Index.php?logout=1" style="text-decoration:none;color:inherit"><span class="t">Sign Out</span></a>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- PAGE CONTENT -->
<div class="container">
  <h1 style="margin-bottom:1rem;">Meal Logs</h1>

  <div class="grid">
    <!-- Log a meal -->
    <div class="card">
      <h3>Log a Meal</h3>
      <?php if ($msg): ?>
        <div class="muted" style="margin:.4rem 0 .6rem"><?= e($msg) ?></div>
      <?php endif; ?>

      <form method="post" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem;align-items:end">
        <div>
          <label>Meal</label>
          <select name="meal_type">
            <option value="breakfast">breakfast</option>
            <option value="lunch">lunch</option>
            <option value="dinner" selected>dinner</option>
            <option value="snack">snack</option>
          </select>
        </div>
        <div>
          <label>Pantry Item (optional)</label>
          <select name="food_item_id">
            <option value="">— none (manual) —</option>
            <?php foreach ($pantry as $f): ?>
              <option value="<?= (int)$f['id'] ?>"><?= e($f['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Qty</label>
          <input type="number" step="0.01" name="quantity" value="1">
        </div>
        <div>
          <label>Unit</label>
          <input name="unit" value="serving">
        </div>
        <div style="grid-column:1 / -1; text-align:right">
          <button type="submit">Add Log</button>
        </div>
      </form>
    </div>

    <!-- Today donut -->
    <div class="card">
      <h3>Today’s Calories</h3>
      <p class="muted" style="margin-top:0;">
        Total consumed today: <strong><?= $calToday ?> kcal</strong>
      </p>
      <canvas id="donut" height="200"></canvas>
    </div>
  </div>

  <!-- History -->
  <div class="card" style="margin-top:1.5rem">
    <h3>Meal History</h3>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Date / Time</th>
            <th>Meal</th>
            <th>Food / Recipe</th>
            <th>Qty</th>
            <th>Calories</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e((string)$r['consumed_at']) ?></td>
            <td><?= e((string)$r['meal_type']) ?></td>
            <td><?= e((string)($r['meal_name'] ?? '(unknown)')) ?></td>
            <td><?= e((string)$r['quantity']) . ' ' . e((string)$r['unit']) ?></td>
            <td><?= (int)$r['calories'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
/* Donut chart for today */
const cal  = <?= (int)$calToday ?>;
const goal = 2000;
new Chart(document.getElementById('donut').getContext('2d'), {
  type: 'doughnut',
  data: {
    labels:['Consumed','Remaining'],
    datasets:[{
      data:[cal, Math.max(0, goal-cal)],
      backgroundColor:['#ff7a00','#f3f3f3'],
      borderWidth:0
    }]
  },
  options: {
    plugins:{ legend:{ display:true, position:'bottom' } },
    cutout:'60%'
  }
});

/* Dropdowns: bell + avatar (same JS as Index.php) */
(function(){
  const bellBtn   = document.getElementById('bellBtn');
  const notifMenu = document.getElementById('notifMenu');
  const avatarBtn = document.getElementById('avatarBtn');
  const profMenu  = document.getElementById('profileMenu');

  function show(el, yes){ if (el) el.style.display = yes ? 'block' : 'none'; }
  function isOpen(el){ return el && el.style.display === 'block'; }

  if (bellBtn && notifMenu) {
    bellBtn.addEventListener('click', (e)=>{
      e.stopPropagation();
      show(profMenu,false);
      show(notifMenu,!isOpen(notifMenu));
      bellBtn.setAttribute('aria-expanded', isOpen(notifMenu) ? 'true':'false');
    });
  }

  if (avatarBtn && profMenu) {
    avatarBtn.addEventListener('click', (e)=>{
      e.stopPropagation();
      show(notifMenu,false);
      show(profMenu,!isOpen(profMenu));
      avatarBtn.setAttribute('aria-expanded', isOpen(profMenu) ? 'true':'false');
    });
  }

  document.addEventListener('click',(e)=>{
    if (notifMenu && !notifMenu.contains(e.target) && (!bellBtn || !bellBtn.contains(e.target))) {
      show(notifMenu,false);
      bellBtn && bellBtn.setAttribute('aria-expanded','false');
    }
    if (profMenu && !profMenu.contains(e.target) && (!avatarBtn || !avatarBtn.contains(e.target))) {
      show(profMenu,false);
      avatarBtn && avatarBtn.setAttribute('aria-expanded','false');
    }
  });
})();
</script>
</body>
</html>
