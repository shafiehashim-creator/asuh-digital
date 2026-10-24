<?php

use App\Mail\BankReceiptUploaded;
use App\Mail\BankTransferRejected;
use App\Mail\OrderReceipt;
use App\Models\Order;
use App\Models\Preorder;
use App\Models\Setting;
use App\Models\User;
use App\Services\BankTransfers;
use App\Services\PaymentProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
});

function paidReceiptOrder(array $attributes = []): Order
{
    $order = Order::factory()->create($attributes);
    Preorder::factory()->create(['user_id' => $order->user_id, 'order_id' => $order->id]);

    return $order->fresh();
}

it('queues one receipt to the buyer when an order becomes paid', function () {
    $order = paidReceiptOrder();

    app(PaymentProcessor::class)->markPaid($order);
    app(PaymentProcessor::class)->markPaid($order->fresh()); // repeated callback

    Mail::assertQueued(OrderReceipt::class, 1);
    Mail::assertQueued(OrderReceipt::class, fn (OrderReceipt $mail) => $mail->hasTo($order->user->email)
        && $mail->order->is($order));
});

it('sends the receipt after an admin confirms a bank transfer too', function () {
    $order = paidReceiptOrder(['payment_method' => 'bank_transfer']);

    app(BankTransfers::class)->confirm($order, User::factory()->admin()->create());

    Mail::assertQueued(OrderReceipt::class, 1);
});

it('shows the order, amount, pass period and company details on the receipt', function () {
    Setting::set('company_name', 'Syarikat Ujian Sdn Bhd');
    Setting::set('company_ssm_no', '202601000001');
    Setting::set('company_sst_no', 'W10-0000-00000000');
    Setting::set('contact_email', 'tanya@example.com');
    $order = paidReceiptOrder();
    app(PaymentProcessor::class)->markPaid($order);
    $order->refresh();

    $mail = new OrderReceipt($order);

    $mail->assertHasSubject('Resit pembayaran '.$order->reference.' · ASUH Digital');
    $mail->assertHasReplyTo('tanya@example.com');
    $mail->assertSeeInHtml($order->reference)
        ->assertSeeInHtml('RM79.00')
        ->assertSeeInHtml('Pas Tahunan (Early Bird)')
        ->assertSeeInHtml($order->pass->ends_at->translatedFormat('j F Y'))
        ->assertSeeInHtml('Syarikat Ujian Sdn Bhd')
        ->assertSeeInHtml('202601000001')
        ->assertSeeInHtml('No. SST: W10-0000-00000000')
        ->assertSeeInHtml('Tiada caj automatik');
});

it('tells the buyer the pass waits for the launch when that rule is set', function () {
    Setting::set('pass_start_rule', 'on_launch');
    $order = paidReceiptOrder();
    app(PaymentProcessor::class)->markPaid($order);

    (new OrderReceipt($order->fresh()))
        ->assertSeeInHtml('bermula pada tarikh platform ASUH Digital dilancarkan');
});

it('leaves company lines out of the receipt until they are set', function () {
    $order = paidReceiptOrder();
    app(PaymentProcessor::class)->markPaid($order);

    (new OrderReceipt($order->fresh()))
        ->assertDontSeeInHtml('No. SST')
        ->assertDontSeeInHtml('Pertanyaan:');
});

it('emails the admins when a bank receipt is uploaded', function () {
    Setting::set('admin_notification_emails', ['admin1@example.com', 'admin2@example.com']);
    $order = paidReceiptOrder(['payment_method' => 'bank_transfer']);

    app(BankTransfers::class)->uploadReceipt($order, UploadedFile::fake()->create('resit.jpg', 100, 'image/jpeg'));

    Mail::assertQueued(BankReceiptUploaded::class, fn ($mail) => $mail->hasTo('admin1@example.com') && $mail->hasTo('admin2@example.com'));

    // The receipt file is never attached to the e-mail.
    $mail = new BankReceiptUploaded($order->fresh());
    $mail->render();

    expect($mail->attachments)->toBeEmpty()
        ->and($mail->rawAttachments)->toBeEmpty()
        ->and($mail->diskAttachments)->toBeEmpty();
});

it('does not email anyone about receipts when no admin e-mail is set', function () {
    $order = paidReceiptOrder(['payment_method' => 'bank_transfer']);

    app(BankTransfers::class)->uploadReceipt($order, UploadedFile::fake()->create('resit.jpg', 100, 'image/jpeg'));

    Mail::assertNotQueued(BankReceiptUploaded::class);
});

it('tells the buyer about a rejected transfer without revealing the internal reason', function () {
    $order = paidReceiptOrder(['payment_method' => 'bank_transfer']);

    app(BankTransfers::class)->reject($order, User::factory()->admin()->create(), 'Rahsia dalaman: resit disyaki palsu');

    Mail::assertQueued(BankTransferRejected::class, fn ($mail) => $mail->hasTo($order->user->email));

    (new BankTransferRejected($order->fresh()))
        ->assertSeeInHtml($order->reference)
        ->assertSeeInHtml('Hantar semula resit')
        ->assertDontSeeInHtml('Rahsia dalaman');
});

it('uses the branded ASUH mail theme', function () {
    expect(config('mail.markdown.theme'))->toBe('asuh')
        ->and(file_exists(resource_path('views/vendor/mail/html/themes/asuh.css')))->toBeTrue();

    $order = paidReceiptOrder();
    app(PaymentProcessor::class)->markPaid($order);

    (new OrderReceipt($order->fresh()))
        ->assertSeeInHtml('#3a1f6e', false)
        ->assertSeeInHtml('Jom Jadi Baik!');
});
