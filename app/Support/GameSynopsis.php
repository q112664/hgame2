<?php

namespace App\Support;

/**
 * English-only SERP hook from a game's original HTML description.
 */
final class GameSynopsis
{
    private const MIN_LENGTH = 24;

    private const PRE_STORY_MIN_LENGTH = 40;

    private const HARD_MAX = 220;

    /**
     * @var list<string>
     */
    private const HEADINGS = [
        'please read before buying',
        "heroine's body development",
        'heroines body development',
        'character introduction',
        'h-scene introduction',
        'h scene introduction',
        'system requirements',
        'recommended specs',
        'recommended spec',
        'content description',
        'content included',
        'game introduction',
        'game description',
        'game overview',
        'game contents',
        'game content',
        'game features',
        'game system',
        'game story',
        'game intro',
        'game info',
        'about this game',
        'about the game',
        'about the story',
        'about the demo',
        'product summary',
        'product info',
        'playing notes',
        'known issues',
        'review campaign',
        'update notice',
        'update history',
        'patch notes',
        'current version',
        'basic info',
        'how to play',
        'daily life',
        'h elements',
        'h scenes',
        'h scene',
        'trial version',
        'specifications',
        'gameplay',
        'features',
        'feature',
        'synopsis',
        'overview',
        'highlights',
        'controls',
        'characters',
        'character',
        'contents',
        'content',
        'credits',
        'setting',
        'prologue',
        'epilogue',
        'combat',
        'difficulty',
        'request',
        'notice',
        'notes',
        'warning',
        'disclaimer',
        'changelog',
        'version',
        'update',
        'patch',
        'append',
        'gallery',
        'install',
        'support',
        'introduction',
        'story',
        'plot',
        'system',
        'systems',
        'staff',
        'specs',
        'spec',
        'modes',
        'mode',
        'world',
        'background',
        'cast',
        'voice',
        'demo',
        'dlc',
        'faq',
        'endings',
        'ending',
    ];

    /**
     * @var list<string>
     */
    private const STORY_HEADINGS = [
        'about the story',
        'game story',
        'synopsis',
        'story',
        'plot',
    ];

    private static ?string $headingAlternation = null;

    private static ?string $storyAlternation = null;

    private static ?string $glueHeadingAlternation = null;

    private static ?string $multiWordHeadingAlternation = null;

    public static function hook(?string $html): string
    {
        return self::sentences($html, 1)[0] ?? '';
    }

    /**
     * @return list<string>
     */
    public static function sentences(?string $html, int $limit = 1): array
    {
        if ($limit < 1) {
            return [];
        }

        $preStory = [];
        $postStory = [];
        $all = [];
        $seenStory = false;
        $inOtherSection = false;

        foreach (self::lines($html) as $line) {
            $heading = self::matchLeadingHeading($line);

            if ($heading !== null) {
                if ($heading['isStory']) {
                    $seenStory = true;
                    $inOtherSection = false;
                } else {
                    $inOtherSection = true;
                }

                $line = $heading['rest'];

                if ($line === '') {
                    continue;
                }
            }

            if (self::shouldSkipLine($line)) {
                continue;
            }

            foreach (self::splitSentences($line) as $sentence) {
                $fromBullet = self::isBulletLine($sentence);
                $normalized = self::normalizeSentence($sentence);

                if ($normalized === null || self::shouldSkipSentence($normalized)) {
                    continue;
                }

                $all[] = $normalized;

                if (
                    ! $seenStory
                    && ! $inOtherSection
                    && ! $fromBullet
                    && self::isPreStoryHook($normalized)
                ) {
                    $preStory[] = $normalized;
                }

                if ($seenStory && ! $inOtherSection) {
                    $postStory[] = $normalized;
                }
            }
        }

        $pool = $all;

        if ($seenStory) {
            $pool = $preStory !== []
                ? array_merge(array_slice($preStory, 0, 1), $postStory)
                : $postStory;
        }

        return array_slice($pool, 0, $limit);
    }

    /**
     * @return list<string>
     */
    private static function lines(?string $html): array
    {
        if ($html === null || trim($html) === '') {
            return [];
        }

        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/<\s*br\s*\/?>/iu', "\n", $html) ?? $html;
        $html = preg_replace(
            '/<\/(?:p|div|h[1-6]|li|tr|td|th|blockquote|section|article|header|footer|figcaption|pre|dt|dd)\s*>/iu',
            "\n",
            $html,
        ) ?? $html;
        $html = preg_replace(
            '/<(?:p|div|h[1-6]|li|tr|td|th|blockquote|section|article|header|footer|figcaption|pre|dt|dd)\b[^>]*>/iu',
            "\n",
            $html,
        ) ?? $html;
        $html = strip_tags($html);
        $html = preg_replace('/[◆■●▼🔽★※]+/u', "\n", $html) ?? $html;

        $parts = preg_split('/\R+/u', $html) ?: [];
        $lines = [];

        foreach ($parts as $part) {
            $line = trim(preg_replace('/[ \t]+/u', ' ', $part) ?? $part);

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @return array{head: string, rest: string, isStory: bool}|null
     */
    private static function matchLeadingHeading(string $line): ?array
    {
        $stripped = self::stripLeadingBullet(trim($line));

        if ($stripped === '') {
            return null;
        }

        if (preg_match('/^[\[【]([^\]】]+)[\]】]\s*(.*)$/u', $stripped, $bracketed) === 1) {
            $inner = self::matchHeadingPhrase(trim((string) $bracketed[1]));

            if ($inner !== null) {
                return [
                    'head' => $inner,
                    'rest' => trim((string) $bracketed[2]),
                    'isStory' => self::isStoryHeadingPhrase($inner),
                ];
            }

            $stripped = trim(trim((string) $bracketed[1]).' '.trim((string) $bracketed[2]));
        }

        $stripped = self::stripWrappingDecorations($stripped);

        if ($stripped === '') {
            return null;
        }

        return self::matchParentheticalHeading($stripped)
            ?? self::matchGluedStoryHeading($stripped)
            ?? self::matchStoryHeadingWithTerminator($stripped)
            ?? self::matchDelimitedHeading($stripped)
            ?? self::matchMultiWordHeadingRest($stripped);
    }

    /**
     * @return array{head: string, rest: string, isStory: bool}|null
     */
    private static function matchParentheticalHeading(string $stripped): ?array
    {
        if (preg_match('/^(?<head>'.self::headingAlternation().')(?<paren>\s*\([^)]*\))(?<tail>[:：\]]?\s*.*)$/iu', $stripped, $matches) !== 1) {
            return null;
        }

        $head = mb_strtolower(trim((string) $matches['head']));
        $rest = trim((string) preg_replace('/^[:：\]]\s*/u', '', trim((string) $matches['tail'])));
        $rest = trim((string) preg_replace('/^[.!?。！？]+$/u', '', $rest));

        if ($rest !== '' && ! self::isStoryHeadingPhrase($head) && ! str_contains($head, ' ')) {
            return null;
        }

        return [
            'head' => $head,
            'rest' => $rest,
            'isStory' => self::isStoryHeadingPhrase($head),
        ];
    }

    /**
     * @return array{head: string, rest: string, isStory: bool}|null
     */
    private static function matchGluedStoryHeading(string $stripped): ?array
    {
        if (preg_match('/^(?<head>'.self::glueHeadingAlternation().')(?=\p{Lu})(?<rest>.+)$/iu', $stripped, $matches) !== 1) {
            return null;
        }

        return self::headingMatch((string) $matches['head'], (string) $matches['rest']);
    }

    /**
     * @return array{head: string, rest: string, isStory: bool}|null
     */
    private static function matchStoryHeadingWithTerminator(string $stripped): ?array
    {
        if (preg_match('/^(?<head>'.self::glueHeadingAlternation().')\s*[.!?。！？]+\s+(?<rest>.+)$/iu', $stripped, $matches) !== 1) {
            return null;
        }

        return self::headingMatch((string) $matches['head'], (string) $matches['rest']);
    }

    /**
     * @return array{head: string, rest: string, isStory: bool}|null
     */
    private static function matchDelimitedHeading(string $stripped): ?array
    {
        if (preg_match('/^(?<head>'.self::headingAlternation().')(?:\s*[:：]\s*|\s*\]\s*|(?:\s*[.!?。！？]*)\s*$)(?<rest>.*)$/iu', $stripped, $matches) !== 1) {
            return null;
        }

        return self::headingMatch((string) $matches['head'], (string) $matches['rest']);
    }

    /**
     * @return array{head: string, rest: string, isStory: bool}|null
     */
    private static function matchMultiWordHeadingRest(string $stripped): ?array
    {
        $multi = self::multiWordHeadingAlternation();

        if ($multi === '') {
            return null;
        }

        if (preg_match('/^(?<head>'.$multi.')\s+(?=\p{Lu}\p{L}{2,})(?<rest>.+)$/iu', $stripped, $matches) !== 1) {
            return null;
        }

        return self::headingMatch((string) $matches['head'], (string) $matches['rest']);
    }

    /**
     * @return array{head: string, rest: string, isStory: bool}
     */
    private static function headingMatch(string $head, string $rest): array
    {
        $head = mb_strtolower(trim($head));

        return [
            'head' => $head,
            'rest' => trim($rest),
            'isStory' => self::isStoryHeadingPhrase($head),
        ];
    }

    private static function matchHeadingPhrase(string $phrase): ?string
    {
        $phrase = self::stripWrappingDecorations($phrase);

        if ($phrase === '') {
            return null;
        }

        if (preg_match('/^(?:'.self::headingAlternation().')(?:\s*\([^)]*\))?\s*$/iu', $phrase, $matches) !== 1) {
            return null;
        }

        return mb_strtolower(trim((string) $matches[0]));
    }

    private static function isStoryHeadingPhrase(string $phrase): bool
    {
        return preg_match('/^(?:'.self::storyAlternation().')(?:\s*\([^)]*\))?\s*$/iu', $phrase) === 1;
    }

    private static function stripWrappingDecorations(string $line): string
    {
        $line = trim($line);
        $line = preg_replace('/^[\s■◆●★※*▼🔽↓]+/u', '', $line) ?? $line;
        $line = preg_replace('/[\s■◆●★※*▼🔽↓]+$/u', '', $line) ?? $line;

        return trim($line);
    }

    private static function isBulletLine(string $text): bool
    {
        return preg_match('/^[-–—*・•＊]\s*/u', trim($text)) === 1;
    }

    private static function stripLeadingBullet(string $text): string
    {
        return trim((string) preg_replace('/^[-–—*・•＊]\s*/u', '', trim($text)));
    }

    private static function headingAlternation(): string
    {
        if (self::$headingAlternation !== null) {
            return self::$headingAlternation;
        }

        $headings = self::HEADINGS;
        usort($headings, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        self::$headingAlternation = implode('|', array_map(
            static fn (string $heading): string => preg_quote($heading, '/'),
            $headings,
        ));

        return self::$headingAlternation;
    }

    private static function storyAlternation(): string
    {
        if (self::$storyAlternation !== null) {
            return self::$storyAlternation;
        }

        $headings = self::STORY_HEADINGS;
        usort($headings, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        self::$storyAlternation = implode('|', array_map(
            static fn (string $heading): string => preg_quote($heading, '/'),
            $headings,
        ));

        return self::$storyAlternation;
    }

    private static function glueHeadingAlternation(): string
    {
        if (self::$glueHeadingAlternation !== null) {
            return self::$glueHeadingAlternation;
        }

        $headings = self::STORY_HEADINGS;
        usort($headings, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        self::$glueHeadingAlternation = implode('|', array_map(
            static fn (string $heading): string => preg_quote($heading, '/'),
            $headings,
        ));

        return self::$glueHeadingAlternation;
    }

    private static function multiWordHeadingAlternation(): string
    {
        if (self::$multiWordHeadingAlternation !== null) {
            return self::$multiWordHeadingAlternation;
        }

        $headings = array_values(array_filter(
            self::HEADINGS,
            static fn (string $heading): bool => str_contains($heading, ' '),
        ));
        usort($headings, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        self::$multiWordHeadingAlternation = implode('|', array_map(
            static fn (string $heading): string => preg_quote($heading, '/'),
            $headings,
        ));

        return self::$multiWordHeadingAlternation;
    }

    /**
     * @return list<string>
     */
    private static function splitSentences(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $parts = preg_split('/(?<=[.!?。！？])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($parts)) {
            return [$text];
        }

        $sentences = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part !== '') {
                $sentences[] = $part;
            }
        }

        return $sentences !== [] ? $sentences : [$text];
    }

    private static function normalizeSentence(string $sentence): ?string
    {
        $sentence = trim(preg_replace('/\s+/u', ' ', $sentence) ?? $sentence);
        $sentence = self::stripLeadingBullet($sentence);
        $sentence = self::stripWrappingDecorations($sentence);

        if (preg_match('/^【([^】]+)】\s*(.*)$/u', $sentence, $matches) === 1) {
            $inner = trim((string) $matches[1]);
            $rest = trim((string) $matches[2]);

            if (self::isBracketMarketing($inner)) {
                $sentence = $rest;
            } elseif ($rest === '') {
                $sentence = $inner;
            } else {
                $sentence = trim($inner.' '.$rest);
            }
        } elseif (str_starts_with($sentence, '【')) {
            $sentence = ltrim(mb_substr($sentence, 1));
        }

        $sentence = trim($sentence);

        $heading = self::matchLeadingHeading($sentence);

        if ($heading !== null) {
            $sentence = $heading['rest'];
        }

        $sentence = trim($sentence);

        return $sentence === '' ? null : $sentence;
    }

    private static function isBracketMarketing(string $inner): bool
    {
        return preg_match(
            '/(?:animated|animation|\bcgs?\b|\bdlc\b|update|ver(?:sion)?\s*\d|作品タイトル|overview|gameplay|features?|content included)/iu',
            $inner,
        ) === 1;
    }

    private static function shouldSkipLine(string $line): bool
    {
        $trimmed = trim($line);
        $stripped = self::stripWrappingDecorations($line);

        if ($stripped === '' || preg_match('/^[-–—*=~_]{3,}$/u', $stripped) === 1) {
            return true;
        }

        if (preg_match('/https?:\/\//iu', $line) === 1) {
            return true;
        }

        if (preg_match('/\bCV\s*:/iu', $line) === 1) {
            return true;
        }

        if (preg_match('/^《.+》$/u', $trimmed) === 1) {
            return true;
        }

        if (preg_match('/^(?:main heroine|not conquerable)\b/iu', $stripped) === 1) {
            return true;
        }

        if (preg_match('/^ver\.?\s*\d/iu', $stripped) === 1 && preg_match('/update notice/iu', $stripped) === 1) {
            return true;
        }

        if (preg_match('/^bug fixes\b/iu', $stripped) === 1) {
            return true;
        }

        if (preg_match('/see ci-en/iu', $stripped) === 1) {
            return true;
        }

        return false;
    }

    private static function shouldSkipSentence(string $sentence): bool
    {
        $stripped = trim((string) preg_replace('/[\s.!?。！？…♪]+$/u', '', $sentence));
        $length = mb_strlen($stripped);

        if ($length < self::MIN_LENGTH) {
            return true;
        }

        if ($length > self::HARD_MAX && ! self::hasNarrativeCue($sentence)) {
            return true;
        }

        if (self::isPrefixJunk($sentence) || self::isBonusCopy($sentence) || self::isFeatureSpeak($sentence)) {
            return true;
        }

        if ($length < self::PRE_STORY_MIN_LENGTH) {
            if (self::isGenreSlogan($stripped)) {
                return true;
            }

            if (preg_match('/[.!?。！？]/u', $sentence) !== 1 && ! self::hasNarrativeCue($sentence)) {
                return true;
            }
        }

        return preg_match('/\b(?:now on steam|over \d[\d,]*\s+downloads?)\b/iu', $sentence) === 1;
    }

    private static function isPreStoryHook(string $sentence): bool
    {
        if (mb_strlen($sentence) < self::PRE_STORY_MIN_LENGTH) {
            return false;
        }

        if (self::isBulletLine($sentence) || self::isFeatureSpeak($sentence)) {
            return false;
        }

        return self::hasNarrativeCue($sentence);
    }

    private static function hasNarrativeCue(string $sentence): bool
    {
        return preg_match(
            '/\b(?:you|your|we|he|she|they|him|his|her|their|i|protagonist|heroine|hero|one day|once|after|when|until|while|then|wakes?|lives?|meets?|finds?|enters?|becomes?|must|tries|tried|decides?|arrives?|summoned)\b|\breincarnat/iu',
            $sentence,
        ) === 1;
    }

    private static function isFeatureSpeak(string $sentence): bool
    {
        return preg_match(
            '/\b(?:fully (?:voiced|animated)|voice actress(?:es)?|h-?scenes? are voiced|base h-?cgs?|\bcgs?\b|full-color|pixel-art graphics|high-quality(?: pixel-art)?|live2d|spine|60\s*fps|blend flawlessly|play with mouse|lots to do|over \d+\s+(?:h[ -]?scenes?|maps|cgs?)|control every motion|your own input|seamless presentation)\b/iu',
            $sentence,
        ) === 1;
    }

    private static function isGenreSlogan(string $sentence): bool
    {
        return preg_match(
            '/^(?:an? |the )?(?:open-world |simple |erotic |lewd |adult |2d |3d |casual |collection )?(?:rpg|slg|adv|simulation game|clicker|action(?: game)?|mini-game)\b/iu',
            $sentence,
        ) === 1;
    }

    private static function isBonusCopy(string $sentence): bool
    {
        if (preg_match('/^(unlocked|locked|demo|trial|dlc|bonus|english|japanese|chinese)(\s+version)?$/iu', $sentence) === 1) {
            return true;
        }

        if (preg_match('/^(by purchasing|purchasing the main game|bonus content|digital art book|original soundtrack|bonuses can be)\b/iu', $sentence) === 1) {
            return true;
        }

        return preg_match('/(?:no additional charge|art book|original soundtrack|特典|おまけ)/iu', $sentence) === 1;
    }

    private static function isPrefixJunk(string $text): bool
    {
        $text = trim($text);

        if (preg_match('/https?:\/\//iu', $text) === 1) {
            return true;
        }

        if (preg_match(
            '/^(?:'.
            'english version is available|'.
            'the english version\b|'.
            'original \(japanese\)|'.
            '\*?please note\b|'.
            'please be sure to read\b|'.
            'viewer discretion|'.
            'no actual sexual|'.
            'contains no (?:actual )?sex|'.
            'current (?:version|playtime) is|'.
            'the current version is|'.
            'append patch|'.
            'made with (?:rpg maker|unity|unreal|action game tkool|tkool)|'.
            'this (?:work|game) was made with ["\']?(?:rpg maker|unity|unreal|action game tkool|tkool)|'.
            'please test the (?:demo|trial)|'.
            'please verify (?:it works|operation)|'.
            'always confirm the game runs|'.
            'always check the recommended|'.
            'depending on (?:the environment|your pc)|'.
            '(?:the |a )?web trial version|'.
            '(?:the )?browser version\b|'.
            'language support includes|'.
            'this product contains the following titles|'.
            'about the bug|'.
            'patch has been posted|'.
            'if the game does not start|'.
            'demo save data cannot be carried over'.
            ')/iu',
            $text,
        ) === 1) {
            return true;
        }

        if (preg_match(
            '/(?:machine-translated|some parts cannot be translated|separately sold dlc|not the official sequel|spin-off that reuses|translation patch dlc|all \d+ upgrades|please download it)/iu',
            $text,
        ) === 1) {
            return true;
        }

        if (preg_match('/\b(?:web )?trial version\b/iu', $text) === 1) {
            return preg_match('/^(?:the |a )?(?:web )?trial version\b/iu', $text) === 1
                || preg_match('/(?:please|\bdemo\b|purchasing|test the|available|web trial)/iu', $text) === 1;
        }

        return false;
    }
}
