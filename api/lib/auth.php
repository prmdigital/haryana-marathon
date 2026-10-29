<?php
/** Admin authentication + CSRF helpers. */
require_once __DIR__ . '/helpers.php';

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
  ];
}
