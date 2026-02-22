<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Una fila por cada asistencia (entrada) registrada.
     */
    public function up(): void
    {
        Schema::create('subscription_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_subscription_id')->constrained('customer_subscriptions')->onDelete('cascade');
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->dateTime('recorded_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_entries');
    }
};
