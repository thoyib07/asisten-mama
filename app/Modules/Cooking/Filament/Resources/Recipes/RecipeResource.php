<?php

namespace App\Modules\Cooking\Filament\Resources\Recipes;

use App\Modules\Cooking\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Modules\Cooking\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Modules\Cooking\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Modules\Cooking\Models\Recipe;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecipeResource extends Resource
{
    protected static ?string $model = Recipe::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            Textarea::make('steps')
                ->label('Langkah (satu langkah per baris)')
                ->required()
                ->rows(8)
                ->formatStateUsing(fn ($state) => is_array($state) ? implode("\n", $state) : $state),
            FileUpload::make('image_url')->image()->directory('recipes')->nullable(),
            TextInput::make('servings')->numeric()->nullable(),
            Select::make('source')->options([
                'seed' => 'Seed',
                'ai' => 'AI',
            ])->default('seed')->required(),
            Select::make('ingredients')
                ->relationship('ingredients', 'name')
                ->multiple()
                ->preload()
                ->searchable()
                ->createOptionForm([
                    TextInput::make('name')->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('source')->badge(),
                TextColumn::make('servings')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecipes::route('/'),
            'create' => CreateRecipe::route('/create'),
            'edit' => EditRecipe::route('/{record}/edit'),
        ];
    }
}
