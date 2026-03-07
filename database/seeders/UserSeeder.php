<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\SellerProfile;
use App\Models\SellerApplication;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin ────────────────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'admin@vaultly.com'],
            [
                'name'              => 'Admin User',
                'password'          => Hash::make('password'),
                'role'              => 'admin',
                'status'            => 'active',
                'email_verified_at' => now(),
            ]
        );

        // ── Sellers ──────────────────────────────────────────────
        $sellers = [
            [
                'name'             => 'Alice Chen',
                'email'            => 'alice@vaultly.com',
                'full_name'        => 'Alice Chen',
                'store_name'       => 'Alice Design Studio',
                'store_slug'       => 'alice-design-studio',
                'store_description' => 'UI/UX designer with 8 years of experience crafting beautiful digital products.',
                'category_focus'   => 'Design Assets',
                'bio'              => 'UI/UX designer with 8 years of experience crafting beautiful digital products.',
                'paypal'           => 'alice@paypal.com',
            ],
            [
                'name'             => 'Bob Martinez',
                'email'            => 'bob@vaultly.com',
                'full_name'        => 'Bob Martinez',
                'store_name'       => 'Bob Dev Kits',
                'store_slug'       => 'bob-dev-kits',
                'store_description' => 'Full-stack developer selling premium code templates and scripts.',
                'category_focus'   => 'Code & Scripts',
                'bio'              => 'Full-stack developer selling premium code templates and scripts.',
                'paypal'           => 'bob@paypal.com',
            ],
            [
                'name'             => 'Carol Wu',
                'email'            => 'carol@vaultly.com',
                'full_name'        => 'Carol Wu',
                'store_name'       => 'Carol Edu Hub',
                'store_slug'       => 'carol-edu-hub',
                'store_description' => 'Educator and author publishing premium courses and ebooks.',
                'category_focus'   => 'Education',
                'bio'              => 'Educator and author publishing premium courses and ebooks.',
                'paypal'           => 'carol@paypal.com',
            ],
        ];

        foreach ($sellers as $s) {
            $user = User::updateOrCreate(
                ['email' => $s['email']],
                [
                    'name'              => $s['name'],
                    'password'          => Hash::make('password'),
                    'role'              => 'seller',
                    'status'            => 'active',
                    'email_verified_at' => now(),
                ]
            );

            SellerApplication::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name'         => $s['full_name'],
                    'store_name'        => $s['store_name'],
                    'store_description' => $s['store_description'],
                    'category_focus'    => $s['category_focus'],
                    'paypal_email'      => $s['paypal'],
                    'status'            => 'approved',
                    'reviewed_at'       => now(),
                ]
            );

            SellerProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'store_name'        => $s['store_name'],
                    'store_slug'        => $s['store_slug'],
                    'store_description' => $s['bio'],
                    'paypal_email'      => $s['paypal'],
                ]
            );
        }

        // ── Buyers ───────────────────────────────────────────────
        $buyers = [
            ['name' => 'David Kim',    'email' => 'david@vaultly.com'],
            ['name' => 'Emma Wilson',  'email' => 'emma@vaultly.com'],
            ['name' => 'Frank Torres', 'email' => 'frank@vaultly.com'],
        ];

        foreach ($buyers as $b) {
            User::updateOrCreate(
                ['email' => $b['email']],
                [
                    'name'              => $b['name'],
                    'password'          => Hash::make('password'),
                    'role'              => 'buyer',
                    'status'            => 'active',
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
