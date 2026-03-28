USE HRM_SYSTEM;

-- Đồng bộ trạng thái workflow nghỉ phép:
-- - CHỜ_GIÁM_ĐỐC_DUYỆT
-- - CHỜ_XÁC_NHẬN_HR
ALTER TABLE requests
MODIFY COLUMN status ENUM(
    'NHÁP',
    'CHỜ_DUYỆT',
    'CHỜ_GIÁM_ĐỐC_DUYỆT',
    'CHỜ_XÁC_NHẬN_HR',
    'ĐANG_XỬ_LÝ',
    'ĐÃ_DUYỆT',
    'TỪ_CHỐI',
    'ĐÃ_HỦY',
    'HOÀN_THÀNH'
) DEFAULT 'NHÁP';
