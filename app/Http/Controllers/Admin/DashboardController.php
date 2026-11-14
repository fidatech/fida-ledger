<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\View\View;

/** Platform dashboard. It counts companies and payments; it never opens a company's books. */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = now()->toDateString();

        return view('admin.dashboard', [
            'counts' => [
                'pending' => Company::where('status', Company::PENDING)->count(),
                'active' => Company::where('status', Company::ACTIVE)->where('subscription_ends_at', '>=', $today)->count(),
                'expired' => Company::where('status', Company::ACTIVE)->where('subscription_ends_at', '<', $today)->count(),
                'suspended' => Company::where('status', Company::SUSPENDED)->count(),
                'users' => User::count(),
                'waiting' => SubscriptionPayment::where('status', SubscriptionPayment::PENDING)->count(),
            ],
            'revenue' => SubscriptionPayment::where('status', SubscriptionPayment::APPROVED)->groupBy('currency')->selectRaw('currency, SUM(amount) AS total')->pluck('total', 'currency'),
            'latest' => Company::with('plan')->latest('id')->limit(10)->get(),
        ]);
    }
}
