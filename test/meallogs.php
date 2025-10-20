<?php
// C:\xampp\htdocs\smartnutrition\meallogs.php
declare(strict_types=1);
session_start();
if (empty($_SESSION['user_id'])) { header('Location: signin.php'); exit; }

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

/* ------- Make sure needed columns exist (soft checks) ------- */
$pdo->exec("
  CREATE TABLE IF NOT EXISTS user_goals (
    user_id INT PRIMARY KEY,
    calorie_goal INT DEFAULT 2000,
    protein_goal DECIMAL(10,2) DEFAULT 75,
    carbs_goal DECIMAL(10,2) DEFAULT 250,
    fat_goal DECIMAL(10,2) DEFAULT 70,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB;
");

/* ------- Fetch user's pantry items for dropdown ------- */
$pantryStmt = $pdo->prepare("
  SELECT id, name, unit, calories_per_unit,
         COALESCE(protein_per_unit,0) AS protein_per_unit,
         COALESCE(carbs_per_unit,0)   AS carbs_per_unit,
         COALESCE(fat_per_unit,0)     AS fat_per_unit
  FROM food_items
  WHERE user_id = ?
  ORDER BY name ASC
");
$pantryStmt->execute([$userId]);
$pantryItems = $pantryStmt->fetchAll();

/* ------- Handle Add Meal submit ------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['food_item_id'])) {
  $foodItemId = (int)$_POST['food_item_id'];
  $qty        = max(0.01, (float)($_POST['quantity'] ?? 1));
  $mealType   = $_POST['meal_type'] ?? 'breakfast';

  // Lookup the selected food item to get per-unit nutrition
  $rowStmt = $pdo->prepare("
    SELECT id, name, unit, calories_per_unit,
           COALESCE(protein_per_unit,0) AS protein_per_unit,
           COALESCE(carbs_per_unit,0)   AS carbs_per_unit,
           COALESCE(fat_per_unit,0)     AS fat_per_unit
    FROM food_items
    WHERE id = ? AND user_id = ?
    LIMIT 1
  ");
  $rowStmt->execute([$foodItemId, $userId]);
  $item = $rowStmt->fetch();

  if ($item) {
    // Calculate totals = per-unit * quantity
    $calories = (int)round(($item['calories_per_unit'] ?? 0) * $qty);
    $protein  = (float)$item['protein_per_unit'] * $qty;
    $carbs    = (float)$item['carbs_per_unit']   * $qty;
    $fat      = (float)$item['fat_per_unit']     * $qty;

    // Insert into meal_logs using FK, and copy unit from food_items for audit
    $ins = $pdo->prepare("
      INSERT INTO meal_logs (user_id, food_item_id, quantity, unit, calories, protein, carbs, fat, meal_type)
      VALUES (?,?,?,?,?,?,?,?,?)
    ");
    $ins->execute([
      $userId,
      $foodItemId,
      $qty,
      $item['unit'] ?: 'pcs',
      $calories,
      $protein,
      $carbs,
      $fat,
      $mealType
    ]);
  }
  header('Location: meallogs.php'); exit;
}

/* ------- Fetch logs (join to show food name) ------- */
$logsStmt = $pdo->prepare("
  SELECT ml.id, ml.quantity, ml.unit, ml.calories, ml.protein, ml.carbs, ml.fat, ml.meal_type, ml.consumed_at,
         fi.name AS food_name
  FROM meal_logs ml
  LEFT JOIN food_items fi ON fi.id = ml.food_item_id
  WHERE ml.user_id = ?
  ORDER BY ml.consumed_at DESC, ml.id DESC
");
$logsStmt->execute([$userId]);
$logs = $logsStmt->fetchAll();

/* ------- Daily totals vs goals ------- */
$today = date('Y-m-d');
$totStmt = $pdo->prepare("
  SELECT SUM(calories) cal, SUM(protein) protein, SUM(carbs) carbs, SUM(fat) fat
  FROM meal_logs WHERE user_id = ? AND DATE(consumed_at) = ?
");
$totStmt->execute([$userId, $today]);
$totals = $totStmt->fetch() ?: ['cal'=>0,'protein'=>0,'carbs'=>0,'fat'=>0];

$goalStmt = $pdo->prepare("SELECT * FROM user_goals WHERE user_id=?");
$goalStmt->execute([$userId]);
$goals = $goalStmt->fetch() ?: ['calorie_goal'=>2000,'protein_goal'=>75,'carbs_goal'=>250,'fat_goal'=>70];

/* ------- Food waste vs pantry (simple ratio) ------- */
$wasteStmt = $pdo->prepare("SELECT COUNT(*) total FROM food_items WHERE user_id=? AND expiry_date < CURDATE()");
$wasteStmt->execute([$userId]);
$wasteCount = (int)($wasteStmt->fetch()['total'] ?? 0);

$pantryCountStmt = $pdo->prepare("SELECT COUNT(*) total FROM food_items WHERE user_id=?");
$pantryCountStmt->execute([$userId]);
$pantryCount = (int)($pantryCountStmt->fetch()['total'] ?? 0);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Meal Logs — Smart Nutrition</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#0b1220;color:#e6ecff;margin:0;padding:0}
    header{padding:1rem;background:#121a2d;display:flex;justify-content:space-between;align-items:center}
    a{color:#6aa1ff;text-decoration:none}
    .container{max-width:1100px;margin:1.5rem auto;padding:1rem}
    form{background:#121a2d;padding:1rem;border-radius:12px;margin-bottom:1rem}
    input,select{padding:.5rem;margin:.2rem 0;width:100%;border-radius:6px;border:1px solid #333;background:#0f1627;color:#fff}
    button{background:#6aa1ff;color:#fff;padding:.6rem 1rem;border:0;border-radius:6px;cursor:pointer;font-weight:bold}
    table{width:100%;border-collapse:collapse;margin-top:1rem}
    th,td{padding:.6rem;border-bottom:1px solid #223}
    .charts{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-top:2rem}
    @media(max-width:800px){.charts{grid-template-columns:1fr}}
    canvas{background:#121a2d;padding:.5rem;border-radius:12px}
  </style>
</head>
<body>
  <header>
    <div><a href="Index.php">← Dashboard</a></div>
    <div><a href="profile.php">Profile</a></div>
  </header>
  <div class="container">
    <h1>Meal Logs</h1>

    <form method="post">
      <h3>Add a Meal</h3>

      <label>Food from Pantry</label>
      <select name="food_item_id" required>
        <option value="" disabled selected>Select an item…</option>
        <?php foreach ($pantryItems as $it): ?>
          <option value="<?= (int)$it['id'] ?>">
            <?= e($it['name']) ?> — <?= (int)$it['calories_per_unit'] ?> kcal / <?= e($it['unit'] ?: 'unit') ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label>Quantity</label>
      <input type="number" step="0.01" min="0.01" name="quantity" placeholder="e.g. 1, 2.5" required>

      <label>Meal Type</label>
      <select name="meal_type">
        <option>breakfast</option>
        <option>lunch</option>
        <option>dinner</option>
        <option>snack</option>
      </select>

      <button type="submit">Add Meal</button>
      <?php if (!$pantryItems): ?>
        <p style="color:#9bb0ff;margin:.5rem 0 0">No pantry items yet. Please add some in <a href="food_items.php">Food Items</a>.</p>
      <?php endif; ?>
    </form>

    <?php
      $cal = (int)($totals['cal'] ?? 0);
      $p   = (float)($totals['protein'] ?? 0);
      $c   = (float)($totals['carbs'] ?? 0);
      $f   = (float)($totals['fat'] ?? 0);
      $gcal = (int)($goals['calorie_goal'] ?? 2000);
      $gp   = (float)($goals['protein_goal'] ?? 75);
      $gc   = (float)($goals['carbs_goal'] ?? 250);
      $gf   = (float)($goals['fat_goal'] ?? 70);
    ?>

    <h3>Today’s Totals (<?= e($today) ?>)</h3>
    <p>Calories: <?= $cal ?> / <?= $gcal ?> kcal</p>
    <p>Protein: <?= $p ?>g / <?= $gp ?>g &nbsp;|&nbsp; Carbs: <?= $c ?>g / <?= $gc ?>g &nbsp;|&nbsp; Fat: <?= $f ?>g / <?= $gf ?>g</p>

    <div class="charts">
      <canvas id="calChart"></canvas>
      <canvas id="wasteChart"></canvas>
    </div>

    <h3>Meal History</h3>
    <table>
      <tr><th>Date</th><th>Meal</th><th>Food</th><th>Qty</th><th>Calories</th></tr>
      <?php foreach ($logs as $log): ?>
        <tr>
          <td><?= e($log['consumed_at'] ?? '') ?></td>
          <td><?= e($log['meal_type'] ?? '') ?></td>
          <td><?= e($log['food_name'] ?? '(deleted item)') ?></td>
          <td><?= e((string)$log['quantity'].' '.($log['unit'] ?? '')) ?></td>
          <td><?= (int)($log['calories'] ?? 0) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('calChart'), {
  type: 'doughnut',
  data: {
    labels: ['Consumed', 'Remaining'],
    datasets: [{
      data: [<?= $cal ?>, <?= max(0, $gcal - $cal) ?>],
      backgroundColor: ['#6aa1ff','#1e293b']
    }]
  },
  options:{plugins:{title:{display:true,text:'Calories vs Goal'}}}
});

new Chart(document.getElementById('wasteChart'), {
  type: 'doughnut',
  data: {
    labels: ['Wasted','Good'],
    datasets: [{
      data: [<?= $wasteCount ?>, <?= max(0, $pantryCount - $wasteCount) ?>],
      backgroundColor: ['#ef4444','#22c55e']
    }]
  },
  options:{plugins:{title:{display:true,text:'Food Wasted vs Pantry'}}}
});
</script>
</body>
</html>
