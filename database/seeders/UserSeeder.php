<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'G00glemap';

        User::updateOrCreate(
            ['email' => 'nkorsakov@skydesk.local'],
            [
                'name' => 'Николай К.',
                'initials' => 'НК',
                'role_title' => 'Владелец',
                'password' => $password,
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'nataliya@skydesk.local'],
            [
                'name' => 'Наталия Я.',
                'initials' => 'НЯ',
                'role_title' => 'Личный помощник',
                'password' => $password,
                'is_admin' => false,
                'email_verified_at' => now(),
            ],
        );
    }
}
