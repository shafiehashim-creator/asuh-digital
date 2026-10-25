<?php

namespace App\Filament\Resources\Children\Pages;

use App\Filament\Resources\Children\ChildResource;
use Filament\Resources\Pages\ListRecords;

class ListChildren extends ListRecords
{
    protected static string $resource = ChildResource::class;
}
