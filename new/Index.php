<?php
// C:\xampp\htdocs\smartnutrition\Index.php
declare(strict_types=1);
session_start();

/* -------- Logout -------- */
if (!empty($_GET['logout'])) {
  session_unset();
  session_destroy();
  header('Location: Home.php');
  exit;
}

/* -------- DB CONFIG -------- */
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

/* -------- Auth guard -------- */
if (!isset($_SESSION['user_id'])) { header('Location: signin.php'); exit; }

$pdo      = db();
$userId   = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? null;
if (!$userName) {
  $st = $pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
  $st->execute([$userId]);
  $userName = $st->fetchColumn() ?: 'User';
  $_SESSION['user_name'] = $userName;
}

/* -------- Dashboard data -------- */
# Pantry counts
$pantryCount = (int)$pdo->query("SELECT COUNT(*) FROM food_items WHERE user_id = {$userId}")->fetchColumn();
$wasteCount  = (int)$pdo->query("SELECT COUNT(*) FROM food_items WHERE user_id = {$userId} AND expiry_date IS NOT NULL AND expiry_date < CURDATE()")->fetchColumn();
$freshCount  = max(0, $pantryCount - $wasteCount);

# Calories today
$today = date('Y-m-d');
$st = $pdo->prepare("SELECT COALESCE(SUM(calories),0) FROM meal_logs WHERE user_id=? AND DATE(consumed_at)=?");
$st->execute([$userId, $today]);
$calToday = (int)$st->fetchColumn();
$calGoal  = 2000;

# Recent meals
$q = $pdo->prepare("
  SELECT ml.*, COALESCE(ml.recipe_title, fi.name) AS item_name
  FROM meal_logs ml
  LEFT JOIN food_items fi ON fi.id = ml.food_item_id
  WHERE ml.user_id=? ORDER BY ml.consumed_at DESC LIMIT 8
");
$q->execute([$userId]);
$recent = $q->fetchAll();

# Notifications: expired / expiring soon / low stock
$lowStockThreshold = 2;
$daysAhead = 3;

$expiredStmt = $pdo->prepare("
  SELECT id, name, unit, quantity, expiry_date
  FROM food_items
  WHERE user_id = ? AND expiry_date IS NOT NULL AND expiry_date < CURDATE()
  ORDER BY expiry_date DESC
");
$expiredStmt->execute([$userId]);
$expiredItems = $expiredStmt->fetchAll();

$expiringStmt = $pdo->prepare("
  SELECT id, name, unit, quantity, expiry_date
  FROM food_items
  WHERE user_id = ? AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
  ORDER BY expiry_date ASC
  LIMIT 10
");
$expiringStmt->execute([$userId, $daysAhead]);
$expiringItems = $expiringStmt->fetchAll();

$lowStockStmt = $pdo->prepare("
  SELECT id, name, unit, quantity
  FROM food_items
  WHERE user_id = ? AND quantity <= ?
  ORDER BY quantity ASC
");
$lowStockStmt->execute([$userId, $lowStockThreshold]);
$lowStockItems = $lowStockStmt->fetchAll();

$notifCount = count($expiredItems) + count($expiringItems) + count($lowStockItems);

/* -------- Helpers -------- */
function pct($a,$b){ return $b ? round(100*$a/$b,1) : 0; }
$avatarInitial = strtoupper(mb_substr(trim($userName), 0, 1));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Dashboard — Smart Nutrition</title>
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
/* Header */
.header{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.2rem;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.05);position:sticky;top:0;z-index:10}
.brand{display:flex;align-items:center;gap:.6rem}
.brand img{width:40px;height:40px}
.brand a{color:var(--text);font-weight:900;font-size:1.2rem}
.brand, .brand *{text-decoration:none} /* remove underline under title */
.nav{display:flex;align-items:center;gap:1rem}
.nav a{color:var(--text);text-decoration:none;font-weight:700}
.nav a:hover{color:var(--accent)}
.welcome{display:flex;align-items:center;gap:.35rem;color:#222;font-weight:700}
.iconbtn{position:relative;border:1px solid var(--border);background:#fff;border-radius:999px;width:38px;height:38px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
.badge{position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;border-radius:999px;padding:2px 6px;font-size:.72rem;font-weight:800}
.avatar{width:38px;height:38px;border-radius:999px;background:#ffe6d0;color:#b34d00;display:inline-flex;align-items:center;justify-content:center;font-weight:900;border:1px solid var(--border);cursor:pointer}

/* Dropdown (used by bell & avatar) */
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
.grid{display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}
.card{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.25rem;box-shadow:var(--shadow)}
.stat{font-size:2rem;font-weight:900;color:var(--accent)}
.table-wrap{overflow:auto;margin-top:.5rem}
table{width:100%;border-collapse:collapse}
th,td{padding:.6rem;border-bottom:1px solid var(--border);text-align:left}
th{color:var(--muted);font-weight:700;font-size:.9rem}
td{font-size:.95rem}
.btn{display:inline-block;padding:.55rem 1.2rem;border-radius:var(--radius);font-weight:700;text-decoration:none;color:#fff;background:linear-gradient(135deg,var(--accent),var(--accent2));border:none}
.btn:hover{opacity:.9}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<header class="header">
  <div class="brand">
    <img src="assets/img/smart_nutrition_logo.png" alt="Smart Nutrition">
    <a href="Index.php">Smart Nutrition</a>
  </div>

  <div class="nav" style="margin-left:auto;margin-right:auto;gap:1.25rem">
    <a href="Home.php">Home</a>
    <a href="food_items.php">Pantry</a>
    <a href="meallogs.php">Meal Logs</a>
    <a href="recipes.php">Recipes</a>
    <a href="profile.php">Profile</a>
  </div>

  <div style="display:flex;align-items:center;gap:.6rem">
    <div class="welcome" title="Signed in">
      <!-- tiny waving hand -->
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 11l2-2m2 0l2-2m2 2l-8 8a4 4 0 106 6l6-6a4 4 0 10-6-6" stroke="#ff7a00" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span>Welcome back, <?= e($userName) ?></span>
    </div>

    <!-- Bell -->
    <div class="profile">
      <button class="iconbtn" id="bellBtn" aria-haspopup="true" aria-expanded="false" title="Notifications">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M15 17h5l-1.4-1.4a2 2 0 0 1-.6-1.4V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5"
                stroke="#333" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <?php if ($notifCount > 0): ?><span class="badge" id="notifCount"><?= $notifCount ?></span><?php endif; ?>
      </button>
      <div id="notifMenu" class="dropdown" role="menu" aria-hidden="true">
        <div class="hdr">Expiring in next <?= (int)$daysAhead ?> day(s)</div>
        <?php if (empty($expiringItems)): ?>
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
        <?php if (!empty($expiredItems)): ?>
          <div class="hdr">Already expired</div>
          <?php foreach ($expiredItems as $it): ?>
            <div class="item">
              <div class="t"><?= e($it['name']) ?></div>
              <div class="s">Expired: <?= e((string)$it['expiry_date']) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($lowStockItems)): ?>
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
      <button class="avatar" id="avatarBtn" aria-haspopup="true" aria-expanded="false" title="Account"><?= e($avatarInitial) ?></button>
      <div id="profileMenu" class="dropdown" role="menu" aria-hidden="true">
        <div class="hdr"><?= e($userName) ?></div>
        <div class="item"><a href="profile.php" style="text-decoration:none;color:inherit"><span class="t">Profile</span></a></div>
        <div class="item"><a href="?logout=1" style="text-decoration:none;color:inherit"><span class="t">Sign Out</span></a></div>
      </div>
    </div>
  </div>
</header>

<div class="container">
  <div class="grid">
    <div class="card">
      <div class="muted">Pantry Items</div>
      <div class="stat"><?= $pantryCount ?></div>
    </div>

    <div class="card">
      <div class="muted">Calories Today</div>
      <div class="stat"><?= $calToday ?> kcal</div>
      <div class="muted" style="margin-top:.3rem">of <?= $calGoal ?> kcal goal (<?= pct($calToday,$calGoal) ?>%)</div>
    </div>

    <div class="card">
      <div class="muted">Food Wasted</div>
      <div class="stat"><?= $wasteCount ?></div>
    </div>
  </div>

  <div class="grid" style="margin-top:1.5rem">
    <div class="card">
      <h3>Recent Meal Logs</h3>
      <?php if (!$recent): ?>
        <p class="muted">No meals logged yet.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Meal</th><th>Food / Recipe</th><th>Qty</th><th>Calories</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $r): ?>
            <tr>
              <td><?= e(substr((string)$r['consumed_at'],0,16)) ?></td>
              <td><?= e((string)$r['meal_type']) ?></td>
              <td><?= e((string)($r['item_name'] ?? '(unknown)')) ?></td>
              <td><?= e((string)$r['quantity']).' '.e((string)($r['unit'] ?? '')) ?></td>
              <td><?= (int)$r['calories'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Calories vs Goal</h3>
      <canvas id="donut" height="200"></canvas>
    </div>

    <div class="card">
      <h3>Fresh vs Expired</h3>
      <canvas id="freshChart" height="200"></canvas>
      <div class="muted" style="margin-top:.4rem">
        Fresh: <strong><?= $freshCount ?></strong> · Expired: <strong><?= $wasteCount ?></strong>
      </div>
    </div>
  </div>
</div>

<script>
/* Charts */
const cal = <?= (int)$calToday ?>, goal = <?= (int)$calGoal ?>;
new Chart(document.getElementById('donut').getContext('2d'), {
  type: 'doughnut',
  data: { labels: ['Consumed','Remaining'],
          datasets: [{ data: [cal, Math.max(0, goal - cal)],
                       backgroundColor: ['#ff7a00','#f3f3f3'], borderWidth: 0 }] },
  options: { plugins: { legend: { display: true, position:'bottom' } }, cutout: '60%' }
});

const fresh = <?= (int)$freshCount ?>, expired = <?= (int)$wasteCount ?>;
new Chart(document.getElementById('freshChart').getContext('2d'), {
  type: 'doughnut',
  data: { labels: ['Fresh','Expired'],
          datasets: [{ data: [fresh, expired],
                       backgroundColor: ['#17b26a', '#ff7a00'], borderWidth: 0 }] },
  options: { plugins: { legend: { display: true, position:'bottom' } }, cutout: '60%' }
});

/* Dropdowns: bell + avatar */
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
      show(profMenu, false);
      show(notifMenu, !isOpen(notifMenu));
      bellBtn.setAttribute('aria-expanded', isOpen(notifMenu) ? 'true' : 'false');
    });
  }

  if (avatarBtn && profMenu) {
    avatarBtn.addEventListener('click', (e)=>{
      e.stopPropagation();
      show(notifMenu, false);
      show(profMenu, !isOpen(profMenu));
      avatarBtn.setAttribute('aria-expanded', isOpen(profMenu) ? 'true' : 'false');
    });
  }

  document.addEventListener('click', (e)=>{
    if (notifMenu && !notifMenu.contains(e.target) && (!bellBtn || !bellBtn.contains(e.target))) {
      show(notifMenu, false);
      bellBtn && bellBtn.setAttribute('aria-expanded', 'false');
    }
    if (profMenu && !profMenu.contains(e.target) && (!avatarBtn || !avatarBtn.contains(e.target))) {
      show(profMenu, false);
      avatarBtn && avatarBtn.setAttribute('aria-expanded', 'false');
    }
  });
})();
</script>
</body>
</html>
