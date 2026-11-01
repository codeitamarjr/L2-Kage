<?php

namespace App\Filament\Resources\GiftBoxTemplates;

use App\Filament\Resources\GiftBoxTemplates\Pages\CreateGiftBoxTemplate;
use App\Filament\Resources\GiftBoxTemplates\Pages\EditGiftBoxTemplate;
use App\Filament\Resources\GiftBoxTemplates\Pages\ListGiftBoxTemplates;
use App\Models\GiftBoxTemplate;
use App\Rules\StoreItem;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class GiftBoxTemplateResource extends Resource
{
    protected static ?string $model = GiftBoxTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'Store';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Gift Box Rotation';

    protected static ?string $modelLabel = 'gift box';

    protected static ?string $pluralModelLabel = 'gift boxes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Gift box settings')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Box name')
                        ->required()
                        ->maxLength(80),
                    TextInput::make('description')
                        ->label('Description')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->helperText('Only active templates are used by the weekly gift box system.')
                        ->default(false),
                    DateTimePicker::make('starts_at')
                        ->label('Available from')
                        ->native(false),
                    DateTimePicker::make('ends_at')
                        ->label('Available until')
                        ->native(false),
                ]),

            Section::make('Guaranteed rewards')
                ->schema([
                    Repeater::make('guaranteed')
                        ->label('Guaranteed items')
                        // One in-game mail carries 8 items: the guaranteed ones plus the bonus.
                        ->maxItems(7)
                        ->helperText('At most 7: one in-game mail carries 8 items, the guaranteed ones plus the bonus.')
                        ->schema([
                            TextInput::make('item')
                                ->label('Item ID or grade key')
                                ->required()
                                ->rule(new StoreItem)
                                ->helperText('Example: 57, 1538, or enchant_weapon'),
                            TextInput::make('count')
                                ->label('Count')
                                ->required()
                                ->integer()
                                ->minValue(1)
                                ->default(1),
                        ])
                        ->default([
                            ['item' => 57, 'count' => 250000],
                        ])
                        ->collapsible()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => $state['item'] ?? null)
                        ->columnSpanFull(),
                ]),

            Section::make('Bonus rewards')
                ->schema([
                    Repeater::make('bonus')
                        ->label('Bonus items')
                        ->schema([
                            TextInput::make('item')
                                ->label('Item ID or grade key')
                                ->required()
                                ->rule(new StoreItem)
                                ->helperText('Example: 57, 3031, or enchant_weapon'),
                            TextInput::make('count')
                                ->label('Count')
                                ->required()
                                ->integer()
                                ->minValue(1)
                                ->default(1),
                            TextInput::make('weight')
                                ->label('Weight')
                                ->required()
                                ->integer()
                                ->minValue(1)
                                ->default(10),
                        ])
                        ->default([
                            ['item' => 'enchant_weapon', 'count' => 1, 'weight' => 25],
                        ])
                        ->collapsible()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => $state['item'] ?? null)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Box name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(40)
                    ->wrap(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGiftBoxTemplates::route('/'),
            'create' => CreateGiftBoxTemplate::route('/create'),
            'edit' => EditGiftBoxTemplate::route('/{record}/edit'),
        ];
    }
}
