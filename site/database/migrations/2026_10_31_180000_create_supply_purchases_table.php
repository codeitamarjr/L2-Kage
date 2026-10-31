<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supply kit purchases (see App\Services\SupplyKits). Unlike the other kinds of order a kit can be bought again and
     * again, so nothing here is unique.
     */
    public function up(): void
    {
        Schema::create('supply_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('account', 45)->index();
            // The key under l2j.supplies.kits, e.g. "hunter".
            $table->string('kit', 48);
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('character_id');
            $table->string('character_name', 35);
            $table->foreignId('game_action_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_purchases');
    }
};
