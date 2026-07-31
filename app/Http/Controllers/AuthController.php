<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TelegramInitDataValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        if (! Auth::attempt(
            [
                'email' => $credentials['email'],
                'password' => $credentials['password'],
            ],
            (bool) ($credentials['remember'] ?? false),
        )) {
            throw ValidationException::withMessages([
                'email' => 'Неверный email или пароль.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function telegram(Request $request, TelegramInitDataValidator $validator): RedirectResponse
    {
        $validated = $request->validate([
            'init_data' => ['required', 'string'],
        ]);

        $telegramUser = $validator->validate($validated['init_data']);

        if ($telegramUser === null) {
            throw ValidationException::withMessages([
                'init_data' => 'Не удалось проверить данные Telegram.',
            ]);
        }

        $user = User::query()->where('telegram_id', $telegramUser['id'])->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'init_data' => 'Этот Telegram-аккаунт не привязан. Войдите через email и пароль.',
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
