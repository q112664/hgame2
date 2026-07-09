<?php

namespace App\Http\Controllers;

use App\Support\MockResources;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResourceController extends Controller
{
    public function show(Request $request, string $resource): Response
    {
        $item = MockResources::find($resource);

        if ($item === null) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('resources/show', [
            'resource' => $item,
            'related' => MockResources::cards()
                ->where('id', '!=', $item['id'])
                ->take(4)
                ->values(),
        ]);
    }
}
