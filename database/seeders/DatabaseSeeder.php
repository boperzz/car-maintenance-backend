<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        // Create default admin user
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role_id' => $adminRole->id,
        ]);

        // Create default staff user
        $staffRole = \App\Models\Role::where('name', 'staff')->first();
        User::factory()->create([
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'role_id' => $staffRole->id,
        ]);

        // Create default customer user
        $userRole = \App\Models\Role::where('name', 'user')->first();
        User::factory()->create([
            'name' => 'Customer User',
            'email' => 'customer@example.com',
            'role_id' => $userRole->id,
        ]);
    }
}
