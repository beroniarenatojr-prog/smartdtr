<?php

use App\Services\RegistrationSchemaRepair;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(RegistrationSchemaRepair::class)->repair();
    }

    public function down(): void
    {
        // AUTO_INCREMENT and the employee role are the schema the app requires.
    }
};
