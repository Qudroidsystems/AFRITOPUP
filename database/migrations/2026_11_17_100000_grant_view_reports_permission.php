<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * New "View reports" permission for Reports & analytics (sales, profit, vendors, customers).
 * Only ADDS it to the standard roles that look at the numbers; other roles are left as admins set them.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $p = Permission::firstOrCreate(['name' => 'View reports', 'guard_name' => 'web']);
        $p->forceFill(['title' => 'Dashboard', 'description' => 'See reports and analytics: sales, cost, profit, success rates, vendors, channels and top customers, and export them.'])->save();

        foreach (['Super Administrator', 'Administrator', 'Finance Officer'] as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            if ($role && !$role->hasPermissionTo($p)) $role->givePermissionTo($p);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'View reports')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
