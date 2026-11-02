<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Mail\EmailVerificationCodeMail;
use App\Models\Company;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Public self-registration: Company + User are created together inside
 * one transaction (rollback on any failure, never a partial record),
 * the new user becomes that company's Administrador — the same role
 * Gate::define('manage-company', ...) and friends already treat as the
 * owner — and the user is logged in immediately to start the OTP
 * verification step. No NIT/razón social/cliente/producto here: those
 * stay in the existing onboarding wizard.
 */
class RegisteredUserController extends Controller
{
    public function __construct(private readonly EmailVerificationService $otp) {}

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data): User {
            $company = Company::create([
                'name' => sprintf('Empresa de %s', $data['name']),
            ]);

            return User::create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::Administrador,
            ]);
        });

        Auth::login($user);
        $request->session()->regenerate();

        $code = $this->otp->issue($user);
        Mail::to($user)->send(new EmailVerificationCodeMail($user->name, $code));

        return redirect()->route('verification.notice');
    }
}
