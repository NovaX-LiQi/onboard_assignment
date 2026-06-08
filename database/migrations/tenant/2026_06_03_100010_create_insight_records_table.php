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
        Schema::create('insight_records', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('level');
            $table->string('external_id');
            $table->date('date');
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->decimal('spend', 10, 2)->default(0);
            // $table->json('raw')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_id', 'date', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insight_records');
    }
};
