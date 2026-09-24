<?php

/*
 * =============================================================================
 *  API ROUTES
 * =============================================================================
 *
 *  PURPOSE
 *  -------
 *  Every endpoint the React frontend talks to. Registered in
 *  `bootstrap/app.php` via `->withRouting(api: ...)` — Laravel auto-prefixes
 *  all of these with `/api` and applies the `api` middleware group
 *  (rate limiting, no CSRF, JSON error rendering from bootstrap/app.php).
 *
 *  WHERE IT IS CALLED (frontend call sites)
 *  ----------------------------------------
 *  - POST   /api/login          <- src/api/auth.js login()    <- Login.jsx
 *  - POST   /api/register       <- src/api/auth.js register() <- Register.jsx
 *  - POST   /api/logout         <- src/api/auth.js logout()   <- Dashboard.jsx
 *  - GET    /api/user           <- src/api/auth.js getCurrentUser() <- AuthContext
 *  - GET    /api/loans          <- src/api/loans.js getLoans() <- LoanList.jsx
 *  - POST   /api/loans          <- src/api/loans.js createLoan() <- LoanForm.jsx
 *  - GET    /api/loans/stats    <- src/api/loans.js getDashboardStats() <- Dashboard.jsx
 *  - PATCH  /api/loans/{id}/status <- src/api/loans.js updateLoanStatus() <- LoanList.jsx
 *  - POST   /api/loans/{id}/repayments <- src/api/loans.js addRepayment()
 *  - DELETE /api/loans/{id}     <- src/api/loans.js deleteLoan() <- LoanList.jsx
 *
 *  AUTH PATTERN (stateful cookie sessions — see AuthController docblock)
 *  ---------------------------------------------------------------------
 *  Login/register/logout are PUBLIC (they bootstrap the session cookie).
 *  Everything below them is wrapped in the `auth` middleware so guests get a
 *  401 -> the frontend axios interceptor boots them to /login.
 *
 *  ORDERING NOTE
 *  -------------
 *  `/loans/stats` is declared BEFORE `/loans/{loan}`. Route matching is
 *  top-down, so if `{loan}` came first, "stats" would be treated as an id.
 *  Route-model binding then needs the `auth` middleware to resolve the user,
 *  hence the grouped closure below.
 * =============================================================================
 */

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LoanController;
use Illuminate\Support\Facades\Route;

// ---- Public routes (no session required) ------------------------------------
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');

Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:6,1');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');

// ---- Authenticated routes (401 for guests) ----------------------------------
Route::middleware('auth')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Dashboard stats FIRST (see ORDERING NOTE above).
    Route::get('/loans/stats', [LoanController::class, 'stats']);

    Route::get('/loans', [LoanController::class, 'index']);
    Route::post('/loans', [LoanController::class, 'store']);
    Route::get('/loans/{loan}', [LoanController::class, 'show']);
    Route::patch('/loans/{loan}/status', [LoanController::class, 'updateStatus']);
    Route::post('/loans/{loan}/repayments', [LoanController::class, 'addRepayment']);
    Route::delete('/loans/{loan}', [LoanController::class, 'destroy']);
});