<?php

namespace App\Http\Controllers;

use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\URL;

class LocaleController extends Controller
{
    /** BM | EN in the header: remember the choice and go back to the same page. */
    public function switch(Request $request, string $locale): RedirectResponse
    {
        abort_unless(Locale::isSupported($locale), 404);

        $request->session()->put(Locale::SESSION_KEY, $locale);
        Cookie::queue(Locale::COOKIE, $locale, 60 * 24 * 365);

        // Signed-in users get their e-mails in this language too.
        $request->user()?->forceFill(['locale' => $locale])->save();

        $back = URL::previous();

        return redirect()->to(str_starts_with($back, url('/')) ? $back : url('/'));
    }
}
