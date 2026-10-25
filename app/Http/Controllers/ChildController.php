<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChildRequest;
use App\Models\Child;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Parents manage their children's profiles from "Akaun saya".
 */
class ChildController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($this->isFull($request)) {
            return redirect()->route('account')->with('status', 'children-full');
        }

        return view('children.form', ['child' => new Child]);
    }

    public function store(ChildRequest $request): RedirectResponse
    {
        if ($this->isFull($request)) {
            return redirect()->route('account')->with('status', 'children-full');
        }

        $request->user()->children()->create($request->validated());

        return redirect()->route('account')->with('status', 'child-saved');
    }

    public function edit(Request $request, Child $child): View
    {
        $this->authorizeOwner($request, $child);

        return view('children.form', ['child' => $child]);
    }

    public function update(ChildRequest $request, Child $child): RedirectResponse
    {
        $this->authorizeOwner($request, $child);

        $child->update($request->validated());

        return redirect()->route('account')->with('status', 'child-saved');
    }

    /**
     * PDPA: removes the profile with everything recorded for it (reading data
     * and badges cascade from the children table).
     */
    public function destroy(Request $request, Child $child): RedirectResponse
    {
        $this->authorizeOwner($request, $child);

        $child->delete();

        return redirect()->route('account')->with('status', 'child-deleted');
    }

    private function isFull(Request $request): bool
    {
        return $request->user()->children()->count() >= Child::MAX_PER_ACCOUNT;
    }

    private function authorizeOwner(Request $request, Child $child): void
    {
        // 404 rather than 403, so other families' profile ids reveal nothing.
        abort_unless($child->user_id === $request->user()->id, 404);
    }
}
