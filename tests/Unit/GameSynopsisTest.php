<?php

use App\Support\GameSynopsis;

test('game synopsis picks the first usable english hook', function (string $html, string $starts) {
    expect(GameSynopsis::hook($html))->toStartWith($starts);
})->with([
    'ai synopsis label' => [
        '<p><strong>Synopsis (AI-translated English)</strong></p><p>Play a puzzle game with friends.</p>',
        'Play a puzzle game',
    ],
    'diamond game story heading' => [
        '<p>◆Game Story</p><p>You run into a mysterious girl.</p>',
        'You run into a mysterious girl',
    ],
    'story heading block' => [
        '<h3>Story</h3><p>You are an ordinary office worker.</p>',
        'You are an ordinary office worker',
    ],
    'unlocked label' => [
        '<p>Unlocked.</p><p>The heavy cell door swings open into the silence.</p>',
        'The heavy cell door swings open into the silence.',
    ],
    'features then story' => [
        '<p>Features:</p><p>・High-quality pixel-art graphics.</p><p>・Characters and backgrounds blend flawlessly, drawing you into the world.</p><p>Story:</p><p>Kato Ryuichi had always been defiant. From a young age, he clashed with his father.</p>',
        'Kato Ryuichi had always been defiant.',
    ],
    'keep pre-story plot hook' => [
        '<p>A self-insert RPG where you claim the power of hypnosis and use it to get into bed with every beautiful girl at your school!</p><p>■Current version: 1.05</p><p>■Story</p><p>You want to finally lose your virginity.</p>',
        'A self-insert RPG where you claim the power of hypnosis',
    ],
    'strip overview bracket keep remainder' => [
        '<p>【Overview】 A 2D action game where you fight to escape a looping otherworld as a magical girl.</p><p>~Content included~</p><p>- Size difference</p>',
        'A 2D action game where you fight to escape a looping otherworld as a magical girl.',
    ],
    'skip changelog until synopsis' => [
        '<p>◆ver1.1.2 update notice</p><p>・Bug fixes</p><p>※See Ci-en for details</p><p>◆Synopsis</p><p>You\'ve been appointed leader of the Sexual Handling Department.</p>',
        "You've been appointed leader of the Sexual Handling Department.",
    ],
    'skip engine line' => [
        '<p>This game was made with Unreal Engine 5.</p><p>High-quality graphics deepen the immersion.</p><p>The boy Roa wakes up on a bed in the mansion.</p>',
        'The boy Roa wakes up on a bed in the mansion.',
    ],
    'glued synopsis heading' => [
        '<p>SynopsisYou and your co-worker Nanako have always had a bitter rivalry.</p>',
        'You and your co-worker Nanako have always had a bitter rivalry.',
    ],
    'triangle synopsis heading' => [
        '<p>▼ Synopsis</p><p>Tatsuya has lived independently in a big city as a university student.</p>',
        'Tatsuya has lived independently in a big city as a university student.',
    ],
    'keep rpg maker flavor sentence' => [
        '<p>An NTR-themed RPG Maker game about a seaside beach house story.</p><p>Summer at the beach house—a story of relationships tested by temptation.</p>',
        'An NTR-themed RPG Maker game about a seaside beach house story.',
    ],
    'newline slogans' => [
        "<p>The fox onee-san next door</p><p>Kind, and a great cook</p><p>Lately she's been dealing with heat-season troubles</p><p>Play with mouse only!</p>",
        "Lately she's been dealing with heat-season troubles",
    ],
    'skip dlc bracket' => [
        '<p>【Separate DLC】Full-color CGs for the main story!</p><p>Install the separately sold DLC and every CG in the main story becomes full color!</p><p>You\'re a freshly opened chiropractor.</p>',
        "You're a freshly opened chiropractor.",
    ],
    'skip version then story' => [
        '<p>The current version is 1.13.</p><p>■Overview</p><p>The latest work in the RPG series by Poison.</p><p>■Story</p><p>On a certain continent centered around the capital, the Baron Kingdom.</p>',
        'On a certain continent centered around the capital, the Baron Kingdom.',
    ],
    'skip language support' => [
        '<p>◆ Language support includes English, Simplified Chinese and Traditional Chinese, but please note some parts cannot be translated.</p><p>◆ Made with RPG Maker MZ</p><p>◆ Game Content</p><p>Netorare (steal) married office lady Haruka before the given number of days runs out.</p>',
        'Netorare (steal) married office lady Haruka before the given number of days runs out.',
    ],
    'skip fully animated marketing' => [
        '<p>【Fully animated】 Control every motion freely with your own input.</p><p>A succubus who drifts out of the dark night — Aoya.</p><p>◆Game Features</p>',
        'A succubus who drifts out of the dark night — Aoya.',
    ],
    'game overview heading' => [
        '<p>[Game Overview]</p><p>An orthodox R18 RPG utilizing a 3 character party system.</p>',
        'An orthodox R18 RPG utilizing a 3 character party system.',
    ],
    'keep title-containing plot sentence' => [
        '<p>Isekai Sex Kingdom is an adult fantasy visual novel set in a world where love is forbidden and desire is tightly controlled by those in power.</p><p>The kingdom trembles on the edge of revolt.</p>',
        'Isekai Sex Kingdom is an adult fantasy visual novel set in a world where love is forbidden',
    ],
    'skip character cv block' => [
        '<p>Characters</p><p>●Hipopo Popota (CV: Hanadera Karen)</p><p>※Main heroine</p><p>A creature that escaped the zoo, interested in game streaming, looking for an owner to take care of her.</p>',
        'A creature that escaped the zoo, interested in game streaming, looking for an owner to take care of her.',
    ],
    'marketing before story uses story' => [
        '<p>Beautiful graphics deepen the immersion of every battle scene.</p><p>Story</p><p>Kato Ryuichi had always been defiant. From a young age, he clashed with his father.</p>',
        'Kato Ryuichi had always been defiant.',
    ],
    'hentai marketing before story uses story' => [
        '<p>This hentai RPG is packed with scenes and extra maps for players.</p><p>Story</p><p>You wake up in a ruined chapel with no memory of your name.</p>',
        'You wake up in a ruined chapel with no memory of your name.',
    ],
    'same paragraph synopsis label' => [
        '<p><strong>Synopsis (AI-translated English)</strong> Play a puzzle game with friends.</p>',
        'Play a puzzle game',
    ],
    'synopsis label with trailing period' => [
        '<p>Synopsis (AI-translated English).</p><p>You run into a mysterious girl behind the school.</p>',
        'You run into a mysterious girl behind the school.',
    ],
    'keep closing parenthesis in plot' => [
        '<p>He said he would stay (if you dare).</p>',
        'He said he would stay (if you dare).',
    ],
    'skip decorative title line' => [
        '<p>《Moonflower Princess Cornelia》</p><p>You inherit a decaying castle on the night of the eclipse.</p>',
        'You inherit a decaying castle on the night of the eclipse.',
    ],
    'bullet without space is not a pre-story hook' => [
        '<p>・Explore dungeons with a party of cute girls you meet along the way.</p><p>Story</p><p>Kato Ryuichi had always been defiant. From a young age, he clashed with his father.</p>',
        'Kato Ryuichi had always been defiant.',
    ],
    'feature bullets without features heading' => [
        '<p>・High-quality pixel-art graphics.</p><p>・Characters and backgrounds blend flawlessly, drawing you into the world.</p><p>Story:</p><p>Kato Ryuichi had always been defiant. From a young age, he clashed with his father.</p>',
        'Kato Ryuichi had always been defiant.',
    ],
    'plot mentioning before purchasing' => [
        '<p>He hesitated before purchasing a gift, then walked back to her apartment anyway.</p>',
        'He hesitated before purchasing a gift, then walked back to her apartment anyway.',
    ],
    'plot mentioning may not run' => [
        '<p>She may not run away this time, not after everything he did to keep her close.</p>',
        'She may not run away this time, not after everything he did to keep her close.',
    ],
    'made with love is not engine junk' => [
        '<p>This game was made with love and care by a tiny circle over several years.</p>',
        'This game was made with love and care by a tiny circle over several years.',
    ],
    'sentence-case story of a girl is plot' => [
        '<p>Story of a girl who lived at the edge of the woods for twenty quiet years.</p>',
        'Story of a girl who lived at the edge of the woods for twenty quiet years.',
    ],
    'title-case story of a girl is plot' => [
        '<p>Story Of A Girl Who Lived at the edge of the woods for twenty quiet years.</p>',
        'Story Of A Girl Who Lived at the edge of the woods for twenty quiet years.',
    ],
    'ending a is not a heading' => [
        '<p>Ending A is the true route she takes after you refuse the contract.</p>',
        'Ending A is the true route she takes after you refuse the contract.',
    ],
    'plot armor is not a story heading' => [
        '<p>Plot Armor could not save him after the demon entered the gates.</p>',
        'Plot Armor could not save him after the demon entered the gates.',
    ],
    'may not run inside community' => [
        '<p>She may not run from the community this time, not after everything he did to keep her close.</p>',
        'She may not run from the community this time, not after everything he did to keep her close.',
    ],
    'light mode on a pc is plot' => [
        '<p>She switched to light mode on her PC before visiting his apartment that night.</p>',
        'She switched to light mode on her PC before visiting his apartment that night.',
    ],
    'about this game loses to later story' => [
        '<p>About this game</p><p>Beautiful graphics and lots of extra maps for players who love long campaigns.</p><p>Story</p><p>You wake up in a ruined chapel with no memory of your name.</p>',
        'You wake up in a ruined chapel with no memory of your name.',
    ],
    'about this game without story still usable' => [
        '<p>About the Game</p><p>Part-Time Witch is a potion-brewing simulator where you physically interact with your ingredients.</p>',
        'Part-Time Witch is a potion-brewing simulator where you physically interact with your ingredients.',
    ],
    'story period then plot on the same line' => [
        '<p>Story. You wake up in a ruined chapel with no memory of your name.</p>',
        'You wake up in a ruined chapel with no memory of your name.',
    ],
    'bullet story heading' => [
        '<p>・Story</p><p>Kato Ryuichi had always been defiant. From a young age, he clashed with his father.</p>',
        'Kato Ryuichi had always been defiant.',
    ],
    'notice parenthetical is not a heading' => [
        '<p>Notice (from the guild) arrived the morning after she left town with him.</p>',
        'Notice (from the guild) arrived the morning after she left town with him.',
    ],
]);

test('game synopsis returns no hook for engine faq and trial-only copy', function (string $html) {
    expect(GameSynopsis::hook($html))->toBe('');
})->with([
    'tkool faq' => [
        '<p>*** This work was made with "Action Game Tkool MV" ***</p><p>Please be sure to read "If the game does not start / Playing notes" first.</p><p>https://tkool.jp/support/faq_actmv.html</p>',
    ],
    'web trial only' => [
        '<p>A web trial version is available!</p><p>No login required—you can play the web trial version immediately in your browser!</p><p>The browser version runs in "light mode" to reduce processing load.</p>',
    ],
    'bonus only' => [
        '<p>By purchasing the main game, you can enjoy the following bonuses at no additional charge.</p><p>【Bonus content】 Digital Art Book.</p>',
    ],
]);

test('game synopsis can return two plot sentences for json-ld', function () {
    $html = '<p>A cowardly female teacher enters the old school building crawling with tentacles to save her students...!!</p><p>The occult research club advisor heads into the old school building at night.</p>';

    $sentences = GameSynopsis::sentences($html, 2);

    expect($sentences)->toHaveCount(2)
        ->and($sentences[0])->toStartWith('A cowardly female teacher enters')
        ->and($sentences[1])->toStartWith('The occult research club advisor');
});

test('json-ld does not continue into characters after story', function () {
    $html = '<p>Story</p><p>Kato Ryuichi had always been defiant. From a young age, he clashed with his father.</p><p>Characters</p><p>A creature that escaped the zoo, interested in game streaming, looking for an owner to take care of her.</p>';

    $sentences = GameSynopsis::sentences($html, 2);

    expect($sentences)->toHaveCount(2)
        ->and($sentences[0])->toStartWith('Kato Ryuichi had always been defiant.')
        ->and($sentences[1])->toStartWith('From a young age, he clashed with his father.')
        ->and(implode(' ', $sentences))->not->toContain('escaped the zoo');
});

test('empty story does not fall back to characters', function () {
    $html = '<p>Story</p><p>TBD.</p><p>Characters</p><p>A creature that escaped the zoo, interested in game streaming, looking for an owner to take care of her.</p>';

    expect(GameSynopsis::hook($html))->toBe('')
        ->and(GameSynopsis::sentences($html, 2))->toBe([]);
});
