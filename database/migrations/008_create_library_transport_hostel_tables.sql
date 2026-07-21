-- ===========================================================================
-- 008 - Library, Transport & Hostel
-- ===========================================================================

-- ---- Library --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `book_categories` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120) NOT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_bookcat_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `books` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `book_category_id` INT UNSIGNED DEFAULT NULL,
    `title`            VARCHAR(255) NOT NULL,
    `author`           VARCHAR(200) DEFAULT NULL,
    `publisher`        VARCHAR(200) DEFAULT NULL,
    `isbn`             VARCHAR(20) DEFAULT NULL,
    `edition`          VARCHAR(60) DEFAULT NULL,
    `rack_no`          VARCHAR(40) DEFAULT NULL,
    `total_copies`     INT UNSIGNED NOT NULL DEFAULT 1,
    `available_copies` INT UNSIGNED NOT NULL DEFAULT 1,
    `price`            DECIMAL(10,2) NOT NULL DEFAULT 0,
    `created_at`       DATETIME DEFAULT NULL,
    `updated_at`       DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_book_category` (`book_category_id`),
    KEY `idx_book_isbn` (`isbn`),
    KEY `idx_book_title` (`title`),
    CONSTRAINT `fk_book_category` FOREIGN KEY (`book_category_id`) REFERENCES `book_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_issues` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `book_id`     BIGINT UNSIGNED NOT NULL,
    `student_id`  BIGINT UNSIGNED DEFAULT NULL,
    `staff_id`    BIGINT UNSIGNED DEFAULT NULL,
    `issue_date`  DATE NOT NULL,
    `due_date`    DATE NOT NULL,
    `return_date` DATE DEFAULT NULL,
    `fine_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `status`      ENUM('issued','returned','lost','overdue') NOT NULL DEFAULT 'issued',
    `issued_by`   BIGINT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_issue_book` (`book_id`),
    KEY `idx_issue_student` (`student_id`),
    KEY `idx_issue_status` (`status`),
    CONSTRAINT `fk_issue_book` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_issue_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_issue_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Transport ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transport_routes` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(150) NOT NULL,
    `start_point` VARCHAR(150) DEFAULT NULL,
    `end_point`   VARCHAR(150) DEFAULT NULL,
    `distance_km` DECIMAL(6,2) DEFAULT NULL,
    `fare`        DECIMAL(10,2) NOT NULL DEFAULT 0,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_route_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vehicles` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `registration_no` VARCHAR(40) NOT NULL,
    `model`          VARCHAR(120) DEFAULT NULL,
    `capacity`       INT UNSIGNED NOT NULL DEFAULT 40,
    `driver_name`    VARCHAR(120) DEFAULT NULL,
    `driver_phone`   VARCHAR(20) DEFAULT NULL,
    `driver_license` VARCHAR(60) DEFAULT NULL,
    `route_id`       INT UNSIGNED DEFAULT NULL,
    `gps_device_id`  VARCHAR(80) DEFAULT NULL,
    `status`         ENUM('active','maintenance','inactive') NOT NULL DEFAULT 'active',
    `created_at`     DATETIME DEFAULT NULL,
    `updated_at`     DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_vehicle_reg` (`registration_no`),
    KEY `idx_vehicle_route` (`route_id`),
    CONSTRAINT `fk_vehicle_route` FOREIGN KEY (`route_id`) REFERENCES `transport_routes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transport_assignments` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`  BIGINT UNSIGNED NOT NULL,
    `route_id`    INT UNSIGNED NOT NULL,
    `vehicle_id`  INT UNSIGNED DEFAULT NULL,
    `pickup_point` VARCHAR(150) DEFAULT NULL,
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_transport_student` (`student_id`),
    KEY `idx_ta_route` (`route_id`),
    CONSTRAINT `fk_ta_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ta_route` FOREIGN KEY (`route_id`) REFERENCES `transport_routes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ta_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Hostel ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `hostels` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(150) NOT NULL,
    `type`       ENUM('boys','girls','mixed') NOT NULL DEFAULT 'boys',
    `warden_name` VARCHAR(120) DEFAULT NULL,
    `address`    VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hostel_rooms` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `hostel_id`  INT UNSIGNED NOT NULL,
    `room_no`    VARCHAR(40) NOT NULL,
    `bed_count`  INT UNSIGNED NOT NULL DEFAULT 1,
    `occupied`   INT UNSIGNED NOT NULL DEFAULT 0,
    `fee`        DECIMAL(10,2) NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_hostel_room` (`hostel_id`, `room_no`),
    CONSTRAINT `fk_hroom_hostel` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hostel_allocations` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`  BIGINT UNSIGNED NOT NULL,
    `hostel_room_id` INT UNSIGNED NOT NULL,
    `allocated_date` DATE DEFAULT NULL,
    `vacated_date`   DATE DEFAULT NULL,
    `status`      ENUM('active','vacated') NOT NULL DEFAULT 'active',
    `created_at`  DATETIME DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_halloc_student` (`student_id`),
    KEY `idx_halloc_room` (`hostel_room_id`),
    CONSTRAINT `fk_halloc_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_halloc_room` FOREIGN KEY (`hostel_room_id`) REFERENCES `hostel_rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
