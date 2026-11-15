<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A key another program signs in with. It works in one company, as one user, with that
 * user's role. Only the SHA-256 of the key is stored, so a copy of the database opens nothing.
 */
class ApiToken extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['can_write' => 'boolean', 'last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Make a key. The plain key is returned once and cannot be read again. */
    public static function issue(Company $company, User $user, string $name, bool $canWrite): array
    {
        $plain = 'fl_'.Str::random(48);
        $token = self::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'can_write' => $canWrite,
        ]);

        return [$token, $plain];
    }

    public static function findByKey(string $plain): ?self
    {
        return self::withoutGlobalScope('company')->where('token_hash', hash('sha256', $plain))->whereNull('revoked_at')->first();
    }
}
