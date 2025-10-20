<?php
// C:\xampp\htdocs\smartnutrition\Index.php
declare(strict_types=1);
session_start();

// Require login
if (empty($_SESSION['user_id'])) {
  header('Location: signin.php');
  exit;
}

// CSRF for sign out
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$userName = $_SESSION['user_name'] ?? 'User';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Smart Nutrition — Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root { --bg:#0b1220; --card:#121a2d; --text:#e6ecff; --muted:#9bb0ff; --accent:#6aa1ff; --danger:#ff6a6a; }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,Segoe UI,Arial,sans-serif;min-height:100vh}
    header{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.2rem;background:rgba(255,255,255,.04);border-bottom:1px solid rgba(255,255,255,.06)}
    .brand{display:flex;align-items:center;gap:.6rem;font-weight:900}
    .brand svg{vertical-align:middle}
    .welcome{color:var(--muted);font-weight:600}

    .content{max-width:1100px;margin:2rem auto;padding:0 1rem}
    .card{background:var(--card);border-radius:16px;padding:1.2rem;box-shadow:0 16px 40px rgba(0,0,0,.35);border:1px solid rgba(255,255,255,.06)}
    .grid{display:grid;gap:1rem;grid-template-columns:1fr 1fr}
    @media (max-width:920px){ .grid{grid-template-columns:1fr} }

    /* Right action dock */
    .dock{
      position:fixed; right:18px; top:50%; transform:translateY(-50%);
      display:flex; flex-direction:column; gap:.6rem; z-index:10;
      background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.08);
      padding:.6rem; border-radius:16px; backdrop-filter: blur(8px);
      box-shadow:0 14px 30px rgba(0,0,0,.35);
    }
    .dock a, .dock button{
      display:flex; align-items:center; justify-content:center; gap:.5rem;
      padding:.7rem .9rem; border-radius:12px; border:1px solid rgba(255,255,255,.10);
      background:#0f1627; color:var(--text); text-decoration:none; font-weight:800; cursor:pointer;
      transition:opacity .15s ease, transform .08s ease;
      min-width:160px;
    }
    .dock a:hover, .dock button:hover{opacity:.92}
    .dock a:active, .dock button:active{transform:translateY(1px)}
    .danger{ background: linear-gradient(135deg, #ff6a6a, #f97316); border-color:rgba(255,106,106,.45) }

    .muted{color:var(--muted)}
  </style>
</head>
<body>
  <header>
    <div class="brand">
      <!-- tiny logo -->
      <div class="logo-wrap" aria-hidden="false">
  <img
    src="assets/img/smart_nutrition_logo.png"
    alt="Smart Nutrition Logo"
    width="80" height="80"
    style="display:block; max-width:220px; height:auto; border-radius:50%; box-shadow:0 8px 24px rgba(0,0,0,.25);" />
</div>
<span>Smart Nutrition And Food Management </span>
    </div>
    <div class="welcome">Welcome, <?= htmlspecialchars($userName) ?>!</div>
  </header>

  <div class="content">
    <div class="card">
      <h2>Dashboard</h2>
      <p class="muted">Use the actions on the right to manage your pantry, meals, recipes, and profile.</p>

      <!-- sample content area -->
      <div class="grid">
        <div class="card" style="background:#0f1627;border-color:#1b2440;">
          <h3>Quick Tips</h3>
          <ul>
            <li>Add your pantry items and set expiry dates.</li>
            <li>Log meals to track calories.</li>
            <li>See recipe ideas based on expiring items.</li>
          </ul>
        </div>
        <div class="card" style="background:#0f1627;border-color:#1b2440;">
          <h3>Status</h3>
          <p class="muted">No data yet — start by adding food items.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Right-side action dock -->
  <aside class="dock" aria-label="Actions">
    <a href="food_items.php">Food Items</a>
    <a href="meallogs.php">Meal Logs</a>
    <a href="recipes.php">Recipes</a>
    <a href="profile.php">Profile</a>

    <!-- Sign out (POST with CSRF) -->
    <form method="post" action="signout.php" style="margin:0">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
      <button class="danger" type="submit">Sign Out</button>
    </form>
  </aside>
</body>
</html>
