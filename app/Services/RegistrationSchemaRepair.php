<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Registration inserts fail when a phpMyAdmin import drops AUTO_INCREMENT
 * from id columns, or when staff_users.role no longer includes "employee".
 * Both surface as a QueryException. This restores those definitions.
 */
class RegistrationSchemaRepair
{
    public function isRepairable(QueryException $exception): bool
    {
        $message = $exception->getMessage()."\n".($exception->getPrevious()?->getMessage() ?? '');

        if (str_contains($message, "Field 'id' doesn't have a default value")) {
            return true;
        }

        return str_contains($message, "column 'role'")
            && (str_contains($message, 'Data truncated') || str_contains($message, 'Incorrect'));
    }

    /**
     * @return list<string>|null null when this database cannot be altered (sqlite tests)
     */
    public function repair(): ?array
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return null;
        }

        $repaired = [];

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($this->idColumnsMissingAutoIncrement() as $column) {
                DB::statement($column['sql']);
                $repaired[] = $column['table'];
            }

            if ($this->ensureStaffRoleEnum()) {
                $repaired[] = 'staff_users.role';
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        return $repaired;
    }

    /**
     * @return list<array{table:string,sql:string}>
     */
    private function idColumnsMissingAutoIncrement(): array
    {
        $rows = DB::select("
            SELECT c.TABLE_NAME AS table_name,
                   c.COLUMN_TYPE AS column_type,
                   (SELECT COUNT(*)
                      FROM information_schema.TABLE_CONSTRAINTS k
                     WHERE k.TABLE_SCHEMA = c.TABLE_SCHEMA
                       AND k.TABLE_NAME = c.TABLE_NAME
                       AND k.CONSTRAINT_TYPE = 'PRIMARY KEY') AS has_pk
              FROM information_schema.COLUMNS c
              JOIN information_schema.TABLES t
                ON t.TABLE_SCHEMA = c.TABLE_SCHEMA
               AND t.TABLE_NAME = c.TABLE_NAME
               AND t.TABLE_TYPE = 'BASE TABLE'
             WHERE c.TABLE_SCHEMA = DATABASE()
               AND c.COLUMN_NAME = 'id'
               AND c.DATA_TYPE IN ('tinyint', 'smallint', 'mediumint', 'int', 'bigint')
               AND c.EXTRA NOT LIKE '%auto_increment%'
        ");

        $columns = [];
        foreach ($rows as $row) {
            $table = (string) $this->value($row, 'table_name');
            $type = (string) $this->value($row, 'column_type');
            $hasPk = (int) $this->value($row, 'has_pk');

            if (! preg_match('/^[A-Za-z0-9_]+$/', $table) || ! preg_match('/^[a-z0-9(), ]+$/i', $type)) {
                continue;
            }

            $sql = 'ALTER TABLE `'.$table.'` MODIFY `id` '.$type.' NOT NULL AUTO_INCREMENT';
            if ($hasPk === 0) {
                $sql .= ', ADD PRIMARY KEY (`id`)';
            }

            $columns[] = ['table' => $table, 'sql' => $sql];
        }

        return $columns;
    }

    private function ensureStaffRoleEnum(): bool
    {
        $column = DB::selectOne("
            SELECT COLUMN_TYPE AS column_type
              FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'staff_users'
               AND COLUMN_NAME = 'role'
        ");

        if (! $column) {
            return false;
        }

        $type = strtolower((string) $this->value($column, 'column_type'));
        if (str_contains($type, "'employee'") && str_contains($type, "'cleaning_staff'")) {
            return false;
        }

        try {
            DB::statement("ALTER TABLE `staff_users` MODIFY COLUMN `role` ENUM('employee', 'teacher', 'non_teaching', 'cleaning_staff') NOT NULL DEFAULT 'employee'");
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    private function value(object $row, string $name): mixed
    {
        foreach (get_object_vars($row) as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }
}
