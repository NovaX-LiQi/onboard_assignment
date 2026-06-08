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
        Schema::create('external_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->text('access_token');
            // $table->text('refresh_token')->nullable();
            $table->string('ad_account_id');
            // $table->timestamp('token_expires_at')->nullable();
            // $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_accounts');
    }
};
