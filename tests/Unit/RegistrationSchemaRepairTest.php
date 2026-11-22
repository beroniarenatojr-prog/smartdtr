<?php

namespace Tests\Unit;

use App\Services\RegistrationSchemaRepair;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class RegistrationSchemaRepairTest extends TestCase
{
    public function test_detects_the_database_errors_that_block_registration(): void
    {
        $repair = new RegistrationSchemaRepair;

        $missingId = new QueryException(
            'mysql',
            'insert into `staff_users` (`email`) values (?)',
            ['person@example.test'],
            new \PDOException("SQLSTATE[HY000]: General error: 1364 Field 'id' doesn't have a default value")
        );
        $badRole = new QueryException(
            'mysql',
            'insert into `staff_users` (`role`) values (?)',
            ['employee'],
            new \PDOException("SQLSTATE[HY000]: General error: 1265 Data truncated for column 'role' at row 1")
        );
        $unknownColumn = new QueryException(
            'mysql',
            'insert into `staff_users` (`last_name`) values (?)',
            ['Cruz'],
            new \PDOException("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'last_name' in 'field list'")
        );

        $this->assertTrue($repair->isRepairable($missingId));
        $this->assertTrue($repair->isRepairable($badRole));
        $this->assertFalse($repair->isRepairable($unknownColumn));
    }

    public function test_sqlite_is_left_alone(): void
    {
        $this->assertNull(app(RegistrationSchemaRepair::class)->repair());
    }
}
