<?php

namespace App\Support;

use App\Models\User;

/**
 * Builds the sidebar from config/menu.php: leaves out what the user's role may not open and
 * the Business modules the company has not switched on, and marks the ONE entry that is the
 * page being shown. Hiding an entry is a convenience only: the routes check the same rules.
 */
class Menu
{
    public static function for(User $user): array
    {
        // a company that has not paid, or whose year ended, sees its payment page only
        if (! company()?->isUsable()) {
            return self::markCurrent([['label' => 'Subscription & Billing', 'icon' => 'credit-card', 'route' => 'billing.show', 'open' => false, 'current' => false]]);
        }

        $menu = [];
        foreach (config('menu') as $item) {
            if (isset($item['children'])) {
                if (! self::allowed($user, $item)) {
                    continue;
                }
                $item['children'] = array_values(array_filter($item['children'], fn (array $child) => self::allowed($user, $child)));
                if ($item['children']) {
                    $menu[] = $item;
                }
            } elseif (isset($item['heading']) || self::allowed($user, $item)) {
                $menu[] = $item;
            }
        }

        // drop headings that have nothing under them
        $clean = [];
        foreach ($menu as $index => $item) {
            $next = $menu[$index + 1] ?? null;
            if (isset($item['heading']) && ($next === null || isset($next['heading']))) {
                continue;
            }
            $clean[] = $item + ['open' => false, 'current' => false];
        }

        return self::markCurrent($clean);
    }

    /** The number shown beside an entry that asks for one ('badge' => 'approvals'): things waiting, or 0. */
    public static function badge(array $item): int
    {
        return 0;
    }

    public static function url(array $item): string
    {
        // 'url' is a page outside the system (it opens in a new tab)
        return $item['url'] ?? route($item['route'], $item['params'] ?? []);
    }

    /**
     * Exactly one entry is the current page. Entries that share a route differ by their
     * parameters (type, view, group ...), so every such parameter must be the same as in
     * the address: "Sale Invoices" (type=invoice) is not current on "Delivery Notes"
     * (type=invoice, view=delivery). When no entry fits exactly (a detail page, for
     * example) only the module is opened and nothing is highlighted.
     */
    private static function markCurrent(array $menu): array
    {
        // for every route: the parameter names the menu uses to tell its entries apart.
        // Other things in the address (a search word, a filter) do not matter.
        $keys = [];
        foreach ($menu as $item) {
            foreach ($item['children'] ?? [$item] as $entry) {
                foreach (isset($entry['route']) ? array_keys($entry['params'] ?? []) : [] as $key) {
                    $keys[$entry['route']][$key] = true;
                }
            }
        }
        $address = fn (string $key) => (string) (request()->route($key) ?? request()->query($key) ?? '');

        $exact = null;
        $loose = null;
        foreach ($menu as $i => $item) {
            foreach ($item['children'] ?? [null] as $j => $child) {
                $entry = $child ?? $item;
                if (! isset($entry['route']) || ! request()->routeIs($entry['active'] ?? $entry['route'])) {
                    continue;
                }
                $loose ??= [$i, $j];
                $same = true;
                foreach (array_keys($keys[$entry['route']] ?? []) as $key) {
                    if ((string) ($entry['params'][$key] ?? '') !== $address($key)) {
                        $same = false;
                        break;
                    }
                }
                if ($same) {
                    $exact = [$i, $j];
                    break 2;
                }
            }
        }

        if ($exact) {
            [$i, $j] = $exact;
            $menu[$i]['open'] = true;
            if (isset($menu[$i]['children'])) {
                $menu[$i]['children'][$j]['current'] = true;
            } else {
                $menu[$i]['current'] = true;
            }
        } elseif ($loose) {
            $menu[$loose[0]]['open'] = true;
        }

        return $menu;
    }

    private static function allowed(User $user, array $item): bool
    {
        return (empty($item['can']) || can_do($item['can']))
            && (empty($item['module']) || module_on($item['module']));
    }
}
