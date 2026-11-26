<?php

namespace App\Filament\Resources\QuizGames\Pages;

use App\Filament\Resources\QuizGames\QuizGameResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuizGames extends ListRecords
{
    protected static string $resource = QuizGameResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Kuiz baharu')];
    }
}
