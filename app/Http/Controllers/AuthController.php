<?php

/*
 * =============================================================================
 *  AuthController
 * =============================================================================
 *
 *  PURPOSE
 *  -------
 *  Handles the entire authentication lifecycle for cookie/session-based auth:
 *  register, login, logout, "who am I", and password reset helpers.
 *
 *  AUTH STRATEGY (IMPORTANT — read before editing)
 *  ------------------------------------------------
 *  This app uses STATEFUL / COOKIE-BASED sessions, NOT token auth (no Sanctum,
 *  no JWT). Flow:
 *    1. Frontend axios client (`src/api/axios.js`) sends `withCredentials: true`,
 *       so the browser stores Laravel's session cookie.
 *    2. Backend: `Auth::attempt()` + `session()->regenerate()` (login),
 *       `Auth::login()` (register), `Auth::guard('web')->logout()` (logout).
 *    3. CORS (`config/cors.php`) sets `supports_credentials => true` and allows
 *       the Vite origin, otherwise the browser drops the cookie.
 *    4. `SESSION_DRIVER=database` -> sessions live in the `sessions` table.
 *
 *  WHERE IT IS CONNECTED
 *  ---------------------
 *  - Routed in `routes/api.php` (POST /api/login, POST /api/register,
 *    POST /api/logout, GET /api/user, forgot/verify/reset).
 *  - Uses `App\Models\User` for lookup and creation.
 *  - Consumed by frontend `src/api/auth.js` and `src/context/AuthContext.jsx`.
 *
 *  WHERE IT IS CALLED
 *  ------------------
 *  - AuthContext.jsx useEffect -> getCurrentUser() -> GET /api/user.
 *  - Login.jsx   handleSubmit -> login()      -> POST /api/login.
 *  - Register.jsx handleSubmit -> register()   -> POST /api/register.
 *  - Dashboard.jsx Log out button -> logout()  -> POST /api/logout.
 *
 *  PATTERN
 *  -------
 *  Standard Laravel controller actions returning JSON:
 *    - validate input -> act -> `response()->json(...)`.
 *    - `Auth::attempt` returns bool; on failure a ValidationException is
 *      thrown so the frontend's `err.response.data.message` render works.
 *    - `/api/user` is route-protected by the `auth` middleware (routes/api.php)
 *      so it 401s for guests (=> AuthContext catches it and sets user null).
 * =============================================================================
 */

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /* -------------------------------------------------------------------------
     * POST /api/login
     * Logs an existing user in. On success Laravel rotates the session id and
     * persists the cookie so every subsequent /api/* request is authenticated.
     * ---------------------------------------------------------------------- */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'), true)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json(['user' => Auth::user()]);
    }

    /* -------------------------------------------------------------------------
     * POST /api/register
     * Creates a new user (role always 'user'; admins are created by seeders).
     * Auto-logs-in the brand new account so the UI lands on the dashboard.
     * ---------------------------------------------------------------------- */
    public function register(Request $request)
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => 'user',
        ]);

        Auth::login($user);

        return response()->json(['user' => $user], 201);
    }

    /* -------------------------------------------------------------------------
     * POST /api/logout
     * Destroys the server-side session so the cookie no longer authenticates.
     * Returns 204 (no body) which the axios client tolerates.
     * ---------------------------------------------------------------------- */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }

    /* -------------------------------------------------------------------------
     * GET /api/user
     * Returns the currently authenticated user (guarded by the `auth`
     * middleware at the route level). 401 for guests -> frontend's 401
     * interceptor redirects to /login.
     * ---------------------------------------------------------------------- */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    /* -------------------------------------------------------------------------
     * GET /api/demo-users
     * Returns a lightweight list of users for the login/register "Sign in as"
     * pickers. PUBLIC (no session) on purpose — it's a dev/demo helper for the
     * React UI. NEVER exposes passwords (they are bcrypt-hashed anyway).
     * Called by: src/api/auth.js getDemoUsers() -> Login.jsx / Register.jsx.
     * ---------------------------------------------------------------------- */
    public function demoUsers()
    {
        return response()->json(
            User::query()
                ->select('id', 'name', 'email', 'role')
                ->orderBy('name')
                ->get()
        );
    }

    /* -------------------------------------------------------------------------
     * POST /api/forgot-password
     * Placeholder. TODO: store an OTP and email it to the user.
     * ---------------------------------------------------------------------- */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        // TODO: store OTP in a password_otps table + email it to the user.
        return response()->json(['success' => true]);
    }

    /* -------------------------------------------------------------------------
     * POST /api/verify-otp
     * Placeholder. TODO: check the OTP row (email + otp + not expired).
     * ---------------------------------------------------------------------- */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp'   => ['required', 'string'],
        ]);
        // TODO: check the OTP row (email + otp + not expired)
        return response()->json(['success' => true]);
    }

    /* -------------------------------------------------------------------------
     * POST /api/reset-password
     * Resets a user's password. Only runs the update when the email exists
     * (no leaking of whether an account exists).
     * ---------------------------------------------------------------------- */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::where('email', $request->email)->first();
        if ($user) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        return response()->json(['success' => true]);
    }
}