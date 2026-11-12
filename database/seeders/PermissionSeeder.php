<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Every permission in AfriTopup, grouped (`title` is the group the role editor
 * shows). Safe to run again: it adds what is missing and refreshes the groups
 * and descriptions, and never removes anything.
 */
class PermissionSeeder extends Seeder
{
    /** group => [permission => description] */
    public const PERMISSIONS = [
        'Dashboard' => [
            'View dashboard' => 'See the platform overview: sales, fundings, wallet float, provider balances and failures.',
        ],
        'Buying' => [
            'Buy services'     => 'Fund a wallet and buy airtime, data, cable TV, electricity, SMS and education payments.',
            'Use reseller API' => 'Create API keys and buy through the reseller API.',
        ],
        'Transactions' => [
            'View transactions'   => 'See every purchase on the platform and export them.',
            'Manage transactions' => 'Re-check purchases with the provider, refund them to the wallet and mark manual ones delivered.',
        ],
        'Wallets' => [
            'View wallets'   => 'See wallet balances, statements and fundings.',
            'Manage wallets' => 'Credit or debit wallets by hand and put wallets on hold.',
        ],
        'Support' => [
            'Manage support' => 'Answer customers in the support chat, assign chats and mark them solved.',
        ],
        'Catalogue' => [
            'Manage services' => 'Manage services, plans and prices, providers, price tiers, countries, schools and SMS sender IDs.',
        ],
        'Users' => [
            'View user'   => 'See the list of accounts and each person\'s profile.',
            'Create user' => 'Create accounts and import staff from Excel.',
            'Update user' => 'Edit accounts, reset passwords, change roles and price tiers, and disable sign-in.',
            'Delete user' => 'Delete accounts. This cannot be undone.',
        ],
        'Roles' => [
            'View role'        => 'See roles and who holds them.',
            'Create role'      => 'Create new roles.',
            'Update role'      => 'Change a role\'s name and permissions.',
            'Delete role'      => 'Delete roles.',
            'Update user-role' => 'Add people to a role.',
            'Remove user-role' => 'Remove people from a role.',
        ],
        'Permissions' => [
            'View permission'   => 'See the list of permissions.',
            'Create permission' => 'Create permissions.',
            'Update permission' => 'Rename permissions.',
            'Delete permission' => 'Delete permissions.',
        ],
        'Security & Audit' => [
            'View activity log' => 'See every change made on the platform and export it.',
            'View online staff' => 'See which staff are signed in right now.',
        ],
        'Settings' => [
            'Manage settings' => 'Change platform settings: Paystack keys and VTU / SMS provider keys.',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $group => $perms) {
            foreach ($perms as $name => $description) {
                $p = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                $p->forceFill(['title' => $group, 'description' => $description])->save();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->command?->info('Permissions: ' . Permission::count());
    }
}
