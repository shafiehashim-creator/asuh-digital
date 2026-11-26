<?php

namespace App\Filament\Resources\QuizGames\Pages;

use App\Filament\Resources\QuizGames\QuizGameResource;
use App\Models\QuizGame;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/** @property QuizGame $record */
class EditQuizGame extends EditRecord
{
    protected static string $resource = QuizGameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Staff play it like a child would (nothing counted for any child).
            Action::make('preview')->label('Cuba kuiz')->icon(Heroicon::OutlinedPlay)->color('gray')
                ->url(fn () => route('child-mode.quiz-games.show', $this->record), shouldOpenInNewTab: true),
            QuizGameResource::publishAction()->record($this->record),
            DeleteAction::make(),
        ];
    }
}
