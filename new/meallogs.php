<?php
// C:\xampp\htdocs\smartnutrition\meallogs.php
declare(strict_types=1);
session_start();
if (empty($_SESSION['user_id'])) { header('Location: signin.php'); exit; }

/* ---------------- DB CONFIG ---------------- */
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

$pdo = db();
$userId = (int)$_SESSION['user_id'];

/* ------------- Ensure tables/columns exist (safe no-ops) ------------- */
$pdo->exec("
CREATE TABLE IF NOT EXISTS meal_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  food_item_id INT NULL,
  recipe_id INT NULL,
  recipe_title VARCHAR(255) NULL,
  meal_type ENUM('breakfast','lunch','dinner','snack') NOT NULL DEFAULT 'breakfast',
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit VARCHAR(32) NOT NULL DEFAULT 'serving',
  calories INT NOT NULL DEFAULT 0,
  protein DECIMAL(10,2) NOT NULL DEFAULT 0,
  carbs DECIMAL(10,2) NOT NULL DEFAULT 0,
  fat DECIMAL(10,2) NOT NULL DEFAULT 0,
  consumed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id),
  INDEX (consumed_at),
  INDEX (recipe_id)
) ENGINE=InnoDB;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS food_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  category VARCHAR(80),
  storage_type ENUM('pantry','refrigerator','freezer') DEFAULT 'pantry',
  quantity DECIMAL(10,2) DEFAULT 1,
  unit VARCHAR(20) DEFAULT 'pcs',
  calories_per_unit INT DEFAULT 0,
  protein_per_unit DECIMAL(10,2) DEFAULT 0,
  carbs_per_unit DECIMAL(10,2) DEFAULT 0,
  fat_per_unit DECIMAL(10,2) DEFAULT 0,
  purchase_date DATE NULL,
  expiry_date DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id)
) ENGINE=InnoDB;
");

/* ---------------- Handle manual log POST (optional) ---------------- */
$msg = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['meal_type'])) {
  $meal   = in_array($_POST['meal_type'], ['breakfast','lunch','dinner','snack'], true) ? $_POST['meal_type'] : 'breakfast';
  $foodId = isset($_POST['food_item_id']) && $_POST['food_item_id'] !== '' ? (int)$_POST['food_item_id'] : null;
  $qty    = max(0.01, (float)($_POST['quantity'] ?? 1));
  $unit   = trim((string)($_POST['unit'] ?? 'serving'));

  $cal=0; $p=0; $c=0; $f=0; $title=null; $rid=null;
  if ($foodId) {
    $st = $pdo->prepare("SELECT calories_per_unit, protein_per_unit, carbs_per_unit, fat_per_unit FROM food_items WHERE id=? AND user_id=?");
    $st->execute([$foodId, $userId]);
    if ($fi = $st->fetch()) {
      $cal = (int)round(($fi['calories_per_unit'] ?? 0) * $qty);
      $p   = round(($fi['protein_per_unit'] ?? 0) * $qty, 2);
      $c   = round(($fi['carbs_per_unit'] ?? 0) * $qty, 2);
      $f   = round(($fi['fat_per_unit'] ?? 0) * $qty, 2);
    }
  }

  $ins = $pdo->prepare("
    INSERT INTO meal_logs (user_id, food_item_id, recipe_id, recipe_title, meal_type, quantity, unit, calories, protein, carbs, fat, consumed_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
  ");
  $ins->execute([$userId, $foodId, $rid, $title, $meal, $qty, $unit, $cal, $p, $c, $f]);
  $msg = 'Meal logged.';
}

/* ---------------- Daily totals for donut ---------------- */
$today = date('Y-m-d');
$tot = $pdo->prepare("
  SELECT
    COALESCE(SUM(calories),0) AS cal,
    COALESCE(SUM(protein),0)  AS protein,
    COALESCE(SUM(carbs),0)    AS carbs,
    COALESCE(SUM(fat),0)      AS fat
  FROM meal_logs
  WHERE user_id=? AND DATE(consumed_at)=?
");
$tot->execute([$userId, $today]);
$totals = $tot->fetch() ?: ['cal'=>0,'protein'=>0,'carbs'=>0,'fat'=>0];

/* ---------------- Fetch recent history ---------------- */
$logs = $pdo->prepare("
  SELECT
    ml.*,
    COALESCE(ml.recipe_title, fi.name) AS item_name
  FROM meal_logs ml
  LEFT JOIN food_items fi ON fi.id = ml.food_item_id
  WHERE ml.user_id = ?
  ORDER BY ml.consumed_at DESC
  LIMIT 50
");
$logs->execute([$userId]);
$rows = $logs->fetchAll();

/* For manual logging dropdown */
$fiStmt = $pdo->prepare("SELECT id, name FROM food_items WHERE user_id=? ORDER BY name ASC");
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
    --panel:#f8f8f8; --border:#e1e1e1;
    --accent:#ff7a00; --accent2:#ff9a33;
    --radius:12px; --shadow:0 4px 20px rgba(0,0,0,.06);
  }
  *{box-sizing:border-box}
  body{margin:0;font-family:"Inter",system-ui,sans-serif;background:var(--bg);color:var(--text)}
  .wrap{max-width:1100px;margin:1.5rem auto;padding:0 1rem}
  .card{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:1rem 1.1rem;box-shadow:var(--shadow)}
  label{display:block;color:var(--muted);font-weight:700;margin-bottom:.3rem}
  input,select{
    width:100%;padding:.65rem .7rem;border-radius:10px;border:1px solid var(--border);
    background:#fafafa;color:var(--text);font-size:1rem
  }
  input:focus,select:focus{outline:none;border-color:#ffb15a;background:#fff}
  button{
    background:linear-gradient(135deg,var(--accent),var(--accent2));border:0;border-radius:10px;color:#fff;
    padding:.7rem 1.1rem;font-weight:800;cursor:pointer;box-shadow:var(--shadow)
  }
  button:hover{opacity:.95}
  table{width:100%;border-collapse:collapse;margin-top:1rem;background:#fff;border:1px solid var(--border);border-radius:10px;overflow:hidden}
  th,td{padding:.65rem;border-bottom:1px solid var(--border);text-align:left}
  th{color:var(--muted);font-weight:800;background:#fdfdfd}
  tr:last-child td{border-bottom:none}
  .muted{color:var(--muted)}
  h2,h3{margin:.2rem 0 .8rem}
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2>Log a Meal</h2>
    <?php if ($msg): ?><div class="muted" style="margin:.4rem 0 .6rem"><?= e($msg) ?></div><?php endif; ?>
    <form method="post" style="display:grid;grid-template-columns:repeat(5,1fr);gap:.7rem;align-items:end">
      <div>
        <label>Meal</label>
        <select name="meal_type">
          <option>breakfast</option><option>lunch</option><option selected>dinner</option><option>snack</option>
        </select>
      </div>
      <div>
        <label>Pantry Item (optional)</label>
        <select name="food_item_id">
          <option value="">— none (recipe or manual) —</option>
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
      <div>
        <button type="submit">Add Log</button>
      </div>
    </form>
  </div>

  <div class="card" style="margin-top:1rem">
    <h3>Today</h3>
    <canvas id="donut" height="170"></canvas>
  </div>

  <div class="card" style="margin-top:1rem">
    <h3>Meal History</h3>
    <table>
      <thead><tr><th>Date</th><th>Meal</th><th>Food / Recipe</th><th>Qty</th><th>Calories</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e((string)$r['consumed_at']) ?></td>
            <td><?= e((string)$r['meal_type']) ?></td>
            <td><?= e((string)($r['item_name'] ?? '(unknown)')) ?></td>
            <td><?= e((string)$r['quantity']) . ' ' . e((string)$r['unit']) ?></td>
            <td><?= (int)$r['calories'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const cal = <?= (int)$totals['cal'] ?>, goal = 2000;
new Chart(document.getElementById('donut').getContext('2d'), {
  type: 'doughnut',
  data: {
    labels:['Consumed','Remaining'],
    datasets:[{
      data:[cal, Math.max(0, goal-cal)],
      backgroundColor:['#ff7a00','#f1f1f1'],
      borderWidth:0
    }]
  },
  options: {
    plugins:{ legend:{ display:true, position:'bottom' }, title:{ display:false } },
    cutout:'60%'
  }
});
</script>
</body>
</html>
