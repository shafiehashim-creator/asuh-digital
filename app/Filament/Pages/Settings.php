<?php

namespace App\Filament\Pages;

use App\Enums\PassStartRule;
use App\Models\Plan;
use App\Models\Setting;
use App\Services\Chip\ChipClient;
use App\Services\PaymentProcessor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * Everything the business said it may "change later": prices, slot limit,
 * landing copy, bank and company details. Values live in the settings table
 * (defaults in config/asuh.php) and plan prices in the plans table.
 *
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Tetapan';

    protected static ?string $title = 'Tetapan';

    protected static ?string $slug = 'tetapan';

    protected static ?int $navigationSort = 90;

    /** Setting keys edited on this page (prices are handled separately). */
    private const KEYS = [
        'preorder_slot_limit', 'preorder_reservation_minutes', 'bank_transfer_reservation_hours',
        'pass_start_rule', 'launch_date',
        'landing_headline', 'landing_subheadline', 'landing_faq', 'refund_policy', 'pilot_duration_days',
        'bank_name', 'bank_account_name', 'bank_account_number',
        'contact_email', 'company_name', 'company_ssm_no', 'company_address', 'company_sst_no',
        'admin_notification_emails',
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $values = collect(self::KEYS)->mapWithKeys(fn (string $key) => [$key => Setting::get($key)])->all();

        $this->form->fill($values + [
            'early_bird_price' => $this->priceInRinggit(Plan::PAS_TAHUNAN_EARLY),
            'regular_price' => $this->priceInRinggit(Plan::PAS_TAHUNAN),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->chipStatus(),

            Section::make('Harga dan slot pre-order')
                ->columns(2)
                ->schema([
                    TextInput::make('early_bird_price')->label('Harga early bird (RM)')
                        ->numeric()->minValue(1)->step(0.01)->prefix('RM')->required(),
                    TextInput::make('regular_price')->label('Harga biasa (RM)')
                        ->helperText('Dipaparkan dipotong di sebelah harga early bird.')
                        ->numeric()->minValue(1)->step(0.01)->prefix('RM')->required(),
                    TextInput::make('preorder_slot_limit')->label('Had slot early bird')
                        ->integer()->minValue(0)->maxValue(100000)->required(),
                    TextInput::make('preorder_reservation_minutes')->label('Tempoh tempahan CHIP (minit)')
                        ->integer()->minValue(5)->maxValue(240)->required(),
                    TextInput::make('bank_transfer_reservation_hours')->label('Tempoh tempahan pindahan bank (jam)')
                        ->integer()->minValue(1)->maxValue(168)->required(),
                ]),

            Section::make('Tarikh mula Pas Tahunan')
                ->columns(2)
                ->schema([
                    Select::make('pass_start_rule')->label('Pas bermula')
                        ->options(PassStartRule::class)->native(false)->required()->live(),
                    DatePicker::make('launch_date')->label('Tarikh launch platform')
                        ->native(false)->displayFormat('j M Y')
                        ->helperText('Pas yang sedang menunggu akan dijadualkan sebaik tarikh ini disimpan.')
                        ->visible(fn (Get $get) => $this->ruleIs($get('pass_start_rule'), PassStartRule::OnLaunch)),
                ]),

            Section::make('Teks landing page')
                ->schema([
                    TextInput::make('landing_headline')->label('Headline')->maxLength(120)->required(),
                    Textarea::make('landing_subheadline')->label('Subheadline')->rows(2)->maxLength(300)->required(),
                    TextInput::make('pilot_duration_days')->label('Tempoh pilot institusi (hari)')
                        ->integer()->minValue(1)->maxValue(365)->required(),
                    Textarea::make('refund_policy')->label('Polisi bayaran balik')->rows(3)->maxLength(1000)->required()
                        ->helperText('Dipaparkan dalam FAQ dan terma.'),
                    Repeater::make('landing_faq')->label('Soalan lazim')
                        ->schema([
                            TextInput::make('question')->label('Soalan')->maxLength(200)->required(),
                            Textarea::make('answer')->label('Jawapan')->rows(3)->maxLength(1500)->required(),
                        ])
                        ->itemLabel(fn (array $state) => $state['question'] ?? null)
                        ->addActionLabel('Tambah soalan')
                        ->reorderable()->collapsible()->collapsed()->defaultItems(0),
                ]),

            Section::make('Pindahan bank')
                ->description('Dipaparkan kepada pembeli yang memilih pindahan bank.')
                ->columns(3)
                ->schema([
                    TextInput::make('bank_name')->label('Nama bank')->maxLength(100),
                    TextInput::make('bank_account_name')->label('Nama akaun')->maxLength(150),
                    TextInput::make('bank_account_number')->label('No. akaun')->maxLength(40),
                ]),

            Section::make('Maklumat syarikat')
                ->description('Dicetak pada resit dan dipaparkan dalam notis privasi serta terma.')
                ->columns(2)
                ->schema([
                    TextInput::make('company_name')->label('Nama syarikat berdaftar')->maxLength(150),
                    TextInput::make('company_ssm_no')->label('No. SSM')->maxLength(50),
                    TextInput::make('company_sst_no')->label('No. SST')->helperText('Kosongkan jika tidak berdaftar.')->maxLength(50),
                    TextInput::make('contact_email')->label('E-mel hubungan pelanggan')->email()->maxLength(150),
                    Textarea::make('company_address')->label('Alamat')->rows(3)->maxLength(500)->columnSpanFull(),
                ]),

            Section::make('Notifikasi')
                ->schema([
                    TagsInput::make('admin_notification_emails')->label('E-mel admin untuk notifikasi')
                        ->helperText('Menerima e-mel resit pindahan bank baharu dan permohonan pilot. Tekan Enter selepas setiap e-mel.')
                        ->placeholder('nama@galeriilmu.com.my')
                        ->nestedRecursiveRules(['email']),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Simpan tetapan')->submit('save')->keyBindings(['mod+s']),
                    ])->sticky(),
                ]),
        ]);
    }

    public function save(PaymentProcessor $payments): void
    {
        $data = $this->form->getState();

        DB::transaction(function () use ($data) {
            foreach (self::KEYS as $key) {
                $value = $data[$key] ?? null;
                Setting::set($key, $key === 'landing_faq' ? array_values($value ?? []) : $value);
            }

            $this->setPrice(Plan::PAS_TAHUNAN_EARLY, $data['early_bird_price']);
            $this->setPrice(Plan::PAS_TAHUNAN, $data['regular_price']);
        });

        $scheduled = $payments->scheduleWaitingPasses();

        Notification::make()
            ->title('Tetapan disimpan.')
            ->body($scheduled ? "{$scheduled} Pas Tahunan yang menunggu telah dijadualkan." : null)
            ->success()
            ->send();
    }

    /**
     * Read-only CHIP status. The keys themselves stay in the server .env
     * (CLAUDE.md): a stolen admin login must not be able to redirect payments.
     */
    private function chipStatus(): Callout
    {
        $howTo = 'Kunci CHIP disimpan dalam fail .env di server, bukan di sini, supaya tidak boleh diubah walaupun akaun admin dicerobohi. Lihat docs/DEPLOY.md.';

        if (! app(ChipClient::class)->isConfigured()) {
            return Callout::make('CHIP: belum disambungkan')
                ->description('Bayaran FPX, kad dan e-wallet ditutup. Pindahan bank masih berfungsi. '.$howTo)
                ->warning();
        }

        return config('services.chip.test_mode')
            ? Callout::make('CHIP: disambungkan (mod ujian)')
                ->description('Bayaran menggunakan wang olok-olok. Tukar kepada kunci live sebelum launch. '.$howTo)
                ->info()
            : Callout::make('CHIP: disambungkan (live)')
                ->description('Bayaran sebenar diterima. '.$howTo)
                ->success();
    }

    private function priceInRinggit(string $code): ?string
    {
        $plan = Plan::findByCode($code);

        return $plan ? number_format($plan->price_sen / 100, 2, '.', '') : null;
    }

    private function setPrice(string $code, mixed $ringgit): void
    {
        // Existing orders keep the amount charged; only new orders use the new price.
        Plan::findByCode($code)?->update(['price_sen' => (int) round(((float) $ringgit) * 100)]);
    }

    private function ruleIs(mixed $state, PassStartRule $rule): bool
    {
        return ($state instanceof PassStartRule ? $state : PassStartRule::tryFrom((string) $state)) === $rule;
    }
}
