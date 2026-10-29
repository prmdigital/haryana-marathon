<?php
/** Admin logout. POST (CSRF-protected). */
require_once __DIR__ . '/../lib/auth.php';

require_method('POST');
require_admin();
require_csrf();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

json_out(['ok' => true]);
