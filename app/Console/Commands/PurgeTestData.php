<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Order;
use App\Models\Pass;
use App\Models\PaymentLog;
use App\Models\PilotRequest;
use App\Models\Preorder;
use App\Models\User;
use App\Services\BankTransfers;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Clears the orders, passes, pre-orders and parent accounts made while
 * testing, right before launch. Admins, settings and plan prices stay.
 *
 * Guard rails: dry run unless --confirm, a typed confirmation phrase, and a
 * hard refusal once CHIP is in live mode (real customers may exist then).
 */
#[Signature('asuh:purge-test-data
    {--confirm : Actually delete (without it, only show what would be deleted)}
    {--include-pilots : Also delete institution pilot requests}')]
#[Description('Delete test orders, passes, pre-orders and parent accounts before launch')]
class PurgeTestData extends Command
{
    public const PHRASE = 'KOSONGKAN';

    public function handle(): int
    {
        if (! config('services.chip.test_mode')) {
            $this->error('CHIP dalam mod LIVE. Arahan ini disekat supaya data pelanggan sebenar tidak terpadam.');

            return self::FAILURE;
        }

        $parents = User::query()->where('role', Role::Parent);
        $receipts = Order::query()->whereNotNull('bank_receipt_path')->pluck('bank_receipt_path');

        $counts = [
            ['Pesanan', Order::query()->count()],
            ['Langganan Tahunan', Pass::query()->count()],
            ['Pre-order', Preorder::query()->count()],
            ['Log bayaran', PaymentLog::query()->count()],
            ['Akaun ibu bapa', (clone $parents)->count()],
            ['Fail resit', $receipts->count()],
            ['Permohonan pilot', $this->option('include-pilots') ? PilotRequest::query()->count() : 'dikekalkan'],
        ];

        $this->table(['Data', 'Akan dipadam'], $counts);
        $this->line('Dikekalkan: akaun admin/editor, tetapan, harga pelan'.($this->option('include-pilots') ? '.' : ', permohonan pilot.'));

        if (! $this->option('confirm')) {
            $this->info('Mod tinjauan sahaja. Tiada apa dipadam. Jalankan semula dengan --confirm untuk memadam.');

            return self::SUCCESS;
        }

        $typed = $this->ask('Taip '.self::PHRASE.' untuk memadam data di atas secara kekal');

        if ($typed !== self::PHRASE) {
            $this->warn('Dibatalkan. Tiada apa dipadam.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($parents) {
            // Children first: passes and pre-orders reference orders, orders reference users.
            PaymentLog::query()->delete();
            Pass::query()->delete();
            Preorder::query()->delete();
            Order::query()->delete();

            $parentIds = (clone $parents)->pluck('id');
            DB::table('sessions')->whereIn('user_id', $parentIds)->delete();
            DB::table('password_reset_tokens')
                ->whereIn('email', User::query()->whereIn('id', $parentIds)->pluck('email'))
                ->delete();
            User::query()->whereIn('id', $parentIds)->delete();

            if ($this->option('include-pilots')) {
                PilotRequest::query()->delete();
            }

            // Queued mails for deleted orders would only fail.
            DB::table('jobs')->delete();
            DB::table('failed_jobs')->delete();
        });

        // Files go after the database commit, so a failed transaction keeps them.
        $receipts->each(fn (string $path) => Storage::disk(BankTransfers::DISK)->delete($path));

        $this->info('Data ujian telah dikosongkan. Laman sedia untuk launch.');

        return self::SUCCESS;
    }
}
