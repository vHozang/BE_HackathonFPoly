USE HRM_SYSTEM;

/* positions.position_group + positions.position_level */
SET @position_group_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'positions'
      AND COLUMN_NAME = 'position_group'
);

SET @ddl := IF(
    @position_group_exists = 0,
    'ALTER TABLE positions ADD COLUMN position_group VARCHAR(100) NULL AFTER position_name',
    'SELECT ''position_group already exists'' AS message'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @position_level_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'positions'
      AND COLUMN_NAME = 'position_level'
);

SET @ddl := IF(
    @position_level_exists = 0,
    'ALTER TABLE positions ADD COLUMN position_level VARCHAR(50) NULL AFTER position_group',
    'SELECT ''position_level already exists'' AS message'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

/* recruitment module */
CREATE TABLE IF NOT EXISTS recruitment_positions (
    recruitment_position_id INT AUTO_INCREMENT PRIMARY KEY,
    position_code VARCHAR(30) NOT NULL,
    position_name VARCHAR(150) NOT NULL,
    department_id INT NULL,
    employment_type ENUM('FULL_TIME', 'PART_TIME', 'CONTRACT', 'INTERN') NOT NULL DEFAULT 'FULL_TIME',
    vacancy_count INT NOT NULL DEFAULT 1,
    description TEXT NULL,
    status ENUM('OPEN', 'CLOSED', 'ON_HOLD') NOT NULL DEFAULT 'OPEN',
    opened_at DATE NULL,
    closed_at DATE NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_recruitment_position_code (position_code),
    INDEX idx_recruitment_position_status (status),
    INDEX idx_recruitment_position_department (department_id),
    CONSTRAINT fk_recruitment_position_department FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
    CONSTRAINT fk_recruitment_position_created_by FOREIGN KEY (created_by) REFERENCES employees(employee_id) ON DELETE SET NULL,
    CONSTRAINT fk_recruitment_position_updated_by FOREIGN KEY (updated_by) REFERENCES employees(employee_id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_candidates (
    candidate_id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_code VARCHAR(30) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NULL,
    phone_number VARCHAR(30) NULL,
    recruitment_position_id INT NOT NULL,
    cv_url VARCHAR(500) NULL,
    source_channel VARCHAR(100) NULL,
    ai_score DECIMAL(5, 2) NULL,
    application_status ENUM('NEW', 'SCREENING', 'INTERVIEWING', 'PASSED', 'REJECTED', 'HIRED') NOT NULL DEFAULT 'NEW',
    applied_at DATE NOT NULL,
    notes TEXT NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_candidate_code (candidate_code),
    INDEX idx_candidate_status (application_status),
    INDEX idx_candidate_position (recruitment_position_id),
    CONSTRAINT fk_candidate_position FOREIGN KEY (recruitment_position_id) REFERENCES recruitment_positions(recruitment_position_id),
    CONSTRAINT fk_candidate_created_by FOREIGN KEY (created_by) REFERENCES employees(employee_id) ON DELETE SET NULL,
    CONSTRAINT fk_candidate_updated_by FOREIGN KEY (updated_by) REFERENCES employees(employee_id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS interview_schedules (
    interview_id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    interviewer_id INT NULL,
    interview_date DATE NOT NULL,
    interview_time TIME NOT NULL,
    interview_mode ENUM('ONLINE', 'OFFLINE') NOT NULL DEFAULT 'ONLINE',
    meeting_link VARCHAR(500) NULL,
    location VARCHAR(255) NULL,
    status ENUM('SCHEDULED', 'COMPLETED', 'CANCELED') NOT NULL DEFAULT 'SCHEDULED',
    result ENUM('PASS', 'FAIL', 'PENDING') NOT NULL DEFAULT 'PENDING',
    evaluation_notes TEXT NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_interview_candidate (candidate_id),
    INDEX idx_interview_status (status),
    INDEX idx_interview_interviewer (interviewer_id),
    CONSTRAINT fk_interview_candidate FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates(candidate_id) ON DELETE CASCADE,
    CONSTRAINT fk_interview_interviewer FOREIGN KEY (interviewer_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
    CONSTRAINT fk_interview_created_by FOREIGN KEY (created_by) REFERENCES employees(employee_id) ON DELETE SET NULL,
    CONSTRAINT fk_interview_updated_by FOREIGN KEY (updated_by) REFERENCES employees(employee_id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

/* internal service ticket module */
CREATE TABLE IF NOT EXISTS service_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_code VARCHAR(30) NOT NULL,
    category_name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_service_category_code (category_code)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_tickets (
    ticket_id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_code VARCHAR(30) NOT NULL,
    requester_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    priority ENUM('LOW', 'MEDIUM', 'HIGH', 'URGENT') NOT NULL DEFAULT 'MEDIUM',
    status ENUM('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED') NOT NULL DEFAULT 'OPEN',
    assigned_to INT NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_service_ticket_code (ticket_code),
    INDEX idx_service_ticket_requester (requester_id),
    INDEX idx_service_ticket_status (status),
    INDEX idx_service_ticket_assignee (assigned_to),
    INDEX idx_service_ticket_category (category_id),
    CONSTRAINT fk_service_ticket_requester FOREIGN KEY (requester_id) REFERENCES employees(employee_id),
    CONSTRAINT fk_service_ticket_category FOREIGN KEY (category_id) REFERENCES service_categories(category_id),
    CONSTRAINT fk_service_ticket_assignee FOREIGN KEY (assigned_to) REFERENCES employees(employee_id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_ticket_updates (
    update_id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    action_type ENUM('COMMENT', 'STATUS_CHANGE', 'ASSIGNMENT', 'SYSTEM') NOT NULL DEFAULT 'COMMENT',
    old_status ENUM('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED') NULL,
    new_status ENUM('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED') NULL,
    content TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_service_ticket_update_ticket (ticket_id),
    INDEX idx_service_ticket_update_created (created_at),
    CONSTRAINT fk_service_ticket_update_ticket FOREIGN KEY (ticket_id) REFERENCES service_tickets(ticket_id) ON DELETE CASCADE,
    CONSTRAINT fk_service_ticket_update_created_by FOREIGN KEY (created_by) REFERENCES employees(employee_id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

/* default seeds */
INSERT INTO service_categories (category_code, category_name, description, status)
VALUES
    ('IT_SUPPORT', 'Hỗ trợ IT', 'Lỗi máy tính, phần mềm, email công ty', TRUE),
    ('FACILITY', 'Cơ sở vật chất', 'Điện, nước, điều hòa, bàn ghế', TRUE),
    ('HR_SUPPORT', 'Hỗ trợ nhân sự', 'Giấy tờ, xác nhận, thủ tục nội bộ', TRUE)
ON DUPLICATE KEY UPDATE
    category_name = VALUES(category_name),
    description = VALUES(description),
    status = VALUES(status);

INSERT INTO notification_configs (
    notification_type,
    is_enabled,
    send_email,
    send_in_app,
    days_before_trigger,
    recipients,
    email_template,
    in_app_template
)
SELECT 'SENIORITY_ALERT', 1, 1, 1, 30, 'HR', 'Thông báo thâm niên', 'Thông báo thâm niên'
WHERE NOT EXISTS (
    SELECT 1 FROM notification_configs WHERE notification_type = 'SENIORITY_ALERT'
);

INSERT INTO notification_configs (
    notification_type,
    is_enabled,
    send_email,
    send_in_app,
    days_before_trigger,
    recipients,
    email_template,
    in_app_template
)
SELECT 'LEAVE_EXPIRY', 1, 1, 1, 7, 'EMPLOYEE', 'Thông báo phép sắp hết hạn', 'Thông báo phép sắp hết hạn'
WHERE NOT EXISTS (
    SELECT 1 FROM notification_configs WHERE notification_type = 'LEAVE_EXPIRY'
);

INSERT INTO notification_configs (
    notification_type,
    is_enabled,
    send_email,
    send_in_app,
    days_before_trigger,
    recipients,
    email_template,
    in_app_template
)
SELECT 'CONTRACT_EXPIRY', 1, 1, 1, 30, 'HR', 'Thông báo hợp đồng sắp hết hạn', 'Thông báo hợp đồng sắp hết hạn'
WHERE NOT EXISTS (
    SELECT 1 FROM notification_configs WHERE notification_type = 'CONTRACT_EXPIRY'
);
