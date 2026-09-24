<?php

/*
 * =============================================================================
 *  LoanController
 * =============================================================================
 *
 *  PURPOSE
 *  -------
 *  Full CRUD + status workflow + repayment handling + dashboard stats for
 *  loans. This is the API layer between the React frontend and the `loans`
 *  / `repayments` tables.
 *
 *  WHERE IT IS CONNECTED
 *  ---------------------
 *  - Routed in `routes/api.php`:
 *       GET    /api/loans            -> index        (list, paginated)
 *       POST   /api/loans            -> store        (create app.)
 *       GET    /api/loans/stats      -> stats        (dashboard cards)
 *       GET    /api/loans/{loan}     -> show         (detail + repayments)
 *       PATCH  /api/loans/{loan}/status -> updateStatus
 *       POST   /api/loans/{loan}/repayments -> addRepayment
 *       DELETE /api/loans/{loan}     -> destroy
 *    NOTE: `stats` sits ABOVE `{loan}` deliberately — otherwise "stats"
 *    would be captured by the {loan} route parameter.
 *  - Uses models `App\Models\Loan` + `App\Models\Repayment`.
 *  - Consumed by frontend `src/api/loans.js`, then:
 *       Dashboard.jsx (getDashboardStats -> stats)
 *       LoanList.jsx  (getLoans -> index, updateLoanStatus, deleteLoan)
 *       LoanForm.jsx  (createLoan -> store)
 *
 *  FIELD ALIGNMENT (do not diverge from this!)
 *  --------------------------------------------
 *  Nothing must break between the migration, this controller and the frontend:
 *    - loans table columns : user_id, purpose, principal, interest_rate,
 *                            duration_months, status (default 'pending')
 *    - LoanForm.jsx sends : purpose, principal(float), interest_rate(float),
 *                           duration_months(int)
 *    - LoanList.jsx reads : loan.purpose, loan.principal, loan.total_payable,
 *                           loan.status, loan.user.name
 *    - Dashboard expects : total_loans, active_loans, paid_loans, overdue_loans,
 *                          total_disbursed, total_collected
 *    - statuses mirrored in Loan::STATUSES + LoanList.jsx STATUS_STYLES.
 *
 *  PATTERN
 *  -------
 *  Thin controller: validate -> persist via relationship -> JSON response.
 *    - Non-admins only ever see/manage their OWN loans (index/show/destroy).
 *    - Admins (role === 'admin') see every loan and can approve/reject/delete.
 *    - `user_id` is always taken from the authenticated user, never the body.
 * =============================================================================
 */

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Repayment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanController extends Controller
{
    /**
     * List loans.
     * Admins: all loans (paginated). Borrowers: only their own loans.
     * Response shape: { data: [...], links, meta } — LoanList.jsx reads `data`.
     */
    public function index(Request $request)
    {
        $query = Loan::with('user')->latest();

        if ($request->user()->role !== 'admin') {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json($query->paginate(10));
    }

    /**
     * Loan detail (+ its repayment installments).
     * Borrowers are scoped to their own loans.
     */
    public function show(Request $request, Loan $loan)
    {
        abort_unless(
            $request->user()->role === 'admin' || $loan->user_id === $request->user()->id,
            403
        );

        return response()->json($loan->load(['user', 'repayments']));
    }

    /**
     * Users only function can create a new loan application. Status always starts as 'pending'.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'purpose'         => ['required', 'string', 'max:255'],
            'principal'       => ['required', 'numeric', 'gt:0'],
            'interest_rate'   => ['nullable', 'numeric', 'min:0'],
            'duration_months' => ['required', 'integer', 'min:1'],
        ]);

        $loan = $request->user()->loans()->create([
            ...$validated,
            'status' => 'pending',
        ]);

        return response()->json($loan, 201);
    }

    /**
     * Admin-only status change (approve / reject / activate / mark paid / overdue).
     * Borrowers cannot mutate their own status.
     */
    public function updateStatus(Request $request, Loan $loan)
    {
        abort_unless($request->user()->role === 'admin', 403);

        $request->validate([
            'status' => ['required', Rule::in(Loan::STATUSES)],
        ]);

        $loan->update(['status' => $request->status]);

        return response()->json($loan);
    }

    /**
     * Record a repayment installment against a loan (borrower or admin).
     */
    public function addRepayment(Request $request, Loan $loan)
    {
        abort_unless(
            $request->user()->role === 'admin' || $loan->user_id === $request->user()->id,
            403
        );

        $validated = $request->validate([
            'amount'  => ['required', 'numeric', 'gt:0'],
            'paid_at' => ['nullable', 'date'],
            'note'    => ['nullable', 'string', 'max:255'],
        ]);

        $repayment = $loan->repayments()->create($validated);

        return response()->json($repayment, 201);
    }

    /**
     * Delete a loan (admin, or the loan's owner).
     */
    public function destroy(Request $request, Loan $loan)
    {
        abort_unless(
            $request->user()->role === 'admin' || $loan->user_id === $request->user()->id,
            403
        );

        $loan->delete();

        return response()->json(null, 204);
    }

    /**
     * Dashboard stat cards. Key names are a HARD contract with Dashboard.jsx:
     *   total_loans      -> "Total loans"       (Loan count)
     *   active_loans     -> "Active"            (status 'active')
     *   paid_loans       -> "Paid off"          (status 'paid')
     *   overdue_loans    -> "Overdue"           (status 'overdue')
     *   total_disbursed  -> "Disbursed"         (principal of lent-out loans)
     *   total_collected  -> "Collected"         (sum of all repayments)
     */
    public function stats()
    {
        return response()->json([
            'total_loans'      => Loan::count(),
            'active_loans'     => Loan::where('status', 'active')->count(),
            'paid_loans'       => Loan::where('status', 'paid')->count(),
            'overdue_loans'    => Loan::where('status', 'overdue')->count(),
            'total_disbursed'  => Loan::disbursed()->sum('principal'),
            'total_collected'  => Repayment::sum('amount'),
        ]);
    }
}