<?php
// C:\xampp\htdocs\smartnutrition\food_items.php
declare(strict_types=1);
session_start();

/* Require login */
if (empty($_SESSION['user_id'])) {
  header('Location: signin.php'); exit;
}

/* ========= Config ========= */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'smart_nutrition';
const DB_USER = 'root';
const DB_PASS = '';
const EXPIRY_SOON_DAYS = 3;

function db(): PDO {
  static $pdo=null;
  if ($pdo) return $pdo;
  $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function d(?string $s): ?string { return $s === '' ? null : $s; } // empty to null

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
$userId = (int)$_SESSION['user_id'];

/* ========= Ensure table exists (safe no-op if already there) ========= */
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
    purchase_date DATE NULL,
    expiry_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (expiry_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB;
");

/* ========= Actions ========= */
$errors = [];
$okMsg  = '';

$validStorage = ['pantry','refrigerator','freezer'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
    $errors[] = 'Security token mismatch. Please reload and try again.';
  } else {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
      $name = trim($_POST['name'] ?? '');
      $category = trim($_POST['category'] ?? '');
      $storage_type = $_POST['storage_type'] ?? 'pantry';
      $quantity = trim($_POST['quantity'] ?? '1');
      $unit = trim($_POST['unit'] ?? 'pcs');
      $cal = trim($_POST['calories_per_unit'] ?? '0');
      $purchase = trim($_POST['purchase_date'] ?? '');
      $expiry = trim($_POST['expiry_date'] ?? '');

      if ($name === '') $errors[] = 'Item name is required.';
      if (!in_array($storage_type, $validStorage, true)) $errors[] = 'Invalid storage type.';
      if ($quantity !== '' && !is_numeric($quantity)) $errors[] = 'Quantity must be numeric.';
      if ($cal !== '' && !ctype_digit(ltrim($cal, '-'))) $errors[] = 'Calories must be a whole number.';
      if ($purchase !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchase)) $errors[] = 'Purchase date must be YYYY-MM-DD.';
      if ($expiry !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) $errors[] = 'Expiry date must be YYYY-MM-DD.';

      if (!$errors) {
        $stmt = $pdo->prepare("
          INSERT INTO food_items (user_id, name, category, storage_type, quantity, unit, calories_per_unit, purchase_date, expiry_date)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
          $userId, $name, d($category), $storage_type, $quantity === '' ? 1 : (float)$quantity,
          $unit === '' ? 'pcs' : $unit, $cal === '' ? 0 : (int)$cal, d($purchase), d($expiry),
        ]);
        $okMsg = 'Item added.';
      }
    }

    if ($action === 'update') {
      $id = (int)($_POST['id'] ?? 0);
      $name = trim($_POST['name'] ?? '');
      $category = trim($_POST['category'] ?? '');
      $storage_type = $_POST['storage_type'] ?? 'pantry';
      $quantity = trim($_POST['quantity'] ?? '1');
      $unit = trim($_POST['unit'] ?? 'pcs');
      $cal = trim($_POST['calories_per_unit'] ?? '0');
      $purchase = trim($_POST['purchase_date'] ?? '');
      $expiry = trim($_POST['expiry_date'] ?? '');

      if ($id <= 0) $errors[] = 'Invalid item.';
      if ($name === '') $errors[] = 'Item name is required.';
      if (!in_array($storage_type, $validStorage, true)) $errors[] = 'Invalid storage type.';
      if ($quantity !== '' && !is_numeric($quantity)) $errors[] = 'Quantity must be numeric.';
      if ($cal !== '' && !is_numeric($cal)) $errors[] = 'Calories must be numeric.';
      if ($purchase !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchase)) $errors[] = 'Purchase date must be YYYY-MM-DD.';
      if ($expiry !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) $errors[] = 'Expiry date must be YYYY-MM-DD.';

      if (!$errors) {
        // ensure ownership
        $own = $pdo->prepare('SELECT id FROM food_items WHERE id=? AND user_id=?');
        $own->execute([$id, $userId]);
        if (!$own->fetch()) {
          $errors[] = 'Item not found.';
        } else {
          $stmt = $pdo->prepare("
            UPDATE food_items
            SET name=?, category=?, storage_type=?, quantity=?, unit=?, calories_per_unit=?, purchase_date=?, expiry_date=?
            WHERE id=? AND user_id=? LIMIT 1
          ");
          $stmt->execute([
            $name, d($category), $storage_type, $quantity === '' ? 1 : (float)$quantity,
            $unit === '' ? 'pcs' : $unit, $cal === '' ? 0 : (int)$cal,
            d($purchase), d($expiry), $id, $userId
          ]);
          $okMsg = 'Item updated.';
        }
      }
    }

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        $errors[] = 'Invalid item.';
      } else {
        $stmt = $pdo->prepare('DELETE FROM food_items WHERE id=? AND user_id=? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $okMsg = 'Item deleted.';
      }
    }
  }
}

/* ========= Fetch items ========= */
$items = [];
$q = $pdo->prepare("
  SELECT id, name, category, storage_type, quantity, unit, calories_per_unit, purchase_date, expiry_date, created_at
  FROM food_items
  WHERE user_id = ?
  ORDER BY (expiry_date IS NULL), expiry_date ASC, created_at DESC
");
$q->execute([$userId]);
$items = $q->fetchAll();

/* Helper: expiry status */
function expiry_status(?string $date): array {
  if (!$date) return ['label'=>'No date','class'=>'no-date'];
  $today = new DateTimeImmutable('today');
  try { $d = new DateTimeImmutable($date); } catch(Throwable $e) { return ['label'=>'Invalid','class'=>'invalid']; }
  $diff = (int)$today->diff($d)->format('%r%a'); // days (signed)
  if ($diff < 0) return ['label'=>"Expired " . abs($diff) . "d ago",'class'=>'expired'];
  if ($diff === 0) return ['label'=>"Expires today",'class'=>'today'];
  if ($diff <= EXPIRY_SOON_DAYS) return ['label'=>"In {$diff}d",'class'=>'soon'];
  return ['label'=>"In {$diff}d",'class'=>'ok'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Food Items — Smart Nutrition</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root { --bg:#0b1220; --card:#121a2d; --panel:#0f1627; --text:#e6ecff; --muted:#9bb0ff; --accent:#6aa1ff; --border:rgba(255,255,255,.08); --danger:#ff6a6a; }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,Segoe UI,Arial,sans-serif;min-height:100vh}
    header{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.2rem;background:rgba(255,255,255,.04);border-bottom:1px solid var(--border)}
    .brand a{color:var(--text);text-decoration:none;font-weight:900}
    .container{max-width:1200px;margin:2rem auto;padding:0 1rem}
    .card{background:var(--card);border-radius:16px;padding:1.1rem;box-shadow:0 16px 40px rgba(0,0,0,.35);border:1px solid var(--border)}
    h1{margin:.2rem 0 1rem}
    .row{display:grid;grid-template-columns:1fr;gap:1rem}

    .alert{ padding:.8rem 1rem; border-radius:.6rem; margin-bottom:1rem }
    .ok{ background:rgba(34,197,94,.15); border:1px solid rgba(34,197,94,.35) }
    .err{ background:rgba(255,106,106,.15); border:1px solid rgba(255,106,106,.35) }

    /* form */
    form.add, form.edit, form.delete { background:var(--panel); border:1px solid #1b2440; padding:1rem; border-radius:12px }
    label{display:flex;flex-direction:column;font-size:.92rem;color:var(--muted)}
    input, select{margin-top:.35rem;padding:.6rem .7rem;border-radius:.6rem;border:1px solid #223;background:#0b1526;color:var(--text)}
    .grid{display:grid;gap:.8rem;grid-template-columns:repeat(6,1fr)}
    @media (max-width:980px){ .grid{grid-template-columns:repeat(3,1fr)} }
    @media (max-width:580px){ .grid{grid-template-columns:1fr} }
    .actions{margin-top:.8rem;display:flex;gap:.6rem;flex-wrap:wrap}
    .btn{border:0;padding:.7rem 1.05rem;border-radius:.7rem;font-weight:900;cursor:pointer}
    .btn-primary{background:linear-gradient(135deg,#6aa1ff,#22d3ee);color:#fff}
    .btn-muted{background:transparent;color:var(--muted);border:1px solid var(--border)}
    .btn-danger{background:linear-gradient(135deg,#ff6a6a,#f97316);color:#fff;border:1px solid rgba(255,106,106,.35)}

    /* table */
    table{width:100%;border-collapse:collapse;background:var(--panel);border:1px solid #1b2440;border-radius:12px;overflow:hidden}
    th,td{padding:.7rem .8rem;border-bottom:1px solid #1b2440;font-size:.95rem}
    th{color:#cdd9ff;text-align:left;background:#101a30}
    tr:last-child td{border-bottom:0}
    .pill{display:inline-block;padding:.25rem .5rem;border-radius:999px;border:1px solid var(--border);font-size:.85rem}
    .exp.expired{color:#fff;background:#8b1c1c;border-color:#a33}
    .exp.today{color:#1e293b;background:#fde68a;border-color:#fcd34d}
    .exp.soon{color:#083344;background:#99f6e4;border-color:#5eead4}
    .exp.ok{color:#052e16;background:#bbf7d0;border-color:#86efac}
    .exp.no-date{color:#1f2937;background:#e5e7eb;border-color:#cbd5e1}
    .tools{display:flex;gap:.4rem}
    details summary{cursor:pointer;list-style:none}
    details summary::-webkit-details-marker{display:none}
    .small{font-size:.85rem;color:#9fb2ffb3}
  </style>
</head>
<body>
<header>
  <div class="brand"><a href="Index.php">← Dashboard</a></div>
  <form method="post" action="signout.php" style="margin:0">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <button class="btn btn-danger" type="submit">Sign Out</button>
  </form>
</header>

<div class="container">
  <div class="card">
    <h1>Food Items (Pantry)</h1>
    <p class="small">Highlighting items that are <strong>expired</strong> or <strong>expiring soon (≤ <?= EXPIRY_SOON_DAYS ?> days)</strong>.</p>

    <?php if ($okMsg): ?><div class="alert ok"><?= e($okMsg) ?></div><?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert err">
        <ul style="margin:.3rem 0 0 1rem">
          <?php foreach ($errors as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Add new item -->
    <form class="add" method="post" action="food_items.php" novalidate>
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="create">
      <div class="grid">
        <label>Item name
          <input name="name" required placeholder="e.g., Milk">
        </label>
        <label>Category
          <input name="category" placeholder="e.g., Dairy">
        </label>
        <label>Storage
          <select name="storage_type">
            <option value="pantry">Pantry</option>
            <option value="refrigerator">Refrigerator</option>
            <option value="freezer">Freezer</option>
          </select>
        </label>
        <label>Quantity
          <input name="quantity" type="number" step="0.01" min="0" value="1">
        </label>
        <label>Unit
          <input name="unit" placeholder="e.g., pcs, g, ml" value="pcs">
        </label>
        <label>Calories / unit
          <input name="calories_per_unit" type="number" step="1" min="0" value="0">
        </label>
        <label>Purchase date
          <input name="purchase_date" type="date">
        </label>
        <label>Expiry date
          <input name="expiry_date" type="date">
        </label>
      </div>
      <div class="actions">
        <button class="btn btn-primary" type="submit">Add item</button>
        <a class="btn btn-muted" href="food_items.php">Reset</a>
      </div>
    </form>
  </div>

  <div class="card" style="margin-top:1rem">
    <h2>Your Items</h2>
    <?php if (!$items): ?>
      <p class="small">No items yet. Add your first one above.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Item</th>
            <th>Category</th>
            <th>Storage</th>
            <th>Qty</th>
            <th>Unit</th>
            <th>Cal/u</th>
            <th>Purchase</th>
            <th>Expiry</th>
            <th>Status</th>
            <th style="width:180px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): 
            $status = expiry_status($it['expiry_date'] ?? null);
          ?>
          <tr>
            <td><?= e($it['name']) ?></td>
            <td><?= e($it['category'] ?? '') ?></td>
            <td><?= e($it['storage_type']) ?></td>
            <td><?= e((string)$it['quantity']) ?></td>
            <td><?= e($it['unit']) ?></td>
            <td><?= e((string)$it['calories_per_unit']) ?></td>
            <td><?= e($it['purchase_date'] ?? '') ?></td>
            <td><?= e($it['expiry_date'] ?? '') ?></td>
            <td><span class="pill exp <?= e($status['class']) ?>"><?= e($status['label']) ?></span></td>
            <td class="tools">
              <details>
                <summary class="btn btn-muted" style="display:inline-block">Edit</summary>
                <form class="edit" method="post" action="food_items.php" style="margin-top:.6rem">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                  <div class="grid">
                    <label>Item name
                      <input name="name" value="<?= e($it['name']) ?>" required>
                    </label>
                    <label>Category
                      <input name="category" value="<?= e($it['category'] ?? '') ?>">
                    </label>
                    <label>Storage
                      <select name="storage_type">
                        <?php foreach (['pantry','refrigerator','freezer'] as $opt): ?>
                          <option value="<?= $opt ?>" <?= $it['storage_type']===$opt?'selected':'' ?>><?= ucfirst($opt) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label>Quantity
                      <input name="quantity" type="number" step="0.01" min="0" value="<?= e((string)$it['quantity']) ?>">
                    </label>
                    <label>Unit
                      <input name="unit" value="<?= e($it['unit']) ?>">
                    </label>
                    <label>Calories / unit
                      <input name="calories_per_unit" type="number" step="1" min="0" value="<?= e((string)$it['calories_per_unit']) ?>">
                    </label>
                    <label>Purchase date
                      <input name="purchase_date" type="date" value="<?= e($it['purchase_date'] ?? '') ?>">
                    </label>
                    <label>Expiry date
                      <input name="expiry_date" type="date" value="<?= e($it['expiry_date'] ?? '') ?>">
                    </label>
                  </div>
                  <div class="actions">
                    <button class="btn btn-primary" type="submit">Save</button>
                  </div>
                </form>
              </details>
              <form class="delete" method="post" action="food_items.php" onsubmit="return confirm('Delete this item?')">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                <button class="btn btn-danger" type="submit">Delete</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="small" style="margin-top:.5rem">Tip: click <strong>Edit</strong> to modify a row; use the <strong>Delete</strong> button to remove it.</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
