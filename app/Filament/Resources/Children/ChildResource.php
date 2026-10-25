<?php

namespace App\Filament\Resources\Children;

use App\Enums\AgeGroup;
use App\Filament\Resources\Children\Pages\ListChildren;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Child;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Read-only (CLAUDE.md): profiles belong to the parents, who add and delete them.
 */
class ChildResource extends Resource
{
    protected static ?string $model = Child::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFaceSmile;

    protected static ?string $modelLabel = 'profil anak';

    protected static ?string $pluralModelLabel = 'Profil anak';

    protected static ?string $slug = 'profil-anak';

    protected static ?int $navigationSort = 13;

    /** Children's data: Super Admin only. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'classroom.institution']))
            ->columns([
                TextColumn::make('nickname')->label('Nama panggilan')->searchable(),
                TextColumn::make('age_group')->label('Umur')->badge(),
                TextColumn::make('user.name')->label('Ibu bapa / kelas')->searchable()
                    // Pupils (Phase D) belong to a class instead of a parent.
                    ->state(fn (Child $record) => $record->user?->name ?? $record->classroom?->name)
                    ->description(fn (Child $record) => $record->user?->email ?? $record->classroom?->institution?->name)
                    ->url(fn (Child $record) => $record->user_id ? CustomerResource::getUrl('view', ['record' => $record->user_id]) : null),
                TextColumn::make('created_at')->label('Dicipta')->date('j M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('age_group')->label('Umur')->options([
                    AgeGroup::SixToNine->value => AgeGroup::SixToNine->getLabel(),
                    AgeGroup::TenToThirteen->value => AgeGroup::TenToThirteen->getLabel(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChildren::route('/'),
        ];
    }
}
