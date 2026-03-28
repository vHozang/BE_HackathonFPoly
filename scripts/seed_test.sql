USE HRM_SYSTEM;

SET @seed_employee_id = (SELECT MIN(employee_id) FROM employees);
SET @seed_shift_id = (
    SELECT COALESCE(
        (
            SELECT shift_type_id
            FROM shift_types
            WHERE shift_code = 'HC'
            LIMIT 1
        ),
        (
            SELECT MIN(shift_type_id)
            FROM shift_types
        )
    )
);
SET @seed_ot_request_type_id = (
    SELECT COALESCE(
        (
            SELECT request_type_id
            FROM request_types
            WHERE request_type_code = 'TC'
            LIMIT 1
        ),
        (
            SELECT MIN(request_type_id)
            FROM request_types
        )
    )
);
SET @seed_contract_id = (
    SELECT contract_id
    FROM contracts
    WHERE employee_id = @seed_employee_id
    ORDER BY contract_id DESC
    LIMIT 1
);

-- Attendance seed
INSERT INTO attendances (
    employee_id,
    attendance_date,
    shift_type_id,
    check_in_time,
    check_out_time,
    check_in_method,
    check_out_method,
    work_type,
    actual_working_hours,
    overtime_hours,
    late_minutes,
    early_leave_minutes,
    is_holiday,
    is_overtime,
    status,
    notes
)
SELECT
    @seed_employee_id,
    '2025-01-15',
    @seed_shift_id,
    '2025-01-15 08:03:00',
    '2025-01-15 17:12:00',
    'MOBILE',
    'MOBILE',
    1, -- work_type enum index: VAN_PHONG
    8.5,
    1.0,
    3,
    0,
    0,
    1,
    2, -- status enum index: DA_DUYET
    'seed_test attendance'
WHERE NOT EXISTS (
    SELECT 1
    FROM attendances
    WHERE employee_id = @seed_employee_id
      AND attendance_date = '2025-01-15'
)
AND @seed_employee_id IS NOT NULL
AND @seed_shift_id IS NOT NULL;

-- Overtime seed (requires request)
INSERT INTO requests (
    request_code,
    request_type_id,
    requester_id,
    request_date,
    from_date,
    to_date,
    duration,
    reason,
    status,
    created_by,
    updated_by
)
SELECT
    'OT-SEED-20250115',
    @seed_ot_request_type_id,
    @seed_employee_id,
    '2025-01-15',
    '2025-01-15 18:00:00',
    '2025-01-15 21:00:00',
    3.0,
    'seed_test overtime request',
    2, -- requests status enum index: CHO_DUYET
    @seed_employee_id,
    @seed_employee_id
WHERE NOT EXISTS (
    SELECT 1
    FROM requests
    WHERE request_code = 'OT-SEED-20250115'
)
AND @seed_employee_id IS NOT NULL;

INSERT INTO overtime_requests (
    request_id,
    employee_id,
    overtime_date,
    start_time,
    end_time,
    break_time,
    reason,
    status
)
SELECT
    r.request_id,
    @seed_employee_id,
    '2025-01-15',
    '18:00:00',
    '21:00:00',
    15,
    'seed_test overtime',
    1 -- overtime status enum index: CHO_DUYET
FROM requests r
WHERE r.request_code = 'OT-SEED-20250115'
  AND NOT EXISTS (
      SELECT 1
      FROM overtime_requests o
      WHERE o.request_id = r.request_id
  )
  AND @seed_employee_id IS NOT NULL;

-- Payroll seed
INSERT INTO salary_periods (
    period_code,
    period_name,
    period_type,
    year,
    month,
    start_date,
    end_date,
    payment_date,
    standard_working_days,
    status,
    notes
)
SELECT
    'SP-2025-01',
    'Salary Period Jan 2025',
    'MONTHLY',
    2025,
    1,
    '2025-01-01',
    '2025-01-31',
    '2025-02-05',
    26,
    'OPEN',
    'seed_test salary period'
WHERE NOT EXISTS (
    SELECT 1
    FROM salary_periods
    WHERE period_code = 'SP-2025-01'
);

INSERT INTO salary_details (
    period_id,
    employee_id,
    contract_id,
    basic_salary,
    gross_salary,
    net_salary,
    total_allowances,
    total_deductions,
    overtime_pay,
    leave_pay,
    bonus,
    penalty,
    personal_income_tax,
    advance_payment,
    bank_account,
    bank_name,
    transfer_status,
    notes
)
SELECT
    sp.period_id,
    @seed_employee_id,
    @seed_contract_id,
    25000000,
    35000000,
    30000000,
    2000000,
    500000,
    1000000,
    0,
    0,
    0,
    1500000,
    0,
    '123456789',
    'VCB',
    'PENDING',
    'seed_test salary detail'
FROM salary_periods sp
WHERE sp.period_code = 'SP-2025-01'
  AND NOT EXISTS (
      SELECT 1
      FROM salary_details sd
      WHERE sd.period_id = sp.period_id
        AND sd.employee_id = @seed_employee_id
  )
  AND @seed_employee_id IS NOT NULL;

INSERT INTO salary_breakdowns (
    salary_detail_id,
    item_type,
    item_id,
    item_name,
    amount,
    is_taxable,
    is_insurable,
    description
)
SELECT
    sd.salary_detail_id,
    'BONUS',
    NULL,
    'Seed Bonus',
    500000,
    1,
    0,
    'seed_test breakdown'
FROM salary_details sd
JOIN salary_periods sp ON sp.period_id = sd.period_id
WHERE sp.period_code = 'SP-2025-01'
  AND sd.employee_id = @seed_employee_id
  AND NOT EXISTS (
      SELECT 1
      FROM salary_breakdowns sb
      WHERE sb.salary_detail_id = sd.salary_detail_id
        AND sb.item_name = 'Seed Bonus'
  )
  AND @seed_employee_id IS NOT NULL;

INSERT INTO payroll_adjustments (
    employee_id,
    amount,
    description,
    apply_month,
    status
)
SELECT
    @seed_employee_id,
    450000,
    'BHXH chi tra che do om dau thang 03',
    '2025-01',
    0
WHERE NOT EXISTS (
    SELECT 1
    FROM payroll_adjustments pa
    WHERE pa.employee_id = @seed_employee_id
      AND pa.apply_month = '2025-01'
      AND pa.description = 'BHXH chi tra che do om dau thang 03'
)
AND @seed_employee_id IS NOT NULL;

-- News / Policy seed
INSERT INTO news_categories (category_code, category_name, description, status)
SELECT
    'API',
    'API Updates',
    'Seeded category for API testing',
    1
WHERE NOT EXISTS (
    SELECT 1
    FROM news_categories
    WHERE category_code = 'API'
);

INSERT INTO news (
    news_code,
    category_id,
    title,
    summary,
    content,
    priority,
    is_important,
    is_pinned,
    published_date,
    published_by,
    status,
    created_by,
    updated_by
)
SELECT
    'NEWS-SEED-001',
    nc.category_id,
    'API Seed News',
    'Seed summary',
    'Seed content for API testing',
    2, -- priority enum index: TRUNG_BINH
    0,
    0,
    NOW(),
    @seed_employee_id,
    2, -- status enum index: DA_XUAT_BAN
    @seed_employee_id,
    @seed_employee_id
FROM news_categories nc
WHERE nc.category_code = 'API'
  AND NOT EXISTS (
      SELECT 1
      FROM news
      WHERE news_code = 'NEWS-SEED-001'
  )
  AND @seed_employee_id IS NOT NULL;

INSERT INTO policies (
    policy_code,
    policy_name,
    policy_type,
    content,
    version,
    effective_date,
    is_required_acknowledgment,
    status,
    created_by
)
SELECT
    'POL-SEED-001',
    'API Seed Policy',
    'QUY_DINH',
    'Seed policy content for API testing',
    '1.0',
    CURDATE(),
    1,
    2, -- status enum index: HIEU_LUC
    @seed_employee_id
WHERE NOT EXISTS (
    SELECT 1
    FROM policies
    WHERE policy_code = 'POL-SEED-001'
)
AND @seed_employee_id IS NOT NULL;
