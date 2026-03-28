USE HRM_SYSTEM;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'employees'
      AND COLUMN_NAME = 'password_hash'
);

SET @ddl := IF(
    @column_exists = 0,
    'ALTER TABLE employees ADD COLUMN password_hash VARCHAR(255) NULL AFTER company_email',
    'SELECT ''password_hash already exists'' AS message'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
