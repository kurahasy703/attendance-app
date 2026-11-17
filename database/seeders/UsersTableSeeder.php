<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 一般ユーザー1
        User::updateOrCreate(
            ['email' => 'user1@example.com'],
            [
                'name' => 'テスト太郎',
                'email' => 'user1@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'admin_status' => 0,
            ]
        );

        // 2. 一般ユーザー2
        User::updateOrCreate(
            ['email' => 'user2@example.com'],
            [
                'name' => 'テスト花子',
                'email' => 'user2@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'admin_status' => 0,
            ]
        );

        // 3. 管理者ユーザー
        User::updateOrCreate(
            ['email' => 'user3@example.com'],
            [
                'name' => '管理者ユーザー',
                'email' => 'user3@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'admin_status' => 1, // 管理者権限
            ]
        );
    }
}
