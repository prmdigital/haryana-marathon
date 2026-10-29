<?php
/** Shared request/response helpers. */
require_once __DIR__ . '/../config.php';

/** Send a JSON response and stop. */
function json_out($data, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  echo json_encode($data);
  exit;
}

function json_err(string $msg, int $status = 400, array $extra = []): void {
  json_out(array_merge(['ok' => false, 'error' => $msg], $extra), $status);
}

/** Only allow a given HTTP method. */
function require_method(string $method): void {
  if ($_SERVER['REQUEST_METHOD'] !== $method) {
    json_err('Method not allowed.', 405);
  }
}

/** Read a JSON body into an array (for application/json POSTs). */
function read_json_body(): array {
  $raw = file_get_contents('php://input');
  if ($raw === '' || $raw === false) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

/** Trim + clamp a string field. */
function clean(?string $v, int $max = 255): string {
  $v = trim((string)$v);
  if (mb_strlen($v) > $max) $v = mb_substr($v, 0, $max);
  return $v;
}

function client_ip(): string {
  return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
}

/** Start a hardened session for admin auth. */
function start_admin_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'secure'   => $https,
    'samesite' => 'Lax',
  ]);
  session_name('HM27ADMIN');
  session_start();
}
