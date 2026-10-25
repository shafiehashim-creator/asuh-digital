<?php

namespace App\Filament\Resources\Institutions\Pages;

use App\Filament\Resources\Institutions\InstitutionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInstitution extends CreateRecord
{
    protected static string $resource = InstitutionResource::class;

    /** Next: add a licence and invite the institution's administrator. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
