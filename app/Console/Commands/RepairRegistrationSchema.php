<?php

namespace App\Console\Commands;

use App\Services\RegistrationSchemaRepair;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairRegistrationSchema extends Command
{
    protected $signature = 'smartdtr:repair-schema';

    protected $description = 'Restore AUTO_INCREMENT on id columns and the employee role so account registration can be saved';

    public function handle(RegistrationSchemaRepair $schema): int
    {
        $repaired = $schema->repair();

        if ($repaired === null) {
            $this->error('This command only repairs MySQL or MariaDB. The current connection is '.DB::getDriverName().'.');

            return self::FAILURE;
        }

        if ($repaired === []) {
            $this->info('Nothing to repair. Id columns already auto-increment, and the employee role is present.');

            return self::SUCCESS;
        }

        foreach ($repaired as $item) {
            $this->line('Repaired '.$item);
        }

        $this->info('Registration can be saved again.');

        return self::SUCCESS;
    }
}
