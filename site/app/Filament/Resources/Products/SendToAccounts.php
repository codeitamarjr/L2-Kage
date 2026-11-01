<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\PremiumAccounts\PremiumAccountResource;
use App\Models\Game\Account;
use App\Models\Game\Character;
use App\Models\Product;
use App\Services\Store;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sends a product to game accounts for free. Mail needs a character, so it waits in each account's Orders tab until the
 * player chooses who receives it; until then it can be cancelled in Store > Orders.
 */
class SendToAccounts
{
    public static function make(): Action
    {
        return Action::make('sendToAccounts')
            ->label('Send')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->visible(fn (Product $record): bool => $record->type !== Product::TYPE_HUNT_PASS && ! $record->trashed())
            ->modalHeading(fn (Product $record): string => "Send {$record->name_en} to accounts")
            ->modalDescription('Free, and whatever limit it has doesn\'t apply. The player chooses the receiving character in their Orders tab.')
            ->modalSubmitActionLabel('Send')
            ->schema([
                Radio::make('target')
                    ->label('To')
                    ->options(fn (): array => [
                        'accounts' => 'These accounts',
                        'all' => 'Every account ('.number_format(self::everyAccount()->count()).')',
                    ])
                    ->default('accounts')
                    ->required()
                    ->live(),
                Select::make('accounts')
                    ->label('Accounts')
                    ->helperText('Search by account or character name.')
                    ->multiple()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => self::search($search))
                    ->getOptionLabelsUsing(fn (array $values): array => array_combine($values, $values))
                    ->required(fn (Get $get): bool => $get('target') === 'accounts')
                    ->visible(fn (Get $get): bool => $get('target') === 'accounts'),
                Textarea::make('note')
                    ->label('Reason')
                    ->helperText('Kept in the log and shown to the player.')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (Product $record, array $data): void {
                $logins = $data['target'] === 'all' ? self::everyAccount()->pluck('login')->all() : array_values($data['accounts']);
                $sent = app(Store::class)->send($record, $logins, PremiumAccountResource::requestedBy(), $data['note']);

                Notification::make()
                    ->title("{$record->name_en} sent to {$sent} account(s)")
                    ->body('It waits in their Orders tab. See it in Store > Orders.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Banned accounts (access level below 0) get nothing.
     *
     * @return Builder<Account>
     */
    private static function everyAccount()
    {
        return Account::query()->where('accessLevel', '>=', 0);
    }

    /**
     * @return array<string, string> login => label
     */
    private static function search(string $search): array
    {
        $like = '%'.addcslashes($search, '%_').'%';
        $byCharacter = Character::query()->where('char_name', 'like', $like)->limit(50)->pluck('account_name')->all();

        return self::everyAccount()
            ->where(fn ($query) => $query->where('login', 'like', $like)->orWhereIn('login', $byCharacter))
            ->orderBy('login')
            ->limit(50)
            ->pluck('login', 'login')
            ->all();
    }
}
