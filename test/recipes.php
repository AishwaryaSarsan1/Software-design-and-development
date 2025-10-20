<?php
// C:\xampp\htdocs\smartnutrition\recipes.php
declare(strict_types=1);
session_start();

/* Require login */
if (empty($_SESSION['user_id'])) { header('Location: signin.php'); exit; }

/* ===== DB CONFIG (XAMPP defaults) ===== */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'smart_nutrition';
const DB_USER = 'root';
const DB_PASS = '';

/* ===== SPOONACULAR KEY (NOT the hash/pin) =====
   Get it from the Food API dashboard, not the Meal Planner page. */
const SPOONACULAR_KEY = 'e03dccce030c4e36a102c9ce21c3cece';

/* ===== Helpers ===== */
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

/* Clean pantry names: lowercase, strip punctuation/units, naive singular */
function clean_ingredient(string $name): string {
  $s = strtolower(trim($name));
  $s = preg_replace('/\((.*?)\)/', '', $s);     // remove (notes)
  $s = preg_replace('/[^a-z\s]/', ' ', $s);     // remove digits, %, -, etc.
  $s = preg_replace('/\s+/', ' ', $s);
  $s = trim($s);
  if ($s === '') return '';
  if (substr($s, -1) === 's' && !in_array($s, ['lettuce','asparagus','hummus'], true)) {
    $s = rtrim($s, 's'); // naive singular
  }
  return $s;
}

/* ===== Spoonacular wrappers ===== */
function spoon_complex_search(array $ingredients, int $number, ?string $diet, array $intolerances, ?int $maxReadyTime): array {
  $url = 'https://api.spoonacular.com/recipes/complexSearch';
  $query = [
    'apiKey' => SPOONACULAR_KEY,
    'includeIngredients' => implode(',', $ingredients),
    'number' => $number,
    'sort' => 'popularity',
    'addRecipeInformation' => 'true', // includes sourceUrl, readyInMinutes, etc.
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
    'ranking'      => $ranking, // 1=max used, 2=min missing
    'ignorePantry' => $ignorePantry ? 'true' : 'false',
  ];
  return http_get_json($url, $query);
}

function spoon_recipe_info(int $id): array {
  $url = "https://api.spoonacular.com/recipes/$id/information";
  $query = ['apiKey' => SPOONACULAR_KEY, 'includeNutrition' => 'false'];
  return http_get_json($url, $query);
}

/* ===== Load user's pantry ===== */
$userId = (int)$_SESSION['user_id'];
$pdo = db();

/* Ensure table exists (no-op if already) */
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

$items = [];
$stmt = $pdo->prepare("
  SELECT id, name, COALESCE(expiry_date,'9999-12-31') AS sort_exp
  FROM food_items
  WHERE user_id = ?
  ORDER BY (expiry_date IS NULL), expiry_date ASC, created_at DESC
");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

/* ===== UI State ===== */
$rawSelected = [];
if (isset($_GET['ing']) && is_array($_GET['ing'])) {
  foreach ($_GET['ing'] as $name) {
    $name = trim((string)$name);
    if ($name !== '') $rawSelected[] = $name;
  }
} else {
  // Preselect up to 5 closest-to-expiry items when page first loads
  foreach ($items as $i => $row) {
    if ($i < 5 && !empty($row['name'])) $rawSelected[] = $row['name'];
  }
}

/* Clean & dedupe ingredients */
$selected = [];
foreach ($rawSelected as $n) {
  $c = clean_ingredient($n);
  if ($c !== '') $selected[] = $c;
}
$selected = array_values(array_unique($selected));

/* Limit ingredients to avoid over-filtering */
$MAX_ING = 5;
$selectedLimited = array_slice($selected, 0, $MAX_ING);

$number  = isset($_GET['n']) ? max(1, min(12, (int)$_GET['n'])) : 8;
$diet    = isset($_GET['diet']) ? trim((string)$_GET['diet']) : '';
$tol     = isset($_GET['intolerances']) && is_array($_GET['intolerances'])
           ? array_values(array_unique(array_map('strval', $_GET['intolerances']))) : [];
$maxTime = isset($_GET['maxReadyTime']) && $_GET['maxReadyTime'] !== '' ? max(1, (int)$_GET['maxReadyTime']) : null;

$errors   = [];
$results  = [];
$noteUsed = []; // to show what we actually sent to Spoonacular

/* ===== Perform search ===== */
if (!empty($_GET['go'])) {
  if (SPOONACULAR_KEY === 'YOUR_REAL_API_KEY_HERE' || SPOONACULAR_KEY === '') {
    $errors[] = 'Please set your Spoonacular API Key at the top of recipes.php (not the hash).';
  } else {
    if ($selectedLimited) {
      if (count($selectedLimited) >= 4) {
        // Be more forgiving when many items selected
        $noteUsed = $selectedLimited;
        $data = spoon_find_by_ingredients($selectedLimited, $number, 2, true);
        if (isset($data['_error'])) {
          $errors[] = $data['_error'];
        } else {
          // normalize to complexSearch-like records
          foreach ($data as $row) {
            $id    = (int)($row['id'] ?? 0);
            $title = $row['title'] ?? 'Recipe';
            $image = $row['image'] ?? '';
            $info  = $id ? spoon_recipe_info($id) : [];
            $results[] = [
              'id' => $id,
              'title' => $title,
              'image' => $image,
              'readyInMinutes' => $info['readyInMinutes'] ?? null,
              'servings' => $info['servings'] ?? null,
              'sourceUrl' => $info['sourceUrl'] ?? null,
              'spoonUrl' => $id ? 'https://spoonacular.com/recipes/'.slug($title).'-'.$id : null,
              'extendedIngredients' => $info['extendedIngredients'] ?? [],
            ];
          }
        }
      } else {
        // Richer info in one call when few items selected
        $noteUsed = $selectedLimited;
        $data = spoon_complex_search($selectedLimited, $number, $diet ?: null, $tol, $maxTime);
        if (isset($data['_error'])) {
          $errors[] = $data['_error'];
        } else {
          $results = $data['results'] ?? [];
          foreach ($results as $k => $r) {
            $id = (int)($r['id'] ?? 0);
            $title = $r['title'] ?? 'recipe';
            $results[$k]['spoonUrl'] = $id ? 'https://spoonacular.com/recipes/'.slug($title).'-'.$id : null;
          }
        }
      }
    } else {
      // Nothing selected: fall back so page isn't blank
      $noteUsed = ['egg','milk','bread'];
      $data = spoon_complex_search($noteUsed, $number, $diet ?: null, $tol, $maxTime);
      if (isset($data['_error'])) {
        $errors[] = $data['_error'];
      } else {
        $results = $data['results'] ?? [];
        foreach ($results as $k => $r) {
          $id = (int)($r['id'] ?? 0);
          $title = $r['title'] ?? 'recipe';
          $results[$k]['spoonUrl'] = $id ? 'https://spoonacular.com/recipes/'.slug($title).'-'.$id : null;
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
    :root{--bg:#0b1220;--card:#121a2d;--panel:#0f1627;--text:#e6ecff;--muted:#9bb0ff;--border:rgba(255,255,255,.08);}
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,Segoe UI,Arial,sans-serif;min-height:100vh}
    header{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.2rem;background:rgba(255,255,255,.04);border-bottom:1px solid var(--border)}
    .brand a{color:var(--text);text-decoration:none;font-weight:900}
    .container{max-width:1200px;margin:2rem auto;padding:0 1rem}
    .card{background:var(--card);border-radius:16px;padding:1.1rem;box-shadow:0 16px 40px rgba(0,0,0,.35);border:1px solid var(--border)}
    h1{margin:.2rem 0 1rem}
    .grid{display:grid;gap:1rem;grid-template-columns:2fr 3fr}
    @media (max-width:980px){ .grid{grid-template-columns:1fr} }
    form.find{ background:var(--panel); border:1px solid #1b2440; padding:1rem; border-radius:12px }
    .row{display:grid;gap:.8rem;grid-template-columns:repeat(2,1fr)}
    @media (max-width:720px){ .row{grid-template-columns:1fr} }
    .choices{ display:flex; flex-wrap:wrap; gap:.5rem; margin-top:.6rem; max-height:180px; overflow:auto; padding-right:.4rem }
    .chip{ display:inline-flex; align-items:center; gap:.4rem; padding:.4rem .6rem; border-radius:999px; border:1px solid var(--border); background:#0b1526 }
    .chip input{ margin:0 }
    label{display:block;color:var(--muted);font-size:.95rem}
    input[type="number"],select{width:100%;margin-top:.35rem;padding:.6rem .7rem;border-radius:.6rem;border:1px solid #223;background:#0b1526;color:var(--text)}
    .intols{display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem;margin-top:.35rem}
    .intols label{display:flex;align-items:center;gap:.4rem}
    .actions{margin-top:.8rem;display:flex;gap:.6rem;flex-wrap:wrap}
    .btn{border:0;padding:.7rem 1.05rem;border-radius:.7rem;font-weight:900;cursor:pointer}
    .btn-primary{background:linear-gradient(135deg,#6aa1ff,#22d3ee);color:#fff}
    .btn-muted{background:transparent;color:var(--muted);border:1px solid var(--border);text-decoration:none;display:inline-block}
    .recipes{display:grid;gap:1rem;grid-template-columns:repeat(3,1fr);margin-top:1rem}
    @media (max-width:1100px){ .recipes{grid-template-columns:repeat(2,1fr)} }
    @media (max-width:720px){ .recipes{grid-template-columns:1fr} }
    .card-r{background:var(--panel);border:1px solid #1b2440;border-radius:14px;overflow:hidden;display:flex;flex-direction:column}
    .thumb{width:100%;height:180px;object-fit:cover;background:#0b1526}
    .pad{padding:.9rem}
    .meta{display:flex;gap:.5rem;flex-wrap:wrap;margin:.4rem 0 .6rem}
    .pill{display:inline-block;padding:.25rem .5rem;border-radius:999px;border:1px solid var(--border);font-size:.85rem}
    .small{font-size:.88rem;color:#9fb2ffb3}
    .title{font-weight:900;margin:0 0 .3rem}
    .go{margin-top:auto;display:flex;gap:.6rem}
    .go a{flex:1;text-align:center;text-decoration:none;color:white;background:linear-gradient(135deg,#6aa1ff,#22d3ee);padding:.6rem;border-radius:.6rem;font-weight:900}
    .alt{background:linear-gradient(135deg,#7c3aed,#22d3ee)}
  </style>
</head>
<body>
  <header>
    <div class="brand"><a href="Index.php">← Dashboard</a></div>
    <div>
      <a href="food_items.php" class="btn btn-muted" style="padding:.5rem .8rem;border-radius:.6rem;">Food Items</a>
      <a href="profile.php" class="btn btn-muted" style="padding:.5rem .8rem;border-radius:.6rem;">Profile</a>
    </div>
  </header>

  <div class="container">
    <div class="card">
      <h1>Recipe Ideas</h1>

      <div class="grid">
        <!-- ===== Left: ingredient & filter form ===== -->
        <form class="find" method="get" action="recipes.php" novalidate>
          <input type="hidden" name="go" value="1">

          <label>Pick ingredients from your pantry</label>
          <div class="choices">
            <?php if (!$items): ?>
              <span class="small">No pantry items yet. Add some in Food Items.</span>
            <?php else: foreach ($items as $row):
              $checked = in_array($row['name'], $rawSelected, true) ? 'checked' : '';
            ?>
              <label class="chip">
                <input type="checkbox" name="ing[]" value="<?= e($row['name']) ?>" <?= $checked ?>> <?= e($row['name']) ?>
              </label>
            <?php endforeach; endif; ?>
          </div>

          <div class="row" style="margin-top:.8rem">
            <div>
              <label for="diet">Diet</label>
              <select id="diet" name="diet">
                <?php foreach ($diets as $d): ?>
                  <option value="<?= e($d) ?>" <?= $diet===$d?'selected':'' ?>><?= $d===''?'(none)':ucfirst($d) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="maxReadyTime">Max ready time (minutes)</label>
              <input id="maxReadyTime" type="number" name="maxReadyTime" min="1" step="1" value="<?= e((string)($maxTime ?? '')) ?>" placeholder="e.g., 30">
            </div>
          </div>

          <div style="margin-top:.6rem">
            <label>Intolerances</label>
            <div class="intols">
              <?php foreach ($intoleranceOptions as $opt): ?>
                <label>
                  <input type="checkbox" name="intolerances[]" value="<?= e($opt) ?>" <?= in_array($opt, $tol, true)?'checked':'' ?>>
                  <?= ucfirst($opt) ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="row" style="margin-top:.8rem">
            <div>
              <label for="n">Number of recipes</label>
              <input id="n" type="number" name="n" min="1" max="12" value="<?= (int)$number ?>">
            </div>
          </div>

          <div class="actions">
            <button class="btn btn-primary" type="submit">Find Recipes</button>
            <a class="btn btn-muted" href="recipes.php">Reset</a>
          </div>

          <p class="small" style="margin-top:.6rem">
            Tip: If a source site is slow or down, use the Spoonacular link on each card.
          </p>
        </form>

        <!-- ===== Right: results ===== -->
        <div>
          <?php if ($errors): ?>
            <div class="small" style="background:rgba(255,106,106,.15);border:1px solid rgba(255,106,106,.35);padding:.7rem 1rem;border-radius:.6rem;margin-bottom:.8rem">
              <ul style="margin:.3rem 0 0 1rem">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if (!empty($_GET['go'])): ?>
            <p class="small" style="margin:.4rem 0">
              Searched with: <em><?= e(implode(', ', $noteUsed)) ?: '— none —' ?></em>
            </p>
          <?php endif; ?>

          <?php if ($results): ?>
            <div class="recipes">
              <?php foreach ($results as $r):
                $title = $r['title'] ?? 'Recipe';
                $image = $r['image'] ?? '';
                $ready = $r['readyInMinutes'] ?? null;
                $serv  = $r['servings'] ?? null;
                $src   = $r['sourceUrl'] ?? null;
                $spoon = $r['spoonUrl'] ?? null;
                $ings  = [];
                if (!empty($r['extendedIngredients'])) {
                  foreach ($r['extendedIngredients'] as $ing) {
                    if (!empty($ing['name'])) $ings[] = $ing['name'];
                  }
                }
              ?>
                <article class="card-r">
                  <img class="thumb" src="<?= e($image) ?>" alt="" loading="lazy">
                  <div class="pad">
                    <h3 class="title"><?= e($title) ?></h3>
                    <div class="meta">
                      <?php if ($ready): ?><span class="pill">Ready in <?= (int)$ready ?>m</span><?php endif; ?>
                      <?php if ($serv):  ?><span class="pill"><?= (int)$serv ?> servings</span><?php endif; ?>
                    </div>
                    <?php if ($ings): ?>
                      <div class="small"><strong>Ingredients:</strong> <?= e(implode(', ', $ings)) ?></div>
                    <?php endif; ?>
                    <div class="go">
                      <?php if ($src):   ?><a target="_blank" rel="noopener noreferrer" href="<?= e($src) ?>">Open Source</a><?php endif; ?>
                      <?php if ($spoon): ?><a class="alt" target="_blank" rel="noopener noreferrer" href="<?= e($spoon) ?>">View on Spoonacular</a><?php endif; ?>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php elseif (empty($errors) && !empty($_GET['go'])): ?>
            <div class="small" style="background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.35);padding:.8rem 1rem;border-radius:.6rem">
              <strong>No recipes found.</strong>
              <div>Try selecting fewer/common items (e.g., <code>egg</code>, <code>milk</code>, <code>apple</code>) or clear filters.</div>
            </div>
          <?php else: ?>
            <p class="small">Pick ingredients + filters and click <strong>Find Recipes</strong> to see ideas.</p>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>
</body>
</html>
