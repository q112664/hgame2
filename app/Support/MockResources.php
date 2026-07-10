<?php

namespace App\Support;

use Illuminate\Support\Collection;

class MockResources
{
    /**
     * @return Collection<int, array{
     *     id: string,
     *     title: string,
     *     thumbnail: string,
     *     category: string,
     *     platform: string,
     *     language: string,
     *     tags: list<string>,
     *     publishedAt: string,
     *     views: int,
     *     description: string,
     *     developer: string,
     *     releaseDate: string,
     *     fileSize: string,
     *     downloads: int
     * }>
     */
    public static function all(): Collection
    {
        return collect([
            [
                'id' => '5329',
                'title' => 'Amairo Chocolate 3',
                'thumbnail' => 'https://t.shionlib.com/game/972/image/6a6033e3-eaeb-4493-a0c5-00d9edb376df.webp',
                'category' => 'Visual Novel',
                'platform' => 'Windows',
                'language' => 'Chinese',
                'tags' => ['Romance', 'Slice of Life'],
                'publishedAt' => '2026-07-08',
                'views' => 12840,
                'description' => 'The third entry in the Amairo Chocolate series continues its warm slice-of-life romance. Follow everyday moments, soft character writing, and a cozy atmosphere built around chocolate, cafés, and gentle relationships.',
                'developer' => 'Amairo Soft',
                'releaseDate' => '2024-12-20',
                'fileSize' => '4.2 GB',
                'downloads' => 3820,
            ],
            [
                'id' => '64',
                'title' => 'Senren＊Banka',
                'thumbnail' => 'https://t.shionlib.com/game/708/image/0a0d7c75-f091-4505-a47d-7078a76721d6.webp',
                'category' => 'Visual Novel',
                'platform' => 'Windows',
                'language' => 'Chinese',
                'tags' => ['Romance', 'Japanese Setting'],
                'publishedAt' => '2026-07-07',
                'views' => 25610,
                'description' => 'A celebrated yuzu-soft classic set in a quiet Japanese town. Senren＊Banka blends traditional atmosphere, memorable heroines, and a heartfelt romance that remains one of the most recommended visual novels in the genre.',
                'developer' => 'Yuzu Soft',
                'releaseDate' => '2016-07-29',
                'fileSize' => '5.8 GB',
                'downloads' => 9120,
            ],
            [
                'id' => '5327',
                'title' => 'Tachibana Lemonade Capriccio',
                'thumbnail' => 'https://t.shionlib.com/game/962/image/9dede7a2-1b63-4d90-a0b5-70c4d9b5fd14.webp',
                'category' => 'Visual Novel',
                'platform' => 'Windows',
                'language' => 'Japanese',
                'tags' => ['Romance', 'School'],
                'publishedAt' => '2026-07-06',
                'views' => 9320,
                'description' => 'A bright school romance with citrus-sweet pacing and lively character interactions. Expect summer festivals, classroom banter, and a lighthearted story centered on unexpected connections.',
                'developer' => 'Studio Capriccio',
                'releaseDate' => '2025-08-15',
                'fileSize' => '3.1 GB',
                'downloads' => 2140,
            ],
            [
                'id' => '5326',
                'title' => 'Amairo Chocolate 2',
                'thumbnail' => 'https://t.shionlib.com/game/725/image/735a6d41-3c4e-4173-8efd-30355a00926f.webp',
                'category' => 'Visual Novel',
                'platform' => 'Windows',
                'language' => 'Chinese',
                'tags' => ['Romance', 'Slice of Life'],
                'publishedAt' => '2026-07-05',
                'views' => 18450,
                'description' => 'The second Amairo Chocolate title expands the cast and deepens everyday romance. Soft visuals, comforting music, and carefully written routes make it a favorite for fans of gentle storytelling.',
                'developer' => 'Amairo Soft',
                'releaseDate' => '2022-03-18',
                'fileSize' => '3.9 GB',
                'downloads' => 6410,
            ],
            [
                'id' => '4440',
                'title' => 'The Magical Girl and the Witch Trial',
                'thumbnail' => 'https://t.shionlib.com/game/1101/image/9a854eaa-24b2-4a0c-b41b-b0325c396e87.webp',
                'category' => 'Visual Novel',
                'platform' => 'Windows',
                'language' => 'Chinese',
                'tags' => ['Fantasy', 'Mystery'],
                'publishedAt' => '2026-07-04',
                'views' => 15200,
                'description' => 'A fantasy mystery that puts magical girls under scrutiny. Courtroom tension, shifting alliances, and layered character motives drive a story that balances spectacle with sharp dialogue.',
                'developer' => 'Witch Court Works',
                'releaseDate' => '2023-11-03',
                'fileSize' => '6.4 GB',
                'downloads' => 5280,
            ],
            [
                'id' => '5080',
                'title' => 'Café Stella and the Reaper’s Butterflies',
                'thumbnail' => 'https://t.shionlib.com/game/709/image/0b922056-fa93-4093-820a-1e65cce7a7ea.webp',
                'category' => 'Visual Novel',
                'platform' => 'Windows',
                'language' => 'Chinese',
                'tags' => ['Romance', 'Fantasy'],
                'publishedAt' => '2026-07-03',
                'views' => 22100,
                'description' => 'A café, a reaper, and butterflies that cross the boundary between life and death. This Yuzu Soft title mixes supernatural fantasy with intimate character drama and a memorable soundtrack.',
                'developer' => 'Yuzu Soft',
                'releaseDate' => '2019-12-20',
                'fileSize' => '7.2 GB',
                'downloads' => 8740,
            ],
            [
                'id' => '4030',
                'title' => 'A Reunion at First Sight',
                'thumbnail' => 'https://t.shionlib.com/game/1099/image/382791b5-d320-4980-8893-fa24f58f5ac0.webp',
                'category' => 'Visual Novel',
                'platform' => 'Windows',
                'language' => 'Chinese',
                'tags' => ['Romance', 'Pure Love'],
                'publishedAt' => '2026-07-02',
                'views' => 8760,
                'description' => 'A pure-love story about meeting again as if for the first time. Quiet scenes, emotional restraint, and carefully paced revelations make this a strong pick for romance-focused readers.',
                'developer' => 'First Sight Studio',
                'releaseDate' => '2024-05-10',
                'fileSize' => '2.8 GB',
                'downloads' => 1960,
            ],
            [
                'id' => '5323',
                'title' => 'Midori no Umi',
                'thumbnail' => 'https://t.shionlib.com/game/500/image/e55269d4-28ac-4c10-953c-c2fb918e34ba.webp',
                'category' => 'Visual Novel',
                'platform' => 'Windows',
                'language' => 'Japanese',
                'tags' => ['Romance', 'Ensemble'],
                'publishedAt' => '2026-07-01',
                'views' => 11420,
                'description' => 'An ensemble romance set against a green coastal landscape. Multiple perspectives and overlapping relationships create a reflective narrative about distance, memory, and belonging.',
                'developer' => 'Midori Works',
                'releaseDate' => '2021-09-24',
                'fileSize' => '4.6 GB',
                'downloads' => 3050,
            ],
            [
                'id' => '5301',
                'title' => 'Amairo Chocolate 2+',
                'thumbnail' => 'https://t.shionlib.com/game/726/image/d59c2fd4-214d-4b99-b773-81473ecbe66b.webp',
                'category' => 'Visual Novel',
                'platform' => 'Windows',
                'language' => 'Chinese',
                'tags' => ['Romance', 'FanDisc'],
                'publishedAt' => '2026-06-30',
                'views' => 7650,
                'description' => 'A fandisc companion to Amairo Chocolate 2 with after-stories, extra scenes, and lighter side content for fans who want more time with the cast.',
                'developer' => 'Amairo Soft',
                'releaseDate' => '2023-01-27',
                'fileSize' => '2.1 GB',
                'downloads' => 2480,
            ],
            [
                'id' => '1427',
                'title' => 'Rakuen',
                'thumbnail' => 'https://t.shionlib.com/game/528/image/9736f2db-93e5-4b53-ad25-0fbc073bbb49.webp',
                'category' => 'Visual Novel',
                'platform' => 'Windows',
                'language' => 'Chinese',
                'tags' => ['Romance', 'School'],
                'publishedAt' => '2026-06-29',
                'views' => 14380,
                'description' => 'A school-life romance about finding a personal paradise in ordinary days. Clean presentation and sincere character writing keep the focus on relationships rather than spectacle.',
                'developer' => 'Rakuen Project',
                'releaseDate' => '2020-06-12',
                'fileSize' => '3.4 GB',
                'downloads' => 4170,
            ],
            [
                'id' => '5321',
                'title' => 'Haikyo Shoujo Gaiden',
                'thumbnail' => 'https://t.shionlib.com/game/1971/image/9612be21-22c8-4189-9bbb-f7f5c66d0f30.webp',
                'category' => 'Visual Novel',
                'platform' => 'Windows',
                'language' => 'Chinese',
                'tags' => ['Romance', 'Side Story'],
                'publishedAt' => '2026-06-28',
                'views' => 6890,
                'description' => 'A side story expanding the Haikyo Shoujo world with additional character focus and quieter narrative beats. Best enjoyed after the main title for full context.',
                'developer' => 'Haikyo Works',
                'releaseDate' => '2025-02-14',
                'fileSize' => '1.9 GB',
                'downloads' => 1630,
            ],
            [
                'id' => '5320',
                'title' => 'Koiin Tenshi!!',
                'thumbnail' => 'https://t.shionlib.com/game/899/image/4b8f7537-d91f-4edd-8bf3-0a1f069c57eb.webp',
                'category' => 'Visual Novel',
                'platform' => 'Android',
                'language' => 'Chinese',
                'tags' => ['Romance', 'Comedy'],
                'publishedAt' => '2026-06-27',
                'views' => 5420,
                'description' => 'A lively romance-comedy about a heaven-sent crush and the chaos that follows. Fast dialogue, playful scenarios, and mobile-friendly packaging make it an easy pick for lighter reading.',
                'developer' => 'Tenshi Games',
                'releaseDate' => '2024-09-01',
                'fileSize' => '1.2 GB',
                'downloads' => 2890,
            ],
        ]);
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     thumbnail: string,
     *     category: string,
     *     platform: string,
     *     language: string,
     *     tags: list<string>,
     *     publishedAt: string,
     *     views: int,
     *     description: string,
     *     developer: string,
     *     releaseDate: string,
     *     fileSize: string,
     *     downloads: int,
     *     screenshots: list<string>,
     *     downloadLinks: list<array{
     *         label: string,
     *         url: string,
     *         note: string|null,
     *         description: string,
     *         platform: string,
     *         language: string,
     *         fileSize: string,
     *         publishedAt: string
     *     }>
     * }|null
     */
    public static function find(string $id): ?array
    {
        $resource = self::all()->firstWhere('id', $id);

        if ($resource === null) {
            return null;
        }

        return [
            ...$resource,
            'screenshots' => self::screenshotsFor($resource),
            'downloadLinks' => self::downloadLinksFor($resource),
        ];
    }

    /**
     * @param  array{thumbnail: string}  $resource
     * @return list<string>
     */
    private static function screenshotsFor(array $resource): array
    {
        $extra = self::all()
            ->pluck('thumbnail')
            ->filter(fn (string $url): bool => $url !== $resource['thumbnail'])
            ->take(3)
            ->values()
            ->all();

        return array_values(array_unique([
            $resource['thumbnail'],
            ...$extra,
        ]));
    }

    /**
     * @param  array{
     *     platform: string,
     *     language: string,
     *     fileSize: string,
     *     publishedAt: string,
     *     title: string
     * }  $resource
     * @return list<array{
     *     label: string,
     *     url: string,
     *     note: string|null,
     *     description: string,
     *     platform: string,
     *     language: string,
     *     fileSize: string,
     *     publishedAt: string
     * }>
     */
    private static function downloadLinksFor(array $resource): array
    {
        return [
            [
                'label' => 'Baidu Netdisk',
                'url' => '#',
                'note' => 'Extract code: hgame',
                'description' => "Full package of {$resource['title']} with Chinese translation and common patches included.",
                'platform' => $resource['platform'],
                'language' => $resource['language'],
                'fileSize' => $resource['fileSize'],
                'publishedAt' => $resource['publishedAt'],
            ],
            [
                'label' => 'Mega',
                'url' => '#',
                'note' => null,
                'description' => "Mirror upload for {$resource['title']}. Suitable for international downloads with stable transfer speeds.",
                'platform' => $resource['platform'],
                'language' => $resource['language'],
                'fileSize' => $resource['fileSize'],
                'publishedAt' => $resource['publishedAt'],
            ],
            [
                'label' => 'Direct Download',
                'url' => '#',
                'note' => null,
                'description' => "Direct file host for {$resource['title']}. No third-party client required.",
                'platform' => $resource['platform'],
                'language' => $resource['language'],
                'fileSize' => $resource['fileSize'],
                'publishedAt' => $resource['publishedAt'],
            ],
        ];
    }

    /**
     * @return Collection<int, array{
     *     id: string,
     *     title: string,
     *     thumbnail: string,
     *     category: string,
     *     platform: string,
     *     language: string,
     *     tags: list<string>,
     *     publishedAt: string,
     *     views: int
     * }>
     */
    public static function cards(): Collection
    {
        return self::all()->map(fn (array $resource): array => [
            'id' => $resource['id'],
            'title' => $resource['title'],
            'thumbnail' => $resource['thumbnail'],
            'category' => $resource['category'],
            'platform' => $resource['platform'],
            'language' => $resource['language'],
            'tags' => $resource['tags'],
            'publishedAt' => $resource['publishedAt'],
            'views' => $resource['views'],
        ])->values();
    }
}
