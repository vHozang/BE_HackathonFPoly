USE HRM_SYSTEM;

-- ---------------------------------------------------------------------------
-- 1) Ensure permission codes exist (safe for old data.sql that misses many codes)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO permissions (permission_code, permission_name, module, description)
VALUES
    ('EMP_VIEW', 'View employees', 'NHAN_SU', 'View employee data'),
    ('EMP_CREATE', 'Create employees', 'NHAN_SU', 'Create employee'),
    ('EMP_EDIT', 'Edit employees', 'NHAN_SU', 'Edit employee'),
    ('EMP_DELETE', 'Delete employees', 'NHAN_SU', 'Delete employee'),
    ('DEPARTMENT_VIEW', 'View departments', 'NHAN_SU', 'View department data'),
    ('DEPARTMENT_EDIT', 'Manage departments', 'NHAN_SU', 'Create/update/delete departments'),
    ('LEAVE_VIEW', 'View leave', 'NGHI_PHEP', 'View leave/request data'),
    ('LEAVE_CREATE', 'Create leave', 'NGHI_PHEP', 'Create leave/request data'),
    ('LEAVE_EDIT', 'Edit leave', 'NGHI_PHEP', 'Edit leave/request data'),
    ('LEAVE_DELETE', 'Delete leave', 'NGHI_PHEP', 'Delete leave/request data'),
    ('SYSTEM_CONFIG', 'System config', 'HE_THONG', 'Manage settings'),
    ('ASSET_VIEW', 'View assets', 'TAI_SAN', 'View assets'),
    ('ASSET_CREATE', 'Create assets', 'TAI_SAN', 'Create assets'),
    ('ASSET_EDIT', 'Edit assets', 'TAI_SAN', 'Edit assets'),
    ('ASSET_ASSIGN', 'Assign assets', 'TAI_SAN', 'Assign assets'),
    ('ATTENDANCE_VIEW', 'View attendance', 'CHAM_CONG', 'View attendance'),
    ('ATTENDANCE_EDIT', 'Edit attendance', 'CHAM_CONG', 'Create/update attendance'),
    ('SALARY_VIEW', 'View salary', 'LUONG', 'View payroll'),
    ('SALARY_CALCULATE', 'Calculate salary', 'LUONG', 'Create/update payroll'),
    ('SALARY_APPROVE', 'Approve salary', 'LUONG', 'Approve/close payroll'),
    ('NEWS_VIEW', 'View news', 'TRUYEN_THONG', 'View news/policies'),
    ('NEWS_CREATE', 'Create news', 'TRUYEN_THONG', 'Create news'),
    ('NEWS_EDIT', 'Edit news', 'TRUYEN_THONG', 'Edit news'),
    ('NEWS_DELETE', 'Delete news', 'TRUYEN_THONG', 'Delete news');

-- ---------------------------------------------------------------------------
-- 2) Ensure role_permission matrix for EMPLOYEE/MANAGER + full access for ADMIN/HR
-- ---------------------------------------------------------------------------
INSERT INTO role_permissions (
    role_id, permission_id, can_access, can_create, can_edit, can_delete, can_approve, can_export
)
SELECT
    r.role_id,
    p.permission_id,
    x.can_access,
    x.can_create,
    x.can_edit,
    x.can_delete,
    x.can_approve,
    x.can_export
FROM (
    SELECT 'EMPLOYEE' AS role_code, 'EMP_VIEW' AS permission_code, 1 AS can_access, 0 AS can_create, 0 AS can_edit, 0 AS can_delete, 0 AS can_approve, 0 AS can_export
    UNION ALL SELECT 'EMPLOYEE', 'LEAVE_VIEW', 1, 0, 0, 0, 0, 0
    UNION ALL SELECT 'EMPLOYEE', 'LEAVE_CREATE', 1, 1, 1, 0, 0, 0
    UNION ALL SELECT 'EMPLOYEE', 'ATTENDANCE_VIEW', 1, 0, 0, 0, 0, 0
    UNION ALL SELECT 'EMPLOYEE', 'ATTENDANCE_EDIT', 1, 1, 1, 0, 0, 0
    UNION ALL SELECT 'EMPLOYEE', 'SALARY_VIEW', 1, 0, 0, 0, 0, 0
    UNION ALL SELECT 'EMPLOYEE', 'NEWS_VIEW', 1, 0, 0, 0, 0, 0
    UNION ALL SELECT 'EMPLOYEE', 'ASSET_VIEW', 1, 0, 0, 0, 0, 0
    UNION ALL SELECT 'EMPLOYEE', 'ASSET_ASSIGN', 1, 1, 0, 0, 0, 0
    UNION ALL SELECT 'MANAGER', 'EMP_VIEW', 1, 0, 0, 0, 0, 0
    UNION ALL SELECT 'MANAGER', 'EMP_EDIT', 1, 0, 1, 0, 0, 0
    UNION ALL SELECT 'MANAGER', 'DEPARTMENT_VIEW', 1, 0, 0, 0, 0, 0
    UNION ALL SELECT 'MANAGER', 'LEAVE_VIEW', 1, 0, 0, 0, 0, 0
    UNION ALL SELECT 'MANAGER', 'LEAVE_CREATE', 1, 1, 1, 0, 0, 0
    UNION ALL SELECT 'MANAGER', 'LEAVE_EDIT', 1, 0, 1, 0, 1, 0
    UNION ALL SELECT 'MANAGER', 'ATTENDANCE_VIEW', 1, 0, 0, 0, 0, 0
    UNION ALL SELECT 'MANAGER', 'ATTENDANCE_EDIT', 1, 1, 1, 0, 1, 0
    UNION ALL SELECT 'MANAGER', 'SALARY_VIEW', 1, 0, 0, 0, 0, 0
    UNION ALL SELECT 'MANAGER', 'NEWS_VIEW', 1, 0, 0, 0, 0, 0
    UNION ALL SELECT 'MANAGER', 'ASSET_VIEW', 1, 0, 0, 0, 0, 0
    UNION ALL SELECT 'MANAGER', 'ASSET_ASSIGN', 1, 1, 0, 0, 0, 0
) x
JOIN roles r ON r.role_code = x.role_code
JOIN permissions p ON p.permission_code = x.permission_code
ON DUPLICATE KEY UPDATE
    can_access = VALUES(can_access),
    can_create = VALUES(can_create),
    can_edit = VALUES(can_edit),
    can_delete = VALUES(can_delete),
    can_approve = VALUES(can_approve),
    can_export = VALUES(can_export);

INSERT INTO role_permissions (
    role_id, permission_id, can_access, can_create, can_edit, can_delete, can_approve, can_export
)
SELECT
    r.role_id,
    p.permission_id,
    1, 1, 1, 1, 1, 1
FROM roles r
JOIN permissions p ON 1 = 1
WHERE r.role_code IN ('ADMIN', 'HR')
ON DUPLICATE KEY UPDATE
    can_access = VALUES(can_access),
    can_create = VALUES(can_create),
    can_edit = VALUES(can_edit),
    can_delete = VALUES(can_delete),
    can_approve = VALUES(can_approve),
    can_export = VALUES(can_export);

-- ---------------------------------------------------------------------------
-- 3) Target demo users used on FE login
-- ---------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_seed_users;
CREATE TEMPORARY TABLE tmp_seed_users (
    employee_id INT PRIMARY KEY,
    employee_code VARCHAR(20) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    company_email VARCHAR(100) NOT NULL,
    role_code VARCHAR(50) NOT NULL
) ENGINE = MEMORY;

INSERT INTO tmp_seed_users (employee_id, employee_code, full_name, company_email, role_code)
SELECT employee_id, employee_code, full_name, company_email, 'ADMIN'
FROM employees
WHERE company_email = 'hai.do@company.com'
UNION ALL
SELECT employee_id, employee_code, full_name, company_email, 'HR'
FROM employees
WHERE company_email = 'mai.tran@company.com'
UNION ALL
SELECT employee_id, employee_code, full_name, company_email, 'MANAGER'
FROM employees
WHERE company_email = 'an.nguyen@company.com'
UNION ALL
SELECT employee_id, employee_code, full_name, company_email, 'EMPLOYEE'
FROM employees
WHERE company_email = 'huong.pham@company.com';

SET @seed_today = CURDATE();
SET @seed_period_code = 'SP-2026-03';
SET @seed_apply_month = '2026-03';
SET @seed_default_department_id = COALESCE((SELECT department_id FROM departments WHERE department_code = 'HCNS' LIMIT 1), (SELECT MIN(department_id) FROM departments));
SET @seed_default_position_id = COALESCE((SELECT position_id FROM positions WHERE position_code = 'CV' LIMIT 1), (SELECT MIN(position_id) FROM positions));
SET @seed_leave_type_id = COALESCE((SELECT leave_type_id FROM leave_types WHERE leave_type_code = 'PHEP_NAM' LIMIT 1), (SELECT MIN(leave_type_id) FROM leave_types));
SET @seed_leave_request_type_id = COALESCE((SELECT request_type_id FROM request_types WHERE request_type_code = 'NP' LIMIT 1), (SELECT MIN(request_type_id) FROM request_types));
SET @seed_ot_request_type_id = COALESCE((SELECT request_type_id FROM request_types WHERE request_type_code = 'TC' LIMIT 1), (SELECT MIN(request_type_id) FROM request_types));
SET @seed_shift_type_id = COALESCE((SELECT shift_type_id FROM shift_types WHERE shift_code = 'HC' LIMIT 1), (SELECT MIN(shift_type_id) FROM shift_types));
SET @seed_document_type_id = COALESCE((SELECT document_type_id FROM document_types WHERE document_type_code = 'CCCD' LIMIT 1), (SELECT MIN(document_type_id) FROM document_types));
SET @seed_qualification_type_id = (SELECT MIN(qualification_type_id) FROM qualification_types);
SET @seed_certificate_type_id = (SELECT MIN(certificate_type_id) FROM certificate_types);
SET @seed_sender_id = (SELECT employee_id FROM tmp_seed_users WHERE role_code = 'ADMIN' LIMIT 1);

-- ---------------------------------------------------------------------------
-- 4) Ensure user roles for demo accounts
-- ---------------------------------------------------------------------------
INSERT INTO employee_roles (
    employee_id, role_id, department_id, effective_date, expiry_date, is_active
)
SELECT
    su.employee_id,
    r.role_id,
    COALESCE(eh.department_id, @seed_default_department_id),
    @seed_today,
    NULL,
    1
FROM tmp_seed_users su
JOIN roles r ON r.role_code = su.role_code
LEFT JOIN employment_histories eh ON eh.employee_id = su.employee_id AND eh.is_current = 1
WHERE NOT EXISTS (
    SELECT 1
    FROM employee_roles er
    WHERE er.employee_id = su.employee_id
      AND er.role_id = r.role_id
      AND er.department_id <=> COALESCE(eh.department_id, @seed_default_department_id)
);

INSERT INTO employee_roles (
    employee_id, role_id, department_id, effective_date, expiry_date, is_active
)
SELECT
    su.employee_id,
    r.role_id,
    COALESCE(eh.department_id, @seed_default_department_id),
    @seed_today,
    NULL,
    1
FROM tmp_seed_users su
JOIN roles r ON r.role_code = 'EMPLOYEE'
LEFT JOIN employment_histories eh ON eh.employee_id = su.employee_id AND eh.is_current = 1
WHERE NOT EXISTS (
    SELECT 1
    FROM employee_roles er
    WHERE er.employee_id = su.employee_id
      AND er.role_id = r.role_id
      AND er.department_id <=> COALESCE(eh.department_id, @seed_default_department_id)
);

-- ---------------------------------------------------------------------------
-- 5) Ensure profile completeness: employment, identity, insurance, qualifications
-- ---------------------------------------------------------------------------
INSERT INTO employment_histories (
    employee_id, department_id, position_id, start_date, end_date, is_current, decision_number, decision_date, notes
)
SELECT
    su.employee_id,
    @seed_default_department_id,
    @seed_default_position_id,
    DATE_SUB(@seed_today, INTERVAL 365 DAY),
    NULL,
    1,
    CONCAT('SEED-EH-', su.employee_code),
    @seed_today,
    'seed_full_user_demo_data'
FROM tmp_seed_users su
WHERE NOT EXISTS (
    SELECT 1
    FROM employment_histories eh
    WHERE eh.employee_id = su.employee_id
      AND eh.is_current = 1
);

INSERT INTO identity_documents (
    employee_id, document_type_id, document_number, full_name, date_of_birth, issue_date, issue_place, expiry_date, has_chip
)
SELECT
    su.employee_id,
    @seed_document_type_id,
    CONCAT('SEED', LPAD(su.employee_id, 8, '0')),
    su.full_name,
    DATE_SUB(@seed_today, INTERVAL 30 YEAR),
    DATE_SUB(@seed_today, INTERVAL 2 YEAR),
    'Cong an TP.HCM',
    DATE_ADD(@seed_today, INTERVAL 18 YEAR),
    1
FROM tmp_seed_users su
WHERE @seed_document_type_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM identity_documents i
      WHERE i.employee_id = su.employee_id
  );

INSERT INTO social_insurance_info (
    employee_id, social_insurance_number, health_insurance_number, tax_code, issue_date, issue_place, status
)
SELECT
    su.employee_id,
    CONCAT('BHXH-SEED-', LPAD(su.employee_id, 5, '0')),
    CONCAT('BHYT-SEED-', LPAD(su.employee_id, 5, '0')),
    CONCAT('TAXSEED', LPAD(su.employee_id, 5, '0')),
    DATE_SUB(@seed_today, INTERVAL 5 YEAR),
    'BHXH TP.HCM',
    'ACTIVE'
FROM tmp_seed_users su
WHERE NOT EXISTS (
    SELECT 1
    FROM social_insurance_info s
    WHERE s.employee_id = su.employee_id
);

INSERT INTO dependents (
    employee_id, full_name, relationship, date_of_birth, id_card_number, deduction_percent, start_date, status
)
SELECT
    su.employee_id,
    CONCAT('Seed dependent ', su.employee_code),
    'Nguoi than',
    DATE_SUB(@seed_today, INTERVAL 10 YEAR),
    CONCAT('DP', LPAD(su.employee_id, 8, '0')),
    100.00,
    DATE_SUB(@seed_today, INTERVAL 2 YEAR),
    1
FROM tmp_seed_users su
WHERE NOT EXISTS (
    SELECT 1
    FROM dependents d
    WHERE d.employee_id = su.employee_id
);

INSERT INTO qualifications (
    employee_id, qualification_type_id, qualification_name, major, school_name, graduation_year, graduation_grade, issued_date, issued_by, qualification_number, is_highest
)
SELECT
    su.employee_id,
    @seed_qualification_type_id,
    CONCAT('Seed qualification ', su.employee_code),
    'Business Administration',
    'Seed University',
    YEAR(DATE_SUB(@seed_today, INTERVAL 8 YEAR)),
    'Gioi',
    DATE_SUB(@seed_today, INTERVAL 8 YEAR),
    'Seed University',
    CONCAT('QLF', LPAD(su.employee_id, 6, '0')),
    1
FROM tmp_seed_users su
WHERE @seed_qualification_type_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM qualifications q
      WHERE q.employee_id = su.employee_id
  );

INSERT INTO certificates (
    employee_id, certificate_type_id, certificate_name, issued_by, issued_date, expiry_date, certificate_number, score
)
SELECT
    su.employee_id,
    @seed_certificate_type_id,
    CONCAT('Seed certificate ', su.employee_code),
    'Seed Center',
    DATE_SUB(@seed_today, INTERVAL 2 YEAR),
    DATE_ADD(@seed_today, INTERVAL 1 YEAR),
    CONCAT('CER', LPAD(su.employee_id, 6, '0')),
    850.00
FROM tmp_seed_users su
WHERE @seed_certificate_type_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM certificates c
      WHERE c.employee_id = su.employee_id
  );

-- ---------------------------------------------------------------------------
-- 6) Ensure salary period + salary details + breakdown + adjustments
-- ---------------------------------------------------------------------------
INSERT INTO salary_periods (
    period_code, period_name, period_type, year, month, start_date, end_date, payment_date, standard_working_days, status, notes
)
SELECT
    @seed_period_code,
    'Salary Period March 2026',
    'MONTHLY',
    2026,
    3,
    '2026-03-01',
    '2026-03-31',
    '2026-04-05',
    26,
    'OPEN',
    'seed_full_user_demo_data'
WHERE NOT EXISTS (
    SELECT 1
    FROM salary_periods sp
    WHERE sp.period_code = @seed_period_code
);

SET @seed_period_id = (SELECT period_id FROM salary_periods WHERE period_code = @seed_period_code LIMIT 1);

INSERT INTO salary_details (
    period_id, employee_id, contract_id, basic_salary, gross_salary, net_salary,
    total_allowances, total_deductions, overtime_pay, leave_pay, bonus, penalty,
    personal_income_tax, advance_payment, bank_account, bank_name, transfer_status, notes
)
SELECT
    @seed_period_id,
    su.employee_id,
    lc.contract_id,
    CASE su.role_code
        WHEN 'ADMIN' THEN 35000000
        WHEN 'HR' THEN 24000000
        WHEN 'MANAGER' THEN 30000000
        ELSE 17000000
    END AS basic_salary,
    CASE su.role_code
        WHEN 'ADMIN' THEN 43000000
        WHEN 'HR' THEN 30000000
        WHEN 'MANAGER' THEN 37500000
        ELSE 21500000
    END AS gross_salary,
    CASE su.role_code
        WHEN 'ADMIN' THEN 39200000
        WHEN 'HR' THEN 27300000
        WHEN 'MANAGER' THEN 34100000
        ELSE 19400000
    END AS net_salary,
    CASE su.role_code
        WHEN 'ADMIN' THEN 4500000
        WHEN 'HR' THEN 2800000
        WHEN 'MANAGER' THEN 3900000
        ELSE 1800000
    END AS total_allowances,
    CASE su.role_code
        WHEN 'ADMIN' THEN 700000
        WHEN 'HR' THEN 500000
        WHEN 'MANAGER' THEN 600000
        ELSE 400000
    END AS total_deductions,
    CASE su.role_code
        WHEN 'ADMIN' THEN 1200000
        WHEN 'HR' THEN 700000
        WHEN 'MANAGER' THEN 900000
        ELSE 500000
    END AS overtime_pay,
    0 AS leave_pay,
    CASE su.role_code
        WHEN 'ADMIN' THEN 1500000
        WHEN 'HR' THEN 900000
        WHEN 'MANAGER' THEN 1200000
        ELSE 700000
    END AS bonus,
    0 AS penalty,
    CASE su.role_code
        WHEN 'ADMIN' THEN 2800000
        WHEN 'HR' THEN 1700000
        WHEN 'MANAGER' THEN 2300000
        ELSE 1200000
    END AS personal_income_tax,
    0 AS advance_payment,
    COALESCE(NULLIF(e.bank_account, ''), CONCAT('SEED', LPAD(su.employee_id, 8, '0'))),
    COALESCE(b.bank_name, 'Vietcombank'),
    'PENDING',
    'seed_full_user_demo_data'
FROM tmp_seed_users su
JOIN employees e ON e.employee_id = su.employee_id
LEFT JOIN banks b ON b.bank_id = e.bank_id
LEFT JOIN (
    SELECT c.employee_id, c.contract_id
    FROM contracts c
    JOIN (
        SELECT employee_id, MAX(contract_id) AS max_contract_id
        FROM contracts
        GROUP BY employee_id
    ) lc2 ON lc2.max_contract_id = c.contract_id
) lc ON lc.employee_id = su.employee_id
WHERE @seed_period_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM salary_details sd
      WHERE sd.period_id = @seed_period_id
        AND sd.employee_id = su.employee_id
  );

INSERT INTO salary_breakdowns (
    salary_detail_id, item_type, item_id, item_name, amount, is_taxable, is_insurable, description
)
SELECT
    sd.salary_detail_id,
    'ALLOWANCE',
    NULL,
    'Seed allowance',
    sd.total_allowances,
    1,
    1,
    'seed_full_user_demo_data'
FROM salary_details sd
JOIN tmp_seed_users su ON su.employee_id = sd.employee_id
WHERE sd.period_id = @seed_period_id
  AND sd.total_allowances > 0
  AND NOT EXISTS (
      SELECT 1
      FROM salary_breakdowns sb
      WHERE sb.salary_detail_id = sd.salary_detail_id
        AND sb.item_name = 'Seed allowance'
  );

INSERT INTO salary_breakdowns (
    salary_detail_id, item_type, item_id, item_name, amount, is_taxable, is_insurable, description
)
SELECT
    sd.salary_detail_id,
    'DEDUCTION',
    NULL,
    'Seed PIT',
    sd.personal_income_tax,
    1,
    0,
    'seed_full_user_demo_data'
FROM salary_details sd
JOIN tmp_seed_users su ON su.employee_id = sd.employee_id
WHERE sd.period_id = @seed_period_id
  AND sd.personal_income_tax > 0
  AND NOT EXISTS (
      SELECT 1
      FROM salary_breakdowns sb
      WHERE sb.salary_detail_id = sd.salary_detail_id
        AND sb.item_name = 'Seed PIT'
  );

INSERT INTO payroll_adjustments (
    employee_id, amount, description, apply_month, status
)
SELECT
    su.employee_id,
    CASE su.role_code
        WHEN 'ADMIN' THEN 600000
        WHEN 'HR' THEN 350000
        WHEN 'MANAGER' THEN 450000
        ELSE 250000
    END AS amount,
    'seed_full_user_demo_data adjustment',
    @seed_apply_month,
    0
FROM tmp_seed_users su
WHERE NOT EXISTS (
    SELECT 1
    FROM payroll_adjustments pa
    WHERE pa.employee_id = su.employee_id
      AND pa.apply_month = @seed_apply_month
      AND pa.description = 'seed_full_user_demo_data adjustment'
);

-- ---------------------------------------------------------------------------
-- 7) Attendance, requests (leave + overtime), notifications
-- ---------------------------------------------------------------------------
INSERT INTO attendances (
    employee_id, attendance_date, shift_type_id, check_in_time, check_out_time,
    check_in_method, check_out_method, work_type, actual_working_hours, overtime_hours,
    late_minutes, early_leave_minutes, is_holiday, is_overtime, status, notes
)
SELECT
    su.employee_id,
    '2026-03-24',
    @seed_shift_type_id,
    '2026-03-24 08:05:00',
    '2026-03-24 17:20:00',
    'MOBILE',
    'MOBILE',
    1,
    8.5,
    1.0,
    5,
    0,
    0,
    1,
    2,
    'seed_full_user_demo_data'
FROM tmp_seed_users su
WHERE @seed_shift_type_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM attendances a
      WHERE a.employee_id = su.employee_id
        AND a.attendance_date = '2026-03-24'
  );

INSERT INTO attendances (
    employee_id, attendance_date, shift_type_id, check_in_time, check_out_time,
    check_in_method, check_out_method, work_type, actual_working_hours, overtime_hours,
    late_minutes, early_leave_minutes, is_holiday, is_overtime, status, notes
)
SELECT
    su.employee_id,
    '2026-03-25',
    @seed_shift_type_id,
    '2026-03-25 08:00:00',
    '2026-03-25 17:05:00',
    'MOBILE',
    'MOBILE',
    1,
    8.0,
    0.5,
    0,
    0,
    0,
    1,
    2,
    'seed_full_user_demo_data'
FROM tmp_seed_users su
WHERE @seed_shift_type_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM attendances a
      WHERE a.employee_id = su.employee_id
        AND a.attendance_date = '2026-03-25'
  );

INSERT INTO requests (
    request_code, request_type_id, requester_id, request_date, from_date, to_date,
    duration, reason, status, created_by, updated_by
)
SELECT
    CONCAT('LEAVE-SEED-20260326-', su.employee_id),
    @seed_leave_request_type_id,
    su.employee_id,
    '2026-03-23',
    '2026-03-28 08:00:00',
    '2026-03-28 17:00:00',
    1.0,
    'seed_full_user_demo_data leave request',
    2,
    su.employee_id,
    su.employee_id
FROM tmp_seed_users su
WHERE @seed_leave_request_type_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM requests r
      WHERE r.request_code = CONCAT('LEAVE-SEED-20260326-', su.employee_id)
  );

INSERT INTO leave_requests (
    request_id, leave_type_id, employee_id, from_date, to_date,
    number_of_days, base_days_used, paid_days, unpaid_days, handover_notes
)
SELECT
    r.request_id,
    @seed_leave_type_id,
    su.employee_id,
    '2026-03-28',
    '2026-03-28',
    1.0,
    1.0,
    1.0,
    0.0,
    'seed_full_user_demo_data handover'
FROM tmp_seed_users su
JOIN requests r ON r.request_code = CONCAT('LEAVE-SEED-20260326-', su.employee_id)
WHERE @seed_leave_type_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM leave_requests lr
      WHERE lr.request_id = r.request_id
  );

INSERT INTO requests (
    request_code, request_type_id, requester_id, request_date, from_date, to_date,
    duration, reason, status, created_by, updated_by
)
SELECT
    CONCAT('OT-SEED-20260326-', su.employee_id),
    @seed_ot_request_type_id,
    su.employee_id,
    '2026-03-25',
    '2026-03-25 18:00:00',
    '2026-03-25 20:30:00',
    2.5,
    'seed_full_user_demo_data overtime request',
    2,
    su.employee_id,
    su.employee_id
FROM tmp_seed_users su
WHERE @seed_ot_request_type_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM requests r
      WHERE r.request_code = CONCAT('OT-SEED-20260326-', su.employee_id)
  );

INSERT INTO overtime_requests (
    request_id, employee_id, overtime_date, start_time, end_time, break_time, reason, status
)
SELECT
    r.request_id,
    su.employee_id,
    '2026-03-25',
    '18:00:00',
    '20:30:00',
    15,
    'seed_full_user_demo_data overtime',
    1
FROM tmp_seed_users su
JOIN requests r ON r.request_code = CONCAT('OT-SEED-20260326-', su.employee_id)
WHERE NOT EXISTS (
    SELECT 1
    FROM overtime_requests o
    WHERE o.request_id = r.request_id
);

INSERT INTO leave_balances (
    employee_id, leave_type_id, year, base_leave, seniority_bonus, total_days, carried_over_days,
    carried_over_source, used_days, pending_days, carry_over_expiry_date, notes
)
SELECT
    su.employee_id,
    @seed_leave_type_id,
    2026,
    12.00,
    1.00,
    13.00,
    2.00,
    '2025',
    1.00,
    0.00,
    '2026-03-31',
    'seed_full_user_demo_data'
FROM tmp_seed_users su
WHERE @seed_leave_type_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM leave_balances lb
      WHERE lb.employee_id = su.employee_id
        AND lb.leave_type_id = @seed_leave_type_id
        AND lb.year = 2026
  );

INSERT INTO notifications (
    notification_type, title, content, sender_id, receiver_id, is_read, priority,
    reference_type, action_url, expires_at
)
SELECT
    'SYSTEM',
    '[SEED] Welcome dashboard',
    CONCAT('Hello ', su.full_name, ', this is seeded notification data.'),
    @seed_sender_id,
    su.employee_id,
    0,
    2,
    'NEWS',
    '/portal/truyen-thong/thong-bao',
    DATE_ADD(NOW(), INTERVAL 30 DAY)
FROM tmp_seed_users su
WHERE NOT EXISTS (
    SELECT 1
    FROM notifications n
    WHERE n.receiver_id = su.employee_id
      AND n.title = '[SEED] Welcome dashboard'
);

INSERT INTO notifications (
    notification_type, title, content, sender_id, receiver_id, is_read, priority,
    reference_type, action_url, expires_at
)
SELECT
    'PAYROLL',
    '[SEED] Payroll ready',
    'Salary slip for period SP-2026-03 is now available.',
    @seed_sender_id,
    su.employee_id,
    0,
    2,
    'ATTENDANCE',
    '/nhanvien/luong',
    DATE_ADD(NOW(), INTERVAL 45 DAY)
FROM tmp_seed_users su
WHERE NOT EXISTS (
    SELECT 1
    FROM notifications n
    WHERE n.receiver_id = su.employee_id
      AND n.title = '[SEED] Payroll ready'
);

INSERT INTO dashboard_views (employee_id, view_date, view_type)
SELECT
    su.employee_id,
    @seed_today,
    CASE su.role_code
        WHEN 'MANAGER' THEN 'MANAGER'
        WHEN 'HR' THEN 'HR'
        ELSE 'EMPLOYEE'
    END
FROM tmp_seed_users su
WHERE NOT EXISTS (
    SELECT 1
    FROM dashboard_views dv
    WHERE dv.employee_id = su.employee_id
      AND dv.view_date = @seed_today
);

DROP TEMPORARY TABLE IF EXISTS tmp_seed_users;
