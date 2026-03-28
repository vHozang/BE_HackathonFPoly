USE HRM_SYSTEM;

CREATE TABLE IF NOT EXISTS recruitment_candidate_cvs (
    cv_id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
    file_size INT NOT NULL,
    file_data LONGBLOB NOT NULL,
    uploaded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_candidate_cv_candidate (candidate_id),
    INDEX idx_candidate_cv_uploaded_by (uploaded_by),
    CONSTRAINT fk_candidate_cv_candidate FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates(candidate_id) ON DELETE CASCADE,
    CONSTRAINT fk_candidate_cv_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES employees(employee_id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'interview_schedules'
      AND COLUMN_NAME = 'department_manager_id'
);

SET @ddl := IF(
    @column_exists = 0,
    'ALTER TABLE interview_schedules ADD COLUMN department_manager_id INT NULL AFTER interviewer_id',
    'SELECT ''department_manager_id already exists'' AS message'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'interview_schedules'
      AND COLUMN_NAME = 'manager_review_notes'
);

SET @ddl := IF(
    @column_exists = 0,
    'ALTER TABLE interview_schedules ADD COLUMN manager_review_notes TEXT NULL AFTER evaluation_notes',
    'SELECT ''manager_review_notes already exists'' AS message'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'interview_schedules'
      AND COLUMN_NAME = 'manager_decision'
);

SET @ddl := IF(
    @column_exists = 0,
    'ALTER TABLE interview_schedules ADD COLUMN manager_decision ENUM(''PASS'', ''FAIL'', ''PENDING'') NOT NULL DEFAULT ''PENDING'' AFTER manager_review_notes',
    'SELECT ''manager_decision already exists'' AS message'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'interview_schedules'
      AND COLUMN_NAME = 'reviewed_at'
);

SET @ddl := IF(
    @column_exists = 0,
    'ALTER TABLE interview_schedules ADD COLUMN reviewed_at DATETIME NULL AFTER manager_decision',
    'SELECT ''reviewed_at already exists'' AS message'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'interview_schedules'
      AND INDEX_NAME = 'idx_interview_department_manager'
);

SET @ddl := IF(
    @index_exists = 0,
    'ALTER TABLE interview_schedules ADD INDEX idx_interview_department_manager (department_manager_id)',
    'SELECT ''idx_interview_department_manager already exists'' AS message'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'interview_schedules'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
      AND CONSTRAINT_NAME = 'fk_interview_department_manager'
);

SET @ddl := IF(
    @fk_exists = 0,
    'ALTER TABLE interview_schedules ADD CONSTRAINT fk_interview_department_manager FOREIGN KEY (department_manager_id) REFERENCES employees(employee_id) ON DELETE SET NULL',
    'SELECT ''fk_interview_department_manager already exists'' AS message'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
