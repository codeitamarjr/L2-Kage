<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something a game account bought in the Store or an admin sent it (see App\Services\Store). It keeps what the product
 * was at the time; the delivery itself is its in-game action.
 *
 * @property int $id
 * @property string $account
 * @property int|null $product_id
 * @property string $source
 * @property string $category
 * @property array{en: string, pt: string} $category_name
 * @property array{en: string, pt: string} $name
 * @property int $quantity
 * @property int|null $price_item
 * @property int|null $price_count
 * @property string|null $price_name
 * @property int|null $character_id
 * @property string|null $character_name
 * @property list<array{id: int|null, name: string, count: int, enchant: int, bonus: bool}>|null $items
 * @property array{items: list<array<string, mixed>>, bonus: list<array<string, mixed>>}|null $contents
 * @property int|null $game_action_id
 * @property string|null $sent_by
 * @property string|null $note
 * @property string|null $limit_key
 * @property Carbon $created_at
 * @property-read GameAction|null $gameAction
 * @property-read Product|null $product
 */
class Order extends Model
{
    public const SOURCE_PURCHASE = 'purchase';

    public const SOURCE_ADMIN = 'admin';

    /** Sent by an admin, until the player chooses the character that receives it. */
    public const STATUS_WAITING = 'waiting';

    /** @var list<string> */
    protected $fillable = [
        'account', 'product_id', 'source', 'category', 'category_name', 'name', 'quantity', 'price_item', 'price_count', 'price_name',
        'character_id', 'character_name', 'items', 'contents', 'game_action_id', 'sent_by', 'note', 'limit_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_name' => 'array',
            'name' => 'array',
            'quantity' => 'integer',
            'price_item' => 'integer',
            'price_count' => 'integer',
            'character_id' => 'integer',
            'items' => 'array',
            'contents' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * The in-game charge and mail.
     *
     * @return BelongsTo<GameAction, $this>
     */
    public function gameAction(): BelongsTo
    {
        return $this->belongsTo(GameAction::class);
    }

    public function isWaiting(): bool
    {
        return $this->character_id === null;
    }

    /**
     * "waiting" for a character, or how the in-game delivery went.
     */
    public function status(): string
    {
        return $this->isWaiting() ? self::STATUS_WAITING : ($this->gameAction->status ?? GameAction::STATUS_FAILED);
    }
}
