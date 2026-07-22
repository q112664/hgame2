<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $keys = [
        'media_disk',
        'aws_access_key_id',
        'aws_secret_access_key',
        'aws_default_region',
        'aws_bucket',
        'aws_url',
        'aws_endpoint',
        'aws_use_path_style_endpoint',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->whereIn('key', $this->keys)->delete();

        foreach ($this->keys as $key) {
            Cache::forget("settings.{$key}");
        }
    }

    public function down(): void
    {
        //
    }
};
