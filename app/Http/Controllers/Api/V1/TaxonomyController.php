<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Language;
use App\Models\Platform;
use App\Support\GameSource;
use Illuminate\Http\JsonResponse;

class TaxonomyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'categories' => Category::query()
                    ->orderBy('name')
                    ->get(['name', 'slug'])
                    ->map(fn (Category $category): array => [
                        'name' => $category->name,
                        'slug' => $category->slug,
                    ])
                    ->values()
                    ->all(),
                'platforms' => Platform::query()
                    ->orderBy('name')
                    ->get(['name', 'slug'])
                    ->map(fn (Platform $platform): array => [
                        'name' => $platform->name,
                        'slug' => $platform->slug,
                    ])
                    ->values()
                    ->all(),
                'languages' => Language::query()
                    ->orderBy('name')
                    ->get(['name', 'code'])
                    ->map(fn (Language $language): array => [
                        'name' => $language->name,
                        'code' => $language->code,
                    ])
                    ->values()
                    ->all(),
                'sources' => GameSource::known(),
            ],
        ]);
    }
}
