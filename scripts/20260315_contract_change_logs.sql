USE HRM_SYSTEM;

CREATE TABLE IF NOT EXISTS contract_change_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NULL,
    contract_no VARCHAR(50) NOT NULL,
    employee_name VARCHAR(120) NOT NULL,
    action_type VARCHAR(30) NOT NULL,
    content VARCHAR(255) NOT NULL,
    icon VARCHAR(30) NOT NULL DEFAULT 'edit',
    bg_class VARCHAR(50) NOT NULL DEFAULT 'bg-slate-500',
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contract_change_logs_created_at (created_at),
    INDEX idx_contract_change_logs_contract_no (contract_no),
    INDEX idx_contract_change_logs_action_type (action_type)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
