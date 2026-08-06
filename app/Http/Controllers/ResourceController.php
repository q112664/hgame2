<?php

namespace App\Http\Controllers;

use App\Actions\Games\ListPublishedGames;
use App\Actions\Games\ListRelatedGames;
use App\Actions\Games\MarkFavoriteDownloadsSeen;
use App\Actions\Games\RecordGameView;
use App\Filament\Resources\Games\GameResource;
use App\Http\Requests\ListResourcesRequest;
use App\Models\Category;
use App\Models\Game;
use App\Models\GameComment;
use App\Models\Language;
use App\Models\Platform;
use App\Models\Setting;
use App\Models\Tag;
use App\Support\GamePresenter;
use App\Support\PageSeo;
use App\Support\TaxonomyDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    private const COMMENTS_PER_PAGE = 20;

    public function __construct(
        private RecordGameView $recordGameView,
        private ListRelatedGames $listRelatedGames,
    ) {}

    public function index(
        ListResourcesRequest $request,
        ListPublishedGames $listPublishedGames,
    ): Response|RedirectResponse {
        if ($redirect = $this->singleTaxonomyRedirect($request)) {
            return redirect()->to($redirect, 301);
        }

        return $this->renderCatalog(
            $request,
            $listPublishedGames,
            taxonomy: null,
        );
    }

    public function genre(
        ListResourcesRequest $request,
        Category $category,
        ListPublishedGames $listPublishedGames,
    ): Response {
        return $this->renderCatalog(
            $request,
            $listPublishedGames,
            taxonomy: [
                'type' => 'category',
                'name' => $category->name,
                'value' => $category->slug,
                'forcedFilters' => ['category' => $category->slug],
            ],
        );
    }

    public function platform(
        ListResourcesRequest $request,
        Platform $platform,
        ListPublishedGames $listPublishedGames,
    ): Response {
        return $this->renderCatalog(
            $request,
            $listPublishedGames,
            taxonomy: [
                'type' => 'platform',
                'name' => $platform->name,
                'value' => $platform->slug,
                'forcedFilters' => ['platform' => $platform->slug],
            ],
        );
    }

    public function language(
        ListResourcesRequest $request,
        Language $language,
        ListPublishedGames $listPublishedGames,
    ): Response {
        return $this->renderCatalog(
            $request,
            $listPublishedGames,
            taxonomy: [
                'type' => 'language',
                'name' => $language->name,
                'value' => $language->code,
                'forcedFilters' => ['language' => $language->code],
            ],
        );
    }

    public function tag(
        ListResourcesRequest $request,
        Tag $tag,
        ListPublishedGames $listPublishedGames,
    ): Response {
        return $this->renderCatalog(
            $request,
            $listPublishedGames,
            taxonomy: [
                'type' => 'tag',
                'name' => $tag->name,
                'value' => $tag->slug,
                'forcedFilters' => ['tags' => [$tag->slug]],
            ],
        );
    }

    public function tagsIndex(): Response
    {
        return Inertia::render('resources/tags', [
            'tags' => TaxonomyDirectory::tagsIndex(),
            'pageSeo' => PageSeo::resourcesTagsIndex(),
        ]);
    }

    public function random(): RedirectResponse
    {
        $game = Game::query()
            ->published()
            ->inRandomOrder()
            ->first(['id', 'slug']);

        $response = $game === null
            ? to_route('resources.index')
            : to_route('resources.details', $game);

        return $response->withHeaders([
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @param  array{
     *     type: 'category'|'platform'|'language'|'tag',
     *     name: string,
     *     value: string,
     *     forcedFilters: array<string, mixed>
     * }|null  $taxonomy
     */
    private function renderCatalog(
        ListResourcesRequest $request,
        ListPublishedGames $listPublishedGames,
        ?array $taxonomy,
    ): Response {
        $page = $request->catalogPage();
        $filters = $request->filters();

        if ($taxonomy !== null) {
            $filters = [
                ...$filters,
                ...$taxonomy['forcedFilters'],
            ];
        }

        $payload = $listPublishedGames($filters);

        /** @var LengthAwarePaginator<int, array<string, mixed>> $resources */
        $resources = $payload['resources'];

        // Empty catalogs still expose last_page = 1; only reject pages past the end.
        abort_if($page > $resources->lastPage(), 404);

        $heading = $taxonomy === null
            ? 'Hentai Games & Eroge Downloads'
            : PageSeo::taxonomyTitle($taxonomy['type'], $taxonomy['name']);

        $resultsHeading = $taxonomy === null
            ? 'All games'
            : match ($taxonomy['type']) {
                'category' => $taxonomy['name'].' games',
                'platform' => $taxonomy['name'].' games',
                'language' => $taxonomy['name'].' games',
                'tag' => 'Tagged '.$taxonomy['name'],
            };

        $pageSeo = $taxonomy === null
            ? PageSeo::resourcesIndex(
                page: $page,
                hasFilters: $request->hasSeoFilters(),
            )
            : PageSeo::resourcesTaxonomy(
                type: $taxonomy['type'],
                name: $taxonomy['name'],
                value: $taxonomy['value'],
                page: $page,
                isPure: $this->isPureTaxonomyFilters($filters, $taxonomy['type']),
            );

        return Inertia::render('resources/index', [
            ...$payload,
            'heading' => $heading,
            'resultsHeading' => $resultsHeading,
            'taxonomy' => $taxonomy === null ? null : [
                'type' => $taxonomy['type'],
                'name' => $taxonomy['name'],
                'value' => $taxonomy['value'],
            ],
            'pageSeo' => $pageSeo,
        ]);
    }

    /**
     * 301 single-dimension query filters to path-based taxonomy URLs.
     */
    private function singleTaxonomyRedirect(ListResourcesRequest $request): ?string
    {
        $filters = $request->filters();

        if ($filters['q'] !== '' || $filters['sort'] !== ListPublishedGames::SORT_LATEST) {
            return null;
        }

        $hasCategory = $filters['category'] !== null;
        $hasPlatform = $filters['platform'] !== null;
        $hasLanguage = $filters['language'] !== null;
        $tagCount = count($filters['tags']);

        $dimensions = ($hasCategory ? 1 : 0)
            + ($hasPlatform ? 1 : 0)
            + ($hasLanguage ? 1 : 0)
            + ($tagCount > 0 ? 1 : 0);

        if ($dimensions !== 1 || $tagCount > 1) {
            return null;
        }

        $page = $request->catalogPage();
        $query = $page > 1 ? ['page' => $page] : [];

        if ($hasCategory) {
            return route('resources.genre', [
                'category' => $filters['category'],
                ...$query,
            ]);
        }

        if ($hasPlatform) {
            return route('resources.platform', [
                'platform' => $filters['platform'],
                ...$query,
            ]);
        }

        if ($hasLanguage) {
            return route('resources.language', [
                'language' => $filters['language'],
                ...$query,
            ]);
        }

        return route('resources.tag', [
            'tag' => $filters['tags'][0],
            ...$query,
        ]);
    }

    /**
     * @param  array{q: string, category: string|null, platform: string|null, language: string|null, tags: list<string>, sort: string}  $filters
     * @param  'category'|'platform'|'language'|'tag'  $type
     */
    private function isPureTaxonomyFilters(array $filters, string $type): bool
    {
        if ($filters['q'] !== '' || $filters['sort'] !== ListPublishedGames::SORT_LATEST) {
            return false;
        }

        return match ($type) {
            'category' => $filters['category'] !== null
                && $filters['platform'] === null
                && $filters['language'] === null
                && $filters['tags'] === [],
            'platform' => $filters['platform'] !== null
                && $filters['category'] === null
                && $filters['language'] === null
                && $filters['tags'] === [],
            'language' => $filters['language'] !== null
                && $filters['category'] === null
                && $filters['platform'] === null
                && $filters['tags'] === [],
            'tag' => count($filters['tags']) === 1
                && $filters['category'] === null
                && $filters['platform'] === null
                && $filters['language'] === null,
        };
    }

    public function show(Game $resource): RedirectResponse
    {
        return to_route('resources.details', ['resource' => $resource]);
    }

    public function details(Request $request, Game $resource): Response
    {
        return $this->renderResource($request, $resource, 'details');
    }

    public function downloads(
        Request $request,
        Game $resource,
    ): Response {
        $game = $this->findGame(
            $resource,
            includeScreenshots: false,
            includeDownloadLinks: true,
            includeTags: false,
        );
        ($this->recordGameView)($request, $game);

        return Inertia::render('resources/show', [
            'activeTab' => 'downloads',
            'resourceNotice' => Setting::resourceNoticeHtml(),
            'comments' => [],
            'commentsCount' => $game->comments()->count(),
            'related' => [],
            'pageSeo' => PageSeo::forGame($game, 'downloads'),
            'resource' => $this->presentResource(
                $game,
                includeScreenshots: false,
                includeReleases: true,
                includeDescription: false,
                includeTags: false,
            ),
        ]);
    }

    public function markDownloadsSeen(
        Request $request,
        Game $resource,
        MarkFavoriteDownloadsSeen $markFavoriteDownloadsSeen,
    ): HttpResponse {
        $markFavoriteDownloadsSeen($request->user(), $resource->id);

        return response()->noContent();
    }

    public function screenshots(Request $request, Game $resource): Response
    {
        return $this->renderResource($request, $resource, 'screenshots');
    }

    public function comments(Request $request, Game $resource): Response
    {
        return $this->renderResource($request, $resource, 'comments');
    }

    private function renderResource(Request $request, Game $resource, string $activeTab): Response
    {
        $includeDetails = $activeTab === 'details';
        $game = $this->findGame(
            $resource,
            includeScreenshots: $activeTab === 'screenshots',
            includeDownloadLinks: $activeTab === 'downloads',
            includeTags: $includeDetails,
        );
        ($this->recordGameView)($request, $game);

        return Inertia::render('resources/show', [
            'activeTab' => $activeTab,
            'resourceNotice' => Setting::resourceNoticeHtml(),
            'comments' => $activeTab === 'comments'
                ? $this->presentComments($game, $request)
                : null,
            'commentsCount' => $game->comments()->count(),
            'related' => $includeDetails
                ? ($this->listRelatedGames)($game)
                : [],
            'pageSeo' => PageSeo::forGame($game, $activeTab),
            'resource' => $this->presentResource(
                $game,
                includeScreenshots: $activeTab === 'screenshots',
                includeReleases: $activeTab === 'downloads',
                includeDescription: $includeDetails,
                includeTags: $includeDetails,
            ),
        ]);
    }

    private function findGame(
        Game $resource,
        bool $includeScreenshots,
        bool $includeDownloadLinks,
        bool $includeTags,
    ): Game {
        $with = [
            'category:id,name,slug',
            'releases' => $includeDownloadLinks
                ? fn ($query) => $query->withDownloadDetails()
                : fn ($query) => $query->withCardSummary(),
        ];

        if ($includeTags) {
            $with['tags'] = fn ($query) => $query->select(['tags.id', 'name', 'slug']);
        }

        if ($includeScreenshots) {
            $with['screenshots'] = fn ($query) => $query->orderBy('sort_order');
        }

        $resource->load($with);

        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentResource(
        Game $game,
        bool $includeScreenshots,
        bool $includeReleases,
        bool $includeDescription,
        bool $includeTags,
    ): array {
        return [
            ...GamePresenter::detail(
                $game,
                includeScreenshots: $includeScreenshots,
                includeReleases: $includeReleases,
                includeDescription: $includeDescription,
                includeTags: $includeTags,
            ),
            'hasDownloads' => $game->releases->isNotEmpty(),
            'isFavorited' => auth()->user()
                ?->favoritedGames()
                ->where('games.id', $game->id)
                ->exists() ?? false,
            'adminEditUrl' => auth()->user()?->is_admin
                ? GameResource::getUrl('edit', ['record' => $game], panel: 'admin')
                : null,
        ];
    }

    /**
     * Nested threads: paginated top-level comments (newest first) with replies (oldest first).
     *
     * @return LengthAwarePaginator<int, non-empty-array<string, mixed>>
     */
    private function presentComments(Game $game, Request $request): LengthAwarePaginator
    {
        $user = auth()->user();
        $commentColumns = [
            'id',
            'user_id',
            'parent_id',
            'reply_to_user_id',
            'body',
            'created_at',
            'updated_at',
        ];

        $focusId = $request->integer('focus');
        $focusRootId = null;

        if ($focusId > 0) {
            $focusComment = $game->comments()
                ->whereKey($focusId)
                ->first(['id', 'parent_id']);

            $focusRootId = $focusComment === null
                ? null
                : (int) ($focusComment->parent_id ?? $focusComment->id);
        }

        $rootQuery = $game->comments()
            ->whereNull('parent_id')
            ->select($commentColumns)
            ->with(['user:id,name,avatar,is_admin', 'replyToUser:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $page = $this->commentPageForRoot($game, $focusRootId);
        $roots = $rootQuery
            ->paginate(self::COMMENTS_PER_PAGE, ['*'], 'page', $page);

        if ($roots->currentPage() > $roots->lastPage()) {
            $roots = $rootQuery->paginate(
                self::COMMENTS_PER_PAGE,
                ['*'],
                'page',
                $roots->lastPage(),
            );
        }

        $roots->appends($request->except('focus'));

        $rootIds = $roots->getCollection()->pluck('id');
        /** @var Collection<int, Collection<int, GameComment>> $repliesByParent */
        $repliesByParent = collect();

        if ($rootIds->isNotEmpty()) {
            $repliesByParent = $game->comments()
                ->whereIn('parent_id', $rootIds->all())
                ->select($commentColumns)
                ->with(['user:id,name,avatar,is_admin', 'replyToUser:id,name'])
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->groupBy('parent_id');
        }

        $transformedRoots = $roots->through(function (GameComment $root) use ($user, $repliesByParent): array {
            $replies = ($repliesByParent->get($root->id) ?? collect())
                ->map(fn (GameComment $reply): array => $this->presentCommentNode($reply, $user))
                ->values()
                ->all();

            return [
                ...$this->presentCommentNode($root, $user),
                'replies' => $replies,
            ];
        });

        return $transformedRoots;
    }

    private function commentPageForRoot(Game $game, ?int $rootId): ?int
    {
        if ($rootId === null) {
            return null;
        }

        $root = $game->comments()
            ->whereNull('parent_id')
            ->whereKey($rootId)
            ->first(['id', 'created_at']);

        if ($root === null) {
            return null;
        }

        $newerRootCount = $game->comments()
            ->whereNull('parent_id')
            ->where(function ($query) use ($root): void {
                $query
                    ->where('created_at', '>', $root->created_at)
                    ->orWhere(function ($query) use ($root): void {
                        $query
                            ->where('created_at', $root->created_at)
                            ->where('id', '>', $root->id);
                    });
            })
            ->count();

        return intdiv($newerRootCount, self::COMMENTS_PER_PAGE) + 1;
    }

    /** @return array<string, mixed> */
    private function presentCommentNode(GameComment $comment, mixed $user): array
    {
        $isMine = $user !== null && $user->id === $comment->user_id;
        $isEdited = $comment->updated_at !== null
            && $comment->created_at !== null
            && $comment->updated_at->gt($comment->created_at->copy()->addSecond());

        $replyTo = null;

        if ($comment->reply_to_user_id !== null && $comment->relationLoaded('replyToUser') && $comment->replyToUser) {
            $replyTo = [
                'id' => $comment->replyToUser->id,
                'name' => $comment->replyToUser->name,
            ];
        }

        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'createdAt' => $comment->created_at?->toIso8601String(),
            'updatedAt' => $comment->updated_at?->toIso8601String(),
            'isEdited' => $isEdited,
            'isMine' => $isMine,
            'canEdit' => $isMine,
            'canDelete' => $isMine || (bool) $user?->is_admin,
            'replyTo' => $replyTo,
            'user' => [
                'id' => $comment->user->id,
                'name' => $comment->user->name,
                'avatar' => $comment->user->avatar,
                'isAdmin' => (bool) $comment->user->is_admin,
            ],
        ];
    }
}
