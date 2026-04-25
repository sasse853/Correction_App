<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Permissions ────────────────────────────────────────────────────
        $permissions = [
            'upload-excel',
            'view-own-submissions',
            'resubmit-correction',
            'view-all-submissions',
            'approve-submission',
            'reject-submission',
            'push-to-db2',
            'view-audit-logs',
            'manage-users',
            'assign-roles',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // ── Roles ──────────────────────────────────────────────────────────
        $employeRole = Role::firstOrCreate(['name' => 'employe', 'guard_name' => 'web']);
        $employeRole->syncPermissions([
            'upload-excel',
            'view-own-submissions',
            'resubmit-correction',
        ]);

        $superieurRole = Role::firstOrCreate(['name' => 'superieur', 'guard_name' => 'web']);
        $superieurRole->syncPermissions([
            'view-all-submissions',
            'approve-submission',
            'reject-submission',
            'push-to-db2',
            'view-audit-logs',
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        // ── Admin account ──────────────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@datacorrection.local'],
            [
                'name'       => 'Administrateur Système',
                'password'   => Hash::make('Admin@1234!'),
                'is_active'  => true,
                'created_by' => null,
            ]
        );
        $admin->assignRole('admin');

        // ── Demo users (optionnel) ─────────────────────────────────────────
        $superieur = User::firstOrCreate(
            ['email' => 'superieur@datacorrection.local'],
            [
                'name'       => 'Jean Supérieur',
                'password'   => Hash::make('Super@1234!'),
                'is_active'  => true,
                'created_by' => $admin->id,
            ]
        );
        $superieur->assignRole('superieur');

        $employe = User::firstOrCreate(
            ['email' => 'employe@datacorrection.local'],
            [
                'name'       => 'Marie Employée',
                'password'   => Hash::make('Employe@1234!'),
                'is_active'  => true,
                'created_by' => $admin->id,
            ]
        );
        $employe->assignRole('employe');

        $this->command->info('✅ Seeder terminé. Comptes créés :');
        $this->command->table(
            ['Email', 'Rôle', 'Mot de passe (à changer!)'],
            [
                ['admin@datacorrection.local',     'admin',     'Admin@1234!'],
                ['superieur@datacorrection.local', 'superieur', 'Super@1234!'],
                ['employe@datacorrection.local',   'employe',   'Employe@1234!'],
            ]
        );
    }
}
