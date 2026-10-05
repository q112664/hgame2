<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'comments_enabled')->delete();

        Cache::forget('settings.comments_enabled');

        DB::table('notifications')
            ->whereIn('type', [
                'comment.replied',
                'App\\Notifications\\CommentRepliedNotification',
            ])
            ->delete();
    }

    /**
     * Comment rows, rating aggregates, the comments setting, and reply
     * notifications are removed with the feature and cannot be restored.
     */
    public function down(): void
    {
        //
    }
};
