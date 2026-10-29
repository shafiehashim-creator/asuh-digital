<?php

use App\Enums\AgeGroup;
use App\Enums\Game;
use App\Enums\Role;
use App\Filament\Resources\GameItems\Pages\ManageGameItems;
use App\Models\Child;
use App\Models\Classroom;
use App\Models\GameItem;
use App\Models\GamePlay;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\GamePlaying;
use App\Support\GameContent;
use App\Support\HijaiyahQuestions;
use App\Support\MathQuestions;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Setting::set('catalog_open', true);
    Setting::set('games_open', true);
});

function gamer(bool $subscribed = true, array $child = []): Child
{
    $parent = User::factory()->create();
    if ($subscribed) {
        $plan = Plan::findByCode(Plan::PAS_TAHUNAN);
        $order = Order::factory()->paid()->create(['user_id' => $parent->id, 'plan_id' => $plan->id]);
        Pass::query()->create([
            'user_id' => $parent->id, 'plan_id' => $plan->id, 'order_id' => $order->id,
            'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
        ]);
    }

    return Child::factory()->for($parent)->create($child + ['nickname' => 'Aisyah', 'age_group' => AgeGroup::SixToNine]);
}

function playAs(Child $child): void
{
    test()->actingAs($child->user)->post("/akaun/anak/{$child->id}/mod-anak")->assertRedirect('/mod-anak');
}

it('opens every game to children of subscribed families', function () {
    $child = gamer();
    playAs($child);

    $home = $this->get('/mod-anak')->assertOk()->assertSee('Permainan')->assertSee('Baki masa bermain hari ini: 20 minit');
    foreach (Game::cases() as $game) {
        $home->assertSee(route('child-mode.game', $game), false);
        $this->get(route('child-mode.game', $game))->assertOk()->assertSee($game->label())->assertSee($game->component());
    }
});

it('keeps games from families without a subscription, pupils, and everyone while closed', function () {
    $child = gamer(subscribed: false);
    playAs($child);
    $this->get('/mod-anak')->assertOk()->assertDontSee(route('child-mode.game', Game::Memory), false);
    $this->get(route('child-mode.game', Game::Memory))->assertNotFound();

    $pupil = Child::factory()->create(['user_id' => null, 'classroom_id' => Classroom::factory()]);
    expect(app(GamePlaying::class)->hasAccess($pupil))->toBeFalse();

    Setting::set('games_open', false);
    expect(app(GamePlaying::class)->hasAccess(gamer()))->toBeFalse();
});

it('lets staff preview the games while they are closed', function () {
    Setting::set('games_open', false);
    $staff = User::factory()->admin()->create();
    $child = Child::factory()->for($staff)->create(['age_group' => AgeGroup::TenToThirteen]);

    expect(app(GamePlaying::class)->hasAccess($child))->toBeTrue();
});

it('counts time played within the real time passed and the daily limit', function () {
    $child = gamer(child: ['daily_game_minutes' => 10]);
    playAs($child);
    $url = route('child-mode.game.progress', Game::Memory);

    $this->postJson($url, ['seconds' => 30])->assertOk()->assertJson(['seconds_left' => 570]);
    // Right away again: no time has passed, so nothing more is counted.
    $this->postJson($url, ['seconds' => 30])->assertJson(['seconds_left' => 570]);

    $this->travel(5)->minutes();
    // One heartbeat never counts more than 35 seconds, and the score is kept.
    $this->postJson($url, ['seconds' => 300, 'score' => 80])->assertJson(['seconds_left' => 535]);
    expect(GamePlay::first())->seconds->toBe(65)->rounds->toBe(1)->best_score->toBe(80);

    $this->postJson($url, ['seconds' => 30, 'score' => 101])->assertUnprocessable();
});

it('locks the games once today\'s limit is used up', function () {
    $child = gamer(child: ['daily_game_minutes' => 10]);
    GamePlay::query()->forceCreate(['child_id' => $child->id, 'game' => Game::Order, 'played_on' => today(), 'seconds' => 600]);
    playAs($child);

    $this->get(route('child-mode.game', Game::Memory))->assertRedirect(route('child-mode.home'));
    $this->get('/mod-anak')->assertSee('Masa bermain hari ini sudah habis');

    $this->travel(1)->days();
    $this->get(route('child-mode.game', Game::Memory))->assertOk();
});

it('lets the parent set the daily game limit, or none', function () {
    $child = gamer();
    $form = ['nickname' => 'Aisyah', 'age_group' => '6-9', 'avatar' => $child->avatar->value, 'game_settings' => '1'];

    $this->actingAs($child->user)->get(route('children.edit', $child))->assertOk()->assertSee('Had bermain sehari');

    $this->put(route('children.update', $child), $form + ['daily_game_minutes' => '45'])->assertSessionHasNoErrors();
    expect($child->fresh()->daily_game_minutes)->toBe(45);

    $this->put(route('children.update', $child), $form + ['daily_game_minutes' => ''])->assertSessionHasNoErrors();
    expect($child->fresh()->daily_game_minutes)->toBeNull();

    $this->put(route('children.update', $child), $form + ['daily_game_minutes' => '999'])->assertSessionHasErrors('daily_game_minutes');
});

it('gives every game content for both age groups at every level', function (Game $game) {
    foreach ([AgeGroup::SixToNine, AgeGroup::TenToThirteen] as $age) {
        foreach (array_keys(Game::LEVELS) as $level) {
            $content = GameContent::for($game, $age, $level);

            // Pilih yang Baik plays as a quiz from Sederhana.
            $component = $content['component'] ?? $game->component();

            match (true) {
                $component === 'gameMemory' => expect(count($content['pairs']))->toBeGreaterThanOrEqual($content['count']),
                $component === 'gameGoodChoice' => expect(count($content['scenarios']))->toBeGreaterThanOrEqual($content['rounds']),
                $component === 'gameOrder' => expect($content['sets'])->each(fn ($set) => $set->items->toBeArray()),
                $component === 'gameWordSearch' => expect(count($content['words']))->toBeGreaterThanOrEqual($content['count'])
                    ->and(collect($content['words'])->every(fn ($w) => strlen($w) <= $content['size']))->toBeTrue(),
                $component === 'gameSliding' => expect($content['size'])->toBeIn([3, 4, 5])->and($content['images'])->toHaveCount(3),
                $component === 'gameSudoku' => expect($content['size'])->toBeIn([4, 6])->and($content['blanks'])->toBeLessThan($content['size'] ** 2),
                default => expect(count($content['questions']))->toBeGreaterThanOrEqual($content['rounds'] ?? 1)
                    ->and(collect($content['questions'])->every(fn ($q) => (isset($q['pick']) || in_array($q['answer'], $q['options'], true))
                        && count(array_unique(array_diff($q['options'], [$q['answer']]))) >= 1))->toBeTrue(),
            };
        }
    }
})->with(Game::cases());

it('makes sums with the right answers, harder for older children', function () {
    foreach ([false, true] as $older) {
        foreach ([1, 2, 3] as $level) {
            foreach (MathQuestions::make($older, $level, 40) as $q) {
                expect(count(array_unique($q['options'])))->toBeGreaterThanOrEqual(3);
                // Stories and two-number targets are checked elsewhere.
                if ($q['big'] === null || isset($q['pick'])) {
                    continue;
                }
                $math = fn (string $side) => eval('return '.str_replace(['×', '÷', '−'], ['*', '/', '-'], $side).';');

                if (in_array($q['answer'], ['<', '=', '>'], true)) {
                    [$left, $right] = explode('❓', $q['big']);
                    expect($math($left) <=> $math($right))->toBe(['<' => -1, '=' => 0, '>' => 1][$q['answer']]);

                    continue;
                }

                [$left, $right] = explode('=', str_replace('❓', $q['answer'], $q['big']));
                expect($math($left))->toEqual($math($right))
                    ->and((int) $q['answer'])->toBeGreaterThanOrEqual(0);
            }
        }
    }
});

it('opens the next level with three stars at the highest level, one at a time', function () {
    $child = gamer();
    playAs($child);
    $url = route('child-mode.game.progress', Game::Math);

    $this->get(route('child-mode.game', [Game::Math, 'tahap' => 3]))->assertOk()->assertSee('🔒 Sederhana')->assertViewHas('level', 1);

    $this->postJson($url, ['seconds' => 30, 'score' => 80, 'level' => 1])->assertJson(['unlocked' => 1]);
    $this->postJson($url, ['seconds' => 30, 'score' => 95, 'level' => 1])->assertJson(['unlocked' => 2]);
    // Three stars again at Mudah does not skip ahead.
    $this->postJson($url, ['seconds' => 30, 'score' => 100, 'level' => 1])->assertJson(['unlocked' => 2]);

    $this->get(route('child-mode.game', [Game::Math, 'tahap' => 2]))->assertOk()->assertViewHas('level', 2)->assertSee('🔒 Sukar');
    $this->get('/mod-anak')->assertSee('Tahap Sederhana');
    // Other games keep their own levels.
    expect(app(GamePlaying::class)->unlockedLevel($child, Game::Memory))->toBe(1);
});

it('plays only active items for the child\'s age, from a pool drawn at random', function () {
    GameItem::query()->create(['game' => Game::Prophets, 'age_group' => 'both', 'prompt' => 'Petunjuk baharu', 'answer' => 'Nabi Lut', 'is_active' => false]);
    GameItem::query()->create(['game' => Game::Prophets, 'age_group' => '10-13', 'prompt' => 'Petunjuk abang', 'answer' => 'Nabi Yahya']);

    $young = collect(GameContent::for(Game::Prophets, AgeGroup::SixToNine)['questions'])->pluck('prompt');
    $old = collect(GameContent::for(Game::Prophets, AgeGroup::TenToThirteen)['questions'])->pluck('prompt');

    expect($young)->not->toContain('Petunjuk baharu')->not->toContain('Petunjuk abang')
        ->and($old)->toContain('Petunjuk abang')
        ->and(GameContent::for(Game::Memory, AgeGroup::SixToNine))->count->toBe(4)->pairs->toHaveCount(24)
        ->and(GameContent::for(Game::Memory, AgeGroup::SixToNine, 3))->count->toBe(8);
});

it('hides a game without enough active items', function () {
    GameItem::query()->where('game', Game::QuickQuiz)->update(['is_active' => false]);
    $child = gamer();
    playAs($child);

    $this->get('/mod-anak')->assertOk()->assertDontSee(route('child-mode.game', Game::QuickQuiz), false)
        ->assertSee(route('child-mode.game', Game::Memory), false);
    $this->get(route('child-mode.game', Game::QuickQuiz))->assertNotFound();
});

it('lets staff write game items, and only a Super Admin switches them on', function () {
    $this->actingAs(User::factory()->admin()->create());
    Livewire\Livewire::test(ManageGameItems::class)->assertOk()
        ->callAction('create', ['game' => 'kuiz-pantas', 'age_group' => 'both', 'prompt' => 'Apa kita ucap ketika naik kenderaan?', 'answer' => 'Doa naik kenderaan', 'options' => ['Bismillah sahaja', 'Diam'], 'is_active' => true])
        ->assertHasNoActionErrors();
    expect(GameItem::latest('id')->first())->is_active->toBeTrue()->options->toBe(['Bismillah sahaja', 'Diam']);

    $this->actingAs(User::factory()->role(Role::Editor)->create());
    Livewire\Livewire::test(ManageGameItems::class)
        ->callAction('create', ['game' => 'cari-perkataan', 'age_group' => 'both', 'prompt' => 'RAHMAT', 'is_active' => true])
        ->assertHasNoActionErrors();
    expect(GameItem::latest('id')->first())->prompt->toBe('RAHMAT')->is_active->toBeFalse();

    Livewire\Livewire::test(ManageGameItems::class)
        ->callAction('create', ['game' => 'cari-perkataan', 'age_group' => 'both', 'prompt' => 'DUA KATA'])
        ->assertHasActionErrors(['prompt']);
});

it('plays a different kind of challenge at each level', function () {
    $young = AgeGroup::SixToNine;
    $old = AgeGroup::TenToThirteen;

    expect(GameContent::for(Game::Memory, $young, 1)['preview'])->toBe(3)
        ->and(GameContent::for(Game::Memory, $old, 3))->words->toBeTrue()->pairs->each->not->toContain('🕌')
        ->and(GameContent::for(Game::GoodChoice, $young, 2))->component->toBe('gameQuiz')
        ->and(GameContent::for(Game::GoodChoice, $young, 3)['questions'][0])->keepOrder->toBeTrue()->why->not->toBeEmpty()
        ->and(GameContent::for(Game::Order, $young, 2))->impostor->toBeTrue()->reverse->toBeFalse()
        ->and(GameContent::for(Game::Order, $old, 3))->reverse->toBeTrue()
        ->and(GameContent::for(Game::Hijaiyah, $young, 2)['questions'][0]['answer'])->toMatch('/^\p{Arabic}$/u')
        ->and(GameContent::for(Game::Hijaiyah, $old, 3)['questions'][0]['big'])->toContain('ـ')
        ->and(GameContent::for(Game::WordSearch, $old, 3)['clues'])->not->toBeEmpty()
        ->and(GameContent::for(Game::WordSearch, $old, 1)['clues'])->toBeNull()
        ->and(GameContent::for(Game::QuickQuiz, $young, 3))->survival->toBe(15)->lives->toBe(3)
        ->and(GameContent::for(Game::Science, $young, 2)['questions'][0]['prompt'])->toStartWith('Yang manakah BUKAN')
        ->and(GameContent::for(Game::Science, $old, 3)['questions'][0])->keepOrder->toBeTrue()
        ->and(GameContent::for(Game::Sudoku, $old, 3))->checkLater->toBeTrue()
        ->and(GameContent::for(Game::Sliding, $old, 3))->size->toBe(5);

    $prophets = collect(GameContent::for(Game::Prophets, $old, 3)['questions'])->pluck('prompt');
    expect($prophets->contains(fn ($p) => str_contains($p, 'PALING')))->toBeTrue();
});

it('makes two-number targets that always have an answer', function () {
    foreach ([false, true] as $older) {
        $pairs = collect(MathQuestions::make($older, 3, 60))->filter(fn ($q) => isset($q['pick']));
        expect($pairs)->not->toBeEmpty();
        foreach ($pairs as $q) {
            $numbers = array_map('intval', $q['options']);
            $hits = 0;
            foreach ($numbers as $i => $a) {
                foreach (array_slice($numbers, $i + 1) as $b) {
                    $hits += ($q['op'] === 'times' ? $a * $b : $a + $b) === $q['target'] ? 1 : 0;
                }
            }
            expect($hits)->toBe(1);
        }
    }
});

it('names letters only once among their choices, and every story hides one wrong sentence', function () {
    foreach ([1, 2, 3] as $level) {
        foreach (HijaiyahQuestions::make(true, $level) as $q) {
            expect($q['options'])->toBe(array_values(array_unique($q['options'])))->toContain($q['answer']);
        }
    }

    $stories = GameItem::query()->where('variant', 'cerita')->get();
    expect($stories)->not->toBeEmpty()
        ->and($stories->every(fn ($story) => in_array($story->answer, $story->options, true)))->toBeTrue();
});
