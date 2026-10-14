<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Public pages we want crawled, in rough order of importance.
     *
     * Feed management pages are intentionally absent: `/feed/{feed}` and
     * `/feed/{id}/confirm/{code}` belong to whoever added the feed, and are
     * already marked `noindex`.
     *
     * @var array<int, string>
     */
    protected array $pages = [
        '/',
        '/status',
        '/updates',
    ];

    public function __invoke(): Response
    {
        return response()
            ->view('sitemap', ['pages' => $this->pages])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
