<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class PolicyAcknowledgment extends Model
{
    protected string $table = 'policy_acknowledgments';
    protected string $primaryKey = 'acknowledgment_id';
    protected array $fillable = [
        'policy_id',
        'employee_id',
        'acknowledged_date',
        'ip_address',
    ];
}
