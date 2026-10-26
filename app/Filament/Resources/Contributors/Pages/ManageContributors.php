<?php

namespace App\Filament\Resources\Contributors\Pages;

use App\Filament\Resources\Contributors\ContributorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageContributors extends ManageRecords
{
    protected static string $resource = ContributorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah penulis atau ilustrator'),
        ];
    }
}
