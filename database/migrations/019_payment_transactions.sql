-- ===========================================================================
-- 019 - Payment transactions ledger.
--
-- A single source of truth for online fee payments across all gateways. Every
-- payment attempt is recorded here and its status is only ever advanced by
-- server-side verification (signature / webhook), never by a frontend response.
--
-- Idempotency & duplicate-payment protection are enforced at the DATABASE level
-- in addition to application checks:
--   * UNIQUE (gateway, gateway_order_id)   -> one ledger row per gateway order
--   * UNIQUE (gateway, gateway_payment_id) -> a payment id can settle only once
-- (NULLs are permitted and may repeat, so pending rows without a payment id are
--  unaffected.)
-- ===========================================================================

CREATE TABLE IF NOT EXISTS `payment_transactions` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid`                VARCHAR(24)     DEFAULT NULL,          -- human-facing txn id (PAY-YYYY-NNNNN)
  `student_id`         BIGINT UNSIGNED DEFAULT NULL,
  `fee_invoice_id`     BIGINT UNSIGNED DEFAULT NULL,
  `gateway`            VARCHAR(30)     NOT NULL,              -- razorpay | phonepe | mobikwik
  `gateway_order_id`   VARCHAR(120)    DEFAULT NULL,
  `gateway_payment_id` VARCHAR(120)    DEFAULT NULL,
  `amount`             DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `currency`           CHAR(3)         NOT NULL DEFAULT 'INR',
  `status`             ENUM('pending','processing','successful','failed','cancelled','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
  `method`             VARCHAR(40)     DEFAULT NULL,          -- upi | card | netbanking ...
  `mode`               ENUM('test','live') NOT NULL DEFAULT 'test',
  `signature_verified` TINYINT(1)      NOT NULL DEFAULT 0,
  `meta`               JSON            DEFAULT NULL,
  `created_at`         DATETIME        DEFAULT NULL,
  `updated_at`         DATETIME        DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ptx_uid`     (`uid`),
  UNIQUE KEY `uq_ptx_order`   (`gateway`, `gateway_order_id`),
  UNIQUE KEY `uq_ptx_payment` (`gateway`, `gateway_payment_id`),
  KEY `idx_ptx_student` (`student_id`),
  KEY `idx_ptx_invoice` (`fee_invoice_id`),
  KEY `idx_ptx_status`  (`status`),
  CONSTRAINT `fk_ptx_student` FOREIGN KEY (`student_id`)     REFERENCES `students`(`id`)      ON DELETE SET NULL,
  CONSTRAINT `fk_ptx_invoice` FOREIGN KEY (`fee_invoice_id`) REFERENCES `fee_invoices`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
