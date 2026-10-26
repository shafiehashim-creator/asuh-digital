<?php

use App\Mail\OrderReceipt;
use App\Models\Book;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\PaymentProcessor;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => $this->seed(PlanSeeder::class));

it('shows the site in Bahasa Melayu by default', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<html lang="ms"', false)
        ->assertSee('Rak buku Islamik digital untuk anak 6–13 tahun');
});

it('switches to English, remembers it and goes back to the same page', function () {
    $this->from(route('about'))
        ->get(route('locale.switch', 'en'))
        ->assertRedirect(route('about'))
        ->assertCookie('asuh_lang', 'en');

    $this->get('/')
        ->assertSee('<html lang="en"', false)
        ->assertSee('A digital Islamic bookshelf for children aged 6–13')
        ->assertDontSee('Rak buku Islamik digital untuk anak 6–13 tahun');

    $this->get(route('locale.switch', 'ms'));
    $this->get('/')->assertSee('Rak buku Islamik digital untuk anak 6–13 tahun');
});

it('reads the language from the cookie on a new visit', function () {
    $this->withCookie('asuh_lang', 'en')
        ->get(route('pass.show'))
        ->assertSee('<html lang="en"', false);
});

it('refuses unknown languages and never redirects off the site', function () {
    $this->get(route('locale.switch', 'fr'))->assertNotFound();

    $this->from('https://example.com/elsewhere')
        ->get(route('locale.switch', 'en'))
        ->assertRedirect(url('/'));
});

it('falls back to the BM setting when no English text is saved', function () {
    Setting::set('landing_headline', 'Tajuk khas');
    Setting::set('landing_headline_en', '');

    $this->get(route('locale.switch', 'en'));
    $this->get('/')->assertSee('Tajuk khas');

    Setting::set('landing_headline_en', 'Special headline');
    $this->get('/')->assertSee('Special headline');
});

it('shows the English legal pages, which defer to the BM version', function (string $route) {
    $this->get(route('locale.switch', 'en'));

    $this->get(route($route))->assertOk()->assertSee('Bahasa Melayu version prevails');
})->with(['privacy', 'terms', 'refund', 'delivery']);

it('translates the checkout and payment pages', function () {
    $this->get(route('locale.switch', 'en'));

    $this->get(route('preorder.create'))
        ->assertOk()
        ->assertSee('Reserve a Yearly Subscription slot')
        ->assertSee('Your account')
        ->assertSee('Full name')
        ->assertSee('Reserve my slot &amp; continue to payment', false)
        ->assertDontSee('Kaedah bayaran');
});

it('uses the English book title and synopsis when they are filled in', function () {
    $book = Book::factory()->published()->create([
        'title' => 'Kisah Nabi Yunus',
        'title_en' => 'The Story of Prophet Yunus',
        'synopsis' => 'Sinopsis BM.',
        'synopsis_en' => null,
    ]);

    expect($book->localTitle())->toBe('Kisah Nabi Yunus');

    app()->setLocale('en');
    expect($book->localTitle())->toBe('The Story of Prophet Yunus')
        ->and($book->localSynopsis())->toBe('Sinopsis BM.');
});

it('saves the choice on the account and sends e-mails in that language', function () {
    Mail::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('locale.switch', 'en'));
    expect($user->fresh()->locale)->toBe('en');

    $order = Order::factory()->create(['user_id' => $user->id]);
    app(PaymentProcessor::class)->markPaid($order);

    Mail::assertQueued(OrderReceipt::class, fn (OrderReceipt $mail) => $mail->locale === 'en');

    $html = (new OrderReceipt($order->fresh()))->locale('en')->render();
    expect($html)->toContain('Amount paid')->not->toContain('Jumlah dibayar');
});

it('has an English line for every text on the public pages', function () {
    $en = json_decode(file_get_contents(lang_path('en.json')), true);

    $files = collect([
        'landing', 'about', 'pass/show', 'catalog/index', 'catalog/show', 'purchase/create',
        'preorder/create', 'preorder/pay', 'preorder/receipt', 'pilot/create', 'pilot/thanks',
        'auth/login', 'auth/register', 'auth/forgot-password', 'auth/reset-password',
        'auth/verify-email', 'auth/confirm-password', 'auth/switch-account',
        'legal/privacy', 'legal/terms', 'legal/refund', 'legal/delivery',
        'components/layouts/app', 'components/book-card', 'components/shelf-book', 'components/book-cover',
        'components/catalog-preview-notice', 'components/slot-counter', 'components/brand-video',
        'components/video-player', 'components/locale-switch', 'mail/order-receipt',
        'videos/index', 'components/showcase-video', 'install', 'components/install-app', 'components/mobile-menu', 'components/monthly-option', 'mail/renewal-reminder',
    ])->map(fn ($view) => resource_path("views/{$view}.blade.php"))
        ->merge([
            app_path('Http/Controllers/PurchaseController.php'),
            app_path('Http/Controllers/ChipPaymentController.php'),
            app_path('Services/PromoCodes.php'),
            app_path('Services/Purchases.php'),
            app_path('Enums/PaymentMethod.php'),
            app_path('Enums/ChipMethod.php'),
            app_path('Models/Order.php'),
            app_path('Services/VideoShowcase.php'),
            app_path('Http/Controllers/ManifestController.php'),
            app_path('Mail/RenewalReminder.php'),
            app_path('Enums/BillingPeriod.php'),
        ]);

    $missing = [];
    foreach ($files as $file) {
        $source = file_get_contents($file);
        preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)*)'/", $source, $calls);
        // Text handed to components that translate it themselves.
        preg_match_all('/<x-(?:field|auth-card|layouts\.app|legal-page)\b(?:[^>"]|"[^"]*")*>/', $source, $tags);
        preg_match_all('/\s(?:label|hint|title|intro)="([^"{]+)"/', implode(' ', $tags[0]), $props);

        foreach ([...$calls[1], ...$props[1]] as $key) {
            $key = stripslashes($key);
            if (! array_key_exists($key, $en)) {
                $missing[] = basename($file).': '.$key;
            }
        }
    }

    expect($missing)->toBe([]);
});

it('saves the language picked before logging in to the account', function () {
    $user = User::factory()->create(['locale' => 'ms']);

    $this->get(route('locale.switch', 'en'));
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

    expect($user->fresh()->locale)->toBe('en');
});

it('shows the account language after logging in on a browser with no pick', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

    expect($user->fresh()->locale)->toBe('en');
    $this->get('/')->assertSee('<html lang="en"', false);
});

it('switches an English account back to BM for e-mails', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)->get(route('locale.switch', 'ms'));

    expect($user->fresh()->locale)->toBe('ms')
        ->and($user->fresh()->preferredLocale())->toBe('ms');
});
