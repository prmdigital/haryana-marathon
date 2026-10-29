<?php
/** Returns whether an admin is logged in, plus the CSRF token. */
require_once __DIR__ . '/../lib/auth.php';

require_method('GET');

if (is_admin()) {
  json_out(['ok' => true, 'authenticated' => true, 'user' => current_admin(), 'csrf' => csrf_token()]);
}
json_out(['ok' => true, 'authenticated' => false]);
