<?php

namespace App\Http\Controllers;

use App\Enums\Badge;
use App\Models\Child;
use App\Models\RewardPromise;
use App\Services\RewardPromises;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Parents' reward promises on the Pencapaian page: "earn this badge and you
 * get this reward", achieved automatically when the badge is earned.
 */
class RewardPromiseController extends Controller
{
    public function store(Request $request, Child $child, RewardPromises $promises): RedirectResponse
    {
        abort_unless($child->user_id === $request->user()->id, 404);
        $child->load('badges', 'rewardPromises');

        $data = $request->validateWithBag('promise'.$child->id, [
            'badge' => ['required', Rule::in(array_map(fn (Badge $badge) => $badge->value, $promises->promisable($child)))],
            'reward' => ['required', 'string', 'max:120'],
        ], [], ['badge' => 'lencana', 'reward' => 'ganjaran']);

        if (! $promises->canAddMore($child)) {
            return back()->withErrors(['reward' => 'Maksimum '.RewardPromise::MAX_OPEN.' janji yang belum tercapai untuk setiap anak.'], 'promise'.$child->id);
        }

        $child->rewardPromises()->create($data);

        return redirect(route('account.progress').'#child-'.$child->id.'-heading')->with('status', 'promise-saved');
    }

    public function fulfil(Request $request, RewardPromise $promise): RedirectResponse
    {
        $this->authorizeOwner($request, $promise);
        abort_unless($promise->isAchieved(), 404);

        $promise->forceFill(['fulfilled_at' => now()])->save();

        return redirect(route('account.progress').'#child-'.$promise->child_id.'-heading')->with('status', 'promise-fulfilled');
    }

    public function destroy(Request $request, RewardPromise $promise): RedirectResponse
    {
        $this->authorizeOwner($request, $promise);

        $promise->delete();

        return redirect(route('account.progress').'#child-'.$promise->child_id.'-heading')->with('status', 'promise-deleted');
    }

    private function authorizeOwner(Request $request, RewardPromise $promise): void
    {
        abort_unless($promise->child?->user_id === $request->user()->id, 404);
    }
}
