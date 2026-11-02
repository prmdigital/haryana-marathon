<?php
/** Admin authentication + CSRF helpers. */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

/** Is an admin currently logged in? */
function is_admin(): bool {
  start_admin_session();
  return !empty($_SESSION['admin_id']);
}

/** Guard an admin-only endpoint. */
function require_admin(): void {
  if (!is_admin()) {
    json_err('Not authenticated.', 401);
  }
}

/** Get (creating if needed) the CSRF token for this session. */
function csrf_token(): string {
  start_admin_session();
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}

/** Require a valid CSRF token on state-changing admin requests. */
function require_csrf(): void {
  start_admin_session();
  $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
    json_err('Invalid or missing CSRF token.', 403);
  }
}

function current_admin(): array {
  start_admin_session();
  return [
    'id'       => $_SESSION['admin_id'] ?? null,
    'username' => $_SESSION['admin_username'] ?? null,
    'role'     => current_role(),
  ];
}

/**
 * The current admin's role: 'super' | 'manager' | 'viewer'.
 * Resolved from the session, falling back to the DB for sessions created before
 * roles existed. If the role column isn't present yet, defaults to 'super' so the
 * panel keeps working exactly as before until the migration is applied.
 */
function current_role(): string {
  start_admin_session();
  if (!empty($_SESSION['admin_role'])) return $_SESSION['admin_role'];
  $id = (int)($_SESSION['admin_id'] ?? 0);
  if ($id > 0) {
    try {
      $st = db()->prepare('SELECT role FROM admin_users WHERE id = ?');
      $st->execute([$id]);
      $role = (string)$st->fetchColumn();
      if ($role !== '') { $_SESSION['admin_role'] = $role; return $role; }
    } catch (Throwable $e) { /* role column not present yet */ }
  }
  return 'super';
}

/** Guard an endpoint to one or more roles (also requires authentication). */
function require_role(array $roles): void {
  require_admin();
  if (!in_array(current_role(), $roles, true)) {
    json_err('You do not have permission for this action.', 403);
  }
}
