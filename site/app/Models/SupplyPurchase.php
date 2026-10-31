<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A supply kit bought by a game account (see App\Services\SupplyKits).
 *
 * @property int $id
 * @property string $account
 * @property string $kit
 * @property int $quantity
 * @property int $character_id
 * @property string $character_name
 * @property int|null $game_action_id
 * @property Carbon $created_at
 */
class SupplyPurchase extends Model
{
    /** @var list<string> */
    protected $fillable = ['account', 'kit', 'quantity', 'character_id', 'character_name', 'game_action_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'character_id' => 'integer',
        ];
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
}
