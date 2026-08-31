<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Enums\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // حساب المدير
        User::create([
            'name' => 'Manager Name',
            'email' => 'manager@app.com',
            'password' => '12345678',
            'role' => Role::MANAGER,
        ]);

        // حساب الموظف
        User::create([
            'name' => 'Employee Name',
            'email' => 'employee@app.com',
            'password' => '12345678',
            'role' => Role::EMPLOYEE,
        ]);
    }
}
