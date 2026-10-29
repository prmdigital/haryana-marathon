<?php
/**
 * ONE-TIME setup. Open https://your-domain/api/setup.php in a browser.
 * It creates the database tables, seeds the editable settings, and creates
 * your first admin login. DELETE THIS FILE once it says "Setup complete".
 */
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/db.php';

$done = false; $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $key  = $_POST['key'] ?? '';
  $user = clean($_POST['admin_user'] ?? '', 60);
  $pass = (string)($_POST['admin_pass'] ?? '');

  if (!hash_equals(SETUP_KEY, $key)) {
    $error = 'Setup key is incorrect (it must match SETUP_KEY in config.php).';
  } elseif (!preg_match('/^[A-Za-z0-9_.\-]{3,60}$/', $user)) {
    $error = 'Admin username must be 3-60 chars (letters, numbers, . _ -).';
  } elseif (strlen($pass) < 8) {
    $error = 'Admin password must be at least 8 characters.';
  } else {
    try {
      $pdo = db();
      // Run schema.sql statement by statement.
      $sql = file_get_contents(__DIR__ . '/schema.sql');
      $sql = preg_replace('/^\s*--.*$/m', '', $sql); // strip comment lines
      foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        $pdo->exec($stmt);
      }
      // Create (or update) the first admin.
      $exists = $pdo->prepare('SELECT id FROM admin_users WHERE username = ?');
      $exists->execute([$user]);
      if ($exists->fetch()) {
        $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE username = ?')
            ->execute([password_hash($pass, PASSWORD_DEFAULT), $user]);
      } else {
        $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)')
            ->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);
      }
      if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
      $done = true;
    } catch (Throwable $e) {
      $error = 'Setup failed: ' . $e->getMessage();
    }
  }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Marathon — Setup</title>
<style>
  body{font-family:system-ui,Arial,sans-serif;background:#f6f4ef;margin:0;padding:40px 16px;color:#1f2a44}
  .card{max-width:440px;margin:0 auto;background:#fff;border:1px solid #e6e1d6;border-radius:14px;padding:28px;box-shadow:0 8px 30px rgba(0,0,0,.06)}
  h1{font-size:20px;margin:0 0 6px}p{color:#5b5547;font-size:14px;line-height:1.5}
  label{display:block;font-size:13px;font-weight:600;margin:14px 0 4px}
  input{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #d9d3c6;border-radius:8px;font-size:14px}
  button{margin-top:18px;width:100%;padding:12px;border:0;border-radius:8px;background:linear-gradient(90deg,#f26a1b,#1f8a3b);color:#fff;font-weight:700;font-size:15px;cursor:pointer}
  .ok{background:#e9f8ee;border:1px solid #bfe6cc;color:#1f6b36;padding:14px;border-radius:10px;font-size:14px}
  .err{background:#fdecec;border:1px solid #f3c2c2;color:#a12020;padding:12px;border-radius:10px;font-size:14px;margin-bottom:8px}
  code{background:#f1ede4;padding:2px 6px;border-radius:5px}
</style>
</head>
<body>
<div class="card">
<?php if ($done): ?>
  <h1>✅ Setup complete</h1>
  <p class="ok">The database is ready and your admin account was created. For security, <strong>delete this <code>setup.php</code> file now</strong>. Then open <code>/admin</code> on your site to log in.</p>
<?php else: ?>
  <h1>Marathon — one-time setup</h1>
  <p>Creates the database tables and your first admin login. You need the <code>SETUP_KEY</code> value from <code>config.php</code>.</p>
  <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post">
    <label>Setup key</label>
    <input name="key" autocomplete="off" required>
    <label>Admin username</label>
    <input name="admin_user" autocomplete="off" required>
    <label>Admin password (min 8 chars)</label>
    <input name="admin_pass" type="password" autocomplete="new-password" required>
    <button type="submit">Run setup</button>
  </form>
<?php endif; ?>
</div>
</body>
</html>
