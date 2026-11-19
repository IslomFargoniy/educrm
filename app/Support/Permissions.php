<?php

namespace App\Support;

use App\Enums\Role;

/**
 * Central list of permissions and the default permissions of each role.
 */
final class Permissions
{
    /**
     * All permissions grouped by area (used for the role editor).
     *
     * @return array<string, list<string>>
     */
    public static function grouped(): array
    {
        return [
            'dashboard' => ['dashboard.view'],
            'settings' => ['branches.manage', 'rooms.manage', 'holidays.manage', 'users.manage', 'roles.manage', 'settings.manage', 'import.run'],
            'students' => ['students.view', 'students.create', 'students.update', 'students.view_phone', 'students.view_debt', 'students.view_payments', 'students.face', 'enrollments.manage'],
            'leads' => ['leads.view', 'leads.manage'],
            'groups' => ['groups.view', 'groups.manage', 'groups.prices', 'lessons.manage', 'attendance.mark', 'attendance.mark_past', 'attendance.edit_past'],
            'finance' => ['payments.create', 'payments.reverse_today', 'payments.reverse', 'refunds.create', 'balance.transfer', 'debts.writeoff', 'teachers.view', 'payouts.create', 'cashboxes.view', 'cashboxes.transfer', 'expenses.create', 'profit.withdraw', 'reports.view'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_merge(...array_values(self::grouped()));
    }

    /**
     * Default permissions per role. Superadmin passes every check through Gate::before.
     *
     * @return array<string, list<string>>
     */
    public static function defaults(): array
    {
        $all = self::all();

        return [
            Role::Superadmin->value => $all,
            Role::Admin->value => array_values(array_diff($all, ['branches.manage', 'roles.manage'])),
            Role::Manager->value => [
                'dashboard.view', 'students.view', 'students.create', 'students.update', 'students.view_phone', 'students.face',
                'students.view_debt', 'students.view_payments', 'enrollments.manage', 'leads.view', 'leads.manage',
                'groups.view', 'groups.manage', 'lessons.manage', 'attendance.mark', 'attendance.edit_past',
                'payments.create', 'payments.reverse_today', 'refunds.create', 'balance.transfer', 'teachers.view',
                'payouts.create', 'cashboxes.view', 'cashboxes.transfer', 'expenses.create',
            ],
            Role::Teacher->value => ['attendance.mark', 'students.view_phone', 'students.view_debt', 'students.view_payments', 'students.face'],
            Role::Parent->value => [],
            Role::Student->value => [],
        ];
    }
}
