<?php

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
        Schema::create('integration_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('status')->default('pending'); // pending, running, success, failed
            $table->date('date_from');
            $table->date('date_to');
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_jobs');
    }
};
