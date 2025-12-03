<?php
// C:\xampp\htdocs\smartnutrition\log_recipe.php
declare(strict_types=1);
session_start();

if (empty($_SESSION['user_id'])) { header('Location: signin.php'); exit; }

/* ---- DB CONFIG ---- */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'smart_nutrition';
const DB_USER = 'root';
const DB_PASS = '';

/* ---- SPOONACULAR KEY (same as recipes.php) ---- */
const SPOONACULAR_KEY = 'e03dccce030c4e36a102c9ce21c3cece';

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

/* ---- HTTP + Spoonacular helpers (minimal set) ---- */
function http_get_json(string $url, array $query): array {
  $q = http_build_query($query);
  $ch = curl_init($url.'?'.$q);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
  ]);
  $res  = curl_exec($ch);
  $err  = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($err) return ['_error'=>"Network error: $err"];
  $text = (string)$res;
  $data = json_decode($text, true);

  if ($code < 200 || $code >= 300) {
    $msg = "HTTP $code from API";
    if (is_array($data) && isset($data['message'])) $msg .= " — ".$data['message'];
    elseif (trim($text) !== '') $msg .= " — ".$text;
    return ['_error'=>$msg];
  }
  return is_array($data) ? $data : ['_error'=>'Invalid JSON from API'];
}

function clean_ingredient(string $name): string {
  $s = strtolower(trim($name));
  $s = preg_replace('/\((.*?)\)/', '', $s);
  $s = preg_replace('/[^a-z\s]/', ' ', $s);
  $s = preg_replace('/\s+/', ' ', $s);
  $s = trim($s);
  if ($s === '') return '';
  if (substr($s, -1) === 's' && !in_array($s, ['lettuce','asparagus','hummus'], true)) {
    $s = rtrim($s, 's');
  }
  return $s;
}

function spoon_recipe_info(int $id): array {
  $url = "https://api.spoonacular.com/recipes/$id/information";
  $query = ['apiKey' => SPOONACULAR_KEY, 'includeNutrition' => 'false'];
  return http_get_json($url, $query);
}

$pdo = db();

/* ---- Ensure meal_logs has the columns we need (safe to keep) ---- */
$cols = $pdo->query("
  SELECT column_name FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'meal_logs'
")->fetchAll(PDO::FETCH_COLUMN);
$has = array_flip($cols);

if (!isset($has['food_item_id'])) $pdo->exec("ALTER TABLE meal_logs ADD COLUMN food_item_id INT NULL AFTER user_id");
if (!isset($has['recipe_id']))    $pdo->exec("ALTER TABLE meal_logs ADD COLUMN recipe_id INT NULL AFTER food_item_id");
if (!isset($has['recipe_title'])) $pdo->exec("ALTER TABLE meal_logs ADD COLUMN recipe_title VARCHAR(255) NULL AFTER recipe_id");
if (!isset($has['quantity']))     $pdo->exec("ALTER TABLE meal_logs ADD COLUMN quantity DECIMAL(10,2) DEFAULT 1 AFTER recipe_title");
if (!isset($has['unit']))         $pdo->exec("ALTER TABLE meal_logs ADD COLUMN unit VARCHAR(20) DEFAULT 'serving' AFTER quantity");
if (!isset($has['calories']))     $pdo->exec("ALTER TABLE meal_logs ADD COLUMN calories INT DEFAULT 0 AFTER unit");
if (!isset($has['protein']))      $pdo->exec("ALTER TABLE meal_logs ADD COLUMN protein DECIMAL(10,2) DEFAULT 0 AFTER calories");
if (!isset($has['carbs']))        $pdo->exec("ALTER TABLE meal_logs ADD COLUMN carbs DECIMAL(10,2) DEFAULT 0 AFTER protein");
if (!isset($has['fat']))          $pdo->exec("ALTER TABLE meal_logs ADD COLUMN fat DECIMAL(10,2) DEFAULT 0 AFTER carbs");
if (!isset($has['meal_type']))    $pdo->exec("ALTER TABLE meal_logs ADD COLUMN meal_type ENUM('breakfast','lunch','dinner','snack') DEFAULT 'lunch' AFTER fat");
if (!isset($has['consumed_at']))  $pdo->exec("ALTER TABLE meal_logs ADD COLUMN consumed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER meal_type");

/* ---- Read POST (sent from recipes.php card form) ---- */
$uid           = (int)$_SESSION['user_id'];
$recipeId      = isset($_POST['recipe_id']) ? (int)$_POST['recipe_id'] : null;
$recipeTitle   = trim((string)($_POST['recipe_title'] ?? 'Recipe'));
$servings      = isset($_POST['servings']) ? max(1, (int)$_POST['servings']) : 1;

$calTotal      = is_numeric($_POST['calories_total'] ?? null) ? (float)$_POST['calories_total'] : null;
$protTotal     = is_numeric($_POST['protein_total']  ?? null) ? (float)$_POST['protein_total']  : null;
$carbTotal     = is_numeric($_POST['carbs_total']    ?? null) ? (float)$_POST['carbs_total']    : null;
$fatTotal      = is_numeric($_POST['fat_total']      ?? null) ? (float)$_POST['fat_total']      : null;

$eatenServings = isset($_POST['eaten_servings']) ? max(0.01, (float)$_POST['eaten_servings']) : 1.0;
$mealType      = $_POST['meal_type'] ?? 'lunch';
if (!in_array($mealType, ['breakfast','lunch','dinner','snack'], true)) $mealType = 'lunch';

/* ---- Derive per-serving values (if totals provided) ---- */
$perCal  = ($calTotal  !== null && $servings) ? $calTotal  / $servings : 0.0;
$perProt = ($protTotal !== null && $servings) ? $protTotal / $servings : 0.0;
$perCarb = ($carbTotal !== null && $servings) ? $carbTotal / $servings : 0.0;
$perFat  = ($fatTotal  !== null && $servings) ? $fatTotal  / $servings : 0.0;

/* ---- Compute stored amounts for the eaten portion ---- */
$calories = (int) round($perCal * $eatenServings);
$protein  = round($perProt * $eatenServings, 2);
$carbs    = round($perCarb * $eatenServings, 2);
$fat      = round($perFat * $eatenServings, 2);

/* ---- INSERT meal log ---- */
$sql = "INSERT INTO meal_logs
(user_id, food_item_id, recipe_id, recipe_title, quantity, unit, calories, protein, carbs, fat, meal_type, consumed_at)
VALUES (?,        NULL,        ?,           ?,        ?,   ?,       ?,       ?,      ?,    ?,        ?,     NOW())";

$stm = $pdo->prepare($sql);
$stm->execute([
  $uid,                 // user_id
  $recipeId,            // recipe_id
  $recipeTitle,         // recipe_title
  $eatenServings,       // quantity
  'serving',            // unit
  $calories,            // calories
  $protein,             // protein
  $carbs,               // carbs
  $fat,                 // fat
  $mealType,            // meal_type
]);

/* ---- Decrement pantry quantities based on Spoonacular recipe ---- */
if ($recipeId && SPOONACULAR_KEY !== '' && SPOONACULAR_KEY !== 'YOUR_REAL_API_KEY_HERE') {
  $info = spoon_recipe_info($recipeId);

  if (!isset($info['_error']) && !empty($info['extendedIngredients']) && $servings > 0) {
    $scale  = $eatenServings / $servings;   // e.g. 1 serving eaten out of 4 total => 0.25
    $needed = [];

    foreach ($info['extendedIngredients'] as $ing) {
      $nameRaw = $ing['name'] ?? '';
      $clean   = clean_ingredient($nameRaw);
      if ($clean === '') continue;

      $amount = isset($ing['amount']) ? (float)$ing['amount'] : 0.0;
      if ($amount <= 0) continue;

      $scaled = $amount * $scale;  // amount used for the portion the user ate
      if ($scaled <= 0) continue;

      if (!isset($needed[$clean])) $needed[$clean] = 0.0;
      $needed[$clean] += $scaled;
    }

    if ($needed) {
      // Load current non-expired pantry items for this user
      $stmt = $pdo->prepare("
        SELECT id, name, quantity, expiry_date, created_at
        FROM food_items
        WHERE user_id = ?
          AND (expiry_date IS NULL OR expiry_date >= CURDATE())
        ORDER BY (expiry_date IS NULL), expiry_date ASC, created_at ASC
      ");
      $stmt->execute([$uid]);
      $pantryRows = $stmt->fetchAll();

      // Group pantry rows by cleaned name
      $byClean = [];
      foreach ($pantryRows as $row) {
        $cleanName = clean_ingredient((string)$row['name']);
        if ($cleanName === '') continue;
        $byClean[$cleanName][] = $row;
      }

      // For each ingredient required by the recipe, decrement pantry quantities
      foreach ($needed as $cleanName => $needQty) {
        if ($needQty <= 0 || empty($byClean[$cleanName])) continue;

        $remaining = $needQty;
        foreach ($byClean[$cleanName] as $row) {
          if ($remaining <= 0) break;

          $currentQty = isset($row['quantity']) ? (float)$row['quantity'] : 0.0;
          if ($currentQty <= 0) continue;

          $deduct = min($currentQty, $remaining);
          $remaining -= $deduct;
          $newQty = max(0.0, $currentQty - $deduct);

          $upd = $pdo->prepare("UPDATE food_items SET quantity = ? WHERE id = ?");
          $upd->execute([$newQty, (int)$row['id']]);
        }
      }
    }
  }
}

header('Location: Index.php?m='.rawurlencode('Recipe logged: '.$recipeTitle));
exit;
