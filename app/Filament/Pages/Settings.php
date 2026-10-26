<?php

namespace App\Filament\Pages;

use App\Enums\PassStartRule;
use App\Enums\VideoProvider;
use App\Models\Plan;
use App\Models\Setting;
use App\Services\CatalogReadiness;
use App\Services\Chip\ChipClient;
use App\Services\PaymentProcessor;
use App\Support\BrandVideo;
use App\Support\VideoId;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
use Illuminate\Support\HtmlString;
use UnitEnum;

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

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

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
        'catalog_open', 'preview_pages', 'whatsapp_number', 'videos_open',
        'chip_fee_fpx_fixed', 'chip_fee_fpx_percent', 'chip_fee_ewallet_fixed', 'chip_fee_ewallet_percent',
        'affiliates_open', 'affiliate_commission_percent', 'affiliate_hold_days', 'affiliate_cookie_days',
        'royalty_pool_percent', 'royalty_plus_ebook_share', 'royalty_min_minutes', 'royalty_single_percent',
        'brand_video_url', 'brand_video_title', 'brand_video_places',
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
            'plus_price' => $this->priceInRinggit(Plan::PAS_PLUS),
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

            Section::make('Katalog dan pembaca e-book (Fasa B)')
                ->schema([
                    $this->catalogReadiness(),
                    Toggle::make('catalog_open')->label('Buka katalog kepada orang ramai')
                        ->helperText('Semasa ditutup, /buku dan pembaca memberi 404 kepada pelawat; staf masih boleh pratonton. Buka hanya apabila buku sudah diterbitkan dan diuji.'),
                    TextInput::make('preview_pages')->label('Halaman pratonton percuma')
                        ->helperText('Bilangan halaman awal yang boleh dibaca sesiapa sahaja tanpa langganan. Hanya untuk buku yang ada versi pantas. 0 untuk menutup pratonton.')
                        ->integer()->minValue(0)->maxValue(20)->required(),
                ]),

            Section::make('Video animasi dan Langganan Plus (Fasa 2)')
                ->columns(2)
                ->schema([
                    Toggle::make('videos_open')->label('Buka video animasi dan Langganan Plus')
                        ->helperText('Semasa ditutup, video dan jualan Langganan Plus tersembunyi daripada pelanggan; staf masih boleh pratonton.')
                        ->columnSpanFull(),
                    TextInput::make('plus_price')->label('Harga Langganan Plus setahun (RM)')
                        ->helperText('0 bermaksud belum dijual. Pelanggan Langganan Tahunan membayar beza harga untuk baki hari mereka.')
                        ->numeric()->minValue(0)->step(0.01)->prefix('RM')->required(),
                ]),

            Section::make('Video lagu ASUH')
                ->columns(2)
                ->schema([
                    TextInput::make('brand_video_url')->label('Pautan YouTube')
                        ->helperText('Tampal pautan video (awam atau unlisted). Kosongkan untuk menyembunyikan video.')
                        ->url()->maxLength(255)
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                            if (filled($value) && ! VideoId::isValid(VideoProvider::YouTube, VideoId::normalise(VideoProvider::YouTube, $value))) {
                                $fail('Pautan YouTube tidak dikenali.');
                            }
                        }),
                    TextInput::make('brand_video_title')->label('Tajuk')->maxLength(100),
                    CheckboxList::make('brand_video_places')->label('Paparkan di')->options(BrandVideo::PLACES)->columns(2)->columnSpanFull(),
                ]),

            Section::make('WhatsApp')
                ->schema([
                    TextInput::make('whatsapp_number')->label('Nombor WhatsApp pertanyaan')
                        ->helperText('Nombor penuh dengan kod negara, tanpa + atau sengkang, contohnya 60123456789. Butang WhatsApp terapung dipaparkan di laman awam apabila diisi.')
                        ->regex('/^\d{9,15}$/')->maxLength(15),
                ]),

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

            Section::make('Tarikh mula Langganan Tahunan')
                ->columns(2)
                ->schema([
                    Select::make('pass_start_rule')->label('Langganan bermula')
                        ->options(PassStartRule::class)->native(false)->required()->live(),
                    DatePicker::make('launch_date')->label('Tarikh launch platform')
                        ->native(false)->displayFormat('j M Y')
                        ->helperText('Langganan yang sedang menunggu akan dijadualkan sebaik tarikh ini disimpan.')
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

            Section::make('Caj pemprosesan CHIP (dibayar pembeli)')
                ->description('Pembeli memilih FPX, e-wallet atau kad di halaman bayaran dan melihat caj sebelum membayar. Caj = amaun tetap + peratus harga. Isi ikut kadar dalam kontrak CHIP; 0 untuk tiada caj. Bayaran kad sentiasa tanpa caj, kerana peniaga di Malaysia tidak dibenarkan mengenakan caj tambahan kepada pemegang kad. Pindahan bank tanpa caj. Laporan jualan tidak mengira caj ini.')
                ->columns(2)
                ->schema([
                    TextInput::make('chip_fee_fpx_fixed')->label('FPX: amaun tetap (RM)')
                        ->numeric()->minValue(0)->maxValue(10)->step(0.01)->prefix('RM')->required(),
                    TextInput::make('chip_fee_fpx_percent')->label('FPX: peratus')
                        ->numeric()->minValue(0)->maxValue(5)->step(0.01)->suffix('%')->required(),
                    TextInput::make('chip_fee_ewallet_fixed')->label('E-wallet: amaun tetap (RM)')
                        ->numeric()->minValue(0)->maxValue(10)->step(0.01)->prefix('RM')->required(),
                    TextInput::make('chip_fee_ewallet_percent')->label('E-wallet: peratus')
                        ->numeric()->minValue(0)->maxValue(5)->step(0.01)->suffix('%')->required(),
                ]),

            Section::make('Affiliate')
                ->description('Pemegang akaun memohon di Akaun saya → Affiliate dan admin meluluskan di Jualan → Affiliate. Komisen dikira daripada harga selepas diskaun, tanpa caj pemprosesan.')
                ->columns(2)
                ->schema([
                    Toggle::make('affiliates_open')->label('Buka permohonan affiliate')
                        ->helperText('Semasa ditutup, menu Affiliate tersembunyi bagi yang belum memohon. Affiliate sedia ada tetap aktif.')
                        ->columnSpanFull(),
                    TextInput::make('affiliate_commission_percent')->label('Komisen lalai (%)')
                        ->helperText('Boleh diubah untuk setiap affiliate.')
                        ->numeric()->minValue(0)->maxValue(50)->step(0.5)->suffix('%')->required(),
                    TextInput::make('affiliate_hold_days')->label('Tempoh sebelum komisen boleh dibayar (hari)')
                        ->helperText('Selepas bayaran pembeli; ikut tempoh bayaran balik.')
                        ->integer()->minValue(0)->maxValue(90)->required(),
                    TextInput::make('affiliate_cookie_days')->label('Tempoh pautan diingati (hari)')
                        ->helperText('Pembelian dalam tempoh ini selepas membuka pautan ?ref= dikira untuk affiliate.')
                        ->integer()->minValue(1)->maxValue(365)->required(),
                ]),

            Section::make('Royalti')
                ->description('Kadar lalai untuk Kandungan → Royalti. Kolam boleh diubah semasa melihat laporan. Pastikan kadar ini sepadan dengan kontrak penulis.')
                ->columns(2)
                ->schema([
                    TextInput::make('royalty_pool_percent')->label('Kolam royalti (% pendapatan langganan)')
                        ->numeric()->minValue(0)->maxValue(100)->step(0.5)->suffix('%')->required(),
                    TextInput::make('royalty_plus_ebook_share')->label('Bahagian e-book dalam Langganan Plus')
                        ->helperText('Selebihnya untuk animasi dan tidak masuk kolam e-book.')
                        ->numeric()->minValue(0)->maxValue(100)->suffix('%')->required(),
                    TextInput::make('royalty_min_minutes')->label('Minit minimum untuk satu bacaan')
                        ->integer()->minValue(1)->maxValue(60)->required(),
                    TextInput::make('royalty_single_percent')->label('Royalti jualan e-book single')
                        ->numeric()->minValue(0)->maxValue(100)->step(0.5)->suffix('%')->required(),
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
                Setting::set($key, in_array($key, ['landing_faq', 'brand_video_places'], true) ? array_values($value ?? []) : $value);
            }

            $this->setPrice(Plan::PAS_TAHUNAN_EARLY, $data['early_bird_price']);
            $this->setPrice(Plan::PAS_TAHUNAN, $data['regular_price']);
            $this->setPrice(Plan::PAS_PLUS, $data['plus_price']);
        });

        $scheduled = $payments->scheduleWaitingPasses();

        Notification::make()
            ->title('Tetapan disimpan.')
            ->body($scheduled ? "{$scheduled} Langganan Tahunan yang menunggu telah dijadualkan." : null)
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

    private function catalogReadiness(): Callout
    {
        $problems = app(CatalogReadiness::class)->problems();

        if ($problems === []) {
            return Callout::make('Senarai semak katalog: sedia')
                ->description('Semua buku yang diterbitkan ada fail PDF, versi pantas dan kaver.')
                ->success();
        }

        return Callout::make('Senarai semak katalog: perlu perhatian')
            ->description(new HtmlString(implode('<br>', array_map(fn (string $problem) => '• '.e($problem), $problems))))
            ->warning();
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
