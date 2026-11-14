<?php

namespace App\Support;

use App\Accounting\Chart;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Opens a new company: the company itself, its owner, its modules, currencies and chart of accounts. */
class Companies
{
    /**
     * @param  array{name: string, type?: string, base_currency: string, country?: ?string, timezone?: string, phone?: ?string,
     *     email?: ?string, plan: Plan}  $data
     *
     * The company starts "pending": it can be used only after a system administrator approves its first payment.
     */
    public static function open(User $owner, array $data): Company
    {
        return DB::transaction(function () use ($owner, $data) {
            $plan = $data['plan'];
            $base = strtoupper($data['base_currency']);

            $company = Company::create([
                'name' => $data['name'],
                'type' => $data['type'] ?? 'other',
                'base_currency' => $base,
                'country' => $data['country'] ?? null,
                'timezone' => $data['timezone'] ?? config('app.timezone'),
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? $owner->email,
                'plan_id' => $plan->id,
                'status' => Company::PENDING,
            ]);

            $company->users()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);

            // the plan decides the modules, not the user: every module the plan includes is switched on
            // (all seven for Business, none for Starter)
            $company->setRelation('plan', $plan);
            Modules::set($company, Modules::entitled($company));

            foreach (config('ledger.currencies') as $code => [$name, $symbol]) {
                $currency = new Currency(['code' => $code, 'name' => $name, 'symbol' => $symbol]);
                $currency->company_id = $company->id;
                $currency->save();
            }
            if (! isset(config('ledger.currencies')[$base])) {
                $currency = new Currency(['code' => $base, 'name' => $base]);
                $currency->company_id = $company->id;
                $currency->save();
            }

            Chart::install($company);

            $owner->update(['last_company_id' => $company->id]);

            return $company;
        });
    }
}
