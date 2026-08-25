<?php

namespace App\Filament\Resources\Households;

use App\Filament\Resources\Households\Pages\ListHouseholds;
use App\Models\Household;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only. Data di dalam household adalah milik customer — backoffice cuma perlu
 * memantau jumlah dan pertumbuhannya, bukan mengeditnya.
 */
class HouseholdResource extends Resource
{
    protected static ?string $model = Household::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $modelLabel = 'Keluarga';

    protected static ?string $pluralModelLabel = 'Keluarga';

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
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('users_count')->label('Anggota')->counts('users')->sortable(),
                // Kode undangan adalah kredensial: siapa pun yang memegangnya bisa mendaftar di
                // /register dan masuk sebagai anggota penuh keluarga itu. Backoffice cuma perlu
                // tahu kodenya ada, bukan nilainya — jangan bikin searchable/copyable.
                TextColumn::make('invite_code')->label('Kode undangan')
                    ->formatStateUsing(fn (?string $state) => filled($state) ? '••••••' : '—'),
                TextColumn::make('created_at')->label('Terdaftar')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHouseholds::route('/'),
        ];
    }
}
