<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultUserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Patricia Gómez',
            'email' => 'patricia.gomez@templet.io',
            'password' => Hash::make('Templet2026+'),
            'region' => 'carib',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Francisco Templet',
            'email' => 'francisco@templet.io',
            'password' => Hash::make('Templet2026+'),
            'region' => 'carib',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }
}
