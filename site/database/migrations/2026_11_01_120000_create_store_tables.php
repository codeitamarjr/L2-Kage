<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Store (see App\Services\Store): categories and products edited in the admin panel, and one table of orders for
     * everything bought in the Store or sent by an admin.
     */
    public function up(): void
    {
        Schema::create('store_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 48)->unique();
            $table->string('name_en', 60);
            $table->string('name_pt', 60);
            // Used by the category's products that have no image of their own.
            $table->string('image')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->foreignId('store_category_id')->constrained()->restrictOnDelete();
            // "mail" for almost everything; the weekly gift box and the Hunt Pass each have their own type.
            $table->string('type', 16)->default('mail');
            $table->string('name_en', 120);
            $table->string('name_pt', 120);
            $table->text('description_en');
            $table->text('description_pt');
            // Uploaded image paths on the public disk, the first one being the cover.
            $table->json('images')->nullable();
            // The drawn picture when there is no image: its art, and the emblem and tier tint over it.
            $table->string('art', 16)->default('package');
            $table->string('emblem', 32)->nullable();
            $table->string('tier', 32)->nullable();
            // The key under config l2j.store.class_groups: its characters are listed first and it can be filtered by.
            $table->string('class_group', 32)->nullable()->index();
            // Null: free.
            $table->unsignedInteger('price_item')->nullable();
            $table->unsignedBigInteger('price_count')->nullable();
            // {item: id or grade key, count, enchant}, and the same with a weight for the one rolled bonus.
            $table->json('items')->nullable();
            $table->json('bonus')->nullable();
            // Null: as many as the player likes; otherwise one per week, month, season or ever.
            $table->string('limit_period', 16)->nullable();
            $table->string('season', 32)->nullable();
            $table->unsignedSmallInteger('max_per_order')->default(1);
            $table->unsignedSmallInteger('min_level')->nullable();
            $table->string('mail_subject', 128)->nullable();
            $table->text('mail_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('account', 45)->index();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // "purchase", or "admin" for what an admin sent.
            $table->string('source', 16)->default('purchase');
            // What it was at the time, so the history stays right after the product changes.
            $table->string('category', 48);
            $table->json('category_name');
            $table->json('name');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('price_item')->nullable();
            $table->unsignedBigInteger('price_count')->nullable();
            $table->string('price_name')->nullable();
            // Null on something an admin sent, until the player chooses who receives it.
            $table->unsignedInteger('character_id')->nullable();
            $table->string('character_name', 35)->nullable();
            // What was sent: {id, name, count, enchant, bonus}.
            $table->json('items')->nullable();
            // Sent by an admin and waiting for a character: the product's {items, bonus} when it was sent.
            $table->json('contents')->nullable();
            $table->foreignId('game_action_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sent_by')->nullable();
            $table->string('note')->nullable();
            // Which limit this order used ("week:2026-10-26"). The unique key is what keeps it to one per account, even if
            // Buy is pressed twice at the same moment; a failed charge clears it so the player can try again.
            $table->string('limit_key', 64)->nullable();
            $table->timestamps();

            $table->unique(['account', 'product_id', 'limit_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('store_categories');
    }
};
