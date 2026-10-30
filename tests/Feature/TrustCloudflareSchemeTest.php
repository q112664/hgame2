<?php

use App\Http\Middleware\TrustCloudflareScheme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

test('a cloudflare https visitor is treated as a secure request', function () {
    config(['app.url' => 'https://eroga.me']);

    $request = Request::create(
        'http://eroga.me/livewire-abc/upload-file',
        'POST',
        server: [
            'HTTP_CF_RAY' => 'a527b31fe9da892f-LAX',
            'HTTP_CF_VISITOR' => '{"scheme":"https"}',
            'HTTP_X_FORWARDED_PROTO' => 'http',
        ],
    );

    (new TrustCloudflareScheme)->handle($request, fn () => response('ok'));

    expect($request->isSecure())->toBeTrue()
        ->and($request->root())->toBe('https://eroga.me')
        ->and($request->header('X-Forwarded-Proto'))->toBe('https');
});

test('requests are untouched while the app url is http', function () {
    config(['app.url' => 'http://hgame.test']);

    $request = Request::create('http://hgame.test/games', 'GET');

    (new TrustCloudflareScheme)->handle($request, fn () => response('ok'));

    expect($request->isSecure())->toBeFalse()
        ->and($request->root())->toBe('http://hgame.test');
});

test('a proxied http request is upgraded when the app url is https', function () {
    config(['app.url' => 'https://eroga.me']);

    $request = Request::create('http://eroga.me/games', 'GET');

    (new TrustCloudflareScheme)->handle($request, fn () => response('ok'));

    expect($request->isSecure())->toBeTrue()
        ->and($request->root())->toBe('https://eroga.me');
});

test('a plain http request stays http when the app url is http', function () {
    config(['app.url' => 'http://eroga.me']);

    $request = Request::create(
        'http://eroga.me/games',
        'GET',
        server: [
            'HTTP_CF_RAY' => 'abc',
            'HTTP_CF_VISITOR' => '{"scheme":"http"}',
        ],
    );

    (new TrustCloudflareScheme)->handle($request, fn () => response('ok'));

    expect($request->isSecure())->toBeFalse();
});

test('a signed url validates once the cloudflare scheme is trusted', function () {
    // Mirrors Setting::applySiteUrl() in production: absolute https urls.
    config(['app.url' => 'https://eroga.me']);
    URL::forceRootUrl('https://eroga.me');
    URL::forceScheme('https');

    $signed = URL::temporarySignedRoute('livewire.upload-file', now()->addMinute());
    $incoming = str_replace('https://eroga.me', 'http://eroga.me', $signed);

    $request = Request::create($incoming, 'POST', server: [
        'HTTP_CF_RAY' => 'a527b31fe9da892f-LAX',
        'HTTP_CF_VISITOR' => '{"scheme":"https"}',
        'HTTP_X_FORWARDED_PROTO' => 'http',
    ]);

    // Without the middleware the signature cannot match: this is the 401 upload bug.
    expect($request->hasValidSignature())->toBeFalse();

    (new TrustCloudflareScheme)->handle($request, fn () => response('ok'));

    expect($request->hasValidSignature())->toBeTrue();
});
