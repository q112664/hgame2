<?php

namespace App\Http\Controllers;

use App\DocStatus;
use App\Models\Doc;
use App\Support\DocPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocController extends Controller
{
    public function index(Request $request): Response
    {
        $category = filled($request->query('category'))
            ? (string) $request->query('category')
            : null;

        $docs = Doc::query()
            ->published()
            ->when(
                $category !== null,
                fn ($query) => $query->where('category', $category),
            )
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (Doc $doc): array => DocPresenter::card($doc))
            ->values()
            ->all();

        $categories = Doc::query()
            ->published()
            ->orderBy('category')
            ->distinct()
            ->pluck('category')
            ->values()
            ->all();

        return Inertia::render('docs/index', [
            'docs' => $docs,
            'categories' => $categories,
            'filters' => [
                'category' => $category,
            ],
        ]);
    }

    public function show(Doc $doc): Response
    {
        abort_unless(
            $doc->status === DocStatus::Published
            && $doc->published_at !== null
            && $doc->published_at->lte(now()),
            404,
        );

        $related = Doc::query()
            ->published()
            ->where('category', $doc->category)
            ->whereKeyNot($doc->getKey())
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Doc $item): array => DocPresenter::card($item))
            ->values()
            ->all();

        return Inertia::render('docs/show', [
            'doc' => DocPresenter::detail($doc),
            'related' => $related,
        ]);
    }
}
