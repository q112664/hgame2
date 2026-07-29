<?php

namespace App\Http\Controllers;

use App\Actions\Games\MarkFavoriteDownloadsSeen;
use App\NotificationTab;
use App\Support\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request, ?string $tab = null): Response
    {
        $activeTab = NotificationTab::tryFromRequest($tab);
        $user = $request->user();

        $query = $user->notifications()->latest();

        $types = $activeTab->types();

        if ($types !== null) {
            $query->whereIn('type', $types);
        }

        $notifications = $query
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification): array => AppNotification::present($notification));

        $tabs = collect(NotificationTab::cases())
            ->map(function (NotificationTab $case) use ($user): array {
                $tabQuery = $user->notifications();
                $tabTypes = $case->types();

                if ($tabTypes !== null) {
                    $tabQuery->whereIn('type', $tabTypes);
                }

                $href = $case === NotificationTab::All
                    ? route('notifications.index', absolute: false)
                    : route('notifications.index', ['tab' => $case->value], absolute: false);

                return [
                    'value' => $case->value,
                    'label' => $case->label(),
                    'href' => $href,
                    'count' => (clone $tabQuery)->count(),
                    'unreadCount' => (clone $tabQuery)->whereNull('read_at')->count(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('notifications/index', [
            'activeTab' => $activeTab->value,
            'tabs' => $tabs,
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(
        Request $request,
        string $notification,
        MarkFavoriteDownloadsSeen $markFavoriteDownloadsSeen,
    ): RedirectResponse {
        $record = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        if ($record->read_at === null) {
            $record->markAsRead();
        }

        $this->syncFavoriteSeen($request, $record, $markFavoriteDownloadsSeen);

        $url = is_string($record->data['url'] ?? null)
            ? $record->data['url']
            : null;

        if ($request->boolean('open') && filled($url)) {
            return redirect()->to($url);
        }

        return back();
    }

    public function markAllAsRead(
        Request $request,
        MarkFavoriteDownloadsSeen $markFavoriteDownloadsSeen,
    ): RedirectResponse {
        $activeTab = NotificationTab::tryFromRequest($request->string('tab')->toString());
        $user = $request->user();
        $query = $user->unreadNotifications();
        $types = $activeTab->types();

        if ($types !== null) {
            $query->whereIn('type', $types);
        }

        $unread = $query->get();

        foreach ($unread as $record) {
            $this->syncFavoriteSeen($request, $record, $markFavoriteDownloadsSeen);
        }

        $unread->markAsRead();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('All notifications marked as read.'),
        ]);

        return back();
    }

    public function clear(
        Request $request,
        MarkFavoriteDownloadsSeen $markFavoriteDownloadsSeen,
    ): RedirectResponse {
        $activeTab = NotificationTab::tryFromRequest($request->string('tab')->toString());
        $user = $request->user();
        $query = $user->notifications();
        $types = $activeTab->types();

        if ($types !== null) {
            $query->whereIn('type', $types);
        }

        $records = $query->get();

        foreach ($records as $record) {
            if ($record->read_at === null) {
                $this->syncFavoriteSeen($request, $record, $markFavoriteDownloadsSeen);
            }
        }

        $deleted = $records->count();
        $query->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $deleted === 0
                ? __('No notifications to clear.')
                : __('Notifications cleared.'),
        ]);

        return back();
    }

    private function syncFavoriteSeen(
        Request $request,
        DatabaseNotification $record,
        MarkFavoriteDownloadsSeen $markFavoriteDownloadsSeen,
    ): void {
        if ($record->type !== 'favorite.downloads_updated') {
            return;
        }

        $gameId = (int) ($record->data['game_id'] ?? 0);

        if ($gameId <= 0) {
            return;
        }

        $markFavoriteDownloadsSeen($request->user(), $gameId);
    }
}
