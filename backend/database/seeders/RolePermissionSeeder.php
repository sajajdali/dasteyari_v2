<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * نقش‌ها و ماتریس مجوز پنل مدیریت — بخش ۲.۲ و ۲.۳ پلن.
 * همهٔ مجوزها روی guard «admin» تعریف می‌شوند (پنل مدیریت). اجرای مجدد امن است.
 */
class RolePermissionSeeder extends Seeder
{
    /** بخش‌های بخش ۲.۳ پلن. */
    private const SECTIONS = [
        'needies', 'requests', 'visits', 'docs', 'donors', 'supports',
        'finance', 'campaigns', 'broadcast', 'tickets', 'content', 'users', 'settings',
    ];

    private const ACTIONS = ['view', 'create', 'edit', 'approve'];

    /** ماتریس مجوز — بخش ۲.۳ پلن. 'all' یعنی هر چهار اقدام. */
    private const MATRIX = [
        'super-admin' => [
            'needies' => 'all', 'requests' => 'all', 'visits' => 'all', 'docs' => 'all',
            'donors' => 'all', 'supports' => 'all', 'finance' => 'all', 'campaigns' => 'all',
            'broadcast' => 'all', 'tickets' => 'all', 'content' => 'all', 'users' => 'all', 'settings' => 'all',
        ],
        'manager' => [
            'needies' => 'all', 'requests' => 'all', 'visits' => ['view', 'approve'], 'docs' => 'all',
            'donors' => 'all', 'supports' => 'all', 'finance' => ['view'], 'campaigns' => 'all',
            'broadcast' => ['view', 'approve'], 'tickets' => ['view'], 'content' => ['view'],
        ],
        'case-officer' => [
            'needies' => ['view', 'edit'], 'requests' => ['view', 'create', 'edit'], 'visits' => ['view'],
            'docs' => ['view', 'create', 'edit', 'approve'], 'donors' => ['view'], 'supports' => ['view'],
            'tickets' => ['view'],
        ],
        'visit-officer' => [
            'needies' => ['view'], 'requests' => ['view'], 'visits' => ['view', 'create', 'edit'],
            'docs' => ['view'],
        ],
        'finance' => [
            'needies' => ['view'], 'requests' => ['view'], 'donors' => ['view'], 'supports' => ['view'],
            'finance' => 'all', 'campaigns' => ['view'],
        ],
        'content' => [
            'campaigns' => ['view', 'create', 'edit'], 'broadcast' => ['view'], 'content' => 'all',
        ],
        'support' => [
            'needies' => ['view'], 'requests' => ['view'], 'donors' => ['view'],
            'broadcast' => ['view', 'create', 'edit'], 'tickets' => 'all',
        ],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::SECTIONS as $section) {
                foreach (self::ACTIONS as $action) {
                    Permission::findOrCreate("$section.$action", 'admin');
                }
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach (self::MATRIX as $roleName => $sections) {
                $role = Role::findOrCreate($roleName, 'admin');
                $permissions = [];
                foreach ($sections as $section => $actions) {
                    $actions = $actions === 'all' ? self::ACTIONS : $actions;
                    foreach ($actions as $action) {
                        $permissions[] = "$section.$action";
                    }
                }
                $role->syncPermissions($permissions);
            }
        });
    }
}
