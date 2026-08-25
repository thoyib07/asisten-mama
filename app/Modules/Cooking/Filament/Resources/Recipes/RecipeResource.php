<?php

namespace App\Modules\Cooking\Filament\Resources\Recipes;

use App\Modules\Cooking\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Modules\Cooking\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Modules\Cooking\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Modules\Cooking\Models\Ingredient;
use App\Modules\Cooking\Models\Recipe;
use App\Modules\Cooking\Support\RecipeTaxonomy;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                ->helperText('Belum ada input durasi per langkah di sini — cuma resep hasil AI yang punya durasi per langkah. Peringatan: menyimpan lewat form ini akan MENGHAPUS durasi per langkah yang sudah ada (kalau resep ini dari AI), karena Textarea ini cuma menyimpan teksnya saja.')
                ->required()
                ->rows(8)
                ->formatStateUsing(fn ($state) => is_array($state)
                    ? implode("\n", array_map(fn ($s) => is_array($s) ? $s['text'] : $s, $state))
                    : $state),
            FileUpload::make('image_url')->image()->directory('recipes')->nullable(),
            TextInput::make('servings')->numeric()->nullable(),
            TextInput::make('duration_minutes')->label('Lama masak (menit)')->numeric()->nullable(),
            Fieldset::make('Perkiraan nilai gizi (total resep)')
                ->schema([
                    TextInput::make('nutrition.calories')->label('Kalori (kkal)')->numeric()->nullable(),
                    TextInput::make('nutrition.protein')->label('Protein (g)')->numeric()->nullable(),
                    TextInput::make('nutrition.carbs')->label('Karbohidrat (g)')->numeric()->nullable(),
                    TextInput::make('nutrition.fat')->label('Lemak (g)')->numeric()->nullable(),
                ]),
            Select::make('source')->options([
                'seed' => 'Seed',
                'ai' => 'AI',
            ])->default('seed')->required(),
            Select::make('meal_categories')
                ->label('Kategori makan')
                ->multiple()
                ->options(RecipeTaxonomy::MEAL_CATEGORIES),
            Select::make('cuisine_type')
                ->label('Jenis masakan')
                ->options(RecipeTaxonomy::CUISINE_TYPES),
            Select::make('ingredients')
                ->relationship('ingredients', 'name')
                ->multiple()
                ->live()
                ->preload()
                ->searchable()
                ->createOptionForm([
                    TextInput::make('name')->required(),
                ]),
            Select::make('primary_ingredient_ids')
                ->label('Bahan utama')
                ->helperText('Pilih dari bahan di atas — dihitung bobot lebih tinggi saat pencarian.')
                ->multiple()
                ->dehydrated(false)
                ->options(fn (Get $get) => Ingredient::whereIn('id', $get('ingredients') ?? [])->pluck('name', 'id'))
                ->afterStateHydrated(function (Select $component, ?Recipe $record) {
                    $component->state(
                        $record ? $record->ingredients()->wherePivot('is_primary', true)->pluck('ingredients.id')->all() : []
                    );
                }),
            Section::make('Gramasi bahan')
                ->schema(fn (Get $get) => collect($get('ingredients') ?? [])
                    ->map(fn ($id) => TextInput::make("ingredient_quantities.{$id}")
                        ->label(Ingredient::find($id)?->name ?? '')
                        ->placeholder('mis. 200 gram')
                        ->dehydrated(false)
                        ->default(fn (?Recipe $record) => $record?->ingredients->firstWhere('id', $id)?->pivot->quantity))
                    ->all()),
        ]);
    }

    /**
     * The nutrition Fieldset dehydrates as {calories,protein,carbs,fat} even
     * when the admin leaves all four blank — collapse that to null so blank
     * fieldsets don't render an empty "Perkiraan Nilai Gizi" section.
     */
    public static function normalizeNutrition(array $data): array
    {
        if (isset($data['nutrition']) && collect($data['nutrition'])->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
            $data['nutrition'] = null;
        }

        return $data;
    }

    public static function syncPrimaryIngredients(Recipe $recipe, array $primaryIngredientIds): void
    {
        $primaryIngredientIds = array_map('intval', $primaryIngredientIds);
        foreach ($recipe->ingredients as $ingredient) {
            $recipe->ingredients()->updateExistingPivot($ingredient->id, [
                'is_primary' => in_array($ingredient->id, $primaryIngredientIds, true),
            ]);
        }
    }

    public static function syncIngredientQuantities(Recipe $recipe, array $quantities): void
    {
        foreach ($quantities as $ingredientId => $quantity) {
            if ($recipe->ingredients->contains((int) $ingredientId)) {
                $recipe->ingredients()->updateExistingPivot((int) $ingredientId, [
                    'quantity' => $quantity !== '' ? $quantity : null,
                ]);
            }
        }
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
            ->filters([
                SelectFilter::make('source')
                    ->label('Sumber')
                    ->options([
                        Recipe::SOURCE_SEED => 'Seed',
                        Recipe::SOURCE_AI => 'AI',
                    ]),
            ])
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
