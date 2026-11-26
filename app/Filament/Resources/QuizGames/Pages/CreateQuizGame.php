<?php

namespace App\Filament\Resources\QuizGames\Pages;

use App\Filament\Resources\QuizGames\QuizGameResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuizGame extends CreateRecord
{
    protected static string $resource = QuizGameResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return EditQuizGame::getUrl(['record' => $this->record]);
    }
}
