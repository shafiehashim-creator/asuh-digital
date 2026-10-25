<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Services\ChildMode;
use App\Services\ParentGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChildModeController extends Controller
{
    public function __construct(private ChildMode $childMode) {}

    /** The parent hands the device over: lock the session to this child. */
    public function enter(Request $request, Child $child): RedirectResponse
    {
        abort_unless($child->user_id === $request->user()->id, 404);

        $this->childMode->enter($child);

        return redirect()->route('child-mode.home');
    }

    public function home(Request $request): View|RedirectResponse
    {
        $child = $this->childMode->child($request->user());

        if (! $child) {
            return redirect()->route('account');
        }

        return view('child-mode.home', [
            'child' => $child,
            'books' => $this->childMode->shelf($child),
        ]);
    }

    public function gate(Request $request, ParentGate $gate): View|RedirectResponse
    {
        if (! $this->childMode->isActive()) {
            return redirect()->route('account');
        }

        $parent = $request->user();

        return view('child-mode.gate', [
            'usesPin' => $parent->hasParentPin(),
            'question' => $parent->hasParentPin() ? null : $gate->newQuestion(),
        ]);
    }

    public function leave(Request $request, ParentGate $gate): RedirectResponse
    {
        $request->validate(['answer' => ['required', 'string', 'max:10']], [], ['answer' => 'jawapan']);

        if (! $gate->passes($request->user(), $request->string('answer'))) {
            return redirect()->route('child-mode.gate')
                ->withErrors(['answer' => $request->user()->hasParentPin() ? 'PIN tidak tepat.' : 'Jawapan tidak tepat. Cuba soalan baharu ini.']);
        }

        $this->childMode->leave();

        return redirect()->route('account');
    }
}
