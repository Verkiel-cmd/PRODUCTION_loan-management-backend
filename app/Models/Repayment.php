<?php

/*
 * =============================================================================
 *  Repayment MODEL
 * =============================================================================
 *
 *  PURPOSE
 *  -------
 *  Represents ONE individual repayment installment on a loan. Created by the
 *  migration `2026_09_19_014327_create_repayments_table`. Each repayment is
 *  a single amount paid against a loan (tracked for the `total_collected`
 *  dashboard card and the loan detail page).
 *
 *  WHERE IT IS CONNECTED
 *  ---------------------
 *  - DB table:    `repayments`
 *  - Belongs to:  `loans`  (parent loan)  -> LoanController::addRepayment
 *  - Used by:     `App\Http\Controllers\LoanController`
 *  - Consumed by: frontend `../api/loans` (POST /api/loans/{id}/repayments;
 *                 loan detail responses embed these via `repayments()`).
 *
 *  WHERE IT IS CALLED
 *  ------------------
 *  - LoanController::addRepayment  -> creates a new row.
 *  - LoanController::stats         -> `Repayment::sum('amount')` for the
 *                                     `total_collected` dashboard stat.
 *  - LoanController::show          -> eager-loaded via Loan->repayments.
 *
 *  PATTERN
 *  -------
 *  Minimal standard Eloquent model:
 *    - `$fillable`  => whitelist mirroring the migration columns.
 *    - `loan()`     => BelongsTo relationship back to the parent Loan.
 *    No casts needed: `paid_at` is a nullable timestamp handled by Laravel.
 * =============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Repayment extends Model
{
    /**
     * Mass-assignable columns. `loan_id` is injected by the controller from
     * the route segment (`/api/loans/{loan}/repayments`), never from the body.
     */
    protected $fillable = [
        'loan_id',
        'amount',
        'paid_at',
        'note',
    ];

    /**
     * Parent loan relationship.
     * Allows `$repayment->loan` and eager loading from the loan side.
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}