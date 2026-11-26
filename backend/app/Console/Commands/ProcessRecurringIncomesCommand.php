<?php

namespace App\Console\Commands;

use App\Services\RecurringIncomeService;
use Illuminate\Console\Command;

class ProcessRecurringIncomesCommand extends Command
{
    protected $signature = 'regain:process-recurring-incomes';

    protected $description = 'Process due recurring monthly incomes idempotently in Asia/Jakarta timezone';

    public function handle(RecurringIncomeService $service): int
    {
        $count = $service->processDueIncomes();
        $this->info("Processed {$count} recurring income transaction(s).");

        return self::SUCCESS;
    }
}
