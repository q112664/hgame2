<?php

namespace App\Actions\Games;

use App\Models\GameDownloadLink;
use App\Support\DownloadLinkName;

class RelabelAutoDerivedDownloadLinks
{
    /**
     * Rename links that were named after a bare host before names were
     * shortened, so `dl.nekobox.club` becomes `Nekobox`.
     *
     * Names a person chose are left alone: DownloadLinkName::resolve() only
     * replaces a name that is missing or is one it would have written itself.
     *
     * @return int The number of links renamed.
     */
    public function __invoke(): int
    {
        $renamed = 0;

        GameDownloadLink::query()
            ->select(['id', 'label', 'url'])
            ->chunkById(500, function ($links) use (&$renamed): void {
                foreach ($links as $link) {
                    $name = DownloadLinkName::resolve($link->label, (string) $link->url);

                    if ($name === $link->label) {
                        continue;
                    }

                    // Quietly, because only the name should change: going through
                    // the saving hook would also force `is_active`, which would
                    // put links that were switched off back on the page.
                    $link->label = $name;
                    $link->saveQuietly();

                    $renamed++;
                }
            });

        return $renamed;
    }
}
