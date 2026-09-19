<?php

/*
 * =============================================================================
 *  Add `role` column to `users`
 * =============================================================================
 *
 *  PURPOSE
 *  -------
 *  The frontend gates admin features (approve/reject/delete loans, "All loans"
 *  view) on `user.role === "admin"` (LoanList.jsx), but the original users
 *  migration had no role column. This migration backfills that concept so an
 *  admin can exist at all.
 *
 *  WHERE IT IS CONNECTED
 *  ---------------------
 *  - Adds `role` (string, default 'user', indexed) to `users`.
 *  - Written into `User::$fillable` so AuthController::register can stamp
 *    every new account as 'user'.
 *  - Read by LoanController::index/show/updateStatus/destroy to decide whether
 *    the caller sees only their own loans or everything.
 *  - Seeded by DatabaseSeeder (admin@example.com / role=admin).
 *
 *  PATTERN
 *  -------
 *  Standard additive migration: `Schema::table('users', ...)` with
 *  `after('email')` for readable column ordering in MySQL; harmless on SQLite.
 * =============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('email')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};