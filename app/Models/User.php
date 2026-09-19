<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/*
 * =============================================================================
 *  User MODEL
 * =============================================================================
 *
 *  PURPOSE
 *  -------
 *  Borrowers AND admins. One row per account in the `users` table. Holds the
 *  credentials, profile name/email, and a `role` discriminator ('user' | 'admin').
 *
 *  WHERE IT IS CONNECTED
 *  ---------------------
 *  - Has many: `loans` (the loan applications this user owns) — used by
 *    LoanController::store (`$request->user()->loans()->create(...)`).
 *  - Consumed by: AuthController (login/register/user), LoanController (authz).
 *  - `role` needed by the frontend `user?.role === "admin"` gate (Dashboard,
 *    LoanList). Ensure the `AddRoleToUsers` migration has run.
 *
 *  WHERE IT IS CALLED
 *  ------------------
 *  - AuthController::register (create), login (Auth::attempt), user (returned).
 *  - AuthContext.jsx stores whatever GET /api/user returns, so every avatar /
 *    name / role read on the frontend comes from this model's attributes.
 *
 *  PATTERN
 *  -------
 *  Standard Laravel Authenticatable:
 *    - `#[Fillable]` / `#[Hidden]` attributes = modern fillable/hidden syntax
 *      (class-level as of this Laravel version).
 *    - password cast 'hashed' keeps Hash::make collisions from blocking login.
 *    - `loans()` adds the hasMany side of Loan->user().
 * =============================================================================
 */

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Loan applications owned by this user.
     * Reverse side of Loan::user(); consumed in LoanController::store/index.
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}