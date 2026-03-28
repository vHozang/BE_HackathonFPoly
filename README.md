# BE Hackathon FPoly - HRM System API (Pure PHP)

Repo GitHub: https://github.com/vHozang/BE_HackathonFPoly

## 1) Yêu cầu môi trường

- PHP 8.1+
- MySQL 8.x
- Git

## 2) Clone project

### Cách 1: Terminal (macOS/Linux)

```bash
git clone https://github.com/vHozang/BE_HackathonFPoly.git
cd BE_HackathonFPoly
```

### Cách 2: PowerShell (Windows)

```powershell
git clone https://github.com/vHozang/BE_HackathonFPoly.git
Set-Location .\BE_HackathonFPoly
```

## 3) Cài đặt nhanh

### Terminal (macOS/Linux)

```bash
cp .env.example .env
```

### PowerShell (Windows)

```powershell
Copy-Item .env.example .env
```

Sau khi copy `.env`, cập nhật lại thông tin DB/JWT trong file `.env`.

## 4) Import database và migrations

Import các file SQL chính:

- `SQL_hackathon v4.sql`
- `data.sql`

Chạy thêm các migration trong `scripts/`:

- `20260313_payroll_adjustments.sql`
- `20260315_employee_password_auth.sql`
- `20260315_contract_change_logs.sql`
- `20260315_dynamic_frontend_modules.sql`
- `20260324_recruitment_cv_and_interview_review.sql`
- `20260325_recruitment_manager_reviews.sql`

## 5) Chạy server local

### Terminal (macOS/Linux)

```bash
php -S 127.0.0.1:8080 -t public
```

### PowerShell (Windows)

```powershell
php -S 127.0.0.1:8080 -t public
```

API base URL:

- `http://127.0.0.1:8080/api/v1`

## 6) Seed dữ liệu test

### PowerShell

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\seed_test.ps1 -HostName 127.0.0.1 -Port 3306 -Database HRM_SYSTEM -Username root -Password ""
```

### Terminal

```bash
php scripts/seed_test.php
```

## 7) Test nhanh API

### Health check

```bash
curl http://127.0.0.1:8080/api/v1/health
```

### Login

```bash
curl -X POST http://127.0.0.1:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"company_email":"an.nguyen@company.com","password":"NV0001"}'
```

### Gọi API có token

```bash
curl http://127.0.0.1:8080/api/v1/employees \
  -H "Authorization: Bearer <ACCESS_TOKEN>"
```

## 8) Tài liệu kèm theo

- Postman collection: `postman/HRM_API_v1.postman_collection.json`
- Postman environment: `postman/HRM_API_v1.postman_environment.json`
- Business spec: `docs/BUSINESS_SPEC.md`
- API spec: `docs/API_SPEC.md`
- CSV template import thử việc: `docs/probation_employees_template.csv`
