USE HRM_SYSTEM;

CREATE TABLE IF NOT EXISTS payroll_adjustments (
    adjustment_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    apply_month CHAR(7) NOT NULL,
    status TINYINT(1) NOT NULL DEFAULT 0,
    paid_salary_detail_id INT NULL,
    paid_period_id INT NULL,
    paid_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_adjustment_lookup (employee_id, apply_month, status),
    INDEX idx_adjustment_paid_detail (paid_salary_detail_id),
    INDEX idx_adjustment_paid_period (paid_period_id),
    CONSTRAINT fk_adjustment_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    CONSTRAINT fk_adjustment_salary_detail FOREIGN KEY (paid_salary_detail_id) REFERENCES salary_details(salary_detail_id) ON DELETE SET NULL,
    CONSTRAINT fk_adjustment_salary_period FOREIGN KEY (paid_period_id) REFERENCES salary_periods(period_id) ON DELETE SET NULL,
    CONSTRAINT chk_adjustment_status CHECK (status IN (0, 1)),
    CONSTRAINT chk_adjustment_apply_month CHECK (apply_month REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$')
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
