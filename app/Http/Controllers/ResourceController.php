<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Games\GameResource;
use App\Models\Game;
use App\Support\GamePresenter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    public function show(string $resource): RedirectResponse
    {
        $this->findResource($resource);

        return to_route('resources.details', ['resource' => $resource]);
    }

    public function details(string $resource): Response
    {
        return $this->renderResource($resource, 'details');
    }

    public function downloads(string $resource): Response
    {
        return $this->renderResource($resource, 'downloads');
    }

    public function screenshots(string $resource): Response
    {
        return $this->renderResource($resource, 'screenshots');
    }

    private function renderResource(string $resource, string $activeTab): Response
    {
        return Inertia::render('resources/show', [
            'activeTab' => $activeTab,
            'resource' => $this->findResource($resource),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function findResource(string $resource): array
    {
        $game = Game::query()
            ->published()
            ->where('slug', $resource)
            ->with([
                'category:id,name',
                'tags:id,name',
                'screenshots' => fn ($query) => $query->orderBy('sort_order'),
                'releases' => fn ($query) => $query
                    ->where('is_active', true)
                    ->whereHas('downloadLinks', fn ($links) => $links->where('is_active', true))
                    ->with([
                        'platforms:id,name,slug',
                        'languages:id,name',
                        'downloadLinks' => fn ($links) => $links
                            ->where('is_active', true)
                            ->orderBy('sort_order'),
                    ])
                    ->orderBy('sort_order'),
            ])
            ->firstOrFail();

        return [
            ...GamePresenter::detail($game),
            'adminEditUrl' => auth()->user()?->is_admin
                ? GameResource::getUrl('edit', ['record' => $game], panel: 'admin')
                : null,
        ];
    }
}
