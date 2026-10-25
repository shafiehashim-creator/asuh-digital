<?php

namespace App\Filament\Resources\Books\Pages;

use App\Filament\Resources\Books\BookResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBook extends CreateRecord
{
    protected static string $resource = BookResource::class;

    /** A new PDF goes straight on to "versi pantas" so phones open it quickly. */
    protected function getRedirectUrl(): string
    {
        return $this->record->files()->exists()
            ? PreparePages::getUrl(['record' => $this->record, 'auto' => 1])
            : parent::getRedirectUrl();
    }
}
