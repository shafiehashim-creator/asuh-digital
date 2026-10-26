<?php

namespace App\Filament\Pages;

use App\Enums\AgeGroup;
use App\Models\MonthlyTopReader;
use App\Services\TopReaders as TopReaderRanking;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * ASUH's 5 best readers of each age group every month (user decision
 * 2026-10-26). Only Super Admins see the ranking and the parents' contact
 * details; announcing gives the winners a badge and e-mails their parents.
 */
class TopReaders extends Page
{
    protected string $view = 'filament.pages.top-readers';

    protected static ?string $title = 'Pembaca terbaik bulanan';

    protected static ?string $navigationLabel = 'Pembaca terbaik';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Keluarga dan institusi';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'pembaca-terbaik';

    public string $month = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        // Last month by default: the one that is over and due for announcing.
        $this->month = now()->subMonthNoOverflow()->format('Y-m');
    }

    /** @return array<string, string> this month and the last 12 */
    public function monthOptions(): array
    {
        return collect(range(0, 12))
            ->mapWithKeys(fn (int $i) => [now()->startOfMonth()->subMonths($i)->format('Y-m') => now()->startOfMonth()->subMonths($i)->translatedFormat('F Y')])
            ->all();
    }

    public function selectedMonth(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->month ?: now()->format('Y-m'))->startOfMonth();
    }

    /** @return array<string, Collection> ranking per age group label */
    public function rankings(): array
    {
        $ranking = app(TopReaderRanking::class);

        return collect(TopReaderRanking::GROUPS)
            ->mapWithKeys(fn (AgeGroup $group) => [$group->getLabel() => $ranking->ranking($this->selectedMonth(), $group)])
            ->all();
    }

    /** @return Collection<int, MonthlyTopReader> */
    public function winners(): Collection
    {
        return MonthlyTopReader::query()->with('child.user')
            ->whereDate('month', $this->selectedMonth())
            ->orderBy('age_group')->orderBy('rank')->get();
    }

    public function markRewardSent(int $id): void
    {
        $winner = MonthlyTopReader::query()->findOrFail($id);
        $winner->forceFill(['reward_sent_at' => $winner->reward_sent_at ? null : now()])->save();
    }

    protected function getHeaderActions(): array
    {
        $ranking = app(TopReaderRanking::class);

        return [
            Action::make('announce')->label('Umumkan pemenang')->icon(Heroicon::OutlinedTrophy)
                ->visible(fn () => $ranking->canAnnounce($this->selectedMonth()))
                ->requiresConfirmation()
                ->modalHeading(fn () => 'Umumkan 5 terbaik '.$this->selectedMonth()->translatedFormat('F Y').'?')
                ->modalDescription('5 teratas setiap kumpulan umur dicatat sebagai pemenang, mendapat lencana Pembaca Terbaik ASUH, dan ibu bapa mereka menerima e-mel. Ini hanya boleh dibuat sekali bagi setiap bulan.')
                ->action(function () use ($ranking) {
                    $count = $ranking->announce($this->selectedMonth());
                    Notification::make()->title($count.' pemenang diumumkan dan ibu bapa dimaklumkan melalui e-mel.')->success()->send();
                }),
        ];
    }
}
