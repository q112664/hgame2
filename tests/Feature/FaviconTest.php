<?php

use App\Models\Setting;
use App\Support\FaviconIcons;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

function fakeFaviconPng(): string
{
    $image = imagecreatetruecolor(64, 64);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagefilledellipse($image, 32, 32, 48, 48, imagecolorallocate($image, 240, 120, 180));

    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();

    return is_string($bytes) ? $bytes : '';
}

beforeEach(function () {
    Storage::fake('public');
    Storage::disk('public')->put('site/favicon/test.png', fakeFaviconPng());
    Setting::set('site_favicon_path', 'site/favicon/test.png');
});

test('the ico endpoint wraps the uploaded artwork', function () {
    $response = $this->get('/favicon.ico');

    $response->assertOk()
        ->assertHeader('content-type', 'image/x-icon');

    $body = $response->getContent();

    // ICO header: reserved 0, type 1, three frames.
    expect(substr($body, 0, 4))->toBe("\x00\x00\x01\x00")
        ->and(unpack('v', substr($body, 4, 2))[1])->toBe(3);
});

test('the png endpoints render the requested size', function () {
    foreach (['/favicon-32.png' => 32, '/favicon-192.png' => 192] as $url => $size) {
        $response = $this->get($url);

        $response->assertOk()->assertHeader('content-type', 'image/png');

        $info = getimagesizefromstring($response->getContent());

        expect($info)->not->toBeFalse()
            ->and($info[0])->toBe($size)
            ->and($info[1])->toBe($size);
    }
});

test('the apple touch icon is 180 square and opaque', function () {
    $response = $this->get('/apple-touch-icon.png');

    $response->assertOk();

    $info = getimagesizefromstring($response->getContent());

    expect($info[0])->toBe(180)
        ->and($info[1])->toBe(180);

    // iOS paints transparency black, so the generated icon has to be fully opaque.
    $image = imagecreatefromstring($response->getContent());
    $corner = imagecolorat($image, 1, 1);

    expect(($corner >> 24) & 0x7F)->toBe(0);
});

test('icons revalidate with an etag', function () {
    $first = $this->get('/favicon-192.png');
    $etag = $first->headers->get('ETag');

    expect($etag)->not->toBeNull();

    $this->withHeaders(['If-None-Match' => $etag])
        ->get('/favicon-192.png')
        ->assertStatus(304);
});

test('the endpoints 404 when no favicon is configured', function () {
    Setting::set('site_favicon_path', '');

    $this->get('/favicon.ico')->assertNotFound();
    $this->get('/apple-touch-icon.png')->assertNotFound();
});

test('the publish command writes the files nginx serves', function () {
    $directory = sys_get_temp_dir().'/hgame-icons-'.uniqid();
    mkdir($directory);

    $written = app(FaviconIcons::class)->publish($directory);

    // nginx answers /favicon.ico itself; the PNG paths stay virtual on purpose.
    expect($written)->toHaveCount(1)
        ->and(is_file($directory.'/favicon.ico'))->toBeTrue()
        ->and(is_file($directory.'/favicon-192.png'))->toBeFalse();

    $ico = file_get_contents($directory.'/favicon.ico');
    expect(substr($ico, 0, 4))->toBe("\x00\x00\x01\x00");

    array_map('unlink', glob($directory.'/*') ?: []);
    rmdir($directory);
});

test('saving website settings republishes the icons', function () {
    $page = app(Filesystem::class)->get(app_path('Filament/Pages/ManageSiteSettings.php'));

    expect($page)
        ->toContain('publishQuietly()')
        ->toContain('FaviconIcons');
});
