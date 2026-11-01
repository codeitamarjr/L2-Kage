<?php

use Database\Seeders\StoreSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Products only one class can receive, for sites that ran the Store's first migration before it had them. Runs the
     * seeder again so a deploy also adds the Samurai's welcome gift.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'class_only')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('class_only')->default(false)->after('class_group');
            });
        }

        (new StoreSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('class_only');
        });
    }
};
