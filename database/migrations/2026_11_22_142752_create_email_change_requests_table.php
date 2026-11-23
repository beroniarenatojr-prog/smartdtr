<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_user_id')->constrained('staff_users')->cascadeOnDelete();
            $table->string('current_email');
            $table->string('new_email');
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('admin_notes', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['staff_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_change_requests');
    }
};
