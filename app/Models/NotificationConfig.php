<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class NotificationConfig extends Model
{
    protected string $table = 'notification_configs';
    protected string $primaryKey = 'config_id';
    protected array $fillable = [
        'notification_type',
        'is_enabled',
        'send_email',
        'send_in_app',
        'days_before_trigger',
        'recipients',
        'email_template',
        'in_app_template',
    ];

    public function listAll(): array
    {
        $sql = "SELECT config_id,
                       notification_type,
                       is_enabled,
                       send_email,
                       send_in_app,
                       days_before_trigger,
                       recipients,
                       email_template,
                       in_app_template
                FROM notification_configs
                ORDER BY config_id ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function upsertMany(array $items): void
    {
        $updateSql = "UPDATE notification_configs
                      SET is_enabled = :is_enabled,
                          send_email = :send_email,
                          send_in_app = :send_in_app,
                          days_before_trigger = :days_before_trigger,
                          recipients = :recipients,
                          email_template = :email_template,
                          in_app_template = :in_app_template
                      WHERE notification_type = :notification_type";
        $updateStmt = $this->db->prepare($updateSql);

        $insertSql = "INSERT INTO notification_configs (
                          notification_type,
                          is_enabled,
                          send_email,
                          send_in_app,
                          days_before_trigger,
                          recipients,
                          email_template,
                          in_app_template
                      ) VALUES (
                          :notification_type,
                          :is_enabled,
                          :send_email,
                          :send_in_app,
                          :days_before_trigger,
                          :recipients,
                          :email_template,
                          :in_app_template
                      )";
        $insertStmt = $this->db->prepare($insertSql);

        foreach ($items as $item) {
            $params = [
                'notification_type' => $item['notification_type'],
                'is_enabled' => (int) ($item['is_enabled'] ?? 1),
                'send_email' => (int) ($item['send_email'] ?? 1),
                'send_in_app' => (int) ($item['send_in_app'] ?? 1),
                'days_before_trigger' => (int) ($item['days_before_trigger'] ?? 30),
                'recipients' => $item['recipients'] ?? 'EMPLOYEE',
                'email_template' => $item['email_template'] ?? null,
                'in_app_template' => $item['in_app_template'] ?? null,
            ];

            $updateStmt->execute($params);
            if ($updateStmt->rowCount() === 0) {
                $insertStmt->execute($params);
            }
        }
    }
}
