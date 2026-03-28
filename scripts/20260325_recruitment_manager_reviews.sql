USE HRM_SYSTEM;

CREATE TABLE IF NOT EXISTS recruitment_candidate_manager_reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    manager_id INT NULL,
    workflow_status ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    manager_score DECIMAL(5,2) NULL,
    manager_review_notes TEXT NULL,
    suggested_interview_date DATE NULL,
    suggested_interview_time TIME NULL,
    reviewed_at DATETIME NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_recruitment_candidate_manager_review_candidate (candidate_id),
    INDEX idx_recruitment_candidate_manager_review_manager (manager_id),
    INDEX idx_recruitment_candidate_manager_review_status (workflow_status),
    CONSTRAINT fk_recruitment_candidate_manager_review_candidate
        FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates(candidate_id) ON DELETE CASCADE,
    CONSTRAINT fk_recruitment_candidate_manager_review_manager
        FOREIGN KEY (manager_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
    CONSTRAINT fk_recruitment_candidate_manager_review_created_by
        FOREIGN KEY (created_by) REFERENCES employees(employee_id) ON DELETE SET NULL,
    CONSTRAINT fk_recruitment_candidate_manager_review_updated_by
        FOREIGN KEY (updated_by) REFERENCES employees(employee_id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

USE HRM_SYSTEM;

INSERT INTO roles (role_code, role_name, description, is_system_role)
VALUES ('DIRECTOR', 'Giám đốc', 'Quyền giám đốc', TRUE)
ON DUPLICATE KEY UPDATE
  role_name = VALUES(role_name),
  description = VALUES(description),
  is_system_role = VALUES(is_system_role);

INSERT INTO employee_roles (employee_id, role_id, department_id, effective_date, expiry_date, is_active)
SELECT 5, r.role_id, 1, CURDATE(), NULL, TRUE
FROM roles r
WHERE r.role_code = 'DIRECTOR'
  AND NOT EXISTS (
    SELECT 1 FROM employee_roles er
    WHERE er.employee_id = 5 AND er.role_id = r.role_id AND er.is_active = TRUE
  );

INSERT INTO role_permissions (
  role_id, permission_id, can_access, can_create, can_edit, can_delete, can_approve, can_export
)
SELECT rd.role_id, rp.permission_id, rp.can_access, rp.can_create, rp.can_edit, rp.can_delete, rp.can_approve, rp.can_export
FROM roles rd
JOIN roles ra ON ra.role_code = 'ADMIN'
JOIN role_permissions rp ON rp.role_id = ra.role_id
WHERE rd.role_code = 'DIRECTOR'
ON DUPLICATE KEY UPDATE
  can_access = VALUES(can_access),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_approve = VALUES(can_approve),
  can_export = VALUES(can_export);
