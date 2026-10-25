<?php

namespace App\Filament\Resources\Institutions\RelationManagers;

use App\Enums\LicenseKind;
use App\Models\License;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LicensesRelationManager extends RelationManager
{
    protected static string $relationship = 'licenses';

    protected static ?string $title = 'Lesen';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('kind')->label('Jenis')->options(LicenseKind::class)->default(LicenseKind::Paid)->required()->native(false),
            TextInput::make('max_students')->label('Bilangan murid maksimum')->integer()->minValue(1)->maxValue(5000)->required(),
            DatePicker::make('starts_on')->label('Mula')->default(today())->required()->native(false)->displayFormat('j M Y'),
            DatePicker::make('ends_on')->label('Tamat')->default(today()->addYear()->subDay())->required()->native(false)
                ->displayFormat('j M Y')->afterOrEqual('starts_on'),
            Toggle::make('includes_videos')->label('Termasuk video animasi (Langganan Plus)')->columnSpanFull(),
            CheckboxList::make('access_days')->label('Hari murid boleh membaca')
                ->options(License::DAYS)->columns(4)->default([1, 2, 3, 4, 5])->columnSpanFull()
                ->helperText('Kosongkan semua untuk sepanjang masa. Di luar waktu ini, murid diajak meminta ibu bapa melanggan untuk membaca di rumah.'),
            TimePicker::make('access_starts')->label('Dari jam')->default('07:00')->seconds(false),
            TimePicker::make('access_ends')->label('Hingga jam')->default('18:00')->seconds(false)->after('access_starts'),
            TextInput::make('notes')->label('Catatan (contoh: no. invois)')->maxLength(500)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('ends_on', 'desc')
            ->columns([
                TextColumn::make('kind')->label('Jenis')->badge(),
                TextColumn::make('max_students')->label('Murid maks.')->alignEnd(),
                IconColumn::make('includes_videos')->label('Video')->boolean(),
                TextColumn::make('hours')->label('Waktu akses')
                    ->state(fn (License $record) => $record->schoolHoursLabel() ?? 'Sepanjang masa')->wrap(),
                TextColumn::make('starts_on')->label('Mula')->date('j M Y'),
                TextColumn::make('ends_on')->label('Tamat')->date('j M Y'),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (License $record) => match (true) {
                        $record->isActive() => 'Aktif',
                        $record->starts_on->isFuture() => 'Belum bermula',
                        default => 'Tamat',
                    })
                    ->color(fn (string $state) => $state === 'Aktif' ? 'success' : 'gray'),
                TextColumn::make('notes')->label('Catatan')->placeholder('-')->wrap(),
            ])
            ->headerActions([CreateAction::make()->label('Tambah lesen')])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('Belum ada lesen');
    }
}
