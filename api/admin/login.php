<?php
/** Admin login. POST JSON { username, password }. */
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

require_method('POST');
$in = read_json_body();
$username = clean($in['username'] ?? '', 60);
$password = (string)($in['password'] ?? '');

if ($username === '' || $password === '') {
  json_err('Username and password are required.', 400);
}

$stmt = db()->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ?');
$stmt->execute([$username]);
$user = $stmt->fetch();

// Constant-ish behaviour: always run a verify to blunt timing/enumeration.
$hash = $user['password_hash'] ?? '$2y$10$usesomesillystringforsalttominimizetimingleakxxxxxxxxxxx';
if (!$user || !password_verify($password, $hash)) {
  usleep(300000);
  json_err('Invalid username or password.', 401);
}

start_admin_session();
session_regenerate_id(true);
$_SESSION['admin_id'] = (int)$user['id'];
$_SESSION['admin_username'] = $user['username'];

db()->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

json_out([
  'ok'    => true,
  'user'  => ['id' => (int)$user['id'], 'username' => $user['username']],
  'csrf'  => csrf_token(),
]);
