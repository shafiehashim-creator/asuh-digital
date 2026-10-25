<?php

namespace App\Services;

use App\Models\PageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Records public page views for the admin traffic report and remembers, per
 * session, where the visitor first came from, so an order made later can be
 * credited to that source. No IP address is stored.
 */
class VisitTracker
{
    private const VISITOR_KEY = 'visit.visitor';

    private const SOURCE_KEY = 'visit.source';

    private const BOTS = '/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|curl|wget|python|headless|lighthouse/i';

    /** Pages that are not visits: admin, files, heartbeats, payment callbacks. */
    private const SKIP_ROUTES = ['filament.*', 'admin.*', 'reader.file', 'reader.page', 'catalog.preview.page',
        'child-mode.progress', 'child-mode.video.progress', 'payment.chip.*', 'livewire.*', 'teacher.*', 'storage.*'];

    public function record(Request $request): void
    {
        if (! $request->isMethod('GET') || $request->expectsJson() || $request->ajax()
            || preg_match(self::BOTS, (string) $request->userAgent())
            || $request->route()?->named(...self::SKIP_ROUTES)
            || $request->user()?->isStaff()) {
            return;
        }

        $session = $request->session();
        $visitor = $session->get(self::VISITOR_KEY) ?? tap(Str::random(32), fn ($id) => $session->put(self::VISITOR_KEY, $id));
        $source = $session->get(self::SOURCE_KEY) ?? tap($this->sourceOf($request), fn ($s) => $session->put(self::SOURCE_KEY, $s));

        PageVisit::query()->create([
            'visited_at' => now(),
            'visitor' => $visitor,
            'user_id' => $request->user()?->id,
            'route' => $request->route()?->getName(),
            'path' => Str::limit('/'.ltrim($request->path(), '/'), 188, ''),
            'source' => $source['source'],
            'medium' => $source['medium'],
            'campaign' => $source['campaign'],
            'device' => $this->device((string) $request->userAgent()),
        ]);
    }

    /**
     * Where this session's visitor first came from, for a new order.
     *
     * @return array{source: ?string, medium: ?string, campaign: ?string}
     */
    public function attribution(): array
    {
        $source = app()->bound('session') && request()->hasSession() ? request()->session()->get(self::SOURCE_KEY) : null;

        return $source ?? ['source' => null, 'medium' => null, 'campaign' => null];
    }

    /** @return array{source: string, medium: ?string, campaign: ?string} */
    private function sourceOf(Request $request): array
    {
        $tag = fn (string $key) => ($v = trim((string) $request->query($key))) === '' ? null : Str::limit(Str::lower($v), 95, '');

        if ($source = $tag('utm_source')) {
            return ['source' => $source, 'medium' => $tag('utm_medium'), 'campaign' => $tag('utm_campaign')];
        }

        // Facebook and Instagram ads add fbclid; Google ads add gclid.
        if ($request->query('gclid')) {
            return ['source' => 'google', 'medium' => 'cpc', 'campaign' => null];
        }

        $referrer = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
        if ($referrer && Str::lower($referrer) !== Str::lower($request->getHost())) {
            return ['source' => Str::limit(Str::lower(preg_replace('/^www\./', '', $referrer)), 95, ''), 'medium' => 'referral', 'campaign' => null];
        }

        if ($request->query('fbclid')) {
            return ['source' => 'facebook', 'medium' => 'social', 'campaign' => null];
        }

        return ['source' => 'terus', 'medium' => null, 'campaign' => null];
    }

    private function device(string $agent): string
    {
        return match (true) {
            (bool) preg_match('/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $agent) => 'tablet',
            (bool) preg_match('/Mobi|iPhone|Android/i', $agent) => 'telefon',
            default => 'komputer',
        };
    }
}
