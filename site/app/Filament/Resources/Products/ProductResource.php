<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Rules\AboveResalePrice;
use App\Rules\DeliverableContents;
use App\Rules\StoreItem;
use App\Services\L2j\GameData;
use App\Services\Store;
use App\Services\StoreContents;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * The Store's products: anything sold or given away on /store, and what "Send to accounts" sends. The weekly gift box and
 * the Hunt Pass are products with their own rules, so their forms are shorter and they can't be deleted.
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Store';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name_en';

    /** The emblems the Store can draw over a product's art (EMBLEMS in resources/js/components/store.tsx). */
    private const EMBLEMS = [
        'knight' => 'Shield', 'samurai' => 'Sword', 'duelist' => 'Dual swords', 'titan' => 'Two-handed sword', 'lancer' => 'Polearm',
        'artisan' => 'Blunt weapon', 'rogue' => 'Dagger', 'archer' => 'Bow', 'brawler' => 'Fists', 'kamael' => 'Ancient sword', 'gunner' => 'Pistol',
        'wizard' => 'Sigil', 'healer' => 'Potion', 'hunter' => 'Hunter potion', 'traveler' => 'Scroll',
    ];

    public static function form(Schema $schema): Schema
    {
        $isHunt = fn (?Product $record): bool => $record?->type === Product::TYPE_HUNT_PASS;

        return $schema->components([
            Section::make('Product')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('store_category_id')
                        ->label('Category')
                        ->relationship('category', 'name_en', fn (Builder $query) => $query->orderBy('sort'))
                        ->preload()
                        ->required()
                        ->native(false),
                    TextInput::make('slug')
                        ->label('Web address')
                        ->prefix('/store/')
                        ->required()
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->maxLength(120)
                        ->helperText('Filled in from the English name.'),
                    Toggle::make('is_active')
                        ->label('Published')
                        ->helperText('Off: hidden from players.')
                        ->default(true),
                    Toggle::make('is_featured')
                        ->label('On the Store\'s front page')
                        ->default(false),
                    DateTimePicker::make('starts_at')
                        ->label('Available from')
                        ->helperText('Times are in UTC. Empty: from now.')
                        ->native(false),
                    DateTimePicker::make('ends_at')
                        ->label('Available until')
                        ->helperText('Afterwards it shows as closed. Empty: no end.')
                        ->after('starts_at')
                        ->native(false),
                ]),

            Section::make('Images')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    FileUpload::make('images')
                        ->multiple()
                        ->reorderable()
                        ->appendFiles()
                        ->image()
                        ->disk('public')
                        ->directory('store')
                        ->visibility('public')
                        ->maxSize(2048)
                        ->maxFiles(8)
                        ->helperText('Up to 8, 2 MB each; a square image works best. The first is the cover, and drag to reorder. With none, the category\'s image or the picture below is used.')
                        ->columnSpanFull(),
                    Select::make('art')
                        ->label('Picture without an image')
                        ->options(Product::ARTS)
                        ->default('package')
                        ->required()
                        ->native(false),
                    Select::make('emblem')
                        ->label('Emblem over the picture')
                        ->options(self::EMBLEMS)
                        ->placeholder('None')
                        ->hidden($isHunt)
                        ->native(false),
                ]),

            Section::make('English')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name_en')
                        ->label('Name')
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                            if (blank($get('slug'))) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    Textarea::make('description_en')
                        ->label('Description')
                        ->helperText('A blank line starts a new paragraph.')
                        ->required()
                        ->rows(4)
                        ->hidden($isHunt),
                ]),

            Section::make('Portuguese')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name_pt')
                        ->label('Nome')
                        ->required()
                        ->maxLength(120),
                    Textarea::make('description_pt')
                        ->label('Descrição')
                        ->required()
                        ->rows(4)
                        ->hidden($isHunt),
                ]),

            Section::make('Price')
                ->columnSpanFull()
                ->description('Paid from the character\'s bag. Empty: free.')
                ->columns(2)
                ->hidden($isHunt)
                ->schema([
                    self::itemSelect('price_item', 'Paid in', gradeKeys: false)
                        ->required(false)
                        ->requiredWith('price_count')
                        ->live()
                        ->helperText('L-Coin is 91663, Adena is 57.'),
                    TextInput::make('price_count')
                        ->label('Price')
                        ->integer()
                        ->minValue(1)
                        ->requiredWith('price_item')
                        ->rule(fn (Get $get) => new AboveResalePrice(((int) $get('price_item')) ?: null, array_values((array) $get('items')))),
                ]),

            Section::make('What\'s inside')
                ->columnSpanFull()
                ->description('Before selling an item, check on its /items page what it really does. A scroll can also be "your grade": it becomes the scroll for the receiving character\'s level.')
                ->hidden($isHunt)
                ->schema([
                    Repeater::make('items')
                        ->label('Items')
                        ->schema(self::itemFields())
                        ->columns(4)
                        ->collapsible()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => self::itemLabel($state['item'] ?? null))
                        ->rule(fn (Get $get) => new DeliverableContents(array_values((array) $get('bonus')), (int) $get('max_per_order')))
                        ->columnSpanFull(),
                    Repeater::make('bonus')
                        ->label('Random bonus')
                        ->helperText('Optional. One of these is rolled by weight for each one bought, like the gift box or a chest.')
                        ->schema([
                            ...self::itemFields(),
                            TextInput::make('weight')->required()->integer()->minValue(1)->default(10),
                        ])
                        ->columns(5)
                        ->defaultItems(0)
                        ->collapsible()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => self::itemLabel($state['item'] ?? null))
                        ->columnSpanFull(),
                ]),

            Section::make('Limits')
                ->columnSpanFull()
                ->columns(2)
                ->hidden($isHunt)
                ->schema([
                    Select::make('limit_period')
                        ->label('Limit per account')
                        ->options(Product::LIMITS)
                        ->placeholder('No limit')
                        ->live()
                        ->native(false),
                    TextInput::make('season')
                        ->helperText('A new season name lets every account buy it again.')
                        ->maxLength(32)
                        ->required(fn (Get $get): bool => $get('limit_period') === Product::LIMIT_SEASON)
                        ->visible(fn (Get $get): bool => $get('limit_period') === Product::LIMIT_SEASON),
                    TextInput::make('max_per_order')
                        ->label('Most in one order')
                        ->helperText('More than 1 multiplies the counts, so everything inside must stack.')
                        ->required()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(100)
                        ->default(1),
                    TextInput::make('min_level')
                        ->label('Minimum character level')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(150),
                ]),

            Section::make('Class')
                ->columnSpanFull()
                ->description('Optional. For gear meant for one class: its characters are listed first and it can be filtered by class.')
                ->columns(2)
                ->collapsed()
                ->hidden($isHunt)
                ->schema([
                    Select::make('class_group')
                        ->label('Class group')
                        ->options(fn (): array => collect(config('l2j.store.class_groups'))->map(fn (array $group): string => $group['name']['en'])->all())
                        ->placeholder('None')
                        ->native(false),
                    Select::make('tier')
                        ->label('Tint of the picture')
                        ->options(['starter' => 'Blue (Starter)', 'advanced' => 'Gold (Advanced)', 'elite' => 'Red (Elite)'])
                        ->placeholder('None')
                        ->native(false),
                ]),

            Section::make('In-game mail')
                ->columnSpanFull()
                ->description('Empty uses the server\'s standard gift mail.')
                ->collapsed()
                ->hidden($isHunt)
                ->schema([
                    TextInput::make('mail_subject')->label('Subject')->maxLength(128),
                    Textarea::make('mail_text')->label('Text')->rows(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                ImageColumn::make('images')->label('')->disk('public')->visibility('public')->limit(1),
                TextColumn::make('name_en')
                    ->label('Name')
                    ->description(fn (Product $record): string => "/store/{$record->slug}")
                    ->searchable(['name_en', 'name_pt', 'slug'])
                    ->limit(50),
                TextColumn::make('category.name_en')->label('Category')->badge(),
                TextColumn::make('price')
                    ->state(fn (Product $record): string => $record->type === Product::TYPE_HUNT_PASS ? 'In game' : (($price = app(Store::class)->price($record)) ? number_format($price['count'])." {$price['name']}" : 'Free')),
                TextColumn::make('limit_period')
                    ->label('Limit')
                    ->formatStateUsing(fn (string $state): string => Product::LIMITS[$state] ?? $state)
                    ->placeholder('No limit')
                    ->toggleable(),
                TextColumn::make('orders_count')->label('Orders')->counts('orders')->sortable(),
                IconColumn::make('is_active')->label('Published')->boolean(),
                IconColumn::make('is_featured')->label('Front page')->boolean()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('store_category_id')->label('Category')->relationship('category', 'name_en'),
                TernaryFilter::make('is_active')->label('Published'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                SendToAccounts::make(),
                EditAction::make(),
                ReplicateAction::make()
                    ->label('Copy')
                    ->excludeAttributes(['orders_count'])
                    ->modalDescription('Makes an unpublished copy to edit, without the images.')
                    ->beforeReplicaSaved(function (Product $replica): void {
                        $replica->slug = Str::limit($replica->slug, 100, '').'-copy-'.Str::lower(Str::random(4));
                        $replica->name_en = Str::limit($replica->name_en, 110, '').' (copy)';
                        $replica->name_pt = Str::limit($replica->name_pt, 110, '').' (cópia)';
                        $replica->is_active = false;
                        // Removing an image from one product deletes the file, so a copy can't share them.
                        $replica->images = null;
                    })
                    ->successRedirectUrl(fn (Product $replica): string => self::getUrl('edit', ['record' => $replica]))
                    ->visible(fn (Product $record): bool => $record->type === Product::TYPE_MAIL && ! $record->trashed()),
                DeleteAction::make()->visible(fn (Product $record): bool => $record->type === Product::TYPE_MAIL && ! $record->trashed()),
                RestoreAction::make(),
            ]);
    }

    /**
     * Deleted products stay in the database, for their orders: the Deleted filter shows them and can restore them.
     *
     * @return Builder<Product>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    /**
     * @return list<Select|TextInput>
     */
    private static function itemFields(): array
    {
        return [
            self::itemSelect('item', 'Item', gradeKeys: true)->columnSpan(2),
            TextInput::make('count')->required()->integer()->minValue(1)->maxValue(2_000_000_000)->default(1),
            TextInput::make('enchant')->integer()->minValue(0)->maxValue(65_535)->default(0)->helperText('Weapons and armor only.'),
        ];
    }

    private static function itemSelect(string $name, string $label, bool $gradeKeys): Select
    {
        return Select::make($name)
            ->label($label)
            ->required()
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => self::searchItems($search, $gradeKeys))
            ->getOptionLabelUsing(fn ($value): ?string => self::itemLabel($value))
            ->rule(new StoreItem);
    }

    /**
     * What an item field offers for a search, by item id or grade key. These are added, not spread: spreading would
     * renumber the item ids.
     *
     * @return array<int|string, string>
     */
    public static function searchItems(string $search, bool $gradeKeys): array
    {
        $grades = $gradeKeys ? array_filter(self::gradeOptions(), fn (string $option): bool => Str::contains($option, $search, ignoreCase: true)) : [];

        return $grades + app(GameData::class)->searchItems($search);
    }

    /**
     * @return array<string, string>
     */
    private static function gradeOptions(): array
    {
        return collect(StoreContents::gradeKeys())
            ->mapWithKeys(fn (string $key): array => [$key => app(StoreContents::class)->describe(['items' => [['item' => $key, 'count' => 1, 'enchant' => 0]], 'bonus' => []])['items'][0]['name']])
            ->all();
    }

    private static function itemLabel(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $item = StoreContents::itemKey((string) $value);

        return is_int($item) ? app(GameData::class)->itemLabel($item) : (self::gradeOptions()[$item] ?? $item);
    }
}
