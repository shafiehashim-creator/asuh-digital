<?php

namespace App\Http\Controllers;

use App\Services\PlusOffer;
use App\Services\PlusWaitlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** "Beritahu saya bila dibuka" on the Langganan Plus sales page. */
class PlusWaitlistController extends Controller
{
    public function __invoke(Request $request, PlusOffer $offer, PlusWaitlist $waitlist): RedirectResponse
    {
        abort_unless($offer->available(), 404);

        if ($offer->onSale()) {
            return redirect()->route('plus.show');
        }

        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $waitlist->join($data['email'], $request->user());

        return redirect()->to(route('plus.show').'#beritahu')
            ->with('plus_waitlist', __('Terima kasih! Kami akan e-mel anda sebaik Langganan Plus dibuka.'));
    }
}
