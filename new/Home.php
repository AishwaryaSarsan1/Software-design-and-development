<?php
// C:\xampp\htdocs\smartnutrition\Home.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Smart Nutrition | Eat Better, Waste Less</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
        --bg: #ffffff;
        --text: #1a1a1a;
        --muted: #555;
        --accent: #ff7a00;
        --accent-dark: #e56a00;
        --radius: 10px;
        --shadow: 0 8px 35px rgba(0,0,0,.08);
    }

    body {
        margin: 0;
        font-family: "Inter", system-ui, sans-serif;
        background: var(--bg);
        color: var(--text);
    }

    /* NAVBAR */
    .navbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 3rem;
        background: #fff;
        box-shadow: var(--shadow);
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .navbar img {
        width: 48px;
        height: 48px;
        border-radius: var(--radius);
    }

    .menu a {
        margin: 0 1rem;
        text-decoration: none;
        color: var(--text);
        font-weight: 600;
        transition: 0.2s;
    }
    .menu a:hover {
        color: var(--accent);
    }

    .btn-nav {
        border: 2px solid var(--accent);
        background: transparent;
        color: var(--accent-dark);
        font-weight: 700;
        padding: .6rem 1.2rem;
        border-radius: var(--radius);
        cursor: pointer;
        transition: 0.25s;
    }

    .btn-nav:hover {
        background: var(--accent);
        color: #fff;
    }

    .btn-nav-filled {
        background: var(--accent);
        border: 2px solid var(--accent);
        color: #fff;
    }

    .header-section {
        text-align: center;
        margin-top: 4rem;
        padding: 2rem;
    }

    h1.title {
        font-size: 3.2rem;
        font-weight: 900;
        margin-bottom: 0.8rem;
        color: #1a1a1a;
    }

    p.lead {
        font-size: 1.2rem;
        color: var(--muted);
        max-width: 600px;
        margin: auto;
    }

    .hero-buttons {
        margin-top: 2rem;
        display: flex;
        justify-content: center;
        gap: 1.5rem;
    }

    .btn {
        padding: 1rem 2rem;
        border-radius: var(--radius);
        text-decoration: none;
        font-weight: 700;
        cursor: pointer;
        transition: 0.25s;
    }

    .btn-primary {
        background: var(--accent);
        color: #fff;
        box-shadow: var(--shadow);
    }

    .btn-primary:hover {
        background: var(--accent-dark);
    }

    .btn-outline {
        border: 2px solid var(--accent);
        color: var(--accent-dark);
        background: #fff;
    }

    .btn-outline:hover {
        background: var(--accent);
        color: #fff;
    }

    footer {
        margin-top: 4rem;
        padding: 1rem;
        text-align: center;
        color: var(--muted);
        font-size: 0.9rem;
    }
</style>

</head>
<body>
<div class="navbar">
    <div class="logo">
        <img src="assets/img/smart_nutrition_logo.png" alt="Smart Nutrition">
        <span style="font-weight:800;font-size:1.2rem;margin-left:.5rem">Smart Nutrition</span>
    </div>

    <div class="menu">
        <a href="Index.php">Dashboard</a>
        <a href="food_items.php">Pantry</a>
        <a href="recipes.php">Recipes</a>
        <a href="meallogs.php">Meal Logs</a>
        <a href="profile.php">Profile</a>
    </div>

    <div>
        <a class="btn-nav" href="signin.php">Sign In</a>
        <a class="btn-nav btn-nav-filled" href="signup.php">Sign Up</a>
    </div>
</div>

<section class="header-section">
    <h1 class="title">Eat Better. Waste Less.</h1>
    <p class="lead">Smart Nutrition helps you track what’s in your pantry, 
        find recipes instantly, and maintain a healthy lifestyle — 
        inspired by how food apps make it simple and fun.</p>

    <div class="hero-buttons">
        <a href="signin.php" class="btn btn-primary">Get Started</a>
        <a href="recipes.php" class="btn btn-outline">Explore Recipes</a>
    </div>
</section>

<footer>
    © <?= date('Y') ?> Smart Nutrition. All rights reserved.
</footer>
</body>
</html>

</html>
