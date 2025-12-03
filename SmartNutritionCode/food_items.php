<?php
// C:\xampp\htdocs\smartnutrition\food_items.php
declare(strict_types=1);
session_start();
if (empty($_SESSION['user_id'])) { header('Location: signin.php'); exit; }

/* -------- CONFIG: DB and API Ninjas key -------- */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'smart_nutrition';
const DB_USER = 'root';
const DB_PASS = '';

const API_NINJAS_KEY = 'BHiBgMKWgADwo738dOY04g==PbU7o1IzkF6ParAy'; // your key

/* --------- Helpers --------- */
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

/* --------- Ensure table + columns exist --------- */
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
    fiber_per_unit DECIMAL(10,2) DEFAULT 0,
    sugar_per_unit DECIMAL(10,2) DEFAULT 0,
    sodium_per_unit DECIMAL(10,2) DEFAULT 0,
    purchase_date DATE NULL,
    expiry_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB;
");

function ensureColumn(PDO $pdo, string $col, string $definition) {
  $stmt = $pdo->prepare("
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'food_items' AND COLUMN_NAME = ?
  ");
  $stmt->execute([DB_NAME, $col]);
  if ((int)$stmt->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE food_items ADD COLUMN {$col} {$definition}");
  }
}
ensureColumn($pdo, 'fiber_per_unit', "DECIMAL(10,2) DEFAULT 0");
ensureColumn($pdo, 'sugar_per_unit', "DECIMAL(10,2) DEFAULT 0");
ensureColumn($pdo, 'sodium_per_unit', "DECIMAL(10,2) DEFAULT 0");

/* --------- API Ninjas helper --------- */
function fetchNutritionFromApiNinjas(string $query): array {
  if (API_NINJAS_KEY === '') return ['_error' => 'API Ninjas key not configured'];
  $url = 'https://api.api-ninjas.com/v1/nutrition?query=' . urlencode($query);

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => ['X-Api-Key: ' . API_NINJAS_KEY, 'Accept: application/json'],
    CURLOPT_TIMEOUT => 12,
    CURLOPT_SSL_VERIFYPEER => true,
  ]);
  $res  = curl_exec($ch);
  $err  = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  @file_put_contents(
    __DIR__ . '/api_ninjas_last.json',
    date('c') . " HTTP:$code\n" . ($err ?: $res) . "\n\n",
    FILE_APPEND
  );

  if ($err) return ['_error' => "Network error: $err"];
  if ($code < 200 || $code >= 300) {
    $data = json_decode((string)$res, true);
    $msg  = "API Ninjas HTTP $code";
    if (is_array($data) && isset($data['error'])) $msg .= ': ' . $data['error'];
    return ['_error' => $msg, 'raw' => $data ?? $res];
  }

  $data = json_decode((string)$res, true);
  if (!is_array($data) || count($data) === 0) {
    return ['_error' => 'No nutrition results returned', 'raw' => $data];
  }

  $item = $data[0];

  $getNumber = function(array $arr, array $keys) {
    foreach ($keys as $k) {
      if (isset($arr[$k]) && is_numeric($arr[$k])) return (float)$arr[$k];
    }
    return null;
  };

  $calories       = $getNumber($item, ['calories', 'nf_calories', 'calorie', 'energy_kcal']);
  $protein_g      = $getNumber($item, ['protein_g', 'protein', 'nf_protein']);
  $fat_total_g    = $getNumber($item, ['fat_total_g', 'total_fat_g', 'total_fat', 'nf_total_fat', 'fat']);
  $carbs_total_g  = $getNumber($item, ['carbohydrates_total_g', 'carbs_g', 'carbs', 'nf_total_carbohydrate']);
  $fiber_g        = $getNumber($item, ['fiber_g', 'fiber']);
  $sugar_g        = $getNumber($item, ['sugar_g', 'sugar']);
  $sodium_mg      = $getNumber($item, ['sodium_mg','sodium']);

  if ($calories === null && ($protein_g !== null || $carbs_total_g !== null || $fat_total_g !== null)) {
    $cal_est = 0.0;
    $cal_est += ($protein_g     !== null ? $protein_g     * 4.0 : 0.0);
    $cal_est += ($carbs_total_g !== null ? $carbs_total_g * 4.0 : 0.0);
    $cal_est += ($fat_total_g   !== null ? $fat_total_g   * 9.0 : 0.0);
    $calories = round($cal_est, 2);
    $item['_calories_estimated'] = true;
  } else {
    $item['_calories_estimated'] = false;
  }

  $present = array_keys(array_filter($item, fn($v) => !is_null($v) && $v !== ''));

  return [
    'name'                  => $item['name'] ?? '',
    'calories'              => $calories,
    'protein_g'             => $protein_g,
    'fat_total_g'           => $fat_total_g,
    'carbohydrates_total_g' => $carbs_total_g,
    'fiber_g'               => $fiber_g,
    'sugar_g'               => $sugar_g,
    'sodium_mg'             => $sodium_mg,
    'raw'                   => $item,
    'present_keys'          => $present
  ];
}

/* --------- Handle manual nutrition update --------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'], $_POST['id'])) {
  $id = (int)$_POST['id'];

  $check = $pdo->prepare("SELECT user_id FROM food_items WHERE id = ?");
  $check->execute([$id]);
  $row = $check->fetch();
  if (!$row || (int)$row['user_id'] !== $userId) {
    header('Location: food_items.php?err=' . rawurlencode('Unauthorized update'));
    exit;
  }

  $cal    = (int)($_POST['calories_per_unit'] ?? 0);
  $prot   = (float)($_POST['protein_per_unit'] ?? 0);
  $carb   = (float)($_POST['carbs_per_unit'] ?? 0);
  $fat    = (float)($_POST['fat_per_unit'] ?? 0);
  $fiber  = (float)($_POST['fiber_per_unit'] ?? 0);
  $sugar  = (float)($_POST['sugar_per_unit'] ?? 0);
  $sodium = (float)($_POST['sodium_per_unit'] ?? 0);

  $upd = $pdo->prepare("
    UPDATE food_items
       SET calories_per_unit=?, protein_per_unit=?, carbs_per_unit=?, fat_per_unit?,
           fiber_per_unit=?, sugar_per_unit=?, sodium_per_unit=?
     WHERE id=?
  ");
  $upd->execute([$cal, $prot, $carb, $fat, $fiber, $sugar, $sodium, $id]);

  header('Location: food_items.php?m=' . rawurlencode('Item updated.'));
  exit;
}

/* --------- Handle add new item with API lookup --------- */
$message = '';
$err     = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name']) && !isset($_POST['update'])) {
  $name          = trim((string)$_POST['name']);
  $category      = trim((string)($_POST['category'] ?? ''));
  $storage       = in_array($_POST['storage_type'] ?? 'pantry', ['pantry','refrigerator','freezer'], true)
                    ? $_POST['storage_type'] : 'pantry';
  $quantity      = max(0.01, (float)($_POST['quantity'] ?? 1));
  $unit          = trim((string)($_POST['unit'] ?? 'pcs'));
  $purchase_date = $_POST['purchase_date'] ?: null;
  $expiry_date   = $_POST['expiry_date']   ?: null;

  $apiQuery = $quantity . ' ' . $unit . ' ' . $name;
  $nutri    = fetchNutritionFromApiNinjas($apiQuery);

  $cal_per_unit = 0; $protein_per_unit = 0.0; $carbs_per_unit = 0.0; $fat_per_unit = 0.0;
  $fiber_per_unit = 0.0; $sugar_per_unit = 0.0; $sodium_per_unit = 0.0;

  if (isset($nutri['_error'])) {
    $err = 'Nutrition lookup failed: ' . $nutri['_error'] . '. Saved with defaults.';
  } else {
    $cal_total        = is_numeric($nutri['calories'])              ? (float)$nutri['calories']              : null;
    $protein_total    = is_numeric($nutri['protein_g'])             ? (float)$nutri['protein_g']             : null;
    $carbs_total      = is_numeric($nutri['carbohydrates_total_g']) ? (float)$nutri['carbohydrates_total_g'] : null;
    $fat_total        = is_numeric($nutri['fat_total_g'])           ? (float)$nutri['fat_total_g']           : null;
    $fiber_total      = is_numeric($nutri['fiber_g'])               ? (float)$nutri['fiber_g']               : null;
    $sugar_total      = is_numeric($nutri['sugar_g'])               ? (float)$nutri['sugar_g']               : null;
    $sodium_total_mg  = is_numeric($nutri['sodium_mg'])             ? (float)$nutri['sodium_mg']             : null;

    if ($cal_total === null && ($protein_total || $carbs_total || $fat_total)) {
      $cal_total = ($protein_total ?: 0) * 4 + ($carbs_total ?: 0) * 4 + ($fat_total ?: 0) * 9;
    }

    $q = $quantity > 0 ? $quantity : 1;
    if ($cal_total       !== null) $cal_per_unit     = (int) round($cal_total / $q);
    if ($protein_total   !== null) $protein_per_unit = round($protein_total / $q, 2);
    if ($carbs_total     !== null) $carbs_per_unit   = round($carbs_total / $q, 2);
    if ($fat_total       !== null) $fat_per_unit     = round($fat_total / $q, 2);
    if ($fiber_total     !== null) $fiber_per_unit   = round($fiber_total / $q, 2);
    if ($sugar_total     !== null) $sugar_per_unit   = round($sugar_total / $q, 2);
    if ($sodium_total_mg !== null) $sodium_per_unit  = round($sodium_total_mg / $q, 2);

    $message = "Nutrition saved (" . ($cal_total !== null ? $cal_total . " kcal total" : "estimated from macros") . ").";
  }

  $ins = $pdo->prepare("
    INSERT INTO food_items
      (user_id, name, category, storage_type, quantity, unit,
       calories_per_unit, protein_per_unit, carbs_per_unit, fat_per_unit,
       fiber_per_unit, sugar_per_unit, sodium_per_unit,
       purchase_date, expiry_date)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
  ");
  $ins->execute([
    $userId, $name, $category, $storage, $quantity, $unit,
    $cal_per_unit, $protein_per_unit, $carbs_per_unit, $fat_per_unit,
    $fiber_per_unit, $sugar_per_unit, $sodium_per_unit,
    $purchase_date, $expiry_date
  ]);

  header('Location: food_items.php?m=' . rawurlencode($message) . ($err ? '&err=' . rawurlencode($err) : ''));
  exit;
}

/* --------- Load items --------- */
$stmt = $pdo->prepare("SELECT * FROM food_items WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

/* --------- Split into active vs expired + aggregate totals (active only) --------- */
$today        = date('Y-m-d');
$itemTotals   = [];
$expiredItems = [];
$activeItems  = [];

foreach ($items as $it) {
    $exp = $it['expiry_date'] ?? null;
    $exp = $exp !== null ? (string)$exp : null;
    $isExpired = ($exp !== null && $exp !== '' && $exp < $today);

    if ($isExpired) {
        $expiredItems[] = $it;
        continue; // don't include in totals or active list
    }

    // Active item
    $activeItems[] = $it;

    $key  = mb_strtolower(trim((string)($it['name'] ?? '')));
    if ($key === '') continue;

    if (!isset($itemTotals[$key])) {
        $itemTotals[$key] = [
            'name'  => (string)$it['name'],
            'unit'  => (string)($it['unit'] ?? ''),
            'qty'   => 0.0,
            'rows'  => 0,
        ];
    }
    $itemTotals[$key]['qty']  += (float)$it['quantity'];
    $itemTotals[$key]['rows'] += 1;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Pantry — Smart Nutrition</title>
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
    .container{
      max-width:1200px;
      margin:2rem auto;
      padding:0 1rem 2rem;
    }
    .card{
      background:var(--panel);
      border-radius:var(--radius);
      border:1px solid var(--border);
      box-shadow:var(--shadow);
      padding:1.25rem 1.5rem;
    }
    h2{
      margin:0 0 .75rem;
      font-size:1.6rem;
      font-weight:900;
      color:#111827;
    }
    h3{
      margin:.2rem 0 .3rem;
      font-size:1.2rem;
      font-weight:800;
    }
    label{
      display:block;
      font-size:.8rem;
      font-weight:600;
      color:var(--muted);
      margin-top:.25rem;
    }
    input,select{
      width:100%;
      padding:.55rem .6rem;
      border-radius:.6rem;
      border:1px solid var(--border);
      background:#fff;
      font-size:.9rem;
      margin-top:.15rem;
      color:var(--text);
    }
    input:focus,select:focus{
      outline:none;
      border-color:var(--accent);
      box-shadow:0 0 0 2px rgba(255,122,0,.12);
    }
    .grid-2{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:.75rem;
      margin-top:.35rem;
    }
    .btn-primary{
      display:inline-block;
      padding:.65rem 1.2rem;
      border-radius:.7rem;
      border:0;
      background:linear-gradient(135deg,var(--accent),var(--accent2));
      color:#fff;
      font-weight:800;
      font-size:.9rem;
      cursor:pointer;
      box-shadow:var(--shadow);
      margin-top:.7rem;
    }
    .note{
      font-size:.8rem;
      color:var(--muted);
      margin-top:.4rem;
    }
    .messages{
      margin-bottom:.7rem;
    }
    .success{
      background:#ecfdf5;
      border:1px solid #bbf7d0;
      color:#166534;
      padding:.5rem .7rem;
      border-radius:.6rem;
      font-size:.82rem;
      margin-bottom:.35rem;
    }
    .error{
      background:#fef2f2;
      border:1px solid #fecaca;
      color:#991b1b;
      padding:.5rem .7rem;
      border-radius:.6rem;
      font-size:.82rem;
      margin-bottom:.35rem;
    }
    .section-header{
      display:flex;
      justify-content:space-between;
      align-items:flex-end;
      margin-top:1.25rem;
      gap:1rem;
    }
    .view-toggle select{
      padding:.35rem .6rem;
      border-radius:.5rem;
      border:1px solid var(--border);
      background:#fff;
      font-size:.8rem;
      color:var(--muted);
    }
    .table-wrap{
      margin-top:.4rem;
      overflow-x:auto;
    }
    table{
      width:100%;
      border-collapse:collapse;
      font-size:.85rem;
      background:#fff;
      border-radius:.75rem;
      overflow:hidden;
      box-shadow:var(--shadow);
    }
    th,td{
      padding:.55rem .6rem;
      border-bottom:1px solid #f3f4f6;
      text-align:left;
      white-space:nowrap;
    }
    th{
      font-size:.78rem;
      font-weight:700;
      color:var(--muted);
      background:#f9fafb;
    }
    tr:last-child td{border-bottom:none;}
    .btn-edit{
      padding:.3rem .6rem;
      border-radius:.5rem;
      border:0;
      background:#eff6ff;
      color:#1d4ed8;
      font-size:.75rem;
      font-weight:700;
      cursor:pointer;
    }
    /* Item totals list */
    .item-totals{
      margin-top:.7rem;
      font-size:.82rem;
      color:var(--muted);
    }
    .item-totals strong{
      color:#374151;
    }
    .totals-list{
      margin:.25rem 0 0 1.1rem;
      padding:0;
      list-style:disc;
    }
    .totals-list li{
      margin-bottom:.1rem;
    }
    /* Modal */
    .modal{
      position:fixed;
      inset:0;
      display:none;
      align-items:center;
      justify-content:center;
      background:rgba(15,23,42,.35);
      z-index:40;
    }
    .modal .panel{
      background:#ffffff;
      padding:1rem 1.1rem 1rem;
      border-radius:var(--radius);
      box-shadow:0 18px 45px rgba(15,23,42,.22);
      width:520px;
      max-width:94%;
    }
    .modal h3{
      margin:0 0 .5rem;
      font-size:1.1rem;
    }
    .modal .grid-2{
      margin-top:.25rem;
    }
    .modal button{
      margin-top:.5rem;
    }
    .btn-cancel{
      padding:.55rem .9rem;
      border-radius:.7rem;
      border:0;
      background:#f3f4f6;
      color:#4b5563;
      font-weight:700;
      font-size:.85rem;
      cursor:pointer;
      margin-left:.4rem;
    }
    @media(max-width:768px){
      header{padding:.7rem 1rem;}
      nav a{margin-left:.6rem;}
      .grid-2{grid-template-columns:1fr;}
    }
    .expired-row{
      background:#fef2f2;
      color:#991b1b;
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
    <a href="food_items.php" class="active">Pantry</a>
    <a href="meallogs.php">Meal Logs</a>
    <a href="recipes.php">Recipes</a>
    <a href="profile.php">Profile</a>
  </nav>
</header>

<div class="container">
  <div class="card">
    <h2>Manage Pantry Items</h2>

    <div class="messages">
      <?php if (!empty($_GET['m'])): ?>
        <div class="success"><?= e((string)$_GET['m']) ?></div>
      <?php endif; ?>
      <?php if (!empty($_GET['err'])): ?>
        <div class="error"><?= e((string)$_GET['err']) ?></div>
      <?php endif; ?>
    </div>

    <!-- Add item form -->
    <form method="post" novalidate>
      <label for="name">Item name</label>
      <input id="name" name="name" required placeholder="e.g., Eggs">

      <div class="grid-2">
        <div>
          <label for="quantity">Quantity</label>
          <input id="quantity" name="quantity" type="number" step="0.01" value="1" required>
        </div>
        <div>
          <label for="unit">Unit</label>
          <input id="unit" name="unit" value="pcs" placeholder="e.g., pcs, g, cup" required>
        </div>
      </div>

      <div class="grid-2">
        <div>
          <label for="category">Category</label>
          <input id="category" name="category" placeholder="e.g., Dairy">
        </div>
        <div>
          <label for="storage_type">Storage</label>
          <select id="storage_type" name="storage_type">
            <option value="pantry">Pantry</option>
            <option value="refrigerator">Refrigerator</option>
            <option value="freezer">Freezer</option>
          </select>
        </div>
      </div>

      <div class="grid-2">
        <div>
          <label for="purchase_date">Purchase date</label>
          <input id="purchase_date" name="purchase_date" type="date">
        </div>
        <div>
          <label for="expiry_date">Expiry date</label>
          <input id="expiry_date" name="expiry_date" type="date">
        </div>
      </div>

      <button type="submit" class="btn-primary">Add item (auto-fetch nutrition)</button>
      <div class="note">
        We call API Ninjas to pre-fill macros per unit. You can always edit values later.
      </div>
    </form>

    <!-- Pantry list -->
    <div class="section-header">
      <div>
        <h3>Your Pantry</h3>
        <div class="note">Switch between per-unit and total (quantity × unit) nutrition.</div>
      </div>
      <div class="view-toggle">
        <label for="viewToggle">View</label>
        <select id="viewToggle">
          <option value="per">Per unit</option>
          <option value="total">Total</option>
        </select>
      </div>
    </div>

    <?php if (!$activeItems && !$expiredItems): ?>
      <p class="note">No items yet — add something above to get started.</p>
    <?php else: ?>

      <?php if ($activeItems): ?>
      <div class="table-wrap">
        <table id="pantryTable">
          <thead>
            <tr>
              <th>Name</th>
              <th>Qty</th>
              <th>Unit</th>
              <th>kcal</th>
              <th>Protein (g)</th>
              <th>Carbs (g)</th>
              <th>Fat (g)</th>
              <th>Fiber (g)</th>
              <th>Sugar (g)</th>
              <th>Sodium (mg)</th>
              <th>Expiry</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($activeItems as $it):
            $qty    = (float)$it['quantity'];
            $cal    = (float)$it['calories_per_unit'];
            $prot   = (float)$it['protein_per_unit'];
            $carb   = (float)$it['carbs_per_unit'];
            $fat    = (float)$it['fat_per_unit'];
            $fiber  = (float)($it['fiber_per_unit'] ?? 0);
            $sugar  = (float)($it['sugar_per_unit'] ?? 0);
            $sodium = (float)($it['sodium_per_unit'] ?? 0);
          ?>
            <tr data-id="<?= (int)$it['id'] ?>"
                data-qty="<?= $qty ?>"
                data-cal="<?= $cal ?>"
                data-prot="<?= $prot ?>"
                data-carb="<?= $carb ?>"
                data-fat="<?= $fat ?>"
                data-fiber="<?= $fiber ?>"
                data-sugar="<?= $sugar ?>"
                data-sodium="<?= $sodium ?>">
              <td><?= e($it['name']) ?></td>
              <td class="col-qty"><?= number_format($qty, 2) ?></td>
              <td><?= e($it['unit']) ?></td>
              <td class="col-cal"><?= (int)$cal ?></td>
              <td class="col-prot"><?= number_format($prot, 2) ?></td>
              <td class="col-carb"><?= number_format($carb, 2) ?></td>
              <td class="col-fat"><?= number_format($fat, 2) ?></td>
              <td class="col-fiber"><?= number_format($fiber, 2) ?></td>
              <td class="col-sugar"><?= number_format($sugar, 2) ?></td>
              <td class="col-sodium"><?= number_format($sodium, 2) ?></td>
              <td><?= e($it['expiry_date'] ?? '') ?></td>
              <td><button type="button" class="btn-edit" data-id="<?= (int)$it['id'] ?>">Edit</button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p class="note">No non-expired items in your pantry right now.</p>
      <?php endif; ?>

      <?php if ($itemTotals): ?>
        <div class="item-totals">
          <strong>Item totals across all entries:</strong>
          <ul class="totals-list">
            <?php foreach ($itemTotals as $t): ?>
              <li>
                <?= e($t['name']) ?> — <?= number_format($t['qty'], 2) . ' ' . e((string)$t['unit']) ?>
                (<?= (int)$t['rows'] ?> <?= $t['rows'] === 1 ? 'entry' : 'entries' ?>)
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!empty($expiredItems)): ?>
        <div class="section-header" style="margin-top:2rem;">
          <div>
            <h3>Expired Items</h3>
            <div class="note">These are past their expiry date and are not counted in totals.</div>
          </div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Qty</th>
                <th>Unit</th>
                <th>kcal</th>
                <th>Protein (g)</th>
                <th>Carbs (g)</th>
                <th>Fat (g)</th>
                <th>Fiber (g)</th>
                <th>Sugar (g)</th>
                <th>Sodium (mg)</th>
                <th>Expired On</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($expiredItems as $it):
              $qty    = (float)$it['quantity'];
              $cal    = (float)$it['calories_per_unit'];
              $prot   = (float)$it['protein_per_unit'];
              $carb   = (float)$it['carbs_per_unit'];
              $fat    = (float)$it['fat_per_unit'];
              $fiber  = (float)($it['fiber_per_unit'] ?? 0);
              $sugar  = (float)($it['sugar_per_unit'] ?? 0);
              $sodium = (float)($it['sodium_per_unit'] ?? 0);
            ?>
              <tr class="expired-row">
                <td><?= e($it['name']) ?></td>
                <td><?= number_format($qty, 2) ?></td>
                <td><?= e($it['unit']) ?></td>
                <td><?= (int)$cal ?></td>
                <td><?= number_format($prot, 2) ?></td>
                <td><?= number_format($carb, 2) ?></td>
                <td><?= number_format($fat, 2) ?></td>
                <td><?= number_format($fiber, 2) ?></td>
                <td><?= number_format($sugar, 2) ?></td>
                <td><?= number_format($sodium, 2) ?></td>
                <td><?= e($it['expiry_date'] ?? '') ?></td>
                <td><button type="button" class="btn-edit" data-id="<?= (int)$it['id'] ?>">Edit</button></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    <?php endif; ?>

    <div class="note" style="margin-top:.6rem;">
      Nutrition values are stored per unit and used across your dashboard, recipes, and meal logs.
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal" aria-hidden="true">
  <div class="panel">
    <h3>Edit nutrition per unit</h3>
    <form id="editForm" method="post">
      <input type="hidden" name="id" id="edit_id">

      <div class="grid-2">
        <div>
          <label>Calories (kcal)</label>
          <input name="calories_per_unit" id="edit_cal" type="number" step="1">
        </div>
        <div>
          <label>Protein (g)</label>
          <input name="protein_per_unit" id="edit_prot" type="number" step="0.01">
        </div>
      </div>

      <div class="grid-2">
        <div>
          <label>Carbs (g)</label>
          <input name="carbs_per_unit" id="edit_carb" type="number" step="0.01">
        </div>
        <div>
          <label>Fat (g)</label>
          <input name="fat_per_unit" id="edit_fat" type="number" step="0.01">
        </div>
      </div>

      <div class="grid-2">
        <div>
          <label>Fiber (g)</label>
          <input name="fiber_per_unit" id="edit_fiber" type="number" step="0.01">
        </div>
        <div>
          <label>Sugar (g)</label>
          <input name="sugar_per_unit" id="edit_sugar" type="number" step="0.01">
        </div>
      </div>

      <div class="grid-2">
        <div>
          <label>Sodium (mg)</label>
          <input name="sodium_per_unit" id="edit_sodium" type="number" step="0.01">
        </div>
        <div style="display:flex;align-items:flex-end;gap:.4rem;">
          <button type="submit" name="update" value="1" class="btn-primary" style="margin-top:0;">Save</button>
          <button type="button" class="btn-cancel" id="closeModal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const toggle = document.getElementById('viewToggle');
  const tbody  = document.querySelector('#pantryTable tbody');

  function fmt(v, intMode){
    if (intMode) return String(Math.round(v));
    return (Math.round(v*100)/100).toFixed(2);
  }

  function updateView(){
    if (!tbody) return;
    const mode = toggle.value;
    tbody.querySelectorAll('tr').forEach(tr=>{
      const qty    = Number(tr.dataset.qty)    || 0;
      const cal    = Number(tr.dataset.cal)    || 0;
      const prot   = Number(tr.dataset.prot)   || 0;
      const carb   = Number(tr.dataset.carb)   || 0;
      const fat    = Number(tr.dataset.fat)    || 0;
      const fiber  = Number(tr.dataset.fiber)  || 0;
      const sugar  = Number(tr.dataset.sugar)  || 0;
      const sodium = Number(tr.dataset.sodium) || 0;

      if (mode === 'total') {
        tr.querySelector('.col-cal').textContent    = fmt(cal*qty, true);
        tr.querySelector('.col-prot').textContent   = fmt(prot*qty, false);
        tr.querySelector('.col-carb').textContent   = fmt(carb*qty, false);
        tr.querySelector('.col-fat').textContent    = fmt(fat*qty, false);
        tr.querySelector('.col-fiber').textContent  = fmt(fiber*qty, false);
        tr.querySelector('.col-sugar').textContent  = fmt(sugar*qty, false);
        tr.querySelector('.col-sodium').textContent = fmt(sodium*qty, false);
      } else {
        tr.querySelector('.col-cal').textContent    = fmt(cal, true);
        tr.querySelector('.col-prot').textContent   = fmt(prot, false);
        tr.querySelector('.col-carb').textContent   = fmt(carb, false);
        tr.querySelector('.col-fat').textContent    = fmt(fat, false);
        tr.querySelector('.col-fiber').textContent  = fmt(fiber, false);
        tr.querySelector('.col-sugar').textContent  = fmt(sugar, false);
        tr.querySelector('.col-sodium').textContent = fmt(sodium, false);
      }
    });
  }

  if (toggle && tbody) {
    toggle.addEventListener('change', updateView);
    updateView();
  }

  // Edit modal
  const editModal    = document.getElementById('editModal');
  const closeModalBtn = document.getElementById('closeModal');

  document.querySelectorAll('.btn-edit').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const id = btn.dataset.id;
      const tr = document.querySelector('tr[data-id="'+id+'"]');
      if (!tr) return;

      document.getElementById('edit_id').value     = id;
      document.getElementById('edit_cal').value    = Number(tr.dataset.cal)    || 0;
      document.getElementById('edit_prot').value   = Number(tr.dataset.prot)   || 0;
      document.getElementById('edit_carb').value   = Number(tr.dataset.carb)   || 0;
      document.getElementById('edit_fat').value    = Number(tr.dataset.fat)    || 0;
      document.getElementById('edit_fiber').value  = Number(tr.dataset.fiber)  || 0;
      document.getElementById('edit_sugar').value  = Number(tr.dataset.sugar)  || 0;
      document.getElementById('edit_sodium').value = Number(tr.dataset.sodium) || 0;

      editModal.style.display = 'flex';
      editModal.setAttribute('aria-hidden','false');
    });
  });

  function closeModal() {
    editModal.style.display = 'none';
    editModal.setAttribute('aria-hidden','true');
  }

  if (closeModalBtn) {
    closeModalBtn.addEventListener('click', closeModal);
  }
  editModal.addEventListener('click', (e)=>{
    if (e.target === editModal) closeModal();
  });
})();
</script>
</body>
</html>
