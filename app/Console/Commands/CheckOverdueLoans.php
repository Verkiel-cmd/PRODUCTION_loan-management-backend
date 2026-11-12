<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Console\Command;

class CheckOverdueLoans extends Command
{
    protected $signature = 'loans:check-overdue';
    protected $description = 'Mark active loans past their due date as overdue and notify';

    public function handle(): int
    {
        $loans = Loan::where('status', 'active')
            ->whereDate('due_date', '<', now())
            ->get();

        foreach ($loans as $loan) {
            $loan->update(['status' => 'overdue']);

            // Notify borrower + admins, same as your updateStatus() trigger
            // e.g. $loan->user->notify(new LoanStatusChanged($loan));
            // User::where('role', 'admin')->each->notify(new LoanStatusChanged($loan));
        }

        $this->info("Marked {$loans->count()} loan(s) as overdue.");

        return self::SUCCESS;
    }
}