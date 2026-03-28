# HRM Business Specification Summary

Nguon tong hop:
- `SQL_hackathon v4.sql` (schema + FK + check + trigger + procedure + event + view)
- `data.sql` (mock data + role/permission mapping + practical business scenarios)

## 1) Domain Modules

1. Core HR master data
- `employees`, `departments`, `positions`
- `nationalities`, `banks`
- employee profile fields include personal, contact, emergency, bank, status, seniority metadata.

2. Contract management
- `contract_types`, `contract_templates`, `contracts`, `contract_histories`
- support probation/fixed/indefinite contracts, renewal chain, history actions.

3. Qualification and identity
- `qualification_types`, `qualifications`
- `certificate_types`, `certificates`
- `document_types`, `identity_documents`, `social_insurance_info`, `dependents`.

4. Asset management
- `asset_categories`, `suppliers`, `asset_locations`, `assets`
- `asset_assignments`, `asset_incidents`, `asset_maintenance`.

5. Request and approval workflow
- `request_types`, `approval_flows`, `approval_steps`
- `requests`, `approval_histories`, `request_attachments`.

6. Leave and seniority
- `leave_types`, `holidays`, `leave_balances`, `seniority_leave_history`
- `leave_requests`, `leave_advancement_config`, `leave_advancement_requests`
- `leave_carryover_tracking`, `leave_transactions`.

7. Attendance and shifts
- `shift_types`, `shift_schedules`, `shift_schedule_details`
- `shift_assignments`, `shift_swaps`, `attendances`, `overtime_requests`.

8. Payroll and insurance
- `insurance_types`, `insurance_claims`
- `allowances`, `deductions`, `employee_allowances`, `employee_deductions`
- `salary_periods`, `salary_attendance_summary`, `salary_details`, `salary_breakdowns`.

9. Internal communication and governance
- `news_categories`, `news`, `news_reads`
- `policies`, `policy_acknowledgments`
- `notification_configs`, `notifications`, `dashboard_views`.

10. IAM and reporting
- `roles`, `permissions`, `role_permissions`, `employee_roles`
- `report_templates`, `report_histories`, `system_configs`.

## 2) Data Integrity and Referential Logic

1. Strong FK network
- Most entities reference `employees` for creator/updater/owner/approver.
- Organizational hierarchy via self-FK (`departments.parent_department_id`).
- Contract renewal chain via self-FK (`contracts.renewed_from_contract_id`).

2. Removed circular FK design
- Request flow: keep one direction from `request_types.approval_flow_id` -> `approval_flows`.
- Insurance-leave relation: keep `insurance_claims.leave_request_id`.

3. CHECK constraints
- `leave_requests`: `from_date <= to_date`, positive days, non-negative paid/unpaid parts.
- `shift_types`: valid shift time pair and coefficient > 0.
- `overtime_requests`: `end_time > start_time`, `break_time >= 0`.
- `salary_periods`: date range and month validation.

## 3) Trigger-based Business Rules (Polymorphic integrity)

1. `leave_transactions` triggers
- Validate `reference_type/reference_id` must appear together.
- Validate referenced object exists based on type:
  - `LEAVE_REQUEST`, `LEAVE_ADVANCEMENT`, `INSURANCE_CLAIM`, `REQUEST`, `ANNUAL_GRANT`, `CARRY_OVER`.

2. `notifications` triggers
- Same pair validation for `reference_type/reference_id`.
- Validate reference points to actual entity (`employees`, `leave_requests`, `contracts`, `attendances`, `news`, `policies`, etc.).

3. `salary_breakdowns` triggers
- Enforce `item_id` required for `ALLOWANCE/DEDUCTION/OVERTIME/LEAVE`.
- Enforce `item_id` null for `BONUS/PENALTY/OTHER`.
- Validate referenced item exists by item type.

## 4) Procedure and Scheduled Events

1. `calculate_seniority_leave()`
- Monthly compute years of service from `seniority_start_date` (fallback `hire_date`).
- Compute bonus leave by 5-year step.
- Write `seniority_leave_history`.
- Upsert current-year `leave_balances`.
- Push seniority notifications.
- Mark expired carry-over entries.

2. Events
- `monthly_seniority_calculation`: schedule monthly call of procedure.
- `daily_leave_expiry_check`: push notifications for carry-over leave near expiry.

## 5) Data-driven Operational Assumptions from `data.sql`

1. Multi-step approval lifecycle is active in sample data.
2. Roles/permissions are seeded and mapped to employees (`employee_roles`).
3. Leave/asset/request domains contain realistic transactional records for API testing.
4. Audit style fields (`created_by`, `updated_by`) are expected in major operational tables.

## 6) API implications

1. Auth needs user identity + role/permission context.
2. Request/leave APIs should respect approval statuses and step progression.
3. Asset assignment APIs should track current lifecycle status.
4. Pagination/filtering is mandatory due table size and high cardinality domains.
5. Authorization should combine:
- `role_permissions` action flags (`can_access/create/edit/delete/approve/export`)
- self-referencing hierarchy scope (manager can access subordinate tree).
