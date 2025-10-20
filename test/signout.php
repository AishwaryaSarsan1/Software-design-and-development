<?php
// C:\xampp\htdocs\smartnutrition\signout.php
declare(strict_types=1);
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Basic CSRF check (best effort for this simple setup)
  if (!empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
  }
}
// After logout, go to Home
header('Location: Home.php');
exit;
