<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Enums\BookStatus;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Book;
use App\Models\Plan;
use App\Models\User;
use App\Services\ManualGrants;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

/**
 * @property User $record
 */
class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('grantPlan')
                ->label('Tambah langganan')
                ->icon(Heroicon::OutlinedIdentification)
                ->modalDescription('Untuk hadiah, pampasan atau bayaran di luar laman (contohnya tunai di booth). Direkod sebagai pesanan manual. Tarikh mula ikut tetapan "Tarikh mula Langganan Tahunan", dan jika pelanggan masih ada langganan aktif, yang baharu bermula selepas itu.')
                ->schema([
                    Select::make('plan_id')->label('Pelan')->required()->native(false)
                        ->options(fn () => Plan::query()->where('is_active', true)->orderBy('price_sen')->pluck('name', 'id')),
                    ...$this->commonFields(),
                ])
                ->modalSubmitActionLabel('Tambah langganan')
                ->action(fn (array $data, ManualGrants $grants) => $this->grant(
                    fn (User $admin) => $grants->plan($this->record, Plan::query()->findOrFail($data['plan_id']), $this->sen($data['amount']), $data['note'], $admin, (bool) $data['send_receipt']),
                    'Langganan ditambah.',
                )),

            Action::make('grantBook')
                ->label('Tambah e-book')
                ->icon(Heroicon::OutlinedBookOpen)
                ->color('gray')
                ->modalDescription('Buku ini kekal dalam akaun pelanggan, sama seperti pembelian single. Direkod sebagai pesanan manual.')
                ->schema([
                    Select::make('book_id')->label('Buku')->required()->searchable()->native(false)
                        ->options(fn () => Book::query()->where('status', BookStatus::Published)
                            ->whereNotIn('id', $this->record->entitlements()->select('book_id'))
                            ->orderBy('title')->pluck('title', 'id')),
                    ...$this->commonFields(),
                ])
                ->modalSubmitActionLabel('Tambah e-book')
                ->action(fn (array $data, ManualGrants $grants) => $this->grant(
                    fn (User $admin) => $grants->book($this->record, Book::query()->findOrFail($data['book_id']), $this->sen($data['amount']), $data['note'], $admin, (bool) $data['send_receipt']),
                    'E-book ditambah ke akaun pelanggan.',
                )),
        ];
    }

    /** @return array<int, Component> */
    private function commonFields(): array
    {
        return [
            TextInput::make('amount')->label('Jumlah dibayar (RM)')->numeric()->minValue(0)->step(0.01)->prefix('RM')->default('0')->required()
                ->helperText('0 untuk percuma. Jumlah ini dikira dalam jualan.'),
            TextInput::make('note')->label('Sebab / catatan')->required()->maxLength(500)
                ->placeholder('Contoh: Tunai di booth PBAKL, hadiah peraduan, pampasan'),
            Toggle::make('send_receipt')->label('Hantar resit e-mel kepada pelanggan')->default(true),
        ];
    }

    private function sen(mixed $ringgit): int
    {
        return (int) round(((float) $ringgit) * 100);
    }

    private function grant(callable $step, string $success): void
    {
        try {
            $step(auth()->user());
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->record->refresh();
        Notification::make()->title($success)->success()->send();
    }
}
