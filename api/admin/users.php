<?php
/**
 * Admin user management (admin only).
 *   GET                       -> list admins
 *   POST { username, password } -> create admin
 *   DELETE (?id= or JSON {id}) -> remove admin
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

require_admin();
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $rows = $pdo->query('SELECT id, username, created_at, last_login FROM admin_users ORDER BY username')->fetchAll();
  foreach ($rows as &$r) $r['id'] = (int)$r['id'];
  unset($r);
  json_out(['ok' => true, 'users' => $rows]);
}

if ($method === 'POST') {
  require_csrf();
  $in = read_json_body();
  $username = clean($in['username'] ?? '', 60);
  $password = (string)($in['password'] ?? '');
  if (!preg_match('/^[A-Za-z0-9_.\-]{3,60}$/', $username)) {
    json_err('Username must be 3-60 chars (letters, numbers, . _ -).', 422);
  }
  if (strlen($password) < 8) json_err('Password must be at least 8 characters.', 422);

  $exists = $pdo->prepare('SELECT 1 FROM admin_users WHERE username = ?');
  $exists->execute([$username]);
  if ($exists->fetch()) json_err('That username already exists.', 409);

  $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)')
      ->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
  json_out(['ok' => true]);
}

if ($method === 'DELETE') {
  require_csrf();
  $id = (int)($_GET['id'] ?? (read_json_body()['id'] ?? 0));
  if ($id <= 0) json_err('Missing id.', 400);
  $me = current_admin();
  if ($id === (int)$me['id']) json_err('You cannot delete your own account.', 400);
  $count = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
  if ($count <= 1) json_err('At least one admin must remain.', 400);
  $pdo->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$id]);
  json_out(['ok' => true]);
}

json_err('Method not allowed.', 405);
