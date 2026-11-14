<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * All companies share one database. A model with this trait only ever shows the rows of
 * the company the request is working in, and a new row is stamped with that company.
 * Where there is no current company (console commands, the admin panel) nothing is hidden,
 * so that code must say which company it means; a row without a company cannot be saved.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            if ($id = company_id()) {
                $query->where($query->getModel()->getTable().'.company_id', $id);
            }
        });

        static::creating(function ($model) {
            $model->company_id ??= company_id();
            if (! $model->company_id) {
                throw new LogicException(class_basename($model).' cannot be saved without a company.');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
