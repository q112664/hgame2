<?php

namespace Database\Seeders;

use App\DocStatus;
use App\Models\Doc;
use Illuminate\Database\Seeder;

class DocSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->articles() as $index => $article) {
            Doc::query()->updateOrCreate(
                ['slug' => $article['slug']],
                [
                    ...$article,
                    'status' => DocStatus::Published,
                    'sort_order' => $index,
                    'reading_minutes' => null,
                ],
            );
        }
    }

    /**
     * @return list<array{
     *     slug: string,
     *     title: string,
     *     excerpt: string,
     *     category: string,
     *     published_at: string,
     *     body: string
     * }>
     */
    private function articles(): array
    {
        return [
            [
                'slug' => 'getting-started',
                'title' => 'Getting started with hgame',
                'excerpt' => 'Learn how to browse resources, use filters, and download releases safely.',
                'category' => 'Guides',
                'published_at' => '2026-07-01 10:00:00',
                'body' => <<<'HTML'
<p>Welcome to hgame. This short guide walks through the core flows on the site so you can find and download visual novel resources quickly.</p>
<h2>Browse resources</h2>
<p>Open the <strong>Resources</strong> page to view the full catalog. Cards show cover art, category, platforms, language chips, version, publish date, and view count.</p>
<p>Use the home page for the latest published titles, or jump in with the Random menu item when you want a surprise pick.</p>
<h2>Filter and search</h2>
<p>Filters cover category, platform, language, and tags. Combine them to narrow results, then sort by newest or most viewed. Global search is available from the header (or press <code>Ctrl</code>/<code>⌘</code> + <code>K</code>).</p>
<ul>
<li>Category chips appear on each card</li>
<li>Platform icons use tooltips for full names</li>
<li>Tags can be managed from the filter dialog</li>
</ul>
<h2>Downloads</h2>
<p>On a resource page, open the <strong>Downloads</strong> tab to see release packages, file sizes, and external links. Always verify the host and keep your system protected when downloading third-party files.</p>
<blockquote><p>External download links are provided by publishers and may require an account on the host service.</p></blockquote>
<h2>Favorites</h2>
<p>Sign in to favorite titles. Favorites keep a shortcut list and can highlight when download packages are updated after you last viewed them.</p>
HTML,
            ],
            [
                'slug' => 'account-and-security',
                'title' => 'Account and security',
                'excerpt' => 'How registration, profile settings, and two-factor authentication work on hgame.',
                'category' => 'Account',
                'published_at' => '2026-07-03 10:00:00',
                'body' => <<<'HTML'
<p>Accounts unlock favorites and profile customization. Security options are available from Settings after you sign in.</p>
<h2>Create an account</h2>
<p>Use Sign up from the header to register with email and password. After registration you can sign in, manage favorites, and update your profile.</p>
<h2>Profile and avatar</h2>
<p>Settings lets you change your display name and avatar. Avatars are stored on the active media disk configured by the site administrator.</p>
<h2>Two-factor authentication</h2>
<p>Enable 2FA from security settings for stronger account protection. Recovery codes should be stored offline in case you lose access to your authenticator app.</p>
HTML,
            ],
            [
                'slug' => 'download-etiquette',
                'title' => 'Download etiquette and safety',
                'excerpt' => 'Best practices when using external download hosts and handling large game packages.',
                'category' => 'Guides',
                'published_at' => '2026-07-05 10:00:00',
                'body' => <<<'HTML'
<p>Resources often point to external storage providers. Treat every download as untrusted software until you have verified it yourself.</p>
<h2>Choose trusted links</h2>
<p>Prefer links labeled clearly by the publisher. Avoid mirror URLs that look altered or that ask for unexpected permissions. When multiple hosts are listed, pick the one you already trust.</p>
<h2>File sizes and storage</h2>
<p>Visual novel packages can be several gigabytes. Confirm free disk space before starting a multi-part archive. Keep incomplete downloads until extraction succeeds.</p>
<ol>
<li>Check the release file size on the Downloads tab</li>
<li>Download all parts if a multi-volume archive is used</li>
<li>Extract with a modern archiver and scan the result</li>
</ol>
<h2>Report issues</h2>
<p>If a link is dead, malware-flagged, or mislabeled, contact the site administrators so the listing can be reviewed. Do not redistribute broken or malicious packages.</p>
HTML,
            ],
            [
                'slug' => 'publishing-resources',
                'title' => 'Publishing resources (admins)',
                'excerpt' => 'Overview of how administrators create games, covers, screenshots, and download releases.',
                'category' => 'Admin',
                'published_at' => '2026-07-08 10:00:00',
                'body' => <<<'HTML'
<p>This document describes the admin publishing flow at a high level. Only administrators can access these tools.</p>
<h2>Admin panel</h2>
<p>Games are managed under the Filament admin panel. Each game has details, cover media, screenshots, tags, and one or more download releases.</p>
<h2>Covers and thumbnails</h2>
<p>Upload a cover image when creating or editing a game. The site generates a smaller card thumbnail automatically so list pages stay fast.</p>
<p>If thumbnails need rebuilding after a size policy change, use <strong>Object storage → Regenerate cover thumbnails</strong> in Settings.</p>
<h2>Releases and links</h2>
<p>Releases attach platforms, languages, version strings, file size labels, and download URLs. Keep version labels consistent so users can spot updates easily.</p>
<h2>API publish</h2>
<p>Administrators can also publish via the authenticated game publish API using a Sanctum token. See the project API notes for the payload shape.</p>
HTML,
            ],
            [
                'slug' => 'faq',
                'title' => 'Frequently asked questions',
                'excerpt' => 'Quick answers about missing downloads, languages, and account recovery.',
                'category' => 'FAQ',
                'published_at' => '2026-07-10 10:00:00',
                'body' => <<<'HTML'
<h2>Why is there no download?</h2>
<p>Some listings are metadata-only while a release is prepared, or the package may have been removed by the host. Check again later or review older release entries on the Downloads tab.</p>
<h2>What do language labels mean?</h2>
<p>Language chips on cards and detail pages refer to the languages included in a release package, not necessarily the original development language.</p>
<h2>I lost account access</h2>
<p>Use Forgot password on the sign-in dialog. If two-factor recovery codes are gone and email recovery fails, contact an administrator with proof of ownership.</p>
HTML,
            ],
        ];
    }
}
