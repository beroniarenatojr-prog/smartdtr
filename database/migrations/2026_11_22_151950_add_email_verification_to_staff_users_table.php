<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('email_verification_code')->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_expires_at')->nullable()->after('email_verification_code');
        });

        // Existing accounts already proved their email in production — do not lock them out.
        DB::table('staff_users')->whereNull('email_verified_at')->update([
            'email_verified_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('staff_users', function (Blueprint $table) {
            $table->dropColumn([
                'email_verified_at',
                'email_verification_code',
                'email_verification_expires_at',
            ]);
        });
    }
};
