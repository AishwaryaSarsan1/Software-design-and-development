<?php
// C:\xampp\htdocs\smartnutrition\recipes.php
declare(strict_types=1);
session_start();

if (empty($_SESSION['user_id'])) { header('Location: signin.php'); exit; }

/* ===== DB CONFIG ===== */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'smart_nutrition';
const DB_USER = 'root';
const DB_PASS = '';

/* ===== SPOONACULAR KEY (Food API key) ===== */
const SPOONACULAR_KEY = 'e03dccce030c4e36a102c9ce21c3cece'; // your key

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
function slug(string $s): string { return strtolower(preg_replace('/[^a-z0-9]+/i', '-', $s)); }

/* ---------- HTTP helper ---------- */
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

/* ---------- Ingredient cleaning ---------- */
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

/* ---------- Spoonacular wrappers ---------- */
function spoon_complex_search(array $ingredients, int $number, ?string $diet, array $intolerances, ?int $maxReadyTime): array {
  $url = 'https://api.spoonacular.com/recipes/complexSearch';
  $query = [
    'apiKey' => SPOONACULAR_KEY,
    'includeIngredients' => implode(',', $ingredients),
    'number' => $number,
    'sort' => 'popularity',
    'addRecipeInformation' => 'true',
  ];
  if ($diet)         $query['diet'] = $diet;
  if ($intolerances) $query['intolerances'] = implode(',', $intolerances);
  if ($maxReadyTime) $query['maxReadyTime'] = $maxReadyTime;
  return http_get_json($url, $query);
}

function spoon_find_by_ingredients(array $ingredients, int $number = 8, int $ranking = 2, bool $ignorePantry = true): array {
  $url = 'https://api.spoonacular.com/recipes/findByIngredients';
  $query = [
    'apiKey'       => SPOONACULAR_KEY,
    'ingredients'  => implode(',', $ingredients),
    'number'       => $number,
    'ranking'      => $ranking,
    'ignorePantry' => $ignorePantry ? 'true' : 'false',
  ];
  return http_get_json($url, $query);
}

function spoon_recipe_info(int $id): array {
  $url = "https://api.spoonacular.com/recipes/$id/information";
  $query = ['apiKey' => SPOONACULAR_KEY, 'includeNutrition' => 'false'];
  return http_get_json($url, $query);
}

/* Nutrition totals via widget JSON */
function spoon_recipe_nutrition(int $id): array {
  $url = "https://api.spoonacular.com/recipes/$id/nutritionWidget.json";
  $data = http_get_json($url, ['apiKey' => SPOONACULAR_KEY]);
  if (isset($data['_error'])) return ['_error' => $data['_error']];

  $parseNum = function($s): float {
    if (!is_string($s)) return 0.0;
    if (preg_match('/([0-9]+(\.[0-9]+)?)/', $s, $m)) return (float)$m[1];
    return 0.0;
  };

  return [
    'calories_total' => $parseNum($data['calories'] ?? ''),
    'protein_total'  => $parseNum($data['protein']  ?? ''),
    'carbs_total'    => $parseNum($data['carbs']    ?? ''),
    'fat_total'      => $parseNum($data['fat']      ?? ''),
  ];
}

/* ===== Load user's pantry (non-expired, unique) ===== */
$userId = (int)$_SESSION['user_id'];
$pdo = db();

/* ensure table exists (safe) */
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
    INDEX (expiry_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB;
");

/* Only non-expired items; sort by nearest expiry; then dedupe by name */
$stmt = $pdo->prepare("
  SELECT id, name, expiry_date
  FROM food_items
  WHERE user_id = ?
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
  ORDER BY (expiry_date IS NULL), expiry_date ASC, created_at DESC
");
$stmt->execute([$userId]);
$rawItems = $stmt->fetchAll();

/* Deduplicate names (case-insensitive) */
$seen = [];
$items = [];
foreach ($rawItems as $row) {
  $name = trim((string)$row['name']);
  if ($name === '') continue;
  $key = mb_strtolower($name, 'UTF-8');
  if (isset($seen[$key])) continue;
  $seen[$key] = true;
  $row['name'] = $name;
  $items[] = $row;
}

/* ===== UI State: selected ingredients ===== */
$rawSelected = [];
if (isset($_GET['ing']) && is_array($_GET['ing'])) {
  foreach ($_GET['ing'] as $name) {
    $name = trim((string)$name);
    if ($name !== '') $rawSelected[] = $name;
  }
} else {
  // default: preselect up to 5 nearest-to-expiry unique items
  foreach ($items as $i => $row) {
    if ($i < 5 && !empty($row['name'])) $rawSelected[] = $row['name'];
  }
}

/* Clean + dedupe for API query */
$selected = [];
foreach ($rawSelected as $n) {
  $c = clean_ingredient($n);
  if ($c !== '') $selected[] = $c;
}
$selected = array_values(array_unique($selected));

$MAX_ING = 5;
$selectedLimited = array_slice($selected, 0, $MAX_ING);

/* Filters */
$number  = isset($_GET['n']) ? max(1, min(12, (int)$_GET['n'])) : 8;
$diet    = isset($_GET['diet']) ? trim((string)$_GET['diet']) : '';
$tol     = isset($_GET['intolerances']) && is_array($_GET['intolerances'])
           ? array_values(array_unique(array_map('strval', $_GET['intolerances']))) : [];
$maxTime = isset($_GET['maxReadyTime']) && $_GET['maxReadyTime'] !== '' ? max(1, (int)$_GET['maxReadyTime']) : null;

$errors   = [];
$results  = [];
$noteUsed = [];

/* ===== Perform search ===== */
if (!empty($_GET['go'])) {
  if (SPOONACULAR_KEY === '' || SPOONACULAR_KEY === 'YOUR_REAL_API_KEY_HERE') {
    $errors[] = 'Please set your Spoonacular API Key at the top of recipes.php.';
  } else {
    if ($selectedLimited) {
      // when we have ingredients from pantry
      $noteUsed = $selectedLimited;

      if (count($selectedLimited) >= 4) {
        $data = spoon_find_by_ingredients($selectedLimited, $number, 2, true);
        if (isset($data['_error'])) {
          $errors[] = $data['_error'];
        } else {
          foreach ($data as $row) {
            $id    = (int)($row['id'] ?? 0);
            $title = $row['title'] ?? 'Recipe';
            $image = $row['image'] ?? '';
            $info  = $id ? spoon_recipe_info($id) : [];
            $nut   = $id ? spoon_recipe_nutrition($id) : [];

            $results[] = [
              'id' => $id,
              'title' => $title,
              'image' => $image,
              'readyInMinutes' => $info['readyInMinutes'] ?? null,
              'servings' => $info['servings'] ?? 1,
              'sourceUrl' => $info['sourceUrl'] ?? null,
              'spoonUrl' => $id ? 'https://spoonacular.com/recipes/'.slug($title).'-'.$id : null,
              'extendedIngredients' => $info['extendedIngredients'] ?? [],
              'calories_total' => isset($nut['calories_total']) ? (float)$nut['calories_total'] : null,
              'protein_total'  => isset($nut['protein_total'])  ? (float)$nut['protein_total']  : null,
              'carbs_total'    => isset($nut['carbs_total'])    ? (float)$nut['carbs_total']    : null,
              'fat_total'      => isset($nut['fat_total'])      ? (float)$nut['fat_total']      : null,
            ];
          }
        }
      } else {
        // use complexSearch + nutrition widget
        $data = spoon_complex_search($selectedLimited, $number, $diet ?: null, $tol, $maxTime);
        if (isset($data['_error'])) {
          $errors[] = $data['_error'];
        } else {
          $results = $data['results'] ?? [];
          foreach ($results as $k => $r) {
            $id    = (int)($r['id'] ?? 0);
            $title = $r['title'] ?? 'recipe';
            $results[$k]['spoonUrl'] = $id ? 'https://spoonacular.com/recipes/'.slug($title).'-'.$id : null;

            if ($id) {
              $nut = spoon_recipe_nutrition($id);
              $results[$k]['calories_total'] = isset($nut['calories_total']) ? (float)$nut['calories_total'] : null;
              $results[$k]['protein_total']  = isset($nut['protein_total'])  ? (float)$nut['protein_total']  : null;
              $results[$k]['carbs_total']    = isset($nut['carbs_total'])    ? (float)$nut['carbs_total']    : null;
              $results[$k]['fat_total']      = isset($nut['fat_total'])      ? (float)$nut['fat_total']      : null;
            }
          }
        }
      }
    } else {
      // no ingredients selected at all: fallback suggestions
      $noteUsed = ['egg','milk','bread'];
      $data = spoon_complex_search($noteUsed, $number, $diet ?: null, $tol, $maxTime);
      if (isset($data['_error'])) {
        $errors[] = $data['_error'];
      } else {
        $results = $data['results'] ?? [];
        foreach ($results as $k => $r) {
          $id    = (int)($r['id'] ?? 0);
          $title = $r['title'] ?? 'recipe';
          $results[$k]['spoonUrl'] = $id ? 'https://spoonacular.com/recipes/'.slug($title).'-'.$id : null;
          if ($id) {
            $nut = spoon_recipe_nutrition($id);
            $results[$k]['calories_total'] = isset($nut['calories_total']) ? (float)$nut['calories_total'] : null;
            $results[$k]['protein_total']  = isset($nut['protein_total'])  ? (float)$nut['protein_total']  : null;
            $results[$k]['carbs_total']    = isset($nut['carbs_total'])    ? (float)$nut['carbs_total']    : null;
            $results[$k]['fat_total']      = isset($nut['fat_total'])      ? (float)$nut['fat_total']      : null;
          }
        }
      }
    }
  }
}

/* ===== UI lists ===== */
$diets = ['', 'vegetarian', 'vegan', 'ketogenic', 'paleo', 'primal', 'pescetarian', 'low FODMAP', 'whole30'];
$intoleranceOptions = ['dairy','egg','gluten','grain','peanut','seafood','sesame','shellfish','soy','sulfite','tree nut','wheat'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Recipes — Smart Nutrition</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --bg:#ffffff;
      --text:#1a1a1a;
      --muted:#555;
      --accent:#ff7a00;
      --accent2:#ff9a33;
      --panel:#f8f8f8;
      --border:#e1e1e1;
      --radius:12px;
      --shadow:0 4px 18px rgba(0,0,0,.06);
    }
    *{box-sizing:border-box;}
    body{
      margin:0;
      font-family:"Inter",system-ui,sans-serif;
      background:var(--bg);
      color:var(--text);
      min-height:100vh;
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
    .brand{
      display:flex;
      align-items:center;
      gap:.6rem;
      font-weight:900;
      color:var(--accent);
    }
    .brand img{
      width:36px;
      height:36px;
    }
    .brand a{
      color:var(--accent);
      text-decoration:none;
      font-weight:800;
      font-size:.9rem;
    }
    nav a{
      margin-left:.9rem;
      text-decoration:none;
      color:var(--text);
      font-weight:700;
      font-size:.92rem;
    }
    nav a:hover{color:var(--accent);}
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
    h1{
      margin:0 0 1rem;
      font-size:1.8rem;
      font-weight:900;
      color:#111827;
    }
    .grid{
      display:grid;
      grid-template-columns: minmax(260px,340px) 1fr;
      gap:1.2rem;
      align-items:flex-start;
    }
    @media(max-width:900px){
      .grid{grid-template-columns:1fr;}
    }
    form.find{
      background:#fff;
      border-radius:var(--radius);
      border:1px solid var(--border);
      padding:1rem;
    }
    label{
      display:block;
      font-size:.86rem;
      color:var(--muted);
      font-weight:600;
    }
    .choices{
      display:flex;
      flex-wrap:wrap;
      gap:.4rem;
      margin-top:.45rem;
      max-height:190px;
      overflow-y:auto;
    }
    .chip{
      display:inline-flex;
      align-items:center;
      gap:.3rem;
      padding:.3rem .7rem;
      border-radius:999px;
      border:1px solid var(--border);
      background:#fff;
      font-size:.83rem;
      color:#111827;
    }
    .chip input{margin:0;}
    select,input[type="number"]{
      width:100%;
      margin-top:.25rem;
      padding:.55rem .6rem;
      border-radius:.6rem;
      border:1px solid var(--border);
      background:#fff;
      font-size:.9rem;
      color:#111827;
    }
    .intols{
      display:grid;
      grid-template-columns:repeat(3,minmax(0,1fr));
      gap:.25rem .5rem;
      margin-top:.25rem;
      font-size:.8rem;
      color:var(--muted);
    }
    .intols label{
      display:flex;
      align-items:center;
      gap:.25rem;
      font-weight:500;
    }
    .actions{
      margin-top:.8rem;
      display:flex;
      gap:.5rem;
      flex-wrap:wrap;
    }
    .btn{
      border:0;
      padding:.6rem 1.1rem;
      border-radius:.7rem;
      font-weight:800;
      cursor:pointer;
      font-size:.9rem;
    }
    .btn-primary{
      background:linear-gradient(135deg,var(--accent),var(--accent2));
      color:#fff;
      box-shadow:var(--shadow);
    }
    .btn-muted{
      background:#fff;
      color:var(--muted);
      border:1px solid var(--border);
    }
    .small{
      font-size:.8rem;
      color:var(--muted);
    }
    .recipes{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
      gap:1rem;
      margin-top:.4rem;
    }
    .card-r{
      background:#fff;
      border-radius:var(--radius);
      border:1px solid var(--border);
      box-shadow:var(--shadow);
      overflow:hidden;
      display:flex;
      flex-direction:column;
    }
    .thumb{
      width:100%;
      height:170px;
      object-fit:cover;
      background:#f3f4f6;
    }
    .pad{
      padding:.8rem .9rem 1rem;
      display:flex;
      flex-direction:column;
      gap:.25rem;
      flex:1;
    }
    .title{
      margin:0;
      font-size:1rem;
      font-weight:800;
      color:#111827;
    }
    .meta{
      display:flex;
      gap:.35rem;
      flex-wrap:wrap;
      margin:.15rem 0 .2rem;
    }
    .pill{
      padding:.15rem .55rem;
      border-radius:999px;
      border:1px solid var(--border);
      font-size:.75rem;
      color:#6b7280;
    }
    .go{
      margin-top:.4rem;
      display:flex;
      gap:.4rem;
      flex-wrap:wrap;
      align-items:center;
    }
    .go label.small{
      margin-right:.1rem;
      color:#6b7280;
    }
    .go input[type="number"],
    .go select{
      width:auto;
      padding:.35rem .5rem;
      font-size:.78rem;
    }
    .go button{
      padding:.4rem .7rem;
      border-radius:.6rem;
      border:0;
      background:linear-gradient(135deg,var(--accent),var(--accent2));
      color:#fff;
      font-size:.78rem;
      font-weight:800;
      cursor:pointer;
      box-shadow:var(--shadow);
    }
    .go a.linkbtn,
    .go a.alt{
      padding:.4rem .7rem;
      border-radius:.6rem;
      text-decoration:none;
      font-size:.75rem;
      font-weight:700;
      color:#fff;
      background:#111827;
    }
    .go a.alt{
      background:#2563eb;
    }
  </style>
</head>
<body>
<header>
  <div class="brand">
    <img src="assets/img/smart_nutrition_logo.png" alt="Smart Nutrition">
    <span>Smart Nutrition</span>
    <a href="Index.php">← Dashboard</a>
  </div>
  <nav>
    <a href="Index.php">Home</a>
    <a href="food_items.php">Pantry</a>
    <a href="meallogs.php">Meal Logs</a>
    <a href="recipes.php" style="color:var(--accent);">Recipes</a>
    <a href="profile.php">Profile</a>
  </nav>
</header>

<div class="container">
  <div class="card">
    <h1>Recipe Ideas</h1>
    <div class="grid">

      <!-- LEFT: ingredient & filters -->
      <form class="find" method="get" action="recipes.php" novalidate>
        <input type="hidden" name="go" value="1">

        <label>Pick ingredients from your pantry</label>
        <div class="choices">
          <?php if (!$items): ?>
            <span class="small">No non-expired pantry items. Add some in Pantry.</span>
          <?php else: foreach ($items as $row):
            $checked = in_array($row['name'], $rawSelected, true) ? 'checked' : '';
          ?>
            <label class="chip">
              <input type="checkbox" name="ing[]" value="<?= e($row['name']) ?>" <?= $checked ?>>
              <?= e($row['name']) ?>
            </label>
          <?php endforeach; endif; ?>
        </div>

        <div class="row" style="margin-top:.7rem;display:grid;grid-template-columns:repeat(2,1fr);gap:.6rem;">
          <div>
            <label for="diet">Diet</label>
            <select id="diet" name="diet">
              <?php foreach ($diets as $d): ?>
                <option value="<?= e($d) ?>" <?= $diet===$d?'selected':'' ?>>
                  <?= $d===''?'(none)':ucfirst($d) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="maxReadyTime">Max ready time (minutes)</label>
            <input id="maxReadyTime" type="number" name="maxReadyTime"
                   min="1" step="1"
                   value="<?= e((string)($maxTime ?? '')) ?>"
                   placeholder="e.g., 30">
          </div>
        </div>

        <div style="margin-top:.6rem;">
          <label>Intolerances</label>
          <div class="intols">
            <?php foreach ($intoleranceOptions as $opt): ?>
              <label>
                <input type="checkbox" name="intolerances[]" value="<?= e($opt) ?>"
                  <?= in_array($opt, $tol, true) ? 'checked' : '' ?>>
                <?= ucfirst($opt) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div style="margin-top:.6rem;max-width:180px;">
          <label for="n">Number of recipes</label>
          <input id="n" type="number" name="n" min="1" max="12" value="<?= (int)$number ?>">
        </div>

        <div class="actions">
          <button class="btn btn-primary" type="submit">Find Recipes</button>
          <a class="btn btn-muted" href="recipes.php">Reset</a>
        </div>

        <p class="small" style="margin-top:.5rem;">
          We automatically ignore expired items and duplicate ingredients.
        </p>
      </form>

      <!-- RIGHT: results -->
      <div>
        <?php if ($errors): ?>
          <div class="small" style="background:#fef2f2;border:1px solid #fecaca;padding:.7rem 1rem;border-radius:.6rem;margin-bottom:.6rem;color:#991b1b;">
            <ul style="margin:.2rem 0 0 1.1rem;padding:0;">
              <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if (!empty($_GET['go'])): ?>
          <p class="small" style="margin:.2rem 0 .5rem;">
            Used ingredients:
            <em><?= $selectedLimited ? e(implode(', ', $selectedLimited)) : 'None (showing generic suggestions)' ?></em>
          </p>
        <?php endif; ?>

        <?php if ($results): ?>
          <div class="recipes">
            <?php foreach ($results as $r):
              $id    = (int)($r['id'] ?? 0);
              $title = $r['title'] ?? 'Recipe';
              $image = $r['image'] ?? '';
              $ready = $r['readyInMinutes'] ?? null;
              $serv  = (int)($r['servings'] ?? 1);
              $src   = $r['sourceUrl'] ?? null;
              $spoon = $r['spoonUrl'] ?? null;

              $calT = $r['calories_total'] ?? null;
              $proT = $r['protein_total']  ?? null;
              $carT = $r['carbs_total']    ?? null;
              $fatT = $r['fat_total']      ?? null;

              $ings = [];
              if (!empty($r['extendedIngredients'])) {
                foreach ($r['extendedIngredients'] as $ing) {
                  if (!empty($ing['name'])) $ings[] = $ing['name'];
                }
              }

              $perCal = ($calT !== null && $serv) ? round($calT / $serv) : null;
            ?>
            <article class="card-r">
              <img class="thumb" src="<?= e($image) ?>" alt="" loading="lazy">
              <div class="pad">
                <h3 class="title"><?= e($title) ?></h3>
                <div class="meta">
                  <?php if ($ready): ?><span class="pill">Ready in <?= (int)$ready ?>m</span><?php endif; ?>
                  <?php if ($serv): ?><span class="pill"><?= $serv ?> servings</span><?php endif; ?>
                  <?php if ($perCal !== null): ?><span class="pill"><?= (int)$perCal ?> kcal / serving</span><?php endif; ?>
                </div>
                <?php if ($ings): ?>
                  <div class="small"><strong>Ingredients:</strong> <?= e(implode(', ', $ings)) ?></div>
                <?php endif; ?>

                <form method="post" action="log_recipe.php" class="go">
                  <input type="hidden" name="recipe_id"      value="<?= $id ?>">
                  <input type="hidden" name="recipe_title"   value="<?= e($title) ?>">
                  <input type="hidden" name="servings"       value="<?= $serv ?>">
                  <input type="hidden" name="calories_total" value="<?= $calT !== null ? (float)$calT : '' ?>">
                  <input type="hidden" name="protein_total"  value="<?= $proT !== null ? (float)$proT : '' ?>">
                  <input type="hidden" name="carbs_total"    value="<?= $carT !== null ? (float)$carT : '' ?>">
                  <input type="hidden" name="fat_total"      value="<?= $fatT !== null ? (float)$fatT : '' ?>">

                  <label class="small">I ate</label>
                  <input name="eaten_servings" type="number" value="1" min="0.25" step="0.25">
                  <select name="meal_type">
                    <option value="breakfast">Breakfast</option>
                    <option value="lunch">Lunch</option>
                    <option value="dinner" selected>Dinner</option>
                    <option value="snack">Snack</option>
                  </select>
                  <button type="submit">Log this</button>
                  <?php if ($src): ?>
                    <a class="linkbtn" target="_blank" rel="noopener noreferrer" href="<?= e($src) ?>">Source</a>
                  <?php endif; ?>
                  <?php if ($spoon): ?>
                    <a class="alt" target="_blank" rel="noopener noreferrer" href="<?= e($spoon) ?>">Spoonacular</a>
                  <?php endif; ?>
                </form>
              </div>
            </article>
            <?php endforeach; ?>
          </div>
        <?php elseif (empty($errors) && !empty($_GET['go'])): ?>
          <div class="small" style="background:#eff6ff;border:1px solid #bfdbfe;padding:.8rem 1rem;border-radius:.6rem;">
            <strong>No recipes found for the selected items.</strong>
            <div>Try selecting fewer or different ingredients, or clear filters to see more ideas.</div>
          </div>
        <?php else: ?>
          <p class="small">Pick some non-expired pantry items, adjust filters, and click <strong>Find Recipes</strong> to get smart suggestions.</p>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>
</body>
</html>
