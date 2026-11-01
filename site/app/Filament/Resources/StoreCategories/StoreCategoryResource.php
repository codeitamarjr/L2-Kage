<?php

namespace App\Filament\Resources\StoreCategories;

use App\Filament\Resources\StoreCategories\Pages\ManageStoreCategories;
use App\Models\Product;
use App\Models\StoreCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The buttons in the Store's banner. A category shows there once it has a published product.
 */
class StoreCategoryResource extends Resource
{
    protected static ?string $model = StoreCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Store';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $modelLabel = 'category';

    protected static ?string $pluralModelLabel = 'categories';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_en')->label('Name')->required()->maxLength(60),
            TextInput::make('name_pt')->label('Nome')->required()->maxLength(60),
            TextInput::make('slug')
                ->label('Web address')
                ->prefix('/store?category=')
                ->required()
                ->alphaDash()
                ->notIn(['main', 'lcoin'])
                ->unique(ignoreRecord: true)
                ->maxLength(48),
            FileUpload::make('image')
                ->image()
                ->disk('public')
                ->directory('store')
                ->visibility('public')
                ->maxSize(2048)
                ->helperText('Optional. For its products that have no image of their own.')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->visibility('public'),
                TextColumn::make('name_en')->label('Name')->searchable(['name_en', 'name_pt']),
                TextColumn::make('name_pt')->label('Nome'),
                TextColumn::make('slug')->label('Web address'),
                TextColumn::make('products_count')->label('Products')->counts('products'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (StoreCategory $record): bool => self::canDelete($record)),
            ]);
    }

    /** A category with products, deleted ones included, has to stay: they point to it. */
    public static function canDelete($record): bool
    {
        return ! Product::withTrashed()->where('store_category_id', $record->id)->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStoreCategories::route('/'),
        ];
    }
}
