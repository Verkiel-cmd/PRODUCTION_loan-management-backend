<?php

/*
 * =============================================================================
 *  Loan MODEL
 * =============================================================================
 *
 *  PURPOSE
 *  -------
 *  Represents ONE loan application row in the `loans` table (created by the
 *  migration `2026_09_19_014316_create_loans_table`). It is the core domain
 *  entity of the loan-management system: every loan a user applies for lives
 *  here, alongside its repayment installments.
 *
 *  WHERE IT IS CONNECTED
 *  ---------------------
 *  - DB table:        `loans`
 *  - Belongs to:      `users`   (the borrower)   -> LoanController::index/show
 *  - Has many:        `repayments` (paid installments) -> LoanController::show
 *  - Used by:         `App\Http\Controllers\LoanController`
 *  - Consumed by FE:  Dashboard.jsx, LoanList.jsx, LoanForm.jsx (via
 *                     `../api/loans` -> axios -> GET/POST/PATCH /api/loans*)
 *
 *  WHERE IT IS CALLED
 *  ------------------
 *  - LoanController (store, index, show, updateStatus, addRepayment, destroy,
 *                   stats) — the ONLY consumer of this model.
 *  - AuthController does NOT touch Loan.
 *
 *  PATTERN
 *  -------
 *  Standard Eloquent model:
 *    - `$fillable`  => mass-assignable columns (whitelist, mirrors migration).
 *    - relationships => `user()` (BelongsTo), `repayments()` (HasMany).
 *    - `total_payable` accessor => virtual column sent to the frontend so the
 *      UI can render "Principal + (principal * rate * months/12)" without
 *      re-implementing the formula. Mirrors LoanForm.jsx preview exactly.
 *    - `$appends` => makes `total_payable` appear in every JSON response
 *      automatically (that is what LoanList.jsx reads as `loan.total_payable`).
 *    - `statuses()`  => single source of truth for allowed status values.
 *      Keep in sync with LoanList.jsx STATUS_STYLES and LoanController rules.
 * =============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    /**
     * Mass-assignable columns. NOTE: `user_id` is always injected from the
     * authenticated user (see LoanController::store) and never taken from the
     * request body.
     */
    protected $fillable = [
        'user_id',
        'purpose',
        'principal',
        'interest_rate',
        'duration_months',
        'status',
    ];

    /**
     * Virtual attributes appended to every JSON serialisation of a Loan.
     * This is what makes `loan.total_payable` exist on the frontend without
     * a real database column.
     */
    protected $appends = ['total_payable'];

    /**
     * The single source of truth for every status a loan can be in.
     *
     * Alignment notes:
     *  - Default for a fresh application is 'pending'.
     *  - Admins approve/reject (LoanList.jsx); then it becomes 'active',
     *    later 'paid' or 'overdue'.
     */
    public const STATUSES = [
        'pending',
        'approved',
        'active',
        'paid',
        'overdue',
        'rejected',
    ];

    /**
     * Borrower relationship.
     * Linked from LoanController::index ($loan->user->name -> LoanList.jsx).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Repayment installments relationship.
     * Linked from LoanController::show (loan detail page).
     */
    public function repayments(): HasMany
    {
        return $this->hasMany(Repayment::class);
    }

    /**
     * Computed "total payable" amount: principal + simple interest.
     *
     * formula: principal * (interest_rate / 100) * (duration_months / 12)
     * matches the live preview in LoanForm.jsx exactly (kept in sync manually).
     */
    protected function totalPayable(): Attribute
    {
        return Attribute::get(function (): float {
            $interest = $this->principal * ($this->interest_rate / 100) * ($this->duration_months / 12);

            return round($this->principal + $interest, 2);
        });
    }

    /**
     * Helper scope: restrict a query to "disbursed" loans (money actually
     * lent out). Used by LoanController::stats for the `total_disbursed` card.
     */
    public function scopeDisbursed(Builder $query): Builder
    {
        return $query->whereIn('status', ['approved', 'active', 'paid', 'overdue']);
    }
}