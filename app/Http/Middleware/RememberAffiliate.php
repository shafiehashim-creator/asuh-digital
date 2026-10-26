<?php

namespace App\Http\Middleware;

use App\Models\Affiliate;
use App\Models\Setting;
use App\Services\PromoCodes;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers an approved affiliate's ?ref=CODE for the days set in the admin,
 * so a purchase made later is credited to them. The latest link wins.
 */
class RememberAffiliate
{
    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->query('ref');

        if (is_string($code) && $code !== '' && ($affiliate = Affiliate::findActiveByCode($code))) {
            Cookie::queue(PromoCodes::COOKIE, $affiliate->code, 60 * 24 * (int) Setting::get('affiliate_cookie_days'));
        }

        return $next($request);
    }
}
