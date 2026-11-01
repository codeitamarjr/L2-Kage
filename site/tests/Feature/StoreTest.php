<?php

namespace Tests\Feature;

use App\Mail\AccountMail;
use App\Models\GameAction;
use App\Models\GiftBoxTemplate;
use App\Models\Order;
use App\Models\Product;
use App\Services\Orders;
use App\Services\Store;
use App\Services\StoreContents;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\FakesGameDatabases;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use FakesGameDatabases, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeGameDatabases();
    }

    private function product(string $slug): Product
    {
        return Product::where('slug', $slug)->firstOrFail();
    }

    public function test_the_store_lists_every_product_and_guests_see_no_account_data(): void
    {
        $this->get('/store')->assertOk()->assertInertia(fn ($page) => $page
            ->component('store/index')
            ->where('filter.category', 'main')
            ->where('owned', [])
            ->where('suggested', null)
            ->where('products.0.slug', 'weekly-gift-box')
            ->where('products.0.price', null)
            ->where('products.1.slug', 'gifts-for-a-true-samurai')
            ->where('products.2.slug', 'kage-pass')
            ->where('products.3.slug', 'hunt-pass')
            ->has('products', 45)
            ->missing('products.0.contents'));

        $this->get('/store/kage-pass')->assertOk()->assertInertia(fn ($page) => $page->where('buyer', null));
    }

    public function test_only_categories_with_products_show_and_new_ones_work_by_themselves(): void
    {
        $this->get('/store')->assertInertia(fn ($page) => $page->where('categories', fn ($categories): bool => collect($categories)->pluck('key')->all() === ['gifts', 'passes', 'packages', 'supplies']));

        $this->product('supplies-hunter')->update(['store_category_id' => DB::table('store_categories')->where('slug', 'chests')->value('id')]);

        $this->get('/store?category=chests')->assertInertia(fn ($page) => $page
            ->where('filter.category', 'chests')
            ->where('categories.3.key', 'chests'));
    }

    public function test_an_unknown_product_or_category_does_not_break(): void
    {
        $this->get('/store/nothing')->assertNotFound();
        $this->actingAs($this->account(), 'player')->post('/store/nothing', ['character_id' => 1])->assertNotFound();
        $this->get('/store?category=nonsense')->assertOk()->assertInertia(fn ($page) => $page->where('filter.category', 'main'));
    }

    public function test_unpublished_and_scheduled_products_are_hidden_and_ended_ones_show_as_closed(): void
    {
        $this->product('supplies-hunter')->update(['is_active' => false]);
        $this->product('supplies-traveler')->update(['starts_at' => now()->addDay()]);
        $this->product('kage-pass')->update(['ends_at' => now()->subDay()]);

        $this->get('/store/supplies-hunter')->assertNotFound();
        $this->get('/store/supplies-traveler')->assertNotFound();
        $this->actingAs($this->account(), 'player')->post('/store/supplies-hunter', ['character_id' => 1])->assertNotFound();
        $this->get('/store')->assertInertia(fn ($page) => $page->has('products', 43));

        $this->get('/store/kage-pass')->assertOk()->assertInertia(fn ($page) => $page->where('product.open', false));
        $this->actingAs($this->account(), 'player')->post('/store/kage-pass', ['character_id' => 1])->assertSessionHasErrors('character_id');
        $this->assertSame(0, GameAction::count());
    }

    public function test_a_product_shows_its_uploaded_images_with_the_first_as_the_cover(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('store/a.webp', 'a');
        Storage::disk('public')->put('store/b.webp', 'b');
        $this->product('supplies-hunter')->update(['images' => ['store/a.webp', 'store/b.webp']]);

        $this->get('/store/supplies-hunter')->assertInertia(fn ($page) => $page
            ->where('product.image', Storage::disk('public')->url('store/a.webp'))
            ->has('product.images', 2)
            ->where('seo.image', Storage::disk('public')->url('store/a.webp')));
        $this->get('/store/supplies-traveler')->assertInertia(fn ($page) => $page->where('product.image', null)->where('product.art', 'supplies'));

        // Removing an image deletes its file.
        $this->product('supplies-hunter')->update(['images' => ['store/b.webp']]);
        Storage::disk('public')->assertMissing('store/a.webp');
        Storage::disk('public')->assertExists('store/b.webp');
    }

    public function test_a_product_page_shows_each_characters_balance_of_that_products_own_price(): void
    {
        $this->actingAs($this->account(), 'player')->get('/store/supplies-hunter')->assertOk()->assertInertia(fn ($page) => $page
            ->where('product.price', ['item' => self::ADENA, 'count' => 100_000, 'name' => 'Adena'])
            ->has('buyer.characters', 2)
            ->where('buyer.characters.0.name', 'Rich')
            ->where('buyer.characters.0.balance', 250_000)
            ->where('buyer.characters.1.name', 'Fresh')
            ->where('buyer.characters.1.balance', 0)); // its Adena is in the warehouse, which can't pay

        $this->actingAs($this->account(), 'player')->get('/store/kage-pass')->assertInertia(fn ($page) => $page
            ->where('buyer.characters.0.balance', 90_000)
            ->where('buyer.characters.1.balance', 1_000));

        $this->actingAs($this->account(), 'player')->get('/store/weekly-gift-box')->assertInertia(fn ($page) => $page
            ->where('product.minLevel', 20)
            ->where('buyer.characters.0.balance', null));
    }

    public function test_class_packages_put_the_characters_of_that_class_first(): void
    {
        $this->actingAs($this->account(), 'player')->get('/store/package-healer-starter')->assertInertia(fn ($page) => $page
            ->where('buyer.characters.0.name', 'Fresh')
            ->where('buyer.characters.1.name', 'Rich'));

        $this->actingAs($this->account(), 'player')->get('/store')->assertInertia(fn ($page) => $page->where('suggested', 'samurai'));
    }

    public function test_a_kit_is_paid_in_its_own_currency_and_multiplied_by_quantity(): void
    {
        $this->actingAs($this->account(), 'player')
            ->post('/store/supplies-hunter', ['character_id' => 1, 'quantity' => 2])
            ->assertRedirect('/account/orders')
            ->assertSessionHas('status', 'order-placed');

        $action = GameAction::sole();
        $this->assertSame(GameAction::ACTION_BUY_PASS, $action->action);
        $this->assertSame(self::ADENA, $action->item_id);
        $this->assertSame(200_000, $action->count);
        $this->assertSame('1539:100:0;5592:40:0;3031:200:0', $action->items);

        $order = app(Orders::class)->history($this->account())[0];
        $this->assertSame('supplies', $order['type']);
        $this->assertSame("Hunter's Kit × 2", $order['name']['en']);
        $this->assertSame(['count' => 200_000, 'currency' => 'Adena'], $order['price']);
    }

    public function test_any_item_can_be_a_price(): void
    {
        $this->product('supplies-hunter')->update(['price_item' => self::LCOIN, 'price_count' => 1_000]);

        $this->actingAs($this->account(), 'player')->post('/store/supplies-hunter', ['character_id' => 2])->assertRedirect('/account/orders');

        $this->assertSame(self::LCOIN, GameAction::sole()->item_id);
    }

    public function test_a_character_that_cannot_pay_gets_nothing_queued(): void
    {
        $account = $this->account();

        // Fresh's Adena is all in the warehouse; Rich has enough for two kits, not three.
        $this->actingAs($account, 'player')->post('/store/supplies-hunter', ['character_id' => 2])->assertSessionHasErrors('character_id');
        $this->actingAs($account, 'player')->post('/store/supplies-hunter', ['character_id' => 1, 'quantity' => 3])->assertSessionHasErrors('character_id');
        // Someone else's character, and quantities out of range.
        $this->actingAs($account, 'player')->post('/store/supplies-traveler', ['character_id' => 3])->assertSessionHasErrors('character_id');
        $this->actingAs($account, 'player')->post('/store/supplies-traveler', ['character_id' => 1, 'quantity' => 0])->assertSessionHasErrors('quantity');
        $this->actingAs($account, 'player')->post('/store/supplies-traveler', ['character_id' => 1, 'quantity' => 11])->assertSessionHasErrors('quantity');

        $this->assertSame(0, GameAction::count());
        $this->assertSame(0, Order::count());
    }

    public function test_quantity_only_applies_to_what_can_be_bought_repeatedly(): void
    {
        $this->actingAs($this->account(), 'player')->post('/store/kage-pass', ['character_id' => 1, 'quantity' => 2])->assertSessionHasErrors('quantity');

        $this->assertSame(0, GameAction::count());
    }

    public function test_the_hunt_pass_cannot_be_bought_on_the_website_and_shows_the_servers_numbers(): void
    {
        $this->actingAs($this->account(), 'player')->post('/store/hunt-pass', ['character_id' => 1])->assertSessionHasErrors('character_id');
        $this->assertSame(0, GameAction::count());

        $dir = sys_get_temp_dir().'/passes-test-'.uniqid();
        mkdir($dir);
        file_put_contents("{$dir}/HuntPass.ini", "EnabledHuntPass = True\nPremiumItemId = 91663\nPremiumItemCount = 1000\nPointsForStep = 300\nDayOfMonth = 16\n");
        config(['l2j.config_dir' => $dir, 'l2j.config_defaults_dir' => null]);

        $this->get('/store/hunt-pass')->assertOk()->assertInertia(fn ($page) => $page
            ->where('product.open', true)
            ->where('product.price', ['item' => self::LCOIN, 'count' => 1_000, 'name' => 'L-Coin'])
            ->where('product.hunt.steps', 280)
            ->has('product.hunt.firstSteps', 10)
            ->where('product.description.en', fn (string $text): bool => str_contains($text, '300 points complete a step'))
            ->where('product.steps.2.en', fn (string $text): bool => str_contains($text, 'on day 16')));

        array_map('unlink', glob("{$dir}/*"));
        rmdir($dir);
    }

    public function test_guests_cannot_buy_and_come_back_to_the_product_after_signing_in(): void
    {
        $this->post('/store/supplies-hunter', ['character_id' => 1])->assertRedirect('/account');
        $this->assertSame(0, GameAction::count());

        DB::connection('login')->table('accounts')->where('login', 'hero')->update(['password' => base64_encode(sha1('secret', true))]);

        $this->get('/account?return=/store/supplies-hunter')->assertOk();
        $this->post('/account/login', ['login' => 'hero', 'password' => 'secret'])->assertRedirect(url('/store/supplies-hunter'));
    }

    public function test_signing_in_only_returns_to_the_store(): void
    {
        DB::connection('login')->table('accounts')->where('login', 'hero')->update(['password' => base64_encode(sha1('secret', true))]);

        $this->get('/account?return=https://evil.example/store/x')->assertOk();
        $this->post('/account/login', ['login' => 'hero', 'password' => 'secret'])->assertRedirect('/account');
    }

    public function test_what_the_account_already_holds_is_marked_and_the_orders_tab_is_only_history(): void
    {
        $account = $this->account();
        $this->actingAs($account, 'player')->post('/store/weekly-gift-box', ['character_id' => 1])->assertSessionHas('status', 'gift-opened');
        $this->actingAs($account, 'player')->post('/store/kage-pass', ['character_id' => 1]);
        $this->actingAs($account, 'player')->post('/store/package-samurai-starter', ['character_id' => 1]);

        $this->actingAs($account, 'player')->get('/store')->assertInertia(fn ($page) => $page
            ->where('owned', ['weekly-gift-box', 'kage-pass', 'package-samurai-starter']));
        $this->actingAs($account, 'player')->get('/store/weekly-gift-box')->assertInertia(fn ($page) => $page
            ->where('buyer.owned.characterName', 'Rich')
            ->whereNot('buyer.owned.nextAt', null));
        $this->actingAs($account, 'player')->get('/store/kage-pass')->assertInertia(fn ($page) => $page
            ->where('buyer.owned.status', GameAction::STATUS_PENDING));
        $this->actingAs($account, 'player')->get('/store/supplies-hunter')->assertInertia(fn ($page) => $page->where('buyer.owned', null));

        $this->actingAs($account, 'player')->get('/account/orders')->assertInertia(fn ($page) => $page->has('orders', 3));
    }

    public function test_only_a_samurai_can_receive_the_samurai_gift_and_only_once(): void
    {
        $this->get('/store/gifts-for-a-true-samurai')->assertOk()->assertInertia(fn ($page) => $page
            ->where('product.classOnly', true)
            ->where('product.group', 'samurai')
            ->where('product.price', null)
            ->where('product.limit.en', 'Once')
            ->has('product.contents.0.items', 3)
            ->where('product.image', fn (string $url): bool => str_ends_with($url, '/storage/store/samurai-gifts.jpg')));

        $account = $this->account();
        // Fresh is a Healer.
        $this->actingAs($account, 'player')->post('/store/gifts-for-a-true-samurai', ['character_id' => 2])->assertSessionHasErrors('character_id');
        $this->assertSame(0, GameAction::count());

        $this->actingAs($account, 'player')->post('/store/gifts-for-a-true-samurai', ['character_id' => 1])->assertRedirect('/account/orders');
        $this->assertSame('105435:1:0;105498:1:0;105499:1:0', GameAction::sole()->items);
        $this->assertSame(GameAction::ACTION_SEND_MAIL, GameAction::sole()->action);

        $this->actingAs($account, 'player')->post('/store/gifts-for-a-true-samurai', ['character_id' => 1])->assertSessionHasErrors('character_id');
        $this->assertSame(1, GameAction::count());

        // Stranger is a Healer too, on an account with no Samurai.
        $this->actingAs($this->account('other'), 'player')->post('/store/gifts-for-a-true-samurai', ['character_id' => 3])->assertSessionHasErrors('character_id');
    }

    public function test_the_grid_has_what_a_card_shows(): void
    {
        $this->get('/store')->assertInertia(fn ($page) => $page
            ->where('products.1.categoryName.en', 'Gifts')
            ->where('products.1.classOnly', true)
            ->where('products.1.limit.en', 'Once')
            ->where('products.1.summary.en', fn (string $text): bool => str_starts_with($text, 'Every Samurai') && ! str_contains($text, "\n"))
            ->missing('products.1.description'));
    }

    public function test_one_per_season_until_the_season_changes(): void
    {
        $account = $this->account();
        $this->actingAs($account, 'player')->post('/store/kage-pass', ['character_id' => 1])->assertRedirect('/account/orders');
        $this->actingAs($account, 'player')->post('/store/kage-pass', ['character_id' => 1])->assertSessionHasErrors('character_id');
        $this->assertSame(1, GameAction::count()); // the refused purchase left nothing behind

        $this->product('kage-pass')->update(['season' => 'season-2']);

        $this->actingAs($account, 'player')->get('/store/kage-pass')->assertInertia(fn ($page) => $page->where('buyer.owned', null));
        $this->actingAs($account, 'player')->post('/store/kage-pass', ['character_id' => 1])->assertRedirect('/account/orders');
    }

    public function test_a_failed_charge_can_be_bought_again_and_stays_in_the_history(): void
    {
        $account = $this->account();
        $this->actingAs($account, 'player')->post('/store/kage-pass', ['character_id' => 1]);
        GameAction::sole()->forceFill(['status' => GameAction::STATUS_FAILED])->save();

        $this->actingAs($account, 'player')->get('/store/kage-pass')->assertInertia(fn ($page) => $page->where('buyer.owned', null));
        $this->actingAs($account, 'player')->post('/store/kage-pass', ['character_id' => 1])->assertRedirect('/account/orders');

        $this->assertSame(['pending', 'failed'], array_column(app(Orders::class)->history($account), 'status'));
    }

    public function test_one_gift_box_per_account_per_week(): void
    {
        $store = app(Store::class);
        $monday = CarbonImmutable::parse('2026-10-26 00:00');
        $gift = $this->product('weekly-gift-box');
        $store->buy($this->account(), $gift, 1, 1, $monday->addDays(2));

        try {
            $store->buy($this->account(), $gift, 1, 1, $monday->addDays(6)->setTime(23, 59));
            $this->fail('A second box in the same week was allowed.');
        } catch (ValidationException) {
            $this->assertSame(1, GameAction::count());
        }

        $store->buy($this->account(), $gift, 1, 1, $monday->addWeek());
        $this->assertSame(2, Order::count());
    }

    public function test_only_own_characters_of_the_minimum_level_can_receive_a_gift_box(): void
    {
        foreach ([2 => 'level too low', 3 => 'another account', 99 => 'unknown'] as $characterId => $case) {
            $this->actingAs($this->account(), 'player')->post('/store/weekly-gift-box', ['character_id' => $characterId])->assertSessionHasErrors('character_id');
            $this->assertSame(0, Order::count(), $case);
        }
    }

    public function test_a_gift_box_queues_one_free_mail_with_its_items_and_a_grade_bonus(): void
    {
        $this->product('weekly-gift-box')->update(['bonus' => [['item' => 'enchant_weapon', 'count' => 1, 'weight' => 1]]]);

        $this->actingAs($this->account(), 'player')->post('/store/weekly-gift-box', ['character_id' => 1])->assertRedirect('/account/orders');

        $action = GameAction::sole();
        $this->assertSame(GameAction::ACTION_SEND_MAIL, $action->action);
        $this->assertStringStartsWith('57:250000:0;', $action->items);
        $this->assertStringEndsWith(';959:1:0', $action->items); // S-grade at 78
        $this->assertSame('Your weekly gift box', $action->mail_subject);
        $this->assertTrue(Order::sole()->items[5]['bonus']);
        $this->assertNull(Order::sole()->price_count);
    }

    public function test_an_active_rotation_replaces_the_gift_box_and_shows_on_its_page(): void
    {
        GiftBoxTemplate::create([
            'name' => 'Samurai Week',
            'description' => 'For the new blades.',
            'is_active' => true,
            'guaranteed' => [['item' => '57', 'count' => '1000'], ['item' => 'enchant_armor', 'count' => '2']],
            'bonus' => [['item' => '3031', 'count' => '50', 'weight' => '1']],
        ]);
        GiftBoxTemplate::create(['name' => 'Over', 'is_active' => true, 'ends_at' => now()->subDay(), 'guaranteed' => [['item' => '57', 'count' => '1']], 'bonus' => []]);

        $this->get('/store/weekly-gift-box')->assertInertia(fn ($page) => $page
            ->where('product.description.en', fn (string $text): bool => str_contains($text, 'Samurai Week — For the new blades.'))
            ->where('product.contents.0.items.1.name', 'Scroll: Enchant Armor (your grade)')
            ->where('product.contents.1.items.0', ['id' => 3031, 'name' => 'Spirit Ore', 'count' => 50, 'enchant' => 0, 'chance' => 100])
            ->has('product.contents.1.note'));

        // Item ids saved as text are items, grade keys are the character's grade scroll (S at level 78).
        $this->actingAs($this->account(), 'player')->post('/store/weekly-gift-box', ['character_id' => 1]);
        $this->assertSame('57:1000:0;960:2:0;3031:50:0', GameAction::sole()->items);
    }

    public function test_grade_scrolls_follow_the_characters_level(): void
    {
        $contents = app(StoreContents::class);
        $box = $contents->normalize([], [['item' => 'enchant_weapon', 'count' => 1, 'weight' => 1]]);

        $this->assertSame(951, $contents->roll($box, 45)[0]['id']); // C-grade
        $this->assertSame(959, $contents->roll($box, 80)[0]['id']); // S-grade
    }

    public function test_bonuses_are_rolled_for_each_one_bought_and_added_together(): void
    {
        $contents = app(StoreContents::class);
        $chest = $contents->normalize([['item' => 57, 'count' => 10]], [['item' => 3031, 'count' => 5, 'weight' => 1]]);

        $this->assertSame([[57, 30, false], [3031, 15, true]], array_map(fn (array $i): array => [$i['id'], $i['count'], $i['bonus']], $contents->roll($chest, 40, 3)));
    }

    public function test_a_package_has_the_weapon_and_armor_at_the_tiers_enchant_and_arrives_in_several_mails(): void
    {
        $this->get('/store/package-knight-elite')->assertOk()->assertInertia(fn ($page) => $page
            ->component('store/show')
            ->where('product.price.count', 60_000)
            ->has('product.contents.0.items', 12)
            ->where('product.contents.0.items.0.enchant', 8)
            ->where('product.contents.0.items.11.enchant', 0)
            ->has('product.steps', 4));

        $this->get('/store/supplies-hunter')->assertInertia(fn ($page) => $page->has('product.steps', 3));
    }

    public function test_buying_emails_a_confirmation_and_shows_the_order(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.com']);

        $this->actingAs($this->account(), 'player')
            ->post('/store/package-samurai-starter', ['character_id' => 1])
            ->assertRedirect('/account/orders')
            ->assertSessionHas('status', 'order-placed');

        Mail::assertSent(AccountMail::class, fn (AccountMail $mail): bool => $mail->hasTo('hero@example.com')
            && str_contains(implode(' ', $mail->lines), 'Samurai Package (Starter) for 5,000 L-Coin'));

        $this->actingAs($this->account(), 'player')->get('/account/orders')->assertOk()->assertInertia(fn ($page) => $page
            ->where('tab', 'orders')
            ->has('orders', 1)
            ->where('orders.0.status', GameAction::STATUS_PENDING)
            ->where('orders.0.typeName.en', 'Class Packages')
            ->where('orders.0.price', ['count' => 5_000, 'currency' => 'L-Coin'])
            ->has('orders.0.items', 11));
    }

    public function test_the_home_page_shows_the_stores_offers(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->where('store.gift.minLevel', 20)
            ->where('store.pass.name.en', 'Kage Pass: Season 1')
            ->where('store.pass.price.count', 1_000)
            ->where('store.packages.groups', 13)
            ->where('store.packages.min', 5_000)
            ->where('store.packages.max', 60_000));

        $this->get('/store')->assertInertia(fn ($page) => $page->where('lcoin.welcome', 1_000)->where('lcoin.giftBox', 1_000));

        $this->product('kage-pass')->delete();
        $this->get('/')->assertInertia(fn ($page) => $page->where('store.pass', null));
    }

    public function test_every_product_page_renders_and_the_sitemap_lists_them(): void
    {
        foreach (['weekly-gift-box', 'kage-pass', 'hunt-pass', 'supplies-traveler', 'package-gunner-elite'] as $slug) {
            $this->get("/store/{$slug}")->assertOk()->assertInertia(fn ($page) => $page->where('product.slug', $slug)->has('seo.title'));
        }

        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/store/weekly-gift-box'))->assertSee(url('/store/package-gunner-elite'));
    }

    public function test_the_old_addresses_moved(): void
    {
        $this->get('/account/store')->assertRedirect('/account/orders');
        $this->get('/passes')->assertRedirect('/store/kage-pass');
        $this->get('/packages')->assertRedirect('/store?category=packages#store');
        $this->get('/treasury/kage-pass')->assertRedirect('/store/kage-pass');
        // Browsers may remember that /store redirected here, so this one shows the page instead of sending them back.
        $this->get('/store')->assertOk();
        $this->get('/treasury')->assertOk()->assertInertia(fn ($page) => $page->component('store/index')->where('seo.canonical', url('/store')));
    }
}
