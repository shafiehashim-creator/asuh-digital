<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\Royalties as RoyaltyReport;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Monthly royalty report: a pool of subscription revenue split by reads,
 * plus single e-book sales, per book and per writer or illustrator.
 * Financial, so Super Admin only.
 */
class Royalties extends Page
{
    protected string $view = 'filament.pages.royalties';

    protected static ?string $title = 'Laporan royalti';

    protected static ?string $navigationLabel = 'Royalti';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Kandungan';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'royalti';

    public string $month = '';

    public float|string $poolPercent = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        // Last month by default: the one that is complete and due for payment.
        $this->month = now()->subMonthNoOverflow()->format('Y-m');
        $this->poolPercent = (float) Setting::get('royalty_pool_percent');
    }

    /** @return array<string, string> the last 24 months */
    public function monthOptions(): array
    {
        return collect(range(0, 23))
            ->mapWithKeys(fn (int $i) => [now()->startOfMonth()->subMonths($i)->format('Y-m') => now()->startOfMonth()->subMonths($i)->translatedFormat('F Y')])
            ->all();
    }

    public function report(): array
    {
        $month = Carbon::createFromFormat('Y-m', $this->month ?: now()->format('Y-m'))->startOfMonth();
        $pool = is_numeric($this->poolPercent) ? max(0, min(100, (float) $this->poolPercent)) : null;

        return app(RoyaltyReport::class)->forMonth($month, $pool);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('csvBooks')->label('CSV ikut buku')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(fn () => $this->csv('buku', ['Buku', 'Penulis', 'Bacaan', 'Bahagian (%)', 'Royalti langganan (RM)', 'Jualan single (RM)', 'Kadar single (%)', 'Royalti single (RM)', 'Jumlah royalti (RM)'],
                    fn (array $r) => $r['books']->map(fn ($b) => [
                        $b['book']->title, $b['book']->author, $b['reads'], number_format($b['share'], 2),
                        Money::plain($b['pool_sen']), Money::plain($b['single_sales_sen']), $b['single_rate'], Money::plain($b['single_sen']), Money::plain($b['total_sen']),
                    ]))),
            Action::make('csvContributors')->label('CSV ikut penerima')->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn () => $this->csv('penerima', ['Nama', 'Peranan', 'Akaun bank', 'Bilangan buku', 'Royalti (RM)'],
                    fn (array $r) => $r['contributors']->map(fn ($c) => [$c['name'], $c['role'], $c['bank'], $c['books'], Money::plain($c['total_sen'])]))),
        ];
    }

    private function csv(string $kind, array $header, callable $rows): StreamedResponse
    {
        $report = $this->report();
        $name = 'royalti-'.$kind.'-'.$report['month']->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($header, $rows, $report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel reads UTF-8 with a BOM
            fputcsv($out, $header);
            foreach ($rows($report) as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
