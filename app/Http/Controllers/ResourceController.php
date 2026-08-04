<?php

namespace App\Http\Controllers;

use App\Actions\Games\ListPublishedGames;
use App\Actions\Games\ListRelatedGames;
use App\Actions\Games\MarkFavoriteDownloadsSeen;
use App\Actions\Games\RecordGameView;
use App\Filament\Resources\Games\GameResource;
use App\Http\Requests\ListResourcesRequest;
use App\Models\Game;
use App\Models\GameComment;
use App\Models\Setting;
use App\Support\GamePresenter;
use App\Support\PageSeo;
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

    public function index(ListResourcesRequest $request, ListPublishedGames $listPublishedGames): Response
    {
        return Inertia::render('resources/index', [
            ...$listPublishedGames($request->filters()),
            'pageSeo' => PageSeo::resourcesIndex(
                page: $request->catalogPage(),
                hasFilters: $request->hasSeoFilters(),
            ),
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
            'category:id,name',
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
