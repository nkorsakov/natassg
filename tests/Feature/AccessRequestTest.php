<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccessRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccessRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_is_sent_to_admins_with_telegram(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true, 'telegram_id' => 111]);
        $adminWithoutTg = User::factory()->create(['is_admin' => true, 'telegram_id' => null]);
        $user = User::factory()->create(['is_admin' => false, 'telegram_id' => 222]);

        $this->post('/access-request', [
            'name' => 'Анна',
            'telegram' => '@anna_pa',
            'place' => 'hero',
        ])->assertRedirect()->assertSessionHas('status', 'access-requested');

        Notification::assertSentTo($admin, AccessRequestNotification::class, function ($n) use ($admin) {
            $text = $n->toTelegram($admin)->toArray()['text'];

            return $n->lead['telegram'] === 'https://t.me/anna_pa'
                && str_contains($text, 'Анна')
                && str_contains($text, 'hero');
        });
        Notification::assertNotSentTo([$adminWithoutTg, $user], AccessRequestNotification::class);
    }

    public function test_contact_is_required(): void
    {
        Notification::fake();

        $this->post('/access-request', ['name' => 'Анна'])
            ->assertSessionHasErrors('phone');

        Notification::assertNothingSent();
    }

    public function test_invalid_telegram_is_rejected(): void
    {
        $this->post('/access-request', ['telegram' => 'not a nick!'])
            ->assertSessionHasErrors('telegram');
    }

    public function test_honeypot_silently_drops_request(): void
    {
        Notification::fake();
        User::factory()->create(['is_admin' => true, 'telegram_id' => 111]);

        $this->post('/access-request', ['phone' => '+79990000000', 'website' => 'spam'])
            ->assertSessionHas('status', 'access-requested');

        Notification::assertNothingSent();
    }
}
