<?php

namespace Tests\Feature;

use App\Models\GiftBoxTemplate;
use App\Models\User;
use App\Rules\GiftBoxItem;
use App\Services\GiftBox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class GiftBoxTemplateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Saved the way the admin form saves it: every field as text.
     */
    private function template(array $overrides = []): GiftBoxTemplate
    {
        return GiftBoxTemplate::create([
            'name' => 'Samurai Week',
            'description' => 'For the new blades.',
            'is_active' => true,
            'guaranteed' => [['item' => '57', 'count' => '1000'], ['item' => 'enchant_armor', 'count' => '2']],
            'bonus' => [['item' => '3031', 'count' => '50', 'weight' => '1']],
            ...$overrides,
        ]);
    }

    public function test_the_admin_pages_render(): void
    {
        $template = $this->template();
        $this->actingAs(User::factory()->create());

        $this->get('/admin/gift-box-templates')->assertOk()->assertSee('Samurai Week');
        $this->get('/admin/gift-box-templates/create')->assertOk();
        $this->get("/admin/gift-box-templates/{$template->id}/edit")->assertOk();
    }

    public function test_an_active_template_replaces_the_configured_box(): void
    {
        $this->template();
        $box = app(GiftBox::class);

        // Item ids saved as text are items, grade keys are the character's grade scroll (C at level 45).
        $items = $box->roll(45);
        $this->assertSame([[57, 1000, false], [952, 2, false], [3031, 50, true]], array_map(fn (array $i): array => [$i['id'], $i['count'], $i['bonus']], $items));

        $contents = $box->contents();
        $this->assertSame(['Adena', 'Scroll: Enchant Armor (your grade)'], array_column($contents['guaranteed'], 'name'));
        $this->assertSame([['id' => 3031, 'name' => 'Spirit Ore', 'count' => 50, 'chance' => 100.0]], $contents['bonus']);
    }

    public function test_inactive_or_expired_templates_leave_the_configured_box(): void
    {
        $this->template(['is_active' => false]);
        $this->template(['ends_at' => now()->subDay()]);

        $this->assertCount(count(config('l2j.gift_box.guaranteed')), app(GiftBox::class)->contents()['guaranteed']);
    }

    public function test_items_must_exist_or_be_a_grade_key(): void
    {
        $valid = fn (string $value): bool => Validator::make(['item' => $value], ['item' => [new GiftBoxItem]])->passes();

        $this->assertTrue($valid('57'));
        $this->assertTrue($valid('enchant_weapon'));
        $this->assertFalse($valid('999999999'));
        $this->assertFalse($valid('enchant_wepon'));
    }
}
