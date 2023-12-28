<?php

it('has landing page', function () {
    $response = $this->get('/');
    $response->assertOk();
});

it('has status page', function () {
    $response = $this->get('/status');
    $response->assertOk();
});

it('has feed manage page', function () {
    $response = $this->get('/feed/9ae5efe4-d9fc-40a9-b6a9-0cc36999cf37');
    $response->assertOk();
    $response->assertSeeText('Last checked');
    $response->assertSee('noindex');
});

it('has 404 response for invalid feed', function () {
    $response = $this->get('/feed/nope-not-a-real-feed');
    $response->assertStatus(404);
});

it('rejects non-feed URL', function () {
    $response = $this->post('/add', [
        'url' => 'https://google.com/',
        'email' => 'hi@example.foo',
    ]);
    $response->assertRedirect('/');
});

//it('accepts feed URL', function () {
//    $response = $this->post('/add', [
//        'url' => 'https://mattstein.com/rss.xml',
//        'email' => 'hi@example.foo',
//    ]);
//    $response->assertRedirectContains('/feed/');
//});
