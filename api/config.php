<?php
/**
 * App configuration.
 *
 * Put your REAL Hostinger database credentials in a file called  config.local.php
 * next to this one (copy config.local.sample.php -> config.local.php and fill it in).
 * config.local.php is created once on the server and is NOT part of the website
 * build, so your credentials are never overwritten when you re-upload the front-end.
 */

// Load real secrets if present.
if (is_file(__DIR__ . '/config.local.php')) {
  require __DIR__ . '/config.local.php';
}

// Fallbacks — used only when config.local.php has not defined them yet.
defined('DB_HOST')        || define('DB_HOST', 'localhost');
defined('DB_NAME')        || define('DB_NAME', 'REPLACE_WITH_DB_NAME');
defined('DB_USER')        || define('DB_USER', 'REPLACE_WITH_DB_USER');
defined('DB_PASS')        || define('DB_PASS', 'REPLACE_WITH_DB_PASSWORD');
defined('SETUP_KEY')      || define('SETUP_KEY', 'CHANGE_ME_to_a_long_random_string');
defined('ALLOWED_ORIGIN') || define('ALLOWED_ORIGIN', '');

// Max ID-proof upload size (bytes) and permitted types.
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB
$GLOBALS['ALLOWED_UPLOAD_MIME'] = [
  'image/jpeg' => 'jpg',
  'image/png'  => 'png',
  'image/webp' => 'webp',
  'application/pdf' => 'pdf',
];

// Uploads directory (inside api/, protected from direct web access).
define('UPLOAD_DIR', __DIR__ . '/uploads');

// Show PHP errors only while setting up; set to false once live.
defined('DEBUG') || define('DEBUG', false);

if (DEBUG) {
  error_reporting(E_ALL);
  ini_set('display_errors', '1');
} else {
  error_reporting(0);
  ini_set('display_errors', '0');
}

date_default_timezone_set('Asia/Kolkata');
