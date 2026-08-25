<?php

namespace App\Filament\Resources\Admins;

use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\Admins\Pages\EditAdmin;
use App\Filament\Resources\Admins\Pages\ListAdmins;
use App\Models\Admin;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;

/**
 * Hanya owner yang boleh mengelola akun admin.
 *
 * Digate seluruhnya, bukan cuma create: admin yang masih boleh MENYUNTING admin lain bisa
 * mengganti password akun owner lalu login sebagai owner — batas "tidak boleh menambah admin"
 * jadi tidak berarti apa-apa. Admin non-owner mengganti nama/passwordnya sendiri lewat halaman
 * profil panel (->profile() di AdminPanelProvider), bukan lewat resource ini.
 */
class AdminResource extends Resource
{
    protected static ?string $model = Admin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $modelLabel = 'Admin';

    protected static ?string $pluralModelLabel = 'Admin';

    protected static ?int $navigationSort = 90;

    /**
     * Menghapus diri sendiri dilarang. Karena admin lain tetap bisa dihapus, aturan ini
     * cukup untuk menjamin selalu tersisa minimal satu admin — tanpa perlu hitung baris.
     *
     * Harus di-override di sini, BUKAN di canDelete(): Filament merutekan otorisasi aksi
     * lewat Page::getDefaultActionAuthorizationResponse() yang memanggil method ini langsung,
     * sementara canDelete() cuma turunannya dan tidak pernah dilihat DeleteAction.
     */
    public static function getViewAnyAuthorizationResponse(): Response
    {
        return static::ownerOnly();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return static::ownerOnly();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return static::ownerOnly();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth('admin')->user()?->isOwner() ?? false;
    }

    private static function ownerOnly(): Response
    {
        return auth('admin')->user()?->isOwner()
            ? Response::allow()
            : Response::deny('Hanya owner yang boleh mengelola akun admin.');
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if ($record->getKey() === auth('admin')->id()) {
            return Response::deny('Tidak bisa menghapus akun sendiri.');
        }

        return static::ownerOnly();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->rule(Password::defaults())
                ->required(fn (string $operation) => $operation === 'create')
                ->helperText(fn (string $operation) => $operation === 'edit'
                    ? 'Kosongkan kalau tidak ingin mengganti password.'
                    : null)
                // Field kosong saat edit tidak boleh ikut tersimpan — kalau ikut, password
                // admin ter-reset jadi string kosong tiap kali datanya disunting.
                ->dehydrated(fn (?string $state) => filled($state)),

            Select::make('role')
                ->label('Role')
                ->options(Admin::roleOptions())
                ->default(Admin::ROLE_ADMIN)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable()->copyable(),
                TextColumn::make('role')->label('Role')->badge()
                    ->formatStateUsing(fn (string $state) => Admin::roleOptions()[$state] ?? $state),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
        // Sengaja tanpa DeleteBulkAction: bulk delete diotorisasi lewat
        // getDeleteAnyAuthorizationResponse() tanpa cek per-record, jadi satu aksi bisa
        // menghapus semua admin termasuk diri sendiri. Menghapus admin massal tidak punya
        // use case; menghilangkan tombolnya lebih kecil dan lebih aman daripada menggate-nya.
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmins::route('/'),
            'create' => CreateAdmin::route('/create'),
            'edit' => EditAdmin::route('/{record}/edit'),
        ];
    }
}
