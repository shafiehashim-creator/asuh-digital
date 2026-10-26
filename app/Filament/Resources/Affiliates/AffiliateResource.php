<?php

namespace App\Filament\Resources\Affiliates;

use App\Enums\AffiliateStatus;
use App\Filament\Resources\Affiliates\Pages\ManageAffiliates;
use App\Models\Affiliate;
use App\Services\Affiliates;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Affiliates (user decision 2026-10-26): applications to approve, each
 * affiliate's commission rate, and paying what is due by bank transfer.
 */
class AffiliateResource extends Resource
{
    protected static ?string $model = Affiliate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static string|UnitEnum|null $navigationGroup = 'Jualan';

    protected static ?string $navigationLabel = 'Affiliate';

    protected static ?int $navigationSort = 15;

    protected static ?string $modelLabel = 'affiliate';

    protected static ?string $pluralModelLabel = 'Affiliate';

    protected static ?string $slug = 'affiliate';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = Affiliate::query()->where('status', AffiliateStatus::Pending)->count();

        return $pending ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        $summary = fn (Affiliate $record) => app(Affiliates::class)->summary($record);

        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('user.name')->label('Nama')->searchable()
                    ->description(fn (Affiliate $record) => $record->user?->email),
                TextColumn::make('code')->label('Kod')->searchable()->copyable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('commission_percent')->label('Komisen')
                    ->state(fn (Affiliate $record) => rtrim(rtrim(number_format($record->effectivePercent(), 2), '0'), '.').'%'.($record->commission_percent === null ? ' (lalai)' : '')),
                TextColumn::make('sales')->label('Jualan')->state(fn (Affiliate $record) => $summary($record)['orders']),
                TextColumn::make('due')->label('Boleh dibayar')->state(fn (Affiliate $record) => Money::format($summary($record)['due']))->weight('bold'),
                TextColumn::make('paid')->label('Telah dibayar')->state(fn (Affiliate $record) => Money::format($summary($record)['paid'])),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(AffiliateStatus::class),
            ])
            ->recordActions([
                Action::make('payDue')
                    ->label('Bayar komisen')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (Affiliate $record) => $summary($record)['due'] > 0)
                    ->modalDescription(fn (Affiliate $record) => 'Pindahkan '.Money::format($summary($record)['due']).' ke '
                        .$record->bank_name.' '.$record->bank_account_number.' ('.$record->bank_account_name.'), kemudian tanda sebagai dibayar.')
                    ->schema([
                        TextInput::make('reference')->label('Rujukan pindahan (pilihan)')->maxLength(100),
                    ])
                    ->modalSubmitActionLabel('Tanda sebagai dibayar')
                    ->action(function (Affiliate $record, array $data) {
                        $paid = app(Affiliates::class)->payDue($record, auth()->user(), $data['reference'] ?? null);
                        Notification::make()->title(Money::format($paid).' ditanda sebagai dibayar.')->success()->send();
                    }),
                Action::make('approve')
                    ->label('Luluskan')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('primary')
                    ->visible(fn (Affiliate $record) => in_array($record->status, [AffiliateStatus::Pending, AffiliateStatus::Suspended], true))
                    ->requiresConfirmation()
                    ->modalDescription('Pautan dan kod affiliate ini akan aktif. Kali pertama, mereka menerima e-mel dengan pautan dan kod.')
                    ->action(fn (Affiliate $record) => app(Affiliates::class)->approve($record)),
                ActionGroup::make([
                    Action::make('details')
                        ->label('Butiran')
                        ->icon(Heroicon::OutlinedEye)
                        ->modalSubmitAction(false)
                        ->schema([
                            TextEntry::make('promotion_channels')->label('Cara mempromosi')->state(fn (Affiliate $record) => $record->promotion_channels),
                            TextEntry::make('phone')->label('Telefon')->state(fn (Affiliate $record) => $record->user?->phone),
                            TextEntry::make('bank')->label('Akaun bank')
                                ->state(fn (Affiliate $record) => "{$record->bank_name} {$record->bank_account_number} ({$record->bank_account_name})"),
                            TextEntry::make('link')->label('Pautan')->state(fn (Affiliate $record) => $record->link())->copyable(),
                        ]),
                    Action::make('settings')
                        ->label('Komisen dan catatan')
                        ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                        ->fillForm(fn (Affiliate $record) => ['commission_percent' => $record->commission_percent, 'admin_note' => $record->admin_note])
                        ->schema([
                            TextInput::make('commission_percent')->label('Komisen (%)')->numeric()->minValue(0)->maxValue(50)->step(0.5)
                                ->placeholder('Ikut lalai di Tetapan')
                                ->helperText('Kosongkan untuk guna kadar lalai. Hanya untuk jualan akan datang.'),
                            Textarea::make('admin_note')->label('Catatan admin')->rows(3)->maxLength(1000),
                        ])
                        ->action(function (Affiliate $record, array $data) {
                            $record->forceFill([
                                'commission_percent' => filled($data['commission_percent']) ? $data['commission_percent'] : null,
                                'admin_note' => $data['admin_note'],
                            ])->save();
                            Notification::make()->title('Disimpan.')->success()->send();
                        }),
                    Action::make('reject')
                        ->label('Tolak')
                        ->icon(Heroicon::OutlinedXMark)
                        ->color('danger')
                        ->visible(fn (Affiliate $record) => $record->status === AffiliateStatus::Pending)
                        ->requiresConfirmation()
                        ->action(fn (Affiliate $record) => $record->forceFill(['status' => AffiliateStatus::Rejected])->save()),
                    Action::make('suspend')
                        ->label('Gantung')
                        ->icon(Heroicon::OutlinedPause)
                        ->color('danger')
                        ->visible(fn (Affiliate $record) => $record->status === AffiliateStatus::Approved)
                        ->requiresConfirmation()
                        ->modalDescription('Pautan dan kod tidak lagi memberi komisen untuk jualan baharu. Komisen sedia ada kekal.')
                        ->action(fn (Affiliate $record) => $record->forceFill(['status' => AffiliateStatus::Suspended])->save()),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAffiliates::route('/'),
        ];
    }
}
