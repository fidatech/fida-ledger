<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A tenant: one set of books with its own accounts, currencies, users, modules and subscription. */
class Company extends Model
{
    public const PENDING = 'pending';     // registered, first payment not approved yet
    public const ACTIVE = 'active';
    public const SUSPENDED = 'suspended'; // stopped by a system administrator

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'setup_fee_paid' => 'boolean',
            'subscription_ends_at' => \App\Casts\Day::class,
            'locked_until' => \App\Casts\Day::class,
            'settings' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role', 'is_active')->withTimestamps();
    }

    public function currencies(): HasMany
    {
        return $this->hasMany(Currency::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CompanyModule::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function today(): string
    {
        return now($this->timezone ?: config('app.timezone'))->toDateString();
    }

    /** Paid and not past its end date: the books can be used. */
    public function isUsable(): bool
    {
        return $this->status === self::ACTIVE
            && $this->subscription_ends_at !== null
            && $this->subscription_ends_at->toDateString() >= $this->today();
    }

    public function isExpired(): bool
    {
        return $this->status === self::ACTIVE && ! $this->isUsable();
    }

    /** Days of the subscription that are left (0 on the last day), or null when there is none. */
    public function daysLeft(): ?int
    {
        if (! $this->isUsable()) {
            return null;
        }

        return (int) round((strtotime($this->subscription_ends_at->toDateString()) - strtotime($this->today())) / 86400);
    }

    /** Is a date inside a locked accounting period? */
    public function isLocked(string $date): bool
    {
        return $this->locked_until !== null && $date <= $this->locked_until->toDateString();
    }
}
