<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'natalia@skydesk.local'],
            [
                'name' => 'Наталия Я.',
                'initials' => 'НЯ',
                'role_title' => 'Личный помощник',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
    }
}
