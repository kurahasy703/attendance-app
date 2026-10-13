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
        User::create([
            'name' => 'テスト太郎',
            'email' => 'user1@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => 0,
        ]);

        // 2. 一般ユーザー2
        User::create([
            'name' => 'テスト花子',
            'email' => 'user2@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => 0,
        ]);

        // 3. 管理者ユーザー
        User::create([
            'name' => '管理者ユーザー',
            'email' => 'user3@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => 1, // 管理者権限
        ]);
    }
}
