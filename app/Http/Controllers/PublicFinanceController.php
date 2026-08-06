<?php

namespace App\Http\Controllers;

use App\Support\PublicFinance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicFinanceController extends Controller
{
    public function show(Request $request): Response
    {
        $unlocked = (bool) $request->session()->get(PublicFinance::SESSION_UNLOCKED, false);
        $subject = PublicFinance::subject();

        return Inertia::render('Finance/Public', [
            'unlocked' => $unlocked && $subject !== null,
            'finance' => ($unlocked && $subject) ? PublicFinance::payload($subject) : null,
            'unavailable' => $subject === null,
        ]);
    }

    public function unlock(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'string', 'max:16'],
        ]);

        if (! PublicFinance::pinMatches((string) $data['pin'])) {
            throw ValidationException::withMessages([
                'pin' => 'Неверный код доступа',
            ]);
        }

        if (! PublicFinance::subject()) {
            throw ValidationException::withMessages([
                'pin' => 'Финансы пока недоступны',
            ]);
        }

        $request->session()->put(PublicFinance::SESSION_UNLOCKED, true);

        return redirect()->route('cashflow.public');
    }

    public function lock(Request $request): RedirectResponse
    {
        $request->session()->forget(PublicFinance::SESSION_UNLOCKED);

        return redirect()->route('cashflow.public');
    }
}
