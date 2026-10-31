<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A character (l2jgs.characters), read-only through the `game` connection.
 *
 * @property int $charId
 * @property string $account_name
 * @property string $char_name
 * @property int $level
 * @property int $classid
 * @property int $base_class
 * @property int $race
 * @property int $sex
 * @property int $online
 * @property int $accesslevel
 * @property int|null $clanid
 */
class Character extends Model
{
    protected $connection = 'game';

    protected $table = 'characters';

    protected $primaryKey = 'charId';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_name', 'login');
    }

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'owner_id', 'charId');
    }

    /**
     * How much of an item (L-Coin, Adena...) each of an account's characters has in the bag, as last saved by the game
     * server. Characters without any are left out.
     *
     * @return array<int, int> character id => count
     */
    public static function bagCounts(string $account, int $itemId): array
    {
        return DB::connection('game')->table('items as i')
            ->join('characters as c', 'c.charId', '=', 'i.owner_id')
            ->where('c.account_name', $account)
            ->where('i.item_id', $itemId)
            ->where('i.loc', 'INVENTORY')
            ->groupBy('i.owner_id')
            ->selectRaw('i.owner_id, SUM(i.count) AS total')
            ->pluck('total', 'owner_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }
}
