<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The optional PIN that guards the way out of Mod Anak. */
class ParentPinController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->validateWithBag('parentPin', [
            'current_password' => ['required', 'current_password'],
            'pin' => ['required', 'digits:4', 'confirmed'],
        ], [], ['pin' => 'PIN', 'current_password' => 'kata laluan semasa']);

        $request->user()->forceFill(['parent_pin' => $request->input('pin')])->save();

        return redirect()->route('account')->with('status', 'pin-saved');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('parentPin', [
            'current_password' => ['required', 'current_password'],
        ], [], ['current_password' => 'kata laluan semasa']);

        $request->user()->forceFill(['parent_pin' => null])->save();

        return redirect()->route('account')->with('status', 'pin-removed');
    }
}
