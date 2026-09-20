<?php

namespace Database\Seeders;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/*
 * =============================================================================
 *  DatabaseSeeder
 * =============================================================================
 *
 *  PURPOSE
 *  -------
 *  Seeds the sqlite database with the accounts you need to try the app right
 *  away. The Login.jsx form is pre-filled with these exact credentials.
 *
 *  WHERE IT IS CONNECTED / WHERE IT IS CALLED
 *  ------------------------------------------
 *  - Invoked by: `php artisan migrate:fresh --seed` (or `db:seed`).
 *  - Admin row   -> logs in via Login.jsx -> sees all loans + approve/reject UI.
 *  - Test User   -> a normal borrower (role 'user') for the non-admin view.
 *  - Sample loan -> so the dashboard/list have data before you create anything.
 *    It is attached to the admin so a logged-in admin sees content immediately.
 *    NOTE: registration defaults every new account to role 'user'.
 *
 *  PATTERN
 *  -------
 *  Standard Seeder: `withoutModelEvents` for speed, explicit creates, and a
 *  synthetic loan using the same columns as the loans migration.
 * =============================================================================
 */

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Admin account — default credentials shown on the Login page.
        $admin = User::factory()->firstOrcreate(
            ['email'    => 'admin@example.com'],
            ['name'     => 'Admin User', 'password' => 'password', 'role' => 'admin'],
        );

        // Normal borrower account (role defaults to 'user').
        $borrower = User::factory()->firstOrcreate(
            ['email'    => 'test@example.com'],
            ['name'     => 'Test User', 'password' => 'password'],
        );

        // One sample application so the dashboard/list aren't empty.
        Loan::firstOrCreate(
            [
                'user_id' => $admin->id,
                'purpose' => 'Home renovation',
            ],
            [
                'principal'       => 5000.00,
                'interest_rate'   => 12.00,
                'duration_months' => 12,
                'status'          => 'active',
            ],
        );

        $borrower->id; // reference kept for clarity; borrower stays loan-less.
        unset($borrower);
    }
}