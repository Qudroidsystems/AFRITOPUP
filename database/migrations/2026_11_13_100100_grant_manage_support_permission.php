<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * New "Manage support" permission for the support chat. Existing roles are
 * kept as admins configured them, so this only ADDS the permission to the
 * standard roles that answer customers.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $p = Permission::firstOrCreate(['name' => 'Manage support', 'guard_name' => 'web']);
        $p->forceFill(['title' => 'Support', 'description' => 'Answer customers in the support chat, assign chats and mark them solved.'])->save();

        foreach (['Super Administrator', 'Administrator', 'Support Officer'] as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            if ($role && !$role->hasPermissionTo($p)) $role->givePermissionTo($p);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'Manage support')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
