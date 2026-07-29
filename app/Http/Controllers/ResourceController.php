<?php

namespace App\Http\Controllers;

use App\Actions\Games\ListPublishedGames;
use App\Actions\Games\MarkFavoriteDownloadsSeen;
use App\Actions\Games\RecordGameView;
use App\Filament\Resources\Games\GameResource;
use App\Http\Requests\ListResourcesRequest;
use App\Models\Game;
use App\Models\GameComment;
use App\Models\Setting;
use App\Support\GamePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    public function __construct(
        private RecordGameView $recordGameView,
    ) {}

    public function index(ListResourcesRequest $request, ListPublishedGames $listPublishedGames): Response
    {
        return Inertia::render('resources/index', $listPublishedGames($request->filters()));
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
                ? $this->presentComments($game)
                : [],
            'commentsCount' => $game->comments()->count(),
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
     * Nested threads: top-level comments (newest first) with one-level replies (oldest first).
     *
     * @return list<array{
     *     id: int,
     *     body: string,
     *     createdAt: string|null,
     *     updatedAt: string|null,
     *     isEdited: bool,
     *     isMine: bool,
     *     canEdit: bool,
     *     canDelete: bool,
     *     replyTo: array{id: int, name: string}|null,
     *     user: array{id: int, name: string, avatar: string|null},
     *     replies: list<array{
     *         id: int,
     *         body: string,
     *         createdAt: string|null,
     *         updatedAt: string|null,
     *         isEdited: bool,
     *         isMine: bool,
     *         canEdit: bool,
     *         canDelete: bool,
     *         replyTo: array{id: int, name: string}|null,
     *         user: array{id: int, name: string, avatar: string|null}
     *     }>
     * }>
     */
    private function presentComments(Game $game): array
    {
        $user = auth()->user();

        $comments = $game->comments()
            ->select([
                'id',
                'user_id',
                'parent_id',
                'reply_to_user_id',
                'body',
                'created_at',
                'updated_at',
            ])
            ->with(['user:id,name,avatar', 'replyToUser:id,name'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        /** @var Collection<int, GameComment> $roots */
        $roots = $comments->whereNull('parent_id')->reverse()->values();
        $repliesByParent = $comments->whereNotNull('parent_id')->groupBy('parent_id');

        return array_values($roots
            ->map(function (GameComment $root) use ($user, $repliesByParent): array {
                $replies = array_values(($repliesByParent->get($root->id) ?? collect())
                    ->values()
                    ->map(fn (GameComment $reply): array => $this->presentCommentNode($reply, $user))
                    ->all());

                return [
                    ...$this->presentCommentNode($root, $user),
                    'replies' => $replies,
                ];
            })
            ->values()
            ->all());
    }

    /**
     * @return array{
     *     id: int,
     *     body: string,
     *     createdAt: string|null,
     *     updatedAt: string|null,
     *     isEdited: bool,
     *     isMine: bool,
     *     canEdit: bool,
     *     canDelete: bool,
     *     replyTo: array{id: int, name: string}|null,
     *     user: array{id: int, name: string, avatar: string|null}
     * }
     */
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
            ],
        ];
    }
}
