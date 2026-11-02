<?php
/**
 * Admin user management — SUPER ADMIN ONLY (enforced server-side).
 *   GET                                 -> list users (with roles)
 *   POST { username, password, role }   -> create a user
 *   POST { id, role }                   -> change a user's role
 *   POST { id, action: "delete" }       -> remove a user
 *   DELETE (?id=)                        -> remove a user (fallback; some hosts block DELETE)
 */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

require_role(['super']); // only a Super Admin may manage users

const ROLES = ['super', 'manager', 'viewer'];

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

/** Remove a user by id, with guards. */
function delete_user(PDO $pdo, int $id): void {
  if ($id <= 0) json_err('Missing id.', 400);
  $me = current_admin();
  if ($id === (int)$me['id']) json_err('You cannot delete your own account.', 400);

  $st = $pdo->prepare('SELECT role FROM admin_users WHERE id = ?');
  $st->execute([$id]);
  $role = $st->fetchColumn();
  $st->closeCursor();
  if ($role === false) json_err('User not found.', 404);

  $count = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
  if ($count <= 1) json_err('At least one admin must remain.', 400);
  if ($role === 'super') {
    $supers = (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'super'")->fetchColumn();
    if ($supers <= 1) json_err('At least one Super Admin must remain.', 400);
  }
  $pdo->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$id]);
  json_out(['ok' => true]);
}

try {
  if ($method === 'GET') {
    try {
      $rows = $pdo->query('SELECT id, username, role, created_at, last_login FROM admin_users ORDER BY username')->fetchAll();
    } catch (Throwable $e) {
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

    // Remove a user (POST route — robust against hosts that block DELETE).
    if (($in['action'] ?? '') === 'delete') {
      delete_user($pdo, (int)($in['id'] ?? 0));
    }

    // Change an existing user's role: { id, role }
    if (!empty($in['id']) && empty($in['username'])) {
      $id   = (int)$in['id'];
      $role = (string)($in['role'] ?? '');
      if (!in_array($role, ROLES, true)) json_err('Invalid role.', 422);
      if ($role !== 'super') {
        $cur = $pdo->prepare('SELECT role FROM admin_users WHERE id = ?');
        $cur->execute([$id]);
        $curRole = $cur->fetchColumn();
        $cur->closeCursor();
        if ($curRole === 'super') {
          $supers = (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'super'")->fetchColumn();
          if ($supers <= 1) json_err('At least one Super Admin must remain.', 400);
        }
      }
      $pdo->prepare('UPDATE admin_users SET role = ? WHERE id = ?')->execute([$role, $id]);
      json_out(['ok' => true]);
    }

    // Create a new user: { username, password, role }
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
    delete_user($pdo, (int)($_GET['id'] ?? (read_json_body()['id'] ?? 0)));
  }

  json_err('Method not allowed.', 405);
} catch (Throwable $e) {
  json_err('Server error: ' . $e->getMessage(), 500);
}
