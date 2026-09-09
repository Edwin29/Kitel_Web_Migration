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
