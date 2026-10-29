<?php
/**
 * SAMPLE — copy this file to  config.local.php  (same folder) and fill in your
 * Hostinger MySQL details. config.local.php holds your secrets and is NOT part of
 * the website build, so it is never overwritten when you re-upload the front-end.
 *
 * Create it once via Hostinger File Manager (right-click config.local.sample.php
 * -> Copy -> rename the copy to config.local.php -> Edit).
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'REPLACE_WITH_DB_NAME');   // e.g. u123456789_marathon
define('DB_USER', 'REPLACE_WITH_DB_USER');   // e.g. u123456789_admin
define('DB_PASS', 'REPLACE_WITH_DB_PASSWORD');

// Any long random string — you'll type it once when running api/setup.php.
define('SETUP_KEY', 'CHANGE_ME_to_a_long_random_string');
