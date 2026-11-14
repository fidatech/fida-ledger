<?php

namespace App\Console\Commands;

use App\Accounting\Recurring;
use App\Models\Company;
use Illuminate\Console\Command;

class RunRecurring extends Command
{
    protected $signature = 'ledger:recurring';

    protected $description = 'Post the recurring Money In and Money Out that are due, for every company with a running subscription';

    public function handle(): int
    {
        $posted = 0;
        $failed = 0;
        // one company at a time, each named explicitly: there is no signed-in user here
        Company::where('status', Company::ACTIVE)->whereHas('plan', fn ($q) => $q->where('code', 'business'))->orderBy('id')->chunkById(100, function ($companies) use (&$posted, &$failed) {
            foreach ($companies as $company) {
                $result = Recurring::run($company);
                $posted += $result['posted'];
                $failed += $result['failed'];
            }
        });
        $this->info("Recurring transactions: {$posted} posted, {$failed} waiting for attention.");

        return self::SUCCESS;
    }
}
