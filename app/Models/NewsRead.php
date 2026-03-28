<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class NewsRead extends Model
{
    protected string $table = 'news_reads';
    protected string $primaryKey = 'read_id';
    protected array $fillable = [
        'news_id',
        'employee_id',
        'read_date',
        'ip_address',
        'device_info',
    ];
}
