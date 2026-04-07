<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $users = [
            [
                'id' => 1,
                'name' => 'Victor Admin',
                'email' => 'admin@queirozprimecrm.com',
                'password' => Hash::make('Admin123456!'),
                'role' => UserRole::Admin->value,
                'is_active' => true,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'name' => 'Amanda Gerente',
                'email' => 'gerente@queirozprimecrm.com',
                'password' => Hash::make('Gerente123456!'),
                'role' => UserRole::Manager->value,
                'is_active' => true,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'name' => 'Josue Vendedor',
                'email' => 'vendedor@queirozprimecrm.com',
                'password' => Hash::make('Vendedor123456!'),
                'role' => UserRole::Seller->value,
                'is_active' => true,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        User::upsert(
            $users,
            ['id'],
            ['name', 'email', 'password', 'role', 'is_active', 'email_verified_at', 'updated_at']
        );
    }
}
