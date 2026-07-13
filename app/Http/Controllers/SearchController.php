<?php

namespace App\Http\Controllers;

use App\Actions\Games\SearchGames;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __invoke(Request $request, SearchGames $searchGames): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));

        return Inertia::render('search', [
            'query' => $query,
            'resources' => $searchGames($query),
        ]);
    }
}
