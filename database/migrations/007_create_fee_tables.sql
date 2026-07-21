-- ===========================================================================
-- 007 - Fees: categories, structures, invoices, payments, discounts
-- ===========================================================================

CREATE TABLE IF NOT EXISTS `fee_categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(120) NOT NULL,         -- Tuition, Transport, Exam, etc.
    `description` VARCHAR(255) DEFAULT NULL,
    `is_recurring` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_fee_category_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fee structure per class + academic year.
CREATE TABLE IF NOT EXISTS `fee_structures` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(150) NOT NULL,
    `class_id`         INT UNSIGNED DEFAULT NULL,
    `academic_year_id` INT UNSIGNED DEFAULT NULL,
    `frequency`        ENUM('monthly','quarterly','half_yearly','yearly','one_time') NOT NULL DEFAULT 'yearly',
    `created_at`       DATETIME DEFAULT NULL,
    `updated_at`       DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_fs_class` (`class_id`),
    KEY `idx_fs_year` (`academic_year_id`),
    CONSTRAINT `fk_fs_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fs_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Line items within a fee structure.
CREATE TABLE IF NOT EXISTS `fee_structure_items` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `fee_structure_id` INT UNSIGNED NOT NULL,
    `fee_category_id`  INT UNSIGNED NOT NULL,
    `amount`           DECIMAL(12,2) NOT NULL DEFAULT 0,
    `due_date`         DATE DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_fsi_structure` (`fee_structure_id`),
    KEY `idx_fsi_category` (`fee_category_id`),
    CONSTRAINT `fk_fsi_structure` FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structures` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fsi_category` FOREIGN KEY (`fee_category_id`) REFERENCES `fee_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scholarships / discounts.
CREATE TABLE IF NOT EXISTS `fee_discounts` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(150) NOT NULL,
    `type`        ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    `value`       DECIMAL(12,2) NOT NULL DEFAULT 0,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student fee invoices.
CREATE TABLE IF NOT EXISTS `fee_invoices` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_no`       VARCHAR(40) NOT NULL,
    `student_id`       BIGINT UNSIGNED NOT NULL,
    `fee_structure_id` INT UNSIGNED DEFAULT NULL,
    `academic_year_id` INT UNSIGNED DEFAULT NULL,
    `total_amount`     DECIMAL(12,2) NOT NULL DEFAULT 0,
    `discount_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0,
    `fine_amount`      DECIMAL(12,2) NOT NULL DEFAULT 0,
    `paid_amount`      DECIMAL(12,2) NOT NULL DEFAULT 0,
    `due_date`         DATE DEFAULT NULL,
    `status`           ENUM('unpaid','partial','paid','overdue','cancelled') NOT NULL DEFAULT 'unpaid',
    `created_at`       DATETIME DEFAULT NULL,
    `updated_at`       DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_invoice_no` (`invoice_no`),
    KEY `idx_inv_student` (`student_id`),
    KEY `idx_inv_status` (`status`),
    CONSTRAINT `fk_inv_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inv_structure` FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structures` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_inv_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoice line items.
CREATE TABLE IF NOT EXISTS `fee_invoice_items` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `fee_invoice_id`  BIGINT UNSIGNED NOT NULL,
    `fee_category_id` INT UNSIGNED DEFAULT NULL,
    `description`     VARCHAR(200) NOT NULL,
    `amount`          DECIMAL(12,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_fii_invoice` (`fee_invoice_id`),
    CONSTRAINT `fk_fii_invoice` FOREIGN KEY (`fee_invoice_id`) REFERENCES `fee_invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fii_category` FOREIGN KEY (`fee_category_id`) REFERENCES `fee_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments against invoices (supports installments).
CREATE TABLE IF NOT EXISTS `fee_payments` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `receipt_no`     VARCHAR(40) NOT NULL,
    `fee_invoice_id` BIGINT UNSIGNED NOT NULL,
    `student_id`     BIGINT UNSIGNED NOT NULL,
    `amount`         DECIMAL(12,2) NOT NULL DEFAULT 0,
    `payment_date`   DATE NOT NULL,
    `method`         ENUM('cash','card','bank_transfer','cheque','online','upi') NOT NULL DEFAULT 'cash',
    `reference`      VARCHAR(120) DEFAULT NULL,
    `transaction_id` VARCHAR(120) DEFAULT NULL,
    `status`         ENUM('success','pending','failed','refunded') NOT NULL DEFAULT 'success',
    `collected_by`   BIGINT UNSIGNED DEFAULT NULL,
    `remarks`        VARCHAR(255) DEFAULT NULL,
    `created_at`     DATETIME DEFAULT NULL,
    `updated_at`     DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_receipt_no` (`receipt_no`),
    KEY `idx_pay_invoice` (`fee_invoice_id`),
    KEY `idx_pay_student` (`student_id`),
    KEY `idx_pay_date` (`payment_date`),
    CONSTRAINT `fk_pay_invoice` FOREIGN KEY (`fee_invoice_id`) REFERENCES `fee_invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pay_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pay_collector` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
