<?php
/**
 * Admin user management — SUPER ADMIN ONLY (enforced server-side).
 *   GET                                 -> list users (with roles)
 *   POST { username, password, role }   -> create a user
 *   POST { id, role }                   -> change a user's role
 *   DELETE (?id= or JSON {id})          -> remove a user
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

require_role(['super']); // only a Super Admin may manage users

const ROLES = ['super', 'manager', 'viewer'];

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  try {
    $rows = $pdo->query('SELECT id, username, role, created_at, last_login FROM admin_users ORDER BY username')->fetchAll();
  } catch (Throwable $e) {
    // role column not added yet — degrade gracefully so the tab still loads.
    $rows = $pdo->query('SELECT id, username, created_at, last_login FROM admin_users ORDER BY username')->fetchAll();
    foreach ($rows as &$r) $r['role'] = 'super';
    unset($r);
  }
  foreach ($rows as &$r) $r['id'] = (int)$r['id'];
  unset($r);
  json_out(['ok' => true, 'users' => $rows, 'roles' => ROLES]);
}

if ($method === 'POST') {
  require_csrf();
  $in = read_json_body();

  // ---- Change an existing user's role: { id, role } -----------------------
  if (!empty($in['id']) && empty($in['username'])) {
    $id   = (int)$in['id'];
    $role = (string)($in['role'] ?? '');
    if (!in_array($role, ROLES, true)) json_err('Invalid role.', 422);
    if ($role !== 'super') {
      $cur = $pdo->prepare('SELECT role FROM admin_users WHERE id = ?');
      $cur->execute([$id]);
      if ($cur->fetchColumn() === 'super') {
        $supers = (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'super'")->fetchColumn();
        if ($supers <= 1) json_err('At least one Super Admin must remain.', 400);
      }
    }
    $pdo->prepare('UPDATE admin_users SET role = ? WHERE id = ?')->execute([$role, $id]);
    json_out(['ok' => true]);
  }

  // ---- Create a new user: { username, password, role } --------------------
  $username = clean($in['username'] ?? '', 60);
  $password = (string)($in['password'] ?? '');
  $role     = (string)($in['role'] ?? 'manager');
  if (!preg_match('/^[A-Za-z0-9_.\-]{3,60}$/', $username)) {
    json_err('Username must be 3-60 chars (letters, numbers, . _ -).', 422);
  }
  if (strlen($password) < 8) json_err('Password must be at least 8 characters.', 422);
  if (!in_array($role, ROLES, true)) json_err('Invalid role.', 422);

  $exists = $pdo->prepare('SELECT 1 FROM admin_users WHERE username = ?');
  $exists->execute([$username]);
  if ($exists->fetch()) json_err('That username already exists.', 409);

  $pdo->prepare('INSERT INTO admin_users (username, password_hash, role) VALUES (?, ?, ?)')
      ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
  json_out(['ok' => true]);
}

if ($method === 'DELETE') {
  require_csrf();
  $id = (int)($_GET['id'] ?? (read_json_body()['id'] ?? 0));
  if ($id <= 0) json_err('Missing id.', 400);
  $me = current_admin();
  if ($id === (int)$me['id']) json_err('You cannot delete your own account.', 400);

  $cur = $pdo->prepare('SELECT role FROM admin_users WHERE id = ?');
  $cur->execute([$id]);
  if ($cur->fetchColumn() === 'super') {
    $supers = (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'super'")->fetchColumn();
    if ($supers <= 1) json_err('At least one Super Admin must remain.', 400);
  }
  $count = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
  if ($count <= 1) json_err('At least one admin must remain.', 400);

  $pdo->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$id]);
  json_out(['ok' => true]);
}

json_err('Method not allowed.', 405);
