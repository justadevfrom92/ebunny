<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/** Friends & Family accounts: money and service changes need the new "vip_accounts" right; Administrators and Customer Service Leads get it. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Role::whereIn('name', ['Administrator', 'Customer Service Lead'])->get() as $role) {
            $role->update(['perms' => array_values(array_unique([...($role->perms ?? []), 'vip_accounts']))]);
        }
    }

    public function down(): void
    {
        foreach (Role::all() as $role) {
            $role->update(['perms' => array_values(array_diff($role->perms ?? [], ['vip_accounts']))]);
        }
    }
};
