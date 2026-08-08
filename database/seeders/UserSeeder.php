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
                'is_demo' => false,
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
                'is_demo' => false,
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'demo@skydesk.local'],
            [
                'name' => 'Демо',
                'initials' => 'ДМ',
                'role_title' => 'Личный помощник',
                'password' => 'demo',
                'is_admin' => false,
                'is_demo' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
