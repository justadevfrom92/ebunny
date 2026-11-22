<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/** Undo the short-lived "vip_accounts" right: Friends & Family changes go to a Customer Service Lead for approval instead. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Role::all() as $role) {
            if (in_array('vip_accounts', $role->perms ?? [], true)) {
                $role->update(['perms' => array_values(array_diff($role->perms, ['vip_accounts']))]);
            }
        }
    }

    public function down(): void {}
};
