<?php

namespace App\Http\Controllers;

use App\Actions\Stats\ListResourceRankings;
use App\RankingPeriod;
use App\Support\PageSeo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class RankingController extends Controller
{
    public function __construct(private ListResourceRankings $listResourceRankings) {}

    public function __invoke(Request $request): Response
    {
        $period = RankingPeriod::fromRouteName($request->route()?->getName());

        $entries = Cache::remember(
            'rankings.'.$period->value.'.'.today()->toDateString(),
            now()->addMinutes(5),
            fn (): array => ($this->listResourceRankings)($period),
        );

        return Inertia::render('rankings/index', [
            'period' => $period->value,
            'entries' => $entries,
            'pageSeo' => PageSeo::rankings($period),
        ]);
    }
}
