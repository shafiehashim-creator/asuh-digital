<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\BankTransfers;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * @property Order $record
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewReceipt')
                ->label('Lihat resit')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('gray')
                ->url(fn () => route('admin.receipt', $this->record), shouldOpenInNewTab: true)
                ->visible(fn () => filled($this->record->bank_receipt_path)),

            Action::make('confirmPayment')
                ->label('Sahkan bayaran')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Sahkan pindahan bank?')
                ->modalDescription(fn () => 'Pastikan '.Money::format($this->record->amount_sen)
                    .' dengan rujukan '.$this->record->reference.' telah masuk ke akaun bank. '
                    .'Langganan Tahunan akan diaktifkan dan resit dihantar kepada pembeli.')
                ->modalSubmitActionLabel('Ya, sahkan')
                ->visible(fn () => $this->isUnpaidBankTransfer())
                ->action(function (BankTransfers $bankTransfers) {
                    $bankTransfers->confirm($this->record, auth()->user());
                    $this->record->refresh();

                    Notification::make()->title('Bayaran disahkan. Langganan Tahunan telah diaktifkan.')->success()->send();
                }),

            Action::make('rejectPayment')
                ->label('Tolak')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Tolak pindahan bank?')
                ->modalDescription('Slot akan dilepaskan untuk pembeli lain. Pembeli masih boleh menghantar resit baharu.')
                ->schema([
                    Textarea::make('reason')
                        ->label('Sebab (untuk rekod dalaman)')
                        ->required()
                        ->maxLength(500),
                ])
                ->modalSubmitActionLabel('Tolak bayaran')
                ->visible(fn () => $this->isUnpaidBankTransfer())
                ->action(function (array $data, BankTransfers $bankTransfers) {
                    $bankTransfers->reject($this->record, auth()->user(), $data['reason']);
                    $this->record->refresh();

                    Notification::make()->title('Pindahan bank ditolak dan slot dilepaskan.')->warning()->send();
                }),
        ];
    }

    private function isUnpaidBankTransfer(): bool
    {
        return $this->record->payment_method === PaymentMethod::BankTransfer
            && $this->record->status === OrderStatus::Pending;
    }
}
