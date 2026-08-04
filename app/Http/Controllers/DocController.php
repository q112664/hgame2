<?php

namespace App\Http\Controllers;

use App\DocStatus;
use App\Models\Doc;
use App\Support\DocPresenter;
use App\Support\PageSeo;
use Inertia\Inertia;
use Inertia\Response;

class DocController extends Controller
{
    public function index(): Response
    {
        $docs = Doc::query()
            ->published()
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (Doc $doc): array => DocPresenter::card($doc))
            ->values()
            ->all();

        return Inertia::render('docs/index', [
            'docs' => $docs,
            'pageSeo' => PageSeo::docsIndex(),
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

        return Inertia::render('docs/show', [
            'doc' => DocPresenter::detail($doc),
            'pageSeo' => PageSeo::forDoc($doc),
        ]);
    }
}
