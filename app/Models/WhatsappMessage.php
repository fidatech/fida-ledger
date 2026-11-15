<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message handed to RelayWA. "accepted" means RelayWA took it; it does not mean the person
 * received or read it, and the system never claims so.
 */
class WhatsappMessage extends Model
{
    use BelongsToCompany;

    public const ACCEPTED = 'accepted';
    public const FAILED = 'failed';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
