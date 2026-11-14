<?php

namespace App\Support;

use App\Exceptions\UserError;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Subscriptions. A company pays the setup fee once and the annual fee every year.
 * A company's own word, or an uploaded receipt, never activates anything: only an
 * administrator's approval does.
 */
class Billing
{
    /**
     * What the company has to pay now.
     *
     * @return array{kind: string, setup_fee: string, annual_fee: string, amount: string, currency: string}
     */
    public static function quote(Company $company): array
    {
        $plan = $company->plan;
        // the setup fee is charged once in the life of a company, never again on a renewal
        $setup = $company->setup_fee_paid ? '0.00' : Dec::round($plan->setup_fee, 2);
        $annual = Dec::round($plan->annual_fee, 2);

        return [
            'kind' => $company->setup_fee_paid ? 'renewal' : 'first',
            'setup_fee' => $setup,
            'annual_fee' => $annual,
            'amount' => Dec::add($setup, $annual, 2),
            'currency' => $plan->currency,
        ];
    }

    /** May the company send a payment now? Not while one is waiting, and not long before the end date. */
    public static function canPay(Company $company): bool
    {
        if (self::waiting($company)) {
            return false;
        }
        if ($company->status === Company::SUSPENDED) {
            return false;
        }
        $left = $company->daysLeft();

        return $left === null || $left <= (int) config('billing.renew_days');
    }

    public static function waiting(Company $company): ?SubscriptionPayment
    {
        return $company->payments()->where('status', SubscriptionPayment::PENDING)->latest('id')->first();
    }

    /** The company says it has paid. Nothing is activated. */
    public static function submit(Company $company, User $user, array $data): SubscriptionPayment
    {
        return DB::transaction(function () use ($company, $user, $data) {
            $company = Company::lockForUpdate()->findOrFail($company->id);
            if (! self::canPay($company)) {
                throw new UserError(__('A payment is already waiting for approval, or no payment is due yet.'));
            }
            $quote = self::quote($company);

            return $company->payments()->create([
                'plan_id' => $company->plan_id,
                'kind' => $quote['kind'],
                'setup_fee' => $quote['setup_fee'],
                'annual_fee' => $quote['annual_fee'],
                'amount' => $quote['amount'],
                'currency' => $quote['currency'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'receipt_path' => $data['receipt_path'] ?? null,
                'status' => SubscriptionPayment::PENDING,
                'submitted_by' => $user->id,
            ]);
        });
    }

    /** An administrator confirms the money arrived: the company is active for one more year. */
    public static function approve(SubscriptionPayment $payment, Admin $admin): SubscriptionPayment
    {
        return DB::transaction(function () use ($payment, $admin) {
            $payment = SubscriptionPayment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== SubscriptionPayment::PENDING) {
                throw new UserError(__('This payment was already reviewed.'));
            }
            $company = Company::lockForUpdate()->findOrFail($payment->company_id);

            // a renewal paid early starts when the running year ends; anything else starts today
            $today = Carbon::parse($company->today());
            $start = $company->isUsable() ? $company->subscription_ends_at->copy()->addDay() : $today;
            $end = $start->copy()->addMonths((int) config('billing.months'))->subDay();

            $payment->update([
                'status' => SubscriptionPayment::APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
            ]);
            $company->update([
                'status' => Company::ACTIVE,
                'setup_fee_paid' => true,
                'subscription_ends_at' => $end->toDateString(),
            ]);

            AuditLog::record('approved subscription payment', $company->name, ['payment' => $payment->id, 'amount' => $payment->amount, 'until' => $end->toDateString()], $company->id);

            return $payment;
        });
    }

    public static function reject(SubscriptionPayment $payment, Admin $admin, string $reason): SubscriptionPayment
    {
        return DB::transaction(function () use ($payment, $admin, $reason) {
            $payment = SubscriptionPayment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== SubscriptionPayment::PENDING) {
                throw new UserError(__('This payment was already reviewed.'));
            }
            if (! array_key_exists($reason, config('billing.reject_reasons'))) {
                throw new UserError(__('Choose a reason.'));
            }

            $payment->update([
                'status' => SubscriptionPayment::REJECTED,
                'rejection_reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            AuditLog::record('rejected subscription payment', $payment->company?->name, ['payment' => $payment->id, 'reason' => $reason], $payment->company_id);

            return $payment;
        });
    }
}
