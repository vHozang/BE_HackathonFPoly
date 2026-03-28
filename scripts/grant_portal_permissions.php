<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

$permissions = [
    ['EMP_VIEW', 'Xem nhân viên', 'NHAN_SU', 'Xem danh sách và thông tin nhân viên'],
    ['EMP_CREATE', 'Thêm nhân viên', 'NHAN_SU', 'Thêm mới nhân viên'],
    ['EMP_EDIT', 'Sửa nhân viên', 'NHAN_SU', 'Cập nhật thông tin nhân viên'],
    ['EMP_DELETE', 'Xóa nhân viên', 'NHAN_SU', 'Xóa nhân viên'],
    ['DEPARTMENT_VIEW', 'Xem phòng ban', 'NHAN_SU', 'Xem danh sách phòng ban'],
    ['DEPARTMENT_EDIT', 'Quản lý phòng ban', 'NHAN_SU', 'Tạo/sửa/xóa phòng ban'],
    ['LEAVE_VIEW', 'Xem đơn nghỉ phép', 'NGHI_PHEP', 'Xem danh sách đơn nghỉ phép'],
    ['LEAVE_CREATE', 'Tạo đơn nghỉ phép', 'NGHI_PHEP', 'Tạo đơn xin nghỉ phép'],
    ['LEAVE_EDIT', 'Sửa đơn nghỉ phép', 'NGHI_PHEP', 'Cập nhật đơn nghỉ phép'],
    ['LEAVE_DELETE', 'Xóa đơn nghỉ phép', 'NGHI_PHEP', 'Xóa đơn nghỉ phép'],
    ['SYSTEM_CONFIG', 'Cấu hình hệ thống', 'HE_THONG', 'Quản lý cấu hình hệ thống'],
    ['ASSET_VIEW', 'Xem tài sản', 'TAI_SAN', 'Xem danh sách tài sản'],
    ['ASSET_CREATE', 'Thêm tài sản', 'TAI_SAN', 'Tạo mới tài sản'],
    ['ASSET_EDIT', 'Sửa tài sản', 'TAI_SAN', 'Cập nhật tài sản'],
    ['ASSET_ASSIGN', 'Cấp phát tài sản', 'TAI_SAN', 'Cấp phát tài sản cho nhân viên'],
    ['ATTENDANCE_VIEW', 'Xem chấm công', 'CHAM_CONG', 'Xem bảng chấm công'],
    ['ATTENDANCE_EDIT', 'Cập nhật chấm công', 'CHAM_CONG', 'Tạo/sửa bản ghi chấm công và tăng ca'],
    ['SALARY_VIEW', 'Xem bảng lương', 'LUONG', 'Xem dữ liệu lương'],
    ['SALARY_CALCULATE', 'Tính lương', 'LUONG', 'Tạo/cập nhật dữ liệu tính lương'],
    ['SALARY_APPROVE', 'Duyệt lương', 'LUONG', 'Duyệt kỳ lương'],
    ['NEWS_VIEW', 'Xem tin tức', 'TRUYEN_THONG', 'Xem tin tức và chính sách'],
    ['NEWS_CREATE', 'Tạo tin tức', 'TRUYEN_THONG', 'Tạo tin tức'],
    ['NEWS_EDIT', 'Sửa tin tức', 'TRUYEN_THONG', 'Cập nhật tin tức'],
    ['NEWS_DELETE', 'Xóa tin tức', 'TRUYEN_THONG', 'Xóa tin tức'],
];

$rules = [
    // EMPLOYEE
    ['EMPLOYEE', 'EMP_VIEW', 1, 0, 0, 0, 0, 0],
    ['EMPLOYEE', 'LEAVE_VIEW', 1, 0, 0, 0, 0, 0],
    ['EMPLOYEE', 'LEAVE_CREATE', 1, 1, 1, 0, 0, 0],
    ['EMPLOYEE', 'ATTENDANCE_VIEW', 1, 0, 0, 0, 0, 0],
    ['EMPLOYEE', 'ATTENDANCE_EDIT', 1, 1, 1, 0, 0, 0],
    ['EMPLOYEE', 'SALARY_VIEW', 1, 0, 0, 0, 0, 0],
    ['EMPLOYEE', 'NEWS_VIEW', 1, 0, 0, 0, 0, 0],
    ['EMPLOYEE', 'ASSET_VIEW', 1, 0, 0, 0, 0, 0],
    ['EMPLOYEE', 'ASSET_ASSIGN', 1, 1, 0, 0, 0, 0],

    // MANAGER
    ['MANAGER', 'EMP_VIEW', 1, 0, 0, 0, 0, 0],
    ['MANAGER', 'EMP_EDIT', 1, 0, 1, 0, 0, 0],
    ['MANAGER', 'DEPARTMENT_VIEW', 1, 0, 0, 0, 0, 0],
    ['MANAGER', 'LEAVE_VIEW', 1, 0, 0, 0, 0, 0],
    ['MANAGER', 'LEAVE_CREATE', 1, 1, 1, 0, 0, 0],
    ['MANAGER', 'LEAVE_EDIT', 1, 0, 1, 0, 1, 0],
    ['MANAGER', 'ATTENDANCE_VIEW', 1, 0, 0, 0, 0, 0],
    ['MANAGER', 'ATTENDANCE_EDIT', 1, 1, 1, 0, 1, 0],
    ['MANAGER', 'SALARY_VIEW', 1, 0, 0, 0, 0, 0],
    ['MANAGER', 'NEWS_VIEW', 1, 0, 0, 0, 0, 0],
    ['MANAGER', 'ASSET_VIEW', 1, 0, 0, 0, 0, 0],
    ['MANAGER', 'ASSET_ASSIGN', 1, 1, 0, 0, 0, 0],
];

$db = Database::connection();

$permissionStmt = $db->prepare(
    'INSERT INTO permissions (permission_code, permission_name, module, description)
     VALUES (:permission_code, :permission_name, :module, :description)
     ON DUPLICATE KEY UPDATE
       permission_name = VALUES(permission_name),
       module = VALUES(module),
       description = VALUES(description)'
);

$rolePermissionStmt = $db->prepare(
    'INSERT INTO role_permissions (
        role_id, permission_id, can_access, can_create, can_edit, can_delete, can_approve, can_export
    )
    SELECT
        r.role_id,
        p.permission_id,
        :can_access,
        :can_create,
        :can_edit,
        :can_delete,
        :can_approve,
        :can_export
    FROM roles r
    JOIN permissions p ON p.permission_code = :permission_code
    WHERE r.role_code = :role_code
    ON DUPLICATE KEY UPDATE
        can_access = VALUES(can_access),
        can_create = VALUES(can_create),
        can_edit = VALUES(can_edit),
        can_delete = VALUES(can_delete),
        can_approve = VALUES(can_approve),
        can_export = VALUES(can_export)'
);

try {
    $db->beginTransaction();

    foreach ($permissions as [$code, $name, $module, $description]) {
        $permissionStmt->execute([
            'permission_code' => $code,
            'permission_name' => $name,
            'module' => $module,
            'description' => $description,
        ]);
    }

    foreach ($rules as [$roleCode, $permissionCode, $canAccess, $canCreate, $canEdit, $canDelete, $canApprove, $canExport]) {
        $rolePermissionStmt->execute([
            'role_code' => $roleCode,
            'permission_code' => $permissionCode,
            'can_access' => $canAccess,
            'can_create' => $canCreate,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
            'can_approve' => $canApprove,
            'can_export' => $canExport,
        ]);
    }

    $db->commit();
    echo "Permission seed applied successfully.\n";
} catch (\Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "Failed to apply permission seed: " . $exception->getMessage() . PHP_EOL);
    exit(1);
}

