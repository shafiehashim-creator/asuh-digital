<?php

namespace App\Filament\Resources\PilotRequests;

use App\Enums\AgeGroup;
use App\Enums\InstitutionType;
use App\Enums\PilotStatus;
use App\Filament\Resources\PilotRequests\Pages\ListPilotRequests;
use App\Filament\Resources\PilotRequests\Pages\ViewPilotRequest;
use App\Models\PilotRequest;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * What the institution submitted stays as sent; the admin only moves the
 * request through its status (see ViewPilotRequest).
 */
class PilotRequestResource extends Resource
{
    protected static ?string $model = PilotRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $recordTitleAttribute = 'institution_name';

    protected static string|UnitEnum|null $navigationGroup = 'Keluarga dan institusi';

    protected static ?string $navigationLabel = 'Permohonan pilot';

    protected static ?int $navigationSort = 22;

    protected static ?string $modelLabel = 'permohonan pilot';

    protected static ?string $pluralModelLabel = 'Permohonan pilot';

    protected static ?string $slug = 'permohonan-pilot';

    /** Sales data: Super Admin only, not editors (CLAUDE.md). */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = PilotRequest::query()->where('status', PilotStatus::New)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Permohonan baharu yang belum dihubungi';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Institusi')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('institution_name')->label('Nama institusi'),
                        TextEntry::make('institution_type')->label('Jenis'),
                        TextEntry::make('state')->label('Negeri'),
                        TextEntry::make('district')->label('Daerah'),
                        TextEntry::make('student_count')->label('Anggaran murid'),
                        TextEntry::make('age_group')->label('Umur murid'),
                    ]),
                Section::make('Pegawai')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('contact_name')->label('Nama'),
                        TextEntry::make('contact_position')->label('Jawatan'),
                        TextEntry::make('email')->label('E-mel')->copyable()
                            ->url(fn (PilotRequest $record) => 'mailto:'.$record->email),
                        TextEntry::make('phone')->label('Telefon')->copyable()
                            ->url(fn (PilotRequest $record) => 'tel:'.$record->phone),
                        TextEntry::make('notes')->label('Catatan')->placeholder('-')->columnSpanFull(),
                    ]),
                Section::make('Status')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('created_at')->label('Diterima')->dateTime('j M Y, g:i A'),
                        TextEntry::make('updated_at')->label('Kemas kini terakhir')->dateTime('j M Y, g:i A'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('institution_name')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('institution_name')->label('Institusi')->searchable()
                    ->description(fn (PilotRequest $record) => $record->institution_type->getLabel()),
                TextColumn::make('state')->label('Lokasi')
                    ->formatStateUsing(fn (PilotRequest $record) => $record->district.', '.$record->state)
                    ->searchable(['state', 'district']),
                TextColumn::make('contact_name')->label('Pegawai')->searchable()
                    ->description(fn (PilotRequest $record) => $record->phone),
                TextColumn::make('student_count')->label('Murid')->numeric()->alignEnd()->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('created_at')->label('Diterima')->dateTime('j M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(PilotStatus::class),
                SelectFilter::make('institution_type')->label('Jenis')->options(InstitutionType::class),
                SelectFilter::make('age_group')->label('Umur murid')->options(AgeGroup::class),
                SelectFilter::make('state')->label('Negeri')
                    ->options(array_combine(config('asuh.states'), config('asuh.states'))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPilotRequests::route('/'),
            'view' => ViewPilotRequest::route('/{record}'),
        ];
    }
}
