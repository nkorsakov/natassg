<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TelegramInitDataValidator
{
    /**
     * Validate Telegram Mini App initData and return parsed user payload.
     *
     * @return array{id: int, first_name?: string, last_name?: string, username?: string}|null
     *
     * @see https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
     */
    public function validate(string $initData, ?int $maxAgeSeconds = 86400): ?array
    {
        $botToken = config('services.telegram.bot_token');

        if (! is_string($botToken) || $botToken === '') {
            Log::warning('Telegram bot token is not configured');

            return null;
        }

        parse_str($initData, $data);

        if (! is_array($data) || empty($data['hash']) || empty($data['user'])) {
            return null;
        }

        $hash = (string) $data['hash'];
        unset($data['hash']);

        ksort($data);

        $checkString = collect($data)
            ->map(fn ($value, $key) => $key.'='.$value)
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $calculatedHash = hash_hmac('sha256', $checkString, $secretKey);

        if (! hash_equals($calculatedHash, $hash)) {
            return null;
        }

        if ($maxAgeSeconds !== null && isset($data['auth_date'])) {
            $authDate = (int) $data['auth_date'];

            if ($authDate < 1 || abs(time() - $authDate) > $maxAgeSeconds) {
                return null;
            }
        }

        $user = json_decode((string) $data['user'], true);

        if (! is_array($user) || empty($user['id'])) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'username' => $user['username'] ?? null,
        ];
    }
}
