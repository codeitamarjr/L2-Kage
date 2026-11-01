<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\StoreCategories\StoreCategoryResource;
use App\Mail\AccountMail;
use App\Models\GameAction;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreCategory;
use App\Models\User;
use App\Services\Orders;
use App\Services\Store;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\FakesGameDatabases;
use Tests\TestCase;

class StoreAdminTest extends TestCase
{
    use FakesGameDatabases, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeGameDatabases();
        $this->artisan('itemdb:import')->assertSuccessful();
        $this->actingAs(User::factory()->create(['email' => 'gm@example.com']));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function chest(array $overrides = []): array
    {
        return [
            'store_category_id' => StoreCategory::where('slug', 'chests')->value('id'),
            'slug' => 'dragon-chest',
            'name_en' => 'Dragon Chest',
            'name_pt' => 'Baú do Dragão',
            'description_en' => "Hot.\n\nVery hot.",
            'description_pt' => "Quente.\n\nMuito quente.",
            'art' => 'package',
            'price_item' => self::LCOIN,
            'price_count' => 500,
            'items' => [['item' => 3031, 'count' => 10, 'enchant' => 0]],
            'bonus' => [['item' => 'enchant_weapon', 'count' => 1, 'enchant' => 0, 'weight' => 1]],
            'max_per_order' => 5,
            'is_active' => true,
            ...$overrides,
        ];
    }

    private function product(string $slug): Product
    {
        return Product::where('slug', $slug)->firstOrFail();
    }

    public function test_the_admin_pages_render(): void
    {
        $this->get('/admin/products')->assertOk()->assertSee('Kage Pass');
        $this->get('/admin/products/create')->assertOk();
        $this->get('/admin/products/'.$this->product('kage-pass')->id.'/edit')->assertOk();
        $this->get('/admin/products/'.$this->product('hunt-pass')->id.'/edit')->assertOk();
        $this->get('/admin/products/'.$this->product('weekly-gift-box')->id.'/edit')->assertOk();
        $this->get('/admin/store-categories')->assertOk()->assertSee('Chests');
        $this->get('/admin/orders')->assertOk();
    }

    public function test_an_admin_adds_a_product_with_images_and_players_can_buy_it(): void
    {
        Storage::fake('public');

        Livewire::test(CreateProduct::class)
            ->fillForm($this->chest(['images' => [UploadedFile::fake()->image('dragon.png', 600, 600)]]))
            ->call('create')
            ->assertHasNoFormErrors();

        $product = $this->product('dragon-chest');
        $this->assertCount(1, $product->images);
        Storage::disk('public')->assertExists($product->images[0]);

        $this->actingAs($this->account(), 'player');
        $this->get('/store?category=chests')->assertInertia(fn ($page) => $page->where('filter.category', 'chests'));
        $this->get('/store/dragon-chest')->assertOk()->assertInertia(fn ($page) => $page
            ->where('product.image', Storage::disk('public')->url($product->images[0]))
            ->where('product.description.en', "Hot.\n\nVery hot.")
            ->where('product.maxQuantity', 5));
        $this->post('/store/dragon-chest', ['character_id' => 1, 'quantity' => 2])->assertRedirect('/account/orders');

        $action = GameAction::sole();
        $this->assertSame(1_000, $action->count);
        $this->assertSame('3031:20:0;959:2:0', $action->items); // two bonus rolls of the S-grade scroll at level 78
    }

    public function test_the_form_refuses_what_the_game_server_would_reject(): void
    {
        $sword = 93422;

        // A count of a non-stackable item.
        Livewire::test(CreateProduct::class)
            ->fillForm($this->chest(['items' => [['item' => $sword, 'count' => 2, 'enchant' => 0]], 'bonus' => [], 'max_per_order' => 1]))
            ->call('create')
            ->assertHasFormErrors(['items']);

        // Several at once of something that doesn't stack.
        Livewire::test(CreateProduct::class)
            ->fillForm($this->chest(['items' => [['item' => $sword, 'count' => 1, 'enchant' => 0]], 'bonus' => [], 'max_per_order' => 2]))
            ->call('create')
            ->assertHasFormErrors(['items']);

        // More than one action can carry.
        Livewire::test(CreateProduct::class)
            ->fillForm($this->chest(['items' => array_fill(0, 33, ['item' => 3031, 'count' => 1, 'enchant' => 0]), 'bonus' => []]))
            ->call('create')
            ->assertHasFormErrors(['items']);

        // An item the server doesn't have.
        Livewire::test(CreateProduct::class)
            ->fillForm($this->chest(['items' => [['item' => 999999999, 'count' => 1, 'enchant' => 0]]]))
            ->call('create')
            ->assertHasFormErrors();

        // Under the list price in Adena: it could be resold to an NPC for a profit. Spirit Ore lists for 400.
        Livewire::test(CreateProduct::class)
            ->fillForm($this->chest(['price_item' => self::ADENA, 'price_count' => 100, 'bonus' => []]))
            ->call('create')
            ->assertHasFormErrors(['price_count']);

        // Nothing inside at all.
        Livewire::test(CreateProduct::class)
            ->fillForm($this->chest(['items' => [], 'bonus' => []]))
            ->call('create')
            ->assertHasFormErrors(['items']);

        $this->assertFalse(Product::where('slug', 'dragon-chest')->exists());
    }

    public function test_searching_for_an_item_offers_it_by_its_real_id_and_grade_scrolls_too(): void
    {
        $this->assertArrayHasKey(3031, ProductResource::searchItems('Spirit Ore', true));
        $this->assertArrayHasKey(self::LCOIN, ProductResource::searchItems('91663', false));
        $this->assertArrayHasKey('enchant_weapon', ProductResource::searchItems('Enchant Weapon', true));
        $this->assertArrayNotHasKey('enchant_weapon', ProductResource::searchItems('Enchant Weapon', false));
    }

    public function test_every_seeded_product_can_be_saved_unchanged(): void
    {
        foreach (['kage-pass', 'weekly-gift-box', 'supplies-hunter', 'package-knight-elite', 'hunt-pass', 'gifts-for-a-true-samurai'] as $slug) {
            $product = $this->product($slug);
            $before = $product->only(['price_count', 'items', 'bonus', 'limit_period', 'season']);

            Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
                ->call('save')
                ->assertHasNoFormErrors();

            $after = $product->fresh();
            $this->assertSame((int) $before['price_count'], (int) $after->price_count, $slug);
            $this->assertSame($before['limit_period'], $after->limit_period, $slug);
            $this->assertCount(count($before['items'] ?? []), $after->items ?? [], $slug);
            $this->assertSame(array_column($before['items'] ?? [], 'item'), array_map(fn ($item) => is_numeric($item) ? (int) $item : $item, array_column($after->items ?? [], 'item')), $slug);
        }
    }

    public function test_copying_a_product_makes_an_unpublished_one_without_its_images(): void
    {
        Storage::fake('public');
        $product = $this->product('supplies-hunter');
        $product->update(['images' => ['store/a.webp']]);

        Livewire::test(ListProducts::class)->callAction(TestAction::make('replicate')->table($product));

        $copy = Product::where('slug', 'like', 'supplies-hunter-copy-%')->sole();
        $this->assertFalse($copy->is_active);
        $this->assertNull($copy->images);
        $this->assertSame($product->items, $copy->items);
    }

    public function test_deleting_a_product_hides_it_and_keeps_its_orders(): void
    {
        $product = $this->product('supplies-hunter');
        app(Store::class)->buy($this->account(), $product, 1, 1);

        Livewire::test(ListProducts::class)->callAction(TestAction::make('delete')->table($product));

        $this->assertTrue($product->fresh()->trashed());
        $this->get('/store/supplies-hunter')->assertNotFound();
        $this->assertSame($product->id, Order::sole()->product_id);
        $this->assertSame("Hunter's Kit", app(Orders::class)->history($this->account())[0]['name']['en']);
    }

    public function test_sending_a_product_makes_it_wait_for_the_player_to_choose_a_character(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.com']);
        $product = $this->product('package-knight-elite');

        Livewire::test(ListProducts::class)
            ->callAction(TestAction::make('sendToAccounts')->table($product), ['target' => 'accounts', 'accounts' => ['hero'], 'note' => 'Sorry for the outage']);

        $order = Order::sole();
        $this->assertSame('hero', $order->account);
        $this->assertSame(Order::SOURCE_ADMIN, $order->source);
        $this->assertSame(Order::STATUS_WAITING, $order->status());
        $this->assertNull($order->limit_key);
        $this->assertSame('admin (gm@example.com)', $order->sent_by);
        $this->assertSame(0, GameAction::count(), 'nothing goes to the game until a character is chosen');

        $this->actingAs($this->account(), 'player');
        $this->get('/')->assertInertia(fn ($page) => $page->where('player.gifts', 1));
        $this->get('/account/orders')->assertInertia(fn ($page) => $page
            ->where('orders.0.status', 'waiting')
            ->where('orders.0.fromStaff', true)
            ->where('orders.0.note', 'Sorry for the outage')
            ->where('orders.0.price', null)
            ->has('orders.0.items', 12)
            ->has('recipients', 2));

        // It didn't use up what the account can still buy.
        $this->get('/store/package-knight-elite')->assertInertia(fn ($page) => $page->where('buyer.owned', null));

        // Any of the player's characters can take it, whatever the level; no one else's.
        $this->post("/account/orders/{$order->id}/redeem", ['character_id' => 3])->assertSessionHasErrors('character_id');
        $this->post("/account/orders/{$order->id}/redeem", ['character_id' => 2])->assertRedirect('/account/orders');

        $action = GameAction::sole();
        $this->assertSame(GameAction::ACTION_SEND_MAIL, $action->action);
        $this->assertNull($action->item_id, 'nothing is charged');
        $this->assertSame(2, $action->character_id);
        $this->assertSame('Your L2 Kage class package', $action->mail_subject);
        $this->assertSame('admin (gm@example.com)', $action->requested_by);
        $this->assertStringStartsWith('93291:1:8;', $action->items);

        $order->refresh();
        $this->assertSame('Fresh', $order->character_name);
        $this->assertSame(GameAction::STATUS_PENDING, $order->status());
        $this->get('/')->assertInertia(fn ($page) => $page->where('player.gifts', 0));

        // A second press finds nothing waiting.
        $this->post("/account/orders/{$order->id}/redeem", ['character_id' => 1])->assertNotFound();
        $this->assertSame(1, GameAction::count());
    }

    public function test_a_class_only_gift_that_was_sent_still_needs_that_class(): void
    {
        app(Store::class)->send($this->product('gifts-for-a-true-samurai'), ['hero'], 'admin', 'Welcome');
        $order = Order::sole();

        $this->actingAs($this->account(), 'player')->post("/account/orders/{$order->id}/redeem", ['character_id' => 2])->assertSessionHasErrors('character_id');
        $this->assertSame(0, GameAction::count());

        $this->actingAs($this->account(), 'player')->post("/account/orders/{$order->id}/redeem", ['character_id' => 1])->assertRedirect('/account/orders');
        $this->assertSame(1, GameAction::count());
    }

    public function test_it_keeps_the_contents_from_when_it_was_sent(): void
    {
        $product = $this->product('supplies-hunter');
        app(Store::class)->send($product, ['hero'], 'admin', 'Gift');
        $product->update(['items' => [['item' => 57, 'count' => 1]]]);

        $this->actingAs($this->account(), 'player')->post('/account/orders/'.Order::sole()->id.'/redeem', ['character_id' => 1]);

        $this->assertSame('1539:50:0;5592:20:0;3031:100:0', GameAction::sole()->items);
    }

    public function test_one_account_cannot_take_what_was_sent_to_another(): void
    {
        app(Store::class)->send($this->product('supplies-hunter'), ['other'], 'admin', 'Gift');

        $this->actingAs($this->account(), 'player')->post('/account/orders/'.Order::sole()->id.'/redeem', ['character_id' => 1])->assertNotFound();
        $this->assertSame(0, GameAction::count());
    }

    public function test_every_account_can_be_sent_it_except_banned_ones(): void
    {
        DB::connection('login')->table('accounts')->insert(['login' => 'banned', 'password' => 'x', 'accessLevel' => -1]);

        Livewire::test(ListProducts::class)
            ->callAction(TestAction::make('sendToAccounts')->table($this->product('weekly-gift-box')), ['target' => 'all', 'note' => 'Happy anniversary']);

        $this->assertEqualsCanonicalizing(['hero', 'other'], Order::pluck('account')->all());
    }

    public function test_the_hunt_pass_cannot_be_sent(): void
    {
        $this->expectException(ValidationException::class);

        app(Store::class)->send($this->product('hunt-pass'), ['hero'], 'admin', null);
    }

    public function test_only_what_still_waits_can_be_cancelled(): void
    {
        $store = app(Store::class);
        $store->send($this->product('supplies-hunter'), ['hero', 'other'], 'admin', 'Oops');
        $delivered = Order::where('account', 'other')->sole();
        $store->redeem($this->account('other'), $delivered, 3);
        $waiting = Order::where('account', 'hero')->sole();

        Livewire::test(ListOrders::class)
            ->assertActionVisible(TestAction::make('delete')->table($waiting))
            ->assertActionHidden(TestAction::make('delete')->table($delivered))
            ->callAction(TestAction::make('delete')->table($waiting));

        $this->assertNull(Order::find($waiting->id));
        $this->assertNotNull(Order::find($delivered->id));
    }

    public function test_a_category_with_products_cannot_be_deleted(): void
    {
        $this->assertFalse(StoreCategoryResource::canDelete(StoreCategory::where('slug', 'gifts')->sole()));
        $this->assertTrue(StoreCategoryResource::canDelete(StoreCategory::where('slug', 'chests')->sole()));
    }

    public function test_the_email_says_a_gift_is_waiting(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.com']);

        app(Store::class)->send($this->product('supplies-hunter'), ['hero', 'other'], 'admin', 'Gift');
        app()->terminate();
        $this->get('/');

        Mail::assertSent(AccountMail::class, fn (AccountMail $mail): bool => $mail->hasTo('hero@example.com') && str_contains(implode(' ', $mail->lines), "Hunter's Kit"));
    }
}
