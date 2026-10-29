-- Shaheed-E-Azam Bhagat Singh Haryana Marathon 2027 — database schema
-- Run once (the setup.php page does this for you automatically).
-- Engine/charset chosen for broad Hostinger MySQL compatibility.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Registrations
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS registrations (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  reg_no          VARCHAR(20)  NOT NULL UNIQUE,
  full_name       VARCHAR(120) NOT NULL,
  email           VARCHAR(160) NOT NULL,
  mobile          VARCHAR(15)  NOT NULL,
  category        VARCHAR(40)  NOT NULL,
  category_label  VARCHAR(80)  NOT NULL,
  dob             VARCHAR(10)  NOT NULL,   -- stored as DD-MM-YYYY (as entered)
  age_on_race_day INT          NULL,
  gender          VARCHAR(20)  NOT NULL,
  state           VARCHAR(80)  NOT NULL,
  city            VARCHAR(80)  NOT NULL,
  tshirt_size     VARCHAR(10)  NOT NULL,
  id_proof_type   VARCHAR(40)  NOT NULL,
  id_file_path    VARCHAR(255) NULL,       -- relative path inside uploads/
  id_file_name    VARCHAR(255) NULL,       -- original filename
  emergency_phone VARCHAR(15)  NULL,
  free_eligible   TINYINT(1)   NOT NULL DEFAULT 0,
  ip_address      VARCHAR(45)  NULL,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at),
  INDEX idx_category (category),
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Admin users (login accounts for the admin panel)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login    TIMESTAMP    NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Editable site settings (key/value). The front-end fetches these and falls
-- back to its built-in defaults if any key is missing, so layout never breaks.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_settings (
  setting_key   VARCHAR(60)  NOT NULL PRIMARY KEY,
  setting_value TEXT         NULL,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the editable keys with the current live values.
INSERT INTO site_settings (setting_key, setting_value) VALUES
  ('countdown_target',        '2027-03-21T05:00:00+05:30'),
  ('registration_open_label', 'Till 14 March 2027'),
  ('fee_half',                '1,899'),
  ('fee_cross',               '1,499'),
  ('fee_cause',               '599'),
  ('fee_children',            '299'),
  ('earlybird_enabled',       '1'),
  ('earlybird_text1',         '40% OFF till Diwali'),
  ('earlybird_text2',         '30% OFF till 31 December'),
  ('contact_phone_reg',       '+91 73409 92413'),
  ('contact_phone_general',   '+91 81467 02026'),
  ('contact_phone_emergency', '+91 73411 24861'),
  ('contact_email_reg',       'registration@shaheedbhagatsinghmarathon.com'),
  ('contact_email_info',      'info@shaheedbhagatsinghmarathon.com'),
  ('contact_email_sponsor',   'sponsors@shaheedbhagatsinghmarathon.com')
ON DUPLICATE KEY UPDATE setting_key = setting_key; -- never overwrite edited values
