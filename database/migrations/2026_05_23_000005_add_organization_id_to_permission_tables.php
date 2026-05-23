<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teamKey = $columnNames['team_foreign_key'] ?? 'organization_id';
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        // Jika kolom teamKey sudah ada di tabel roles (karena migrasi dijalankan fresh dengan config teams => true),
        // maka kita hanya perlu merubah primary key di pivot table menjadi unique index dan membuat kolom nullable.
        if (Schema::hasColumn($tableNames['roles'], $teamKey)) {
            // 2. Modify model_has_permissions table
            try {
                Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) {
                    $table->dropPrimary('model_has_permissions_permission_model_type_primary');
                });
            } catch (\Exception $e) {}
            try {
                Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamKey) {
                    $table->unsignedBigInteger($teamKey)->nullable()->change();
                });
            } catch (\Exception $e) {}
            try {
                Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamKey, $pivotPermission) {
                    $table->unique([$teamKey, $pivotPermission, 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_unique');
                });
            } catch (\Exception $e) {}

            // 3. Modify model_has_roles table
            try {
                Schema::table($tableNames['model_has_roles'], function (Blueprint $table) {
                    $table->dropPrimary('model_has_roles_role_model_type_primary');
                });
            } catch (\Exception $e) {}
            try {
                Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamKey) {
                    $table->unsignedBigInteger($teamKey)->nullable()->change();
                });
            } catch (\Exception $e) {}
            try {
                Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamKey, $pivotRole) {
                    $table->unique([$teamKey, $pivotRole, 'model_id', 'model_type'], 'model_has_roles_role_model_type_unique');
                });
            } catch (\Exception $e) {}

            return;
        }

        // Jalankan migrasi normal untuk database lama yang belum memiliki kolom teamKey
        // 1. Modify roles table
        Schema::table($tableNames['roles'], function (Blueprint $table) use ($teamKey) {
            $table->dropUnique('roles_name_guard_name_unique');
            $table->unsignedBigInteger($teamKey)->nullable()->after('id');
            $table->index($teamKey, 'roles_team_foreign_key_index');
            $table->unique([$teamKey, 'name', 'guard_name'], 'roles_team_name_guard_name_unique');
        });

        // 2. Modify model_has_permissions table
        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamKey, $pivotPermission) {
            $table->dropPrimary('model_has_permissions_permission_model_type_primary');
            $table->unsignedBigInteger($teamKey)->nullable()->after($pivotPermission);
            $table->index($teamKey, 'model_has_permissions_team_foreign_key_index');
            $table->unique([$teamKey, $pivotPermission, 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_unique');
        });

        // 3. Modify model_has_roles table
        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamKey, $pivotRole) {
            $table->dropPrimary('model_has_roles_role_model_type_primary');
            $table->unsignedBigInteger($teamKey)->nullable()->after($pivotRole);
            $table->index($teamKey, 'model_has_roles_team_foreign_key_index');
            $table->unique([$teamKey, $pivotRole, 'model_id', 'model_type'], 'model_has_roles_role_model_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teamKey = $columnNames['team_foreign_key'] ?? 'organization_id';
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        // Revert model_has_roles
        try {
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamKey, $pivotRole) {
                $table->dropUnique('model_has_roles_role_model_type_unique');
                if (Schema::hasColumn($tableNames['model_has_roles'], $teamKey)) {
                    $table->dropColumn($teamKey);
                }
                $table->primary([$pivotRole, 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
            });
        } catch (\Exception $e) {}

        // Revert model_has_permissions
        try {
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamKey, $pivotPermission) {
                $table->dropUnique('model_has_permissions_permission_model_type_unique');
                if (Schema::hasColumn($tableNames['model_has_permissions'], $teamKey)) {
                    $table->dropColumn($teamKey);
                }
                $table->primary([$pivotPermission, 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
            });
        } catch (\Exception $e) {}

        // Revert roles
        try {
            Schema::table($tableNames['roles'], function (Blueprint $table) use ($teamKey) {
                $table->dropUnique('roles_team_name_guard_name_unique');
                if (Schema::hasColumn($tableNames['roles'], $teamKey)) {
                    $table->dropColumn($teamKey);
                }
                $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
            });
        } catch (\Exception $e) {}
    }
};
