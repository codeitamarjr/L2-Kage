<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\GameAction;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Everything bought in the Store or sent by an admin. Read-only: an order is the record of what happened, and the delivery
 * itself is in Item Deliveries. What an admin sent can be cancelled until the player chooses a character for it.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'Store';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('gameAction'))
            ->defaultSort('id', 'desc')
            ->poll('10s')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('created_at')->label('When')->since()->sortable(),
                TextColumn::make('account')->searchable(),
                TextColumn::make('character_name')->label('Character')->placeholder('Not chosen yet')->searchable(),
                TextColumn::make('product')
                    ->label('Product')
                    ->state(fn (Order $record): string => $record->name['en'].($record->quantity > 1 ? " × {$record->quantity}" : ''))
                    ->description(fn (Order $record): ?string => $record->note)
                    ->wrap(),
                TextColumn::make('price')
                    ->state(fn (Order $record): string => $record->price_count ? number_format($record->price_count)." {$record->price_name}" : 'Free'),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === Order::SOURCE_ADMIN ? 'Sent by admin' : 'Purchase')
                    ->color(fn (string $state): string => $state === Order::SOURCE_ADMIN ? 'info' : 'gray'),
                TextColumn::make('status')
                    ->state(fn (Order $record): string => $record->status())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        GameAction::STATUS_DONE => 'success',
                        GameAction::STATUS_FAILED => 'danger',
                        GameAction::STATUS_PROCESSING, Order::STATUS_WAITING => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('sent_by')->label('By')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('source')->options([Order::SOURCE_PURCHASE => 'Purchase', Order::SOURCE_ADMIN => 'Sent by admin']),
                TernaryFilter::make('waiting')
                    ->label('Waiting for a character')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('character_id'),
                        false: fn (Builder $query) => $query->whereNotNull('character_id'),
                    ),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Cancel')
                    ->modalHeading('Cancel this gift')
                    ->modalDescription('The account won\'t receive it.')
                    ->visible(fn (Order $record): bool => self::canDelete($record)),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return $record->isWaiting();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
        ];
    }
}
