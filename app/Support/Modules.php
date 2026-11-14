<?php

namespace App\Support;

use App\Exceptions\UserError;
use App\Models\Company;
use App\Models\CompanyModule;
use App\Models\Module;
use Illuminate\Support\Facades\DB;

/**
 * Entitlements: which optional Business modules a company may use, and which are switched on.
 *
 *   entitled  the platform offers the module and the company's plan includes it
 *   active    entitled and switched on by the company
 *
 * Switching a module off hides it and blocks its screens; its records and journal entries stay.
 */
class Modules
{
    /** @var \WeakMap<Company, array{entitled: list<string>, on: array<string, bool>}>|null read once per company object */
    private static ?\WeakMap $cache = null;

    /** Every optional module: key => definition from config/modules.php. */
    public static function all(): array
    {
        return config('modules');
    }

    /** Keys of the modules the company's plan includes and the platform offers. */
    public static function entitled(Company $company): array
    {
        return self::state($company)['entitled'];
    }

    public static function active(Company $company, string $module): bool
    {
        $state = self::state($company);

        return ! empty($state['on'][$module]) && in_array($module, $state['entitled'], true);
    }

    private static function state(Company $company): array
    {
        self::$cache ??= new \WeakMap;
        if (! isset(self::$cache[$company])) {
            $plan = $company->plan?->code;
            $rows = Module::all()->keyBy('key');
            self::$cache[$company] = [
                'entitled' => array_values(array_filter(array_keys(self::all()), function (string $key) use ($rows, $plan) {
                    $row = $rows->get($key);

                    return $row !== null && $row->is_available && in_array($plan, $row->plans ?? [], true);
                })),
                'on' => CompanyModule::where('company_id', $company->id)->pluck('is_active', 'module')->map(fn ($on) => (bool) $on)->all(),
            ];
        }

        return self::$cache[$company];
    }

    /** Keys of the active modules of a company. */
    public static function activeKeys(Company $company): array
    {
        return array_values(array_filter(array_keys(self::all()), fn (string $key) => self::active($company, $key)));
    }

    /**
     * Add what a selection needs: choosing Point of Sale also switches on Products, Sales and Inventory.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function withRequirements(array $keys): array
    {
        $all = self::all();
        $keys = array_values(array_intersect(array_keys($all), $keys));
        do {
            $before = count($keys);
            foreach ($keys as $key) {
                $keys = array_values(array_unique(array_merge($keys, $all[$key]['requires'])));
            }
        } while (count($keys) !== $before);

        return array_values(array_intersect(array_keys($all), $keys));
    }

    /**
     * Set the modules of a company to exactly this selection (plus what the selection requires).
     * Anything the plan does not include is refused, so Starter can never switch on a Business module.
     *
     * @param  list<string>  $keys
     */
    public static function set(Company $company, array $keys): void
    {
        $keys = self::withRequirements($keys);
        $entitled = self::entitled($company);
        if ($refused = array_diff($keys, $entitled)) {
            $names = array_map(fn (string $key) => __(self::all()[$key]['name']), $refused);
            throw new UserError(__('Your plan does not include: :modules. Upgrade to Business to use them.', ['modules' => implode(', ', $names)]));
        }

        DB::transaction(function () use ($company, $keys) {
            foreach (array_keys(self::all()) as $key) {
                CompanyModule::updateOrCreate(
                    ['company_id' => $company->id, 'module' => $key],
                    ['is_active' => in_array($key, $keys, true)],
                );
            }
        });
        unset(self::$cache[$company]);
    }
}
