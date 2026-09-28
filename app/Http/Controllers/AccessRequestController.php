<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\AccessRequestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class AccessRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Honeypot: bots fill every field, humans never see this one.
        if (filled($request->input('website'))) {
            return back()->with('status', 'access-requested');
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40', 'required_without_all:email,telegram'],
            'email' => ['nullable', 'email', 'max:190'],
            'telegram' => ['nullable', 'string', 'max:120', 'regex:/^(@|https?:\/\/)?(t\.me\/)?@?[A-Za-z0-9_]{4,32}\/?$/'],
            'place' => ['nullable', 'string', 'max:32'],
        ], [
            'phone.required_without_all' => 'Укажите телефон, email или Telegram.',
            'telegram.regex' => 'Укажите ник вида @username или ссылку t.me/username.',
        ]);

        $data['telegram'] = $this->telegramLink($data['telegram'] ?? null);

        $admins = User::query()
            ->where('is_admin', true)
            ->whereNotNull('telegram_id')
            ->get();

        if ($admins->isEmpty()) {
            Log::warning('Access request received but no admin has telegram_id', $data);
        } else {
            $tz = config('notifications.timezone', config('app.timezone'));

            Notification::send($admins, new AccessRequestNotification(
                [
                    'name' => $data['name'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'telegram' => $data['telegram'],
                    'place' => $data['place'] ?? null,
                ],
                now()->timezone($tz)->format('d.m.Y H:i'),
            ));
        }

        return back()->with('status', 'access-requested');
    }

    private function telegramLink(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $username = Str::of($value)
            ->trim()
            ->replaceMatches('#^https?://#', '')
            ->replaceMatches('#^t\.me/#', '')
            ->trim('@/')
            ->toString();

        return "https://t.me/{$username}";
    }
}
