<?php
// C:\xampp\htdocs\smartnutrition\Home.php
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Smart Nutrition — Eat Better. Waste Less.</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Smart Nutrition helps you manage your pantry, track calories, and get recipe suggestions based on items you already have.">
  <link rel="stylesheet" href="assets/css/styles.css"><!-- optional if you have it -->

  <style>
    :root { --bg:#0b1220; --card:#121a2d; --text:#e6ecff; --muted:#9bb0ff; --accent:#6aa1ff; --danger:#ff6a6a;
            --ring1: rgba(106,161,255,.18); --ring2: rgba(34,197,94,.12); --ring3: rgba(34,211,238,.12);
            --border: rgba(255,255,255,.08); --shadow: 0 30px 70px rgba(0,0,0,.45); --radius:24px; }
    *,*::before,*::after{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0; min-height:100vh; color:var(--text);
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, Helvetica, sans-serif;
      background: var(--bg);
      background-image:
        radial-gradient(900px 600px at 85% -10%, var(--ring1), transparent 60%),
        radial-gradient(800px 700px at -10% 100%, var(--ring2), transparent 60%),
        radial-gradient(900px 700px at 40% 120%, var(--ring3), transparent 60%),
        linear-gradient(180deg, rgba(0,0,0,.15), rgba(0,0,0,.2));
      background-attachment: fixed;
    }
    @keyframes floaty{0%{transform:translate3d(0,0,0) scale(1)}50%{transform:translate3d(0,-6px,0) scale(1.01)}100%{transform:translate3d(0,0,0) scale(1)}}
    @media (prefers-reduced-motion: reduce){*{animation:none !important; transition:none !important}}

    .wrap{min-height:100vh; display:grid; place-items:center; padding: clamp(1.25rem, 3vw, 2.5rem);}
    .hero{
      width:min(1100px, 95vw); display:grid; gap:2.2rem;
      grid-template-columns: minmax(280px, 380px) 1fr; align-items:center;
      background: linear-gradient(180deg, rgba(18,26,45,.92), rgba(18,26,45,.85));
      border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow);
      padding: clamp(1.25rem, 3.5vw, 3rem); position: relative; overflow: hidden;
      backdrop-filter: blur(10px); animation: floaty 10s ease-in-out infinite;
    }
    @media (max-width:860px){ .hero{ grid-template-columns:1fr; text-align:center } }
    .orb{ position:absolute; width:280px; height:280px; border-radius:999px; filter:blur(40px); opacity:.16; pointer-events:none }
    .orb.blue{ background:#60a5fa; right:-80px; top:-80px } .orb.cyan{ background:#22d3ee; left:-80px; bottom:-80px }

    .logo-wrap{ display:grid; place-items:center; padding:8px }
    .brand-lockup{ text-align:center; margin-top:.5rem }
    .brand-title{ font-size: clamp(1.3rem, 2.4vw, 1.6rem); font-weight:900; margin:.25rem 0 0; color:var(--text) }
    .brand-tag{ margin:.25rem 0 0; color:var(--muted); font-weight:600; opacity:.9; font-size:.95rem }
    .logo-ring{ filter: drop-shadow(0 10px 28px rgba(34,211,238,.18)); }

    h1.title{ font-size:clamp(1.8rem, 3.6vw, 2.6rem); line-height:1.15; margin:.25rem 0 .35rem; font-weight:1000 }
    p.lead{ color:var(--muted); font-size:clamp(1rem,1.6vw,1.1rem); line-height:1.6; margin:.25rem 0 1.25rem }
    .badges{ display:flex; gap:.6rem; flex-wrap:wrap; margin:.5rem 0 1.25rem }
    .badge{ display:inline-flex; align-items:center; gap:.45rem; padding:.45rem .7rem; border-radius:999px;
            background: rgba(255,255,255,.08); border:1px solid var(--border); font-weight:700; font-size:.85rem; color:#cde2ff }
    .dot{ width:8px; height:8px; border-radius:999px; display:inline-block; background:currentColor; opacity:.9 }
    .actions{ display:flex; gap:.9rem; flex-wrap:wrap; margin-top:.6rem }
    .btn{ appearance:none; border:0; cursor:pointer; padding:.95rem 1.35rem; border-radius:14px; font-weight:900; letter-spacing:.25px;
          text-decoration:none; display:inline-flex; align-items:center; justify-content:center; transition: transform .08s ease, opacity .15s ease, box-shadow .25s ease; white-space:nowrap }
    .btn:active{ transform: translateY(1px) }
    .btn-primary{ color:white; background:linear-gradient(135deg, #6aa1ff, #22d3ee 60%, #7c3aed); box-shadow:0 14px 28px rgba(34,211,238,.25) }
    .btn-outline{ color:#e6ecff; background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03)); border:1px solid rgba(255,255,255,.08) }
    .meta{ margin-top:1rem; color:#9fb2ffb3; font-size:.9rem }
    .footnote{ margin-top: clamp(1rem, 3vw, 2rem); text-align:center; opacity:.75; font-size:.9rem }
  </style>
</head>
<body>
  <div class="wrap" role="main">
    <section class="hero" aria-label="Smart Nutrition Home">
      <div class="orb blue"></div>
      <div class="orb cyan"></div>

      <div class="logo-wrap" aria-hidden="false">
  <img
    src="assets/img/smart_nutrition_logo.png"
    alt="Smart Nutrition Logo"
    width="220" height="220"
    style="display:block; max-width:220px; height:auto; border-radius:50%; box-shadow:0 8px 24px rgba(0,0,0,.25);" />
</div>


      <article>
        <h1 class="title">Eat Better. Waste Less.</h1>
        <p class="lead">
          Manage your pantry, track calories, and get recipe suggestions from what you already have — all in one place.
          Designed for students, families, fitness enthusiasts, and small kitchens.
        </p>

        <div class="badges" aria-label="Key benefits">
          <span class="badge"><span class="dot" style="color:#22c55e"></span> Reduce Waste</span>
          <span class="badge"><span class="dot" style="color:#60a5fa"></span> Track Calories</span>
          <span class="badge"><span class="dot" style="color:#22d3ee"></span> Smart Recipes</span>
          <span class="badge"><span class="dot" style="color:#7c3aed"></span> Clean Dashboard</span>
        </div>

        <div class="actions" role="group" aria-label="Primary actions">
          <!-- Use relative links because Home.php and signup.php are in the same folder -->
          <a class="btn btn-primary" href="signin.php" aria-label="Sign in to Smart Nutrition">Sign In</a>
          <a class="btn btn-outline" href="signup.php" aria-label="Create a Smart Nutrition account">Sign Up</a>
        </div>

        <p class="meta">By continuing you agree to our Terms and acknowledge our Privacy Policy.</p>
      </article>
    </section>

    <p class="footnote">© <?= date('Y') ?> Smart Nutrition. All rights reserved.</p>
  </div>
</body>
</html>
