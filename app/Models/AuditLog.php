<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Who did what: sensitive actions of users inside a company and of the system administrators. */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }

    public static function record(string $action, ?string $subject = null, array $details = [], ?int $companyId = null): self
    {
        return self::create([
            'company_id' => $companyId ?? company_id(),
            'user_id' => auth('web')->id(),
            'admin_id' => auth('admin')->id(),
            'action' => $action,
            'subject' => $subject,
            'details' => $details ?: null,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
