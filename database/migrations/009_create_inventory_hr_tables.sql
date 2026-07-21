-- ===========================================================================
-- 009 - Inventory & HR/Payroll
-- ===========================================================================

-- ---- Inventory ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `item_categories` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120) NOT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_itemcat_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vendors` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(150) NOT NULL,
    `contact_person` VARCHAR(120) DEFAULT NULL,
    `phone`      VARCHAR(20) DEFAULT NULL,
    `email`      VARCHAR(190) DEFAULT NULL,
    `address`    VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_items` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_category_id` INT UNSIGNED DEFAULT NULL,
    `name`             VARCHAR(150) NOT NULL,
    `sku`              VARCHAR(60) DEFAULT NULL,
    `unit`             VARCHAR(30) DEFAULT NULL,
    `quantity`         INT NOT NULL DEFAULT 0,
    `reorder_level`    INT NOT NULL DEFAULT 0,
    `unit_price`       DECIMAL(12,2) NOT NULL DEFAULT 0,
    `is_asset`         TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`       DATETIME DEFAULT NULL,
    `updated_at`       DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_item_category` (`item_category_id`),
    CONSTRAINT `fk_item_category` FOREIGN KEY (`item_category_id`) REFERENCES `item_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchases` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vendor_id`   INT UNSIGNED DEFAULT NULL,
    `invoice_no`  VARCHAR(60) DEFAULT NULL,
    `purchase_date` DATE NOT NULL,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `status`      ENUM('ordered','received','cancelled') NOT NULL DEFAULT 'received',
    `created_by`  BIGINT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_purchase_vendor` (`vendor_id`),
    CONSTRAINT `fk_purchase_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_items` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_id` BIGINT UNSIGNED NOT NULL,
    `inventory_item_id` BIGINT UNSIGNED DEFAULT NULL,
    `description` VARCHAR(200) NOT NULL,
    `quantity`    INT NOT NULL DEFAULT 1,
    `unit_price`  DECIMAL(12,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_pi_purchase` (`purchase_id`),
    CONSTRAINT `fk_pi_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pi_item` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- HR / Payroll ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leave_types` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(120) NOT NULL,
    `days_allowed` INT NOT NULL DEFAULT 0,
    `is_paid`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_leavetype_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leaves` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id`     BIGINT UNSIGNED NOT NULL,
    `leave_type_id` INT UNSIGNED DEFAULT NULL,
    `start_date`   DATE NOT NULL,
    `end_date`     DATE NOT NULL,
    `days`         INT NOT NULL DEFAULT 1,
    `reason`       VARCHAR(255) DEFAULT NULL,
    `status`       ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    `approved_by`  BIGINT UNSIGNED DEFAULT NULL,
    `created_at`   DATETIME DEFAULT NULL,
    `updated_at`   DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_leave_staff` (`staff_id`),
    KEY `idx_leave_status` (`status`),
    CONSTRAINT `fk_leave_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_leave_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `holidays` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(150) NOT NULL,
    `date`       DATE NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_holiday_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payrolls` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id`    BIGINT UNSIGNED NOT NULL,
    `month`       TINYINT UNSIGNED NOT NULL,
    `year`        SMALLINT UNSIGNED NOT NULL,
    `basic_salary` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `allowances`  DECIMAL(12,2) NOT NULL DEFAULT 0,
    `deductions`  DECIMAL(12,2) NOT NULL DEFAULT 0,
    `net_salary`  DECIMAL(12,2) NOT NULL DEFAULT 0,
    `status`      ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    `paid_at`     DATETIME DEFAULT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payroll_period` (`staff_id`, `month`, `year`),
    KEY `idx_payroll_staff` (`staff_id`),
    CONSTRAINT `fk_payroll_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
