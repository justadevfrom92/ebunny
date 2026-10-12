<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Roles and FICTIONAL demo users. Every demo user's password is ADMIN_DEMO_PASSWORD (default "password"). */
class AccessSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DatabaseSeeder::data('roles') as $r) {
            Role::updateOrCreate(['name' => $r['name']], ['perms' => $r['perms']]);
        }

        $password = env('ADMIN_DEMO_PASSWORD', 'password');
        $users = DatabaseSeeder::data('users');
        foreach ($users as $u) {
            User::updateOrCreate(['email' => $u['email']], [
                'name' => $u['name'],
                'password' => $password,
                'role_id' => Role::where('name', $u['role'])->value('id'),
                'active' => $u['active'],
                'employee_id' => $u['employee_id'] ?? null, 'title' => $u['title'] ?? null, 'department' => $u['department'] ?? null,
                'phone_ext' => $u['ext'] ?? null, 'location' => $u['location'] ?? null, 'hired_on' => $u['hired'] ?? null,
                'extra_perms' => $u['extra'] ?? [], 'denied_perms' => $u['denied'] ?? [], 'access_note' => $u['note'] ?? null,
            ]);
        }
        // Managers once everyone exists
        foreach ($users as $u) {
            if ($u['manager'] ?? null) {
                User::where('email', $u['email'])->update(['manager_id' => User::where('email', $u['manager'])->value('id')]);
            }
        }
    }
}
