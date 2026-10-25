<?php

namespace App\Filament\Resources\Institutions;

use App\Enums\InstitutionType;
use App\Filament\Resources\Institutions\Pages\CreateInstitution;
use App\Filament\Resources\Institutions\Pages\EditInstitution;
use App\Filament\Resources\Institutions\Pages\ListInstitutions;
use App\Filament\Resources\Institutions\RelationManagers\InvitationsRelationManager;
use App\Filament\Resources\Institutions\RelationManagers\LicensesRelationManager;
use App\Filament\Resources\Institutions\RelationManagers\UsersRelationManager;
use App\Models\Institution;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Institutions and their licences (Phase D). Licences are set up by hand once
 * the institution pays outside the site, or for a free pilot.
 */
class InstitutionResource extends Resource
{
    protected static ?string $model = Institution::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'institusi';

    protected static ?string $pluralModelLabel = 'Institusi dan lesen';

    protected static ?string $slug = 'institusi';

    protected static ?int $navigationSort = 30;

    /** Licences are sales: Super Admin only. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Institusi')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Nama institusi')->required()->maxLength(255),
                        Select::make('type')->label('Jenis')->options(InstitutionType::class)->required()->native(false),
                        Select::make('state')->label('Negeri')->options(array_combine(config('asuh.states'), config('asuh.states')))->required()->native(false),
                        TextInput::make('district')->label('Daerah')->maxLength(100),
                        Select::make('pilot_request_id')->label('Permohonan pilot')
                            ->relationship('pilotRequest', 'institution_name')
                            ->searchable()->preload()->placeholder('Tiada'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('users'))
            ->columns([
                TextColumn::make('name')->label('Institusi')->searchable()
                    ->description(fn (Institution $record) => $record->type->getLabel().' · '.$record->state),
                TextColumn::make('license')->label('Lesen')->badge()
                    ->state(function (Institution $record) {
                        $license = $record->activeLicense();

                        return $license ? $license->kind->getLabel().' hingga '.$license->ends_on->translatedFormat('j M Y') : 'Tiada lesen aktif';
                    })
                    ->color(fn (string $state) => str_starts_with($state, 'Tiada') ? 'gray' : 'success'),
                TextColumn::make('users_count')->label('Guru')->alignEnd(),
                TextColumn::make('created_at')->label('Dicipta')->date('j M Y')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            LicensesRelationManager::class,
            UsersRelationManager::class,
            InvitationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstitutions::route('/'),
            'create' => CreateInstitution::route('/cipta'),
            'edit' => EditInstitution::route('/{record}/ubah'),
        ];
    }
}
