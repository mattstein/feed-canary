<?php

use App\Models\Feed;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| Sitemap
|--------------------------------------------------------------------------
|
| The sitemap advertises the handful of public pages and nothing else.
| Feed management URLs are private to whoever added the feed, so they
| must never show up here.
|
*/

/**
 * Parse the `<loc>` values out of a sitemap response.
 *
 * @return array<int, string>
 */
function sitemapLocations(TestResponse $response): array
{
    $sitemap = simplexml_load_string((string) $response->getContent());

    if (! $sitemap instanceof SimpleXMLElement) {
        throw new RuntimeException('The sitemap isn’t valid XML.');
    }

    $sitemap->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');

    $urls = $sitemap->xpath('//s:url');

    return array_map(fn ($url): string => (string) $url->loc, is_array($urls) ? $urls : []);
}

it('serves a valid sitemap of the public pages', function () {
    $response = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    expect(sitemapLocations($response))->toBe([
        url('/'),
        url('/status'),
        url('/updates'),
    ]);
});

it('leaves feed management pages out of the sitemap', function () {
    $feed = Feed::factory()->unconfirmed()->create();

    $content = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($content)->not->toContain('/feed/');
    expect($content)->not->toContain((string) $feed->confirmation_code);

    $feed->delete();
});

it('only advertises pages that actually respond', function () {
    // Don’t bother the validators when rendering /status
    Http::fake([
        'https://validator.w3.org/feed' => Http::response(),
        'https://feedvalidator.org' => Http::response(),
    ]);

    foreach (sitemapLocations($this->get('/sitemap.xml')) as $location) {
        $this->get($location)->assertOk();
    }
});

it('points robots.txt at the sitemap', function () {
    expect(file_get_contents(public_path('robots.txt')))
        ->toContain('Sitemap: https://feedcanary.com/sitemap.xml');
});
