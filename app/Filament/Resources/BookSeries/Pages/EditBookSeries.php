<?php

namespace App\Filament\Resources\BookSeries\Pages;

use App\Filament\Resources\BookSeries\BookSeriesResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Route;

class EditBookSeries extends EditRecord
{
    protected static string $resource = BookSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')->label('Lihat halaman siri')->icon(Heroicon::OutlinedEye)->color('gray')
                ->url(fn () => route('series.show', $this->record), shouldOpenInNewTab: true)
                ->visible(fn () => Route::has('series.show')),
            DeleteAction::make()->modalDescription('Buku dalam siri ini tidak dipadam, cuma dikeluarkan daripada siri.'),
        ];
    }
}
