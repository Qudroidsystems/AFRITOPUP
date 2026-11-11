<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The starting roles. A role's permissions are only set when the role is
 * first created, so changes made later in the role editor are kept when
 * the seeder runs again. People can hold several roles at once.
 */
class RoleSeeder extends Seeder
{
    /** role => [badge colour, permissions] ('*' = everything; Super Administrator also bypasses checks) */
    public const ROLES = [
        'Super Administrator' => ['#0B8457', ['*']],

        'Administrator' => ['#0f172a', [
            'View dashboard', 'View transactions', 'Manage transactions', 'View wallets', 'Manage wallets', 'Manage services',
            'View user', 'Create user', 'Update user', 'View role', 'View activity log', 'View online staff',
        ]],

        'Support Officer' => ['#2563EB', [
            'View dashboard', 'View transactions', 'Manage transactions', 'View wallets', 'View user', 'View online staff',
        ]],

        'Finance Officer' => ['#10B981', [
            'View dashboard', 'View transactions', 'View wallets', 'Manage wallets', 'View user',
        ]],

        // People who buy
        'Customer' => ['#F5A623', ['Buy services']],
        'Agent'    => ['#7C3AED', ['Buy services']],
        'Reseller' => ['#EC4899', ['Buy services', 'Use reseller API']],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $name => [$badge, $perms]) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            if ($role) continue; // keep whatever was configured since

            $role = Role::create(['name' => $name, 'guard_name' => 'web', 'badge' => $badge]);
            $role->syncPermissions($perms === ['*'] ? \Spatie\Permission\Models\Permission::all() : $perms);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->command?->info('Roles: ' . Role::count());
    }
}
