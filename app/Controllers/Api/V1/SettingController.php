<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Validator;
use App\Models\NotificationConfig;
use App\Models\SystemConfig;

class SettingController extends Controller
{
    private const GENERAL_KEYS = [
        'company_name',
        'company_tax_code',
        'company_address',
        'system_language',
        'system_timezone',
        'security_require_2fa',
        'security_session_timeout',
        'backup_last_run_at',
    ];

    private SystemConfig $systemConfigs;
    private NotificationConfig $notificationConfigs;

    public function __construct()
    {
        $this->systemConfigs = new SystemConfig();
        $this->notificationConfigs = new NotificationConfig();
    }

    public function general(Request $request): array
    {
        $rows = $this->systemConfigs->findByKeys(self::GENERAL_KEYS);

        $data = [
            'company_name' => (string) ($rows['company_name']['config_value'] ?? 'HRM Portal'),
            'company_tax_code' => (string) ($rows['company_tax_code']['config_value'] ?? ''),
            'company_address' => (string) ($rows['company_address']['config_value'] ?? ''),
            'system_language' => (string) ($rows['system_language']['config_value'] ?? 'vi'),
            'system_timezone' => (string) ($rows['system_timezone']['config_value'] ?? 'Asia/Ho_Chi_Minh'),
            'security_require_2fa' => (int) ($rows['security_require_2fa']['config_value'] ?? 0) === 1,
            'security_session_timeout' => (string) ($rows['security_session_timeout']['config_value'] ?? '60'),
            'backup_last_run_at' => (string) ($rows['backup_last_run_at']['config_value'] ?? ''),
        ];

        return $this->ok($data, 'General setting');
    }

    public function updateGeneral(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'company_name' => ['string'],
            'company_tax_code' => ['string'],
            'company_address' => ['string'],
            'system_language' => ['string'],
            'system_timezone' => ['string'],
            'security_require_2fa' => ['boolean'],
            'security_session_timeout' => ['string'],
            'backup_last_run_at' => ['string'],
        ]);

        foreach ($payload as $key => $value) {
            $configType = 'TEXT';
            $storedValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

            if (in_array($key, ['security_require_2fa'], true)) {
                $configType = 'BOOLEAN';
            } elseif (in_array($key, ['security_session_timeout'], true)) {
                $configType = 'NUMBER';
            }

            $this->systemConfigs->upsert(
                $key,
                $storedValue,
                $configType,
                null,
                'SYSTEM'
            );
        }

        return $this->general($request);
    }

    public function notificationIndex(Request $request): array
    {
        $items = $this->notificationConfigs->listAll();
        return $this->ok($items, 'Notification config list');
    }

    public function notificationUpdate(Request $request): array
    {
        $payload = $request->all();
        $items = $payload['items'] ?? null;
        if (!is_array($items)) {
            throw new HttpException('items is required', 422, 'validation_error');
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['notification_type'])) {
                continue;
            }

            $normalized[] = [
                'notification_type' => (string) $item['notification_type'],
                'is_enabled' => $this->toBoolInt($item['is_enabled'] ?? true),
                'send_email' => $this->toBoolInt($item['send_email'] ?? true),
                'send_in_app' => $this->toBoolInt($item['send_in_app'] ?? true),
                'days_before_trigger' => (int) ($item['days_before_trigger'] ?? 30),
                'recipients' => (string) ($item['recipients'] ?? 'EMPLOYEE'),
                'email_template' => isset($item['email_template']) ? (string) $item['email_template'] : null,
                'in_app_template' => isset($item['in_app_template']) ? (string) $item['in_app_template'] : null,
            ];
        }

        if ($normalized === []) {
            throw new HttpException('No valid notification items', 422, 'validation_error');
        }

        $this->notificationConfigs->upsertMany($normalized);
        return $this->ok($this->notificationConfigs->listAll(), 'Notification config updated');
    }

    private function toBoolInt(mixed $value): int
    {
        return in_array($value, [true, 1, '1', 'true', 'TRUE'], true) ? 1 : 0;
    }
}
