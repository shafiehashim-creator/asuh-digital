<?php

use App\Enums\AgeGroup;
use App\Enums\Badge;
use App\Enums\Role;
use App\Filament\Pages\TopReaders as TopReadersPage;
use App\Mail\RewardPromiseAchieved;
use App\Mail\TopReaderWinner;
use App\Models\Book;
use App\Models\Child;
use App\Models\ChildBadge;
use App\Models\MonthlyTopReader;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\ReadingSession;
use App\Models\RewardPromise;
use App\Models\Setting;
use App\Models\User;
use App\Services\BadgeAwarder;
use App\Services\TopReaders;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Setting::set('catalog_open', true);
    Mail::fake();
});

/** A family child whose parent subscribes for the whole of $from's month and beyond. */
function rewardChild(string $nickname = 'Aisyah', AgeGroup $age = AgeGroup::SixToNine, ?Carbon $from = null): Child
{
    $parent = User::factory()->create();
    $order = Order::factory()->paid()->create(['user_id' => $parent->id]);
    Pass::query()->create([
        'user_id' => $parent->id, 'plan_id' => $order->plan_id, 'order_id' => $order->id,
        'starts_at' => ($from ?? now())->copy()->subMonths(2), 'ends_at' => now()->addYear(),
    ]);

    return Child::factory()->for($parent)->create(['nickname' => $nickname, 'age_group' => $age]);
}

function rewardReadOn(Child $child, string $day, int $minutes): void
{
    ReadingSession::query()->create(['child_id' => $child->id, 'book_id' => Book::factory()->published()->create()->id, 'read_on' => $day, 'minutes' => $minutes, 'last_page' => 1]);
}

it('lets a parent promise a reward for a badge, shown to the child in Mod Anak', function () {
    $child = rewardChild();
    $this->actingAs($child->user);

    $this->post(route('reward-promises.store', $child), ['badge' => Badge::KeenReader->value, 'reward' => 'Aiskrim coklat'])
        ->assertRedirect();
    expect(RewardPromise::query()->sole())->badge->toBe(Badge::KeenReader)->reward->toBe('Aiskrim coklat');

    $this->get(route('account.progress'))->assertSee('Janji ganjaran')->assertSee('Pembaca Rajin → Aiskrim coklat')->assertSee('Dalam usaha');

    $this->post(route('children.child-mode', $child));
    $this->get(route('child-mode.home'))->assertSee('Janji ibu ayah')->assertSee('Dapatkan lencana Pembaca Rajin → Aiskrim coklat');
});

it('marks the promise achieved when the badge is earned, e-mails the parent, and lets them mark it kept', function () {
    $child = rewardChild();
    $promise = $child->rewardPromises()->create(['badge' => Badge::KeenReader, 'reward' => 'Ke taman']);

    foreach ([2, 1, 0] as $daysAgo) {
        rewardReadOn($child, today()->subDays($daysAgo)->toDateString(), 10);
    }
    app(BadgeAwarder::class)->check($child);

    expect($promise->fresh()->isAchieved())->toBeTrue();
    Mail::assertQueued(RewardPromiseAchieved::class, fn ($mail) => $mail->hasTo($child->user->email));

    $this->actingAs($child->user)->get(route('account.progress'))->assertSee('Tunaikan janji anda')->assertSee('Sudah ditunaikan');
    $this->patch(route('reward-promises.fulfil', $promise))->assertRedirect();
    expect($promise->fresh()->isFulfilled())->toBeTrue();
});

it('only promises badges not yet earned, at most three open, for the parent\'s own child', function () {
    $child = rewardChild();
    ChildBadge::query()->create(['child_id' => $child->id, 'badge' => Badge::FirstQuiz->value, 'awarded_at' => now()]);
    $this->actingAs($child->user);

    $this->post(route('reward-promises.store', $child), ['badge' => Badge::FirstQuiz->value, 'reward' => 'Hadiah'])
        ->assertSessionHasErrors(['badge'], null, 'promise'.$child->id);

    foreach ([Badge::QuizExpert, Badge::KeenReader, Badge::ReadingWeek] as $badge) {
        $this->post(route('reward-promises.store', $child), ['badge' => $badge->value, 'reward' => 'Hadiah']);
    }
    $this->post(route('reward-promises.store', $child), ['badge' => Badge::SteadyReader->value, 'reward' => 'Hadiah'])
        ->assertSessionHasErrors(['reward'], null, 'promise'.$child->id);
    expect(RewardPromise::query()->count())->toBe(3);

    $other = rewardChild('Umar');
    $this->post(route('reward-promises.store', $other), ['badge' => Badge::KeenReader->value, 'reward' => 'X'])->assertNotFound();
    $this->delete(route('reward-promises.destroy', RewardPromise::query()->first()))->assertRedirect();
    $this->actingAs($other->user)->delete(route('reward-promises.destroy', RewardPromise::query()->first()))->assertNotFound();
});

it('ranks each age group by points that reward steady reading and quizzes, not open books', function () {
    $month = now()->subMonthNoOverflow()->startOfMonth();
    $steady = rewardChild('Steady', AgeGroup::SixToNine, $month);
    $marathon = rewardChild('Marathon', AgeGroup::SixToNine, $month);
    $older = rewardChild('Older', AgeGroup::TenToThirteen, $month);
    $pupil = Child::factory()->create(['user_id' => null, 'age_group' => AgeGroup::SixToNine]);
    $lapsed = Child::factory()->for(User::factory())->create(['age_group' => AgeGroup::SixToNine]);

    // Steady: 10 days × 20 minutes and one quiz. Marathon: one day of 240 minutes.
    foreach (range(1, 10) as $day) {
        rewardReadOn($steady, $month->copy()->addDays($day)->toDateString(), 20);
    }
    $quiz = Quiz::query()->create(['book_id' => Book::factory()->published()->create()->id]);
    QuizAttempt::query()->forceCreate(['child_id' => $steady->id, 'quiz_id' => $quiz->id, 'score' => 5, 'total' => 5, 'passed' => true, 'created_at' => $month->copy()->addDays(3)]);
    rewardReadOn($marathon, $month->copy()->addDays(2)->toDateString(), 240);
    rewardReadOn($older, $month->copy()->addDays(2)->toDateString(), 30);
    rewardReadOn($pupil, $month->copy()->addDays(2)->toDateString(), 60);
    rewardReadOn($lapsed, $month->copy()->addDays(2)->toDateString(), 60);

    $young = app(TopReaders::class)->ranking($month, AgeGroup::SixToNine);
    expect($young->pluck('child.nickname')->all())->toBe(['Steady', 'Marathon'])
        ->and($young[0]['points'])->toBe(200 + 100 + 20)
        ->and($young[1]['points'])->toBe(60 + 10);
    expect(app(TopReaders::class)->ranking($month, AgeGroup::TenToThirteen)->pluck('child.nickname')->all())->toBe(['Older']);
});

it('announces last month\'s winners once, with a badge, an e-mail and the parent\'s promise', function () {
    $month = now()->subMonthNoOverflow()->startOfMonth();
    $child = rewardChild('Aisyah', AgeGroup::SixToNine, $month);
    $promise = $child->rewardPromises()->create(['badge' => Badge::TopReader, 'reward' => 'Basikal']);
    rewardReadOn($child, $month->copy()->addDays(4)->toDateString(), 30);

    expect(app(TopReaders::class)->canAnnounce(now()->startOfMonth()))->toBeFalse();

    $this->actingAs(User::factory()->create(['role' => Role::Admin]));
    Livewire::test(TopReadersPage::class)
        ->assertSee('Aisyah')
        ->callAction('announce');

    expect(MonthlyTopReader::query()->sole())->rank->toBe(1)->points->toBe(40)
        ->and($child->badges()->pluck('badge')->all())->toContain(Badge::TopReader)
        ->and($promise->fresh()->isAchieved())->toBeTrue();
    Mail::assertQueued(TopReaderWinner::class, fn ($mail) => $mail->hasTo($child->user->email));

    expect(app(TopReaders::class)->announce($month))->toBe(0);
    expect(MonthlyTopReader::query()->count())->toBe(1);

    $this->get('/admin/pembaca-terbaik')->assertOk()->assertSee('Pemenang yang diumumkan');
    $this->actingAs(User::factory()->create(['role' => Role::Editor]))->get('/admin/pembaca-terbaik')->assertForbidden();
});
