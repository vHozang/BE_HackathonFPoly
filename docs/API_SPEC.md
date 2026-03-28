# HRM API v1 (Pure PHP, No Framework)

Base URL:
- `/api/v1`

Response format:
```json
{
  "success": true,
  "message": "OK",
  "data": {},
  "meta": {}
}
```

## 1) Auth

1. `POST /api/v1/auth/login`
- body:
```json
{
  "company_email": "an.nguyen@company.com",
  "password": "NV0001"
}
```
- returns JWT + user + roles + permissions.

2. `GET /api/v1/auth/me` (Bearer required)
3. `POST /api/v1/auth/refresh` (Bearer required)

## 2) Health

1. `GET /api/v1/health`

## 3) Employees (Bearer required)

1. `GET /api/v1/employees`
- query: `page`, `per_page`, `q`, `status`, `department_id`
2. `GET /api/v1/employees/{id}`
3. `POST /api/v1/employees`
4. `PUT|PATCH /api/v1/employees/{id}`
5. `DELETE /api/v1/employees/{id}`

## 3.1) Contract Change Logs (Bearer required)

1. `GET /api/v1/contract-change-logs`
- query: `page`, `per_page`, `q`
2. `POST /api/v1/contract-change-logs`
- body:
```json
{
  "contract_no": "HD-2026-1001",
  "employee_name": "Nguyen Van A",
  "action_type": "CREATE"
}
```

## 4) Departments (Bearer required)

1. `GET /api/v1/departments`
- query: `page`, `per_page`, `q`, `manager_id`
2. `GET /api/v1/departments/{id}`
3. `POST /api/v1/departments`
4. `PUT|PATCH /api/v1/departments/{id}`
5. `DELETE /api/v1/departments/{id}`

## 5) Request Types (Bearer required)

1. `GET /api/v1/request-types`
2. `GET /api/v1/request-types/{id}`
3. `POST /api/v1/request-types`
4. `PUT|PATCH /api/v1/request-types/{id}`
5. `DELETE /api/v1/request-types/{id}`

## 6) Requests (Bearer required)

1. `GET /api/v1/requests`
- query: `page`, `per_page`, `q`, `status`, `requester_id`, `request_type_id`
2. `GET /api/v1/requests/{id}`
3. `POST /api/v1/requests`
4. `PUT|PATCH /api/v1/requests/{id}`
5. `DELETE /api/v1/requests/{id}`

## 7) Leave (Bearer required)

1. `GET /api/v1/leave-requests`
- query: `page`, `per_page`, `employee_id`, `leave_type_id`, `date_from`, `date_to`
2. `GET /api/v1/leave-requests/{id}`
3. `POST /api/v1/leave-requests`
- creates both:
  - one record in `requests`
  - one record in `leave_requests`
4. `PATCH /api/v1/leave-requests/{id}`
5. `DELETE /api/v1/leave-requests/{id}`
6. `GET /api/v1/leave-balances`
- query: `page`, `per_page`, `employee_id`, `year`
7. `GET /api/v1/leave-balances/{id}`

## 8) Assets (Bearer required)

1. `GET /api/v1/assets`
- query: `page`, `per_page`, `q`, `status`
2. `GET /api/v1/assets/{id}`
3. `POST /api/v1/assets`
4. `PUT|PATCH /api/v1/assets/{id}`

## 9) Asset Assignments (Bearer required)

1. `GET /api/v1/asset-assignments`
- query: `page`, `per_page`, `employee_id`, `status`
2. `POST /api/v1/asset-assignments`

## 10) Attendance & Overtime (Bearer required)

1. Attendance
- `GET /api/v1/attendances`
  - query: `page`, `per_page`, `date_from`, `date_to`, `status`, `employee_id`, `scope=self|hierarchy`
- `GET /api/v1/attendances/{id}`
- `POST /api/v1/attendances`
- `PUT|PATCH /api/v1/attendances/{id}`
- `DELETE /api/v1/attendances/{id}`

2. Overtime
- `GET /api/v1/overtime-requests`
  - query: `page`, `per_page`, `date_from`, `date_to`, `status`, `employee_id`, `scope=self|hierarchy`
- `GET /api/v1/overtime-requests/{id}`
- `POST /api/v1/overtime-requests`
- `PUT|PATCH /api/v1/overtime-requests/{id}`
- `DELETE /api/v1/overtime-requests/{id}`

## 11) Payroll (Bearer required)

1. Salary periods
- `GET /api/v1/salary-periods`
- `GET /api/v1/salary-periods/{id}`
- `POST /api/v1/salary-periods`
- `PUT|PATCH /api/v1/salary-periods/{id}`
- `POST /api/v1/salary-periods/{id}/close`
  - chốt kỳ lương, tự động cộng khoản `payroll_adjustments` chưa thanh toán theo `apply_month`, rồi đánh dấu đã chi trả (`status=1`).

2. Salary details
- `GET /api/v1/salary-details`
  - query: `page`, `per_page`, `period_id`, `transfer_status`, `employee_id`, `scope=self|hierarchy`
- `GET /api/v1/salary-details/{id}`
  - trả thêm `payroll_adjustments` đã áp dụng cho phiếu lương đó.
- `POST /api/v1/salary-details`
- `PUT|PATCH /api/v1/salary-details/{id}`

3. Salary breakdowns
- `GET /api/v1/salary-breakdowns`
- `GET /api/v1/salary-breakdowns/{id}`
- `POST /api/v1/salary-breakdowns`
- `PUT|PATCH /api/v1/salary-breakdowns/{id}`
- `DELETE /api/v1/salary-breakdowns/{id}`

4. Payroll adjustments (BHXH/điều chỉnh treo theo kỳ lương)
- `GET /api/v1/payroll-adjustments`
  - query: `page`, `per_page`, `employee_id`, `apply_month` (`YYYY-MM`), `status` (`0|1`), `scope=self|hierarchy`
- `GET /api/v1/payroll-adjustments/{id}`
- `POST /api/v1/payroll-adjustments`
- `PUT|PATCH /api/v1/payroll-adjustments/{id}`

## 12) News & Policies (Bearer required)

1. News categories
- `GET /api/v1/news-categories`
- `GET /api/v1/news-categories/{id}`
- `POST /api/v1/news-categories`
- `PUT|PATCH /api/v1/news-categories/{id}`
- `DELETE /api/v1/news-categories/{id}`

2. News
- `GET /api/v1/news`
- `GET /api/v1/news/{id}`
- `POST /api/v1/news`
- `PUT|PATCH /api/v1/news/{id}`
- `DELETE /api/v1/news/{id}`
- `POST /api/v1/news/{id}/read`

3. Policies
- `GET /api/v1/policies`
- `GET /api/v1/policies/{id}`
- `POST /api/v1/policies`
- `PUT|PATCH /api/v1/policies/{id}`
- `DELETE /api/v1/policies/{id}`
- `POST /api/v1/policies/{id}/acknowledge`

## 12.1) Recruitment (Bearer required)

1. Recruitment positions
- `GET /api/v1/recruitment-positions`
- `POST /api/v1/recruitment-positions`
- `PATCH /api/v1/recruitment-positions/{id}`
- `DELETE /api/v1/recruitment-positions/{id}`

2. Recruitment candidates
- `GET /api/v1/recruitment-candidates`
- `GET /api/v1/recruitment-candidates/{id}`
- `POST /api/v1/recruitment-candidates`
- `PATCH /api/v1/recruitment-candidates/{id}`
- `GET /api/v1/recruitment-candidates/{id}/manager-review`
- `PATCH /api/v1/recruitment-candidates/{id}/manager-review`
  - workflow states for FE recruitment flow:
    - `PENDING` (HR forward to manager)
    - `APPROVED` (manager approved CV)
    - `REJECTED` (manager rejected CV)
- `POST /api/v1/recruitment-candidates/{id}/cv` (`multipart/form-data`, field: `file`, only PDF, max 10MB)
- `GET /api/v1/recruitment-candidates/{id}/cv` (returns `application/pdf`, query `download=1` to force download)

3. Interviews
- `GET /api/v1/interviews`
- `POST /api/v1/interviews`
  - HR creates interview schedule.
  - System auto-routes to department manager using `recruitment_position.department_id -> departments.manager_id`.
  - System inserts in-app notification into `notifications` table for that manager.
- `PATCH /api/v1/interviews/{id}`
- `PATCH /api/v1/interviews/{id}/manager-review`
  - body:
```json
{
  "manager_decision": "PASS",
  "manager_review_notes": "Good technical skill and communication."
}
```
  - only mapped department manager can review.
  - review updates interview result + candidate status (`PASSED`/`REJECTED`).

## 12.2) Notifications (Bearer required)

1. `GET /api/v1/notifications`
- query: `page`, `per_page`, `receiver_id` (or `userId`), `is_read`
2. `POST /api/v1/notifications`
3. `PATCH /api/v1/notifications/{id}`

## 13) Permission middleware (role_permissions)

Route-level permission checks are mapped to `role_permissions`:
- action `access/create/edit/delete/approve/export`
- permission code examples:
  - `ATTENDANCE_VIEW`, `ATTENDANCE_EDIT`
  - `SALARY_VIEW`, `SALARY_CALCULATE`, `SALARY_APPROVE`
  - `NEWS_VIEW`, `NEWS_CREATE`, `NEWS_EDIT`, `NEWS_DELETE`
  - `EMP_*`, `DEPARTMENT_*`, `ASSET_*`

## 14) Self-referencing hierarchy authorization

1. Auth payload includes:
- `managed_department_ids`
- `hierarchy_employee_ids`

2. Non-privileged users:
- default scope = self
- with `scope=hierarchy`, can access self + subordinate employees in department tree.

3. Endpoint:
- `GET /api/v1/auth/hierarchy` returns current hierarchy scope.

## 15) Validation, Error, Pagination

1. Validation errors return HTTP 422 with `error=validation_error`.
2. Auth failures return HTTP 401.
3. Not found returns HTTP 404.
4. List endpoints return pagination metadata:
```json
{
  "meta": {
    "total": 120,
    "page": 2,
    "per_page": 20,
    "last_page": 6
  }
}
```

## 16) Versioning strategy

- Version in URL: `/api/v1/...`
- Add future version side-by-side: `/api/v2/...` with separate route file.
