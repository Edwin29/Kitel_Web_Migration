CREATE TABLE IF NOT EXISTS kitel_rental_allowed_groups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_srl BIGINT NOT NULL,
  group_title_snapshot VARCHAR(100) NOT NULL,
  permission_type VARCHAR(20) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_allowed_group_permission (group_srl, permission_type),
  KEY idx_allowed_groups_active (active, permission_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kitel_rental_categories (
  category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  next_serial INT UNSIGNED NOT NULL DEFAULT 1,
  description TEXT NULL,
  tracking_mode VARCHAR(10) NOT NULL DEFAULT 'unique',
  max_per_user INT UNSIGNED NULL,
  -- 카테고리별 대여 기간(일). NULL이면 app/config.php 의 default_due_days 를 쓴다.
  due_days INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by_member_srl BIGINT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (category_id),
  UNIQUE KEY uniq_category_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kitel_rental_items (
  item_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT UNSIGNED NOT NULL,
  serial_no INT UNSIGNED NOT NULL,
  display_no INT UNSIGNED NULL,
  label VARCHAR(160) NOT NULL,
  public_code VARCHAR(40) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'available',
  location VARCHAR(160) NULL,
  condition_note TEXT NULL,
  admin_memo TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by_member_srl BIGINT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (item_id),
  UNIQUE KEY uniq_item_public_code (public_code),
  UNIQUE KEY uniq_item_category_serial (category_id, serial_no),
  KEY idx_items_status (status, is_active),
  CONSTRAINT fk_items_category FOREIGN KEY (category_id)
    REFERENCES kitel_rental_categories (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kitel_rental_bundles (
  bundle_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  public_code VARCHAR(40) NOT NULL,
  description TEXT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by_member_srl BIGINT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (bundle_id),
  UNIQUE KEY uniq_bundle_public_code (public_code),
  KEY idx_bundles_active_sort (is_active, sort_order, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kitel_rental_bundle_categories (
  bundle_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (bundle_id, category_id),
  UNIQUE KEY uniq_bundle_category_once (category_id),
  KEY idx_bundle_categories_sort (bundle_id, sort_order),
  CONSTRAINT fk_bundle_categories_bundle FOREIGN KEY (bundle_id)
    REFERENCES kitel_rental_bundles (bundle_id)
    ON DELETE CASCADE,
  CONSTRAINT fk_bundle_categories_category FOREIGN KEY (category_id)
    REFERENCES kitel_rental_categories (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kitel_rental_loans (
  loan_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id INT UNSIGNED NOT NULL,
  borrower_member_srl BIGINT NOT NULL,
  borrower_user_id_snapshot VARCHAR(120) NULL,
  borrower_name_snapshot VARCHAR(120) NOT NULL,
  actual_user_name VARCHAR(120) NULL,
  actual_user_contact VARCHAR(120) NULL,
  borrowed_at DATETIME NOT NULL,
  due_at DATETIME NOT NULL,
  returned_at DATETIME NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'borrowed',
  created_by_member_srl BIGINT NOT NULL,
  returned_by_member_srl BIGINT NULL,
  return_type VARCHAR(20) NULL,
  admin_note TEXT NULL,
  related_loan_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (loan_id),
  KEY idx_loans_item_status (item_id, status),
  KEY idx_loans_borrower (borrower_member_srl, status),
  KEY idx_loans_due (due_at, status),
  CONSTRAINT fk_loans_item FOREIGN KEY (item_id)
    REFERENCES kitel_rental_items (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kitel_rental_logs (
  log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id INT UNSIGNED NULL,
  loan_id INT UNSIGNED NULL,
  actor_member_srl BIGINT NULL,
  action VARCHAR(60) NOT NULL,
  before_status VARCHAR(20) NULL,
  after_status VARCHAR(20) NULL,
  memo TEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (log_id),
  KEY idx_logs_item (item_id),
  KEY idx_logs_loan (loan_id),
  KEY idx_logs_action (action),
  KEY idx_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
