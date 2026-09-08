<?php

namespace App\Http\Controllers;

use App\Support\IndexNow;
use Illuminate\Http\Response;

class IndexNowKeyController extends Controller
{
    public function __invoke(string $indexNowKey): Response
    {
        $expected = IndexNow::normalizedKey();

        if (
            ! IndexNow::enabled()
            || $expected === null
            || ! hash_equals($expected, $indexNowKey)
        ) {
            abort(404);
        }

        return response($expected, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
