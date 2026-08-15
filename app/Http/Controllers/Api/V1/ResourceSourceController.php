<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ResourceSources\UpsertResourceSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreResourceSourceRequest;
use App\Models\ResourceSource;
use App\Support\GameSource;
use Illuminate\Http\JsonResponse;

class ResourceSourceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => GameSource::known(),
        ]);
    }

    public function store(
        StoreResourceSourceRequest $request,
        UpsertResourceSource $upsertResourceSource,
    ): JsonResponse {
        $source = $upsertResourceSource($request->validated());

        return response()->json([
            'data' => $this->payload($source),
        ], 201);
    }

    /**
     * @return array{name: string, slug: string, favicon_url: string|null, host_hint: string|null, sort_order: int}
     */
    private function payload(ResourceSource $source): array
    {
        return [
            'name' => $source->name,
            'slug' => $source->slug,
            'favicon_url' => $source->iconUrl(),
            'host_hint' => $source->host_hint,
            'sort_order' => (int) $source->sort_order,
        ];
    }
}
