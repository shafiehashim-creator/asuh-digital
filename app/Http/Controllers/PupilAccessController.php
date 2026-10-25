<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\Classroom;
use App\Services\ChildMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pupils (Phase D) come in with their class code and tap their own name: no
 * e-mail or password for children (CLAUDE.md, PDPA). They then use Mod Anak.
 */
class PupilAccessController extends Controller
{
    private const CLASS_KEY = 'pupil_class';

    public function __construct(private ChildMode $childMode) {}

    public function join(): View
    {
        return view('pupil.join');
    }

    public function enterCode(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:12']], [], ['code' => 'kod kelas']);
        $classroom = Classroom::findByCode($request->string('code'));

        if (! $classroom) {
            return back()->withInput()->withErrors(['code' => 'Kod kelas tidak dijumpai. Semak semula dengan guru kamu.']);
        }

        $request->session()->put(self::CLASS_KEY, $classroom->id);

        return redirect()->route('pupil.names');
    }

    public function names(Request $request): View|RedirectResponse
    {
        $classroom = $this->classroom($request);

        if (! $classroom) {
            return redirect()->route('pupil.join');
        }

        return view('pupil.names', [
            'classroom' => $classroom->load('pupils', 'institution'),
            'licensed' => $classroom->institution->activeLicense() !== null,
        ]);
    }

    public function choose(Request $request, Child $pupil): RedirectResponse
    {
        $classroom = $this->classroom($request);
        abort_unless($classroom && $pupil->classroom_id === $classroom->id, 404);

        if (! $classroom->institution->activeLicense()) {
            return redirect()->route('pupil.names');
        }

        $this->childMode->enterAsPupil($pupil);

        return redirect()->route('child-mode.home');
    }

    /** "Tukar murid": back to the class's name list on a shared tablet. */
    public function leave(): RedirectResponse
    {
        $this->childMode->leave();

        return redirect()->route('pupil.names');
    }

    private function classroom(Request $request): ?Classroom
    {
        $id = $request->session()->get(self::CLASS_KEY);

        return $id ? Classroom::query()->find($id) : null;
    }
}
