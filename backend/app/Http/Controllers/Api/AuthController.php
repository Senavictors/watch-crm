<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function csrfCookie(Request $request)
    {
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function login(Request $request)
    {
        $key = Str::transliterate(Str::lower($request->string('email'))).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            event(new Lockout($request));

            throw ValidationException::withMessages([
                'email' => ['Muitas tentativas. Aguarde alguns instantes antes de tentar novamente.'],
            ]);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            $this->audit('auth.login_failed', 'Falha de login.', null, [
                'email' => $credentials['email'],
            ]);

            throw ValidationException::withMessages([
                'email' => ['As credenciais informadas são inválidas.'],
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        RateLimiter::clear($key);

        $this->audit('auth.login_succeeded', 'Login realizado com sucesso.', $user);

        return response()->json([
            'user' => $this->toUserPayload($user->fresh()),
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        $this->audit('auth.logout', 'Logout realizado.', $user);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => $this->toUserPayload($user),
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($data);

        $this->audit('auth.password_reset_requested', 'Solicitação de reset de senha.', null, [
            'email' => $data['email'],
            'status' => $status,
        ]);

        return response()->json([
            'message' => 'Se existir uma conta para este e-mail, enviaremos as instruções de recuperação.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', 'min:12'],
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        $this->audit('auth.password_reset_completed', 'Senha redefinida com sucesso.', null, [
            'email' => $data['email'],
        ]);

        return response()->json([
            'message' => 'Senha redefinida com sucesso.',
        ]);
    }

    private function toUserPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleName(),
            'permissions' => $user->permissions(),
            'isActive' => $user->is_active,
            'lastLoginAt' => optional($user->last_login_at)->toIso8601String(),
            'twoFactorEnabled' => filled($user->two_factor_secret) && filled($user->two_factor_confirmed_at),
        ];
    }
}
