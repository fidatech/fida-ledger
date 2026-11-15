<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** A company's WhatsApp connection. The session key is stored encrypted and is never sent back to a browser. */
class WhatsappSetting extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected $hidden = ['session_key'];

    protected function casts(): array
    {
        return ['session_key' => 'encrypted', 'is_enabled' => 'boolean', 'templates' => 'array'];
    }

    public static function of(Company $company): self
    {
        return self::withoutGlobalScope('company')->firstOrNew(['company_id' => $company->id]);
    }

    public function isReady(): bool
    {
        return $this->is_enabled && filled($this->session_key);
    }

    /** The text of a message: the company's own wording, or the standard one. */
    public function template(string $key): string
    {
        return ($this->templates[$key] ?? null) ?: (string) config('whatsapp.templates.'.$key.'.body');
    }
}
