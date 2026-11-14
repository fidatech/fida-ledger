<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** A person who signs in. One user can belong to several companies, with a role in each. */
class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean', 'email_verified_at' => 'datetime', 'two_factor_secret' => 'encrypted', 'two_factor_recovery_codes' => 'array', 'two_factor_confirmed_at' => 'datetime'];
    }

    /** The companies the user may open (an active membership). */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withPivot('role', 'is_active')->wherePivot('is_active', true)->withTimestamps();
    }

    /** The user's role in a company, or null when he is not a member of it. */
    public function roleIn(Company $company): ?string
    {
        if (! array_key_exists($company->id, $this->roles)) {
            $this->roles[$company->id] = $this->companies()->where('companies.id', $company->id)->first()?->pivot->role;
        }

        return $this->roles[$company->id];
    }

    /** @var array<int, ?string> roles already looked up in this request */
    private array $roles = [];

    public function canDo(Company $company, string $permission): bool
    {
        $role = $this->roleIn($company);
        if ($role === null) {
            return false;
        }
        $can = config('ledger.roles.'.$role.'.can', []);

        return in_array('*', $can, true) || in_array($permission, $can, true);
    }

    /** Two-factor sign-in is on once the user proved, with a first code, that the authenticator app works. */
    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    /** Use up a recovery code. Each one works once. */
    public function useRecoveryCode(string $code): bool
    {
        $code = strtoupper(trim($code));
        foreach ($this->two_factor_recovery_codes ?? [] as $index => $hash) {
            if (Hash::check($code, $hash)) {
                $left = $this->two_factor_recovery_codes;
                unset($left[$index]);
                $this->forceFill(['two_factor_recovery_codes' => array_values($left)])->save();

                return true;
            }
        }

        return false;
    }

    /** Email verification is asked for only where it is switched on (config/billing.php 'verify_email'). */
    public function needsVerification(): bool
    {
        return config('billing.verify_email') && ! $this->hasVerifiedEmail();
    }

    public function initials(): string
    {
        return self::initialsOf($this->name);
    }

    public static function initialsOf(?string $name): string
    {
        $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = mb_substr($words[0] ?? '?', 0, 1).(count($words) > 1 ? mb_substr(end($words), 0, 1) : '');

        return mb_strtoupper($letters);
    }
}
