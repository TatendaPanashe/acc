<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PlatformDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $metrics = PlatformMetrics::forMonth($request->query('month'));
        $previous = $metrics->previous();

        $mrr = $metrics->mrr();
        $previousMrr = $previous->mrr();
        $failed = $metrics->failedPayments();
        $previousFailed = $previous->failedPayments(0);
        $cancellations = $metrics->cancellations();
        $previousCancellations = $previous->cancellations(0);
        $registrations = $metrics->newRegistrations();
        $previousRegistrations = $previous->newRegistrations();
        $movements = $metrics->movements();
        $previousMovements = $previous->movements();

        return view('platform.dashboard', [
            'metrics' => $metrics,
            'previous' => $previous,
            'months' => collect(range(0, 11))->map(fn ($i) => now()->startOfMonth()->subMonthsNoOverflow($i))
                ->mapWithKeys(fn (Carbon $m) => [$m->format('Y-m') => $m->format('F Y')]),
            'mrr' => $mrr,
            'mrrChange' => PlatformMetrics::change($mrr, $previousMrr),
            'previousMrr' => $previousMrr,
            'arr' => $metrics->arr(),
            'mrrHistory' => $metrics->mrrHistory(),
            'movements' => $movements,
            'movementChanges' => collect($movements)->map(fn ($value, $key) => PlatformMetrics::change($value, $previousMovements[$key]))
                ->put('lost', PlatformMetrics::change($movements['churn'] + $movements['contraction'], $previousMovements['churn'] + $previousMovements['contraction'])),
            'accounts' => $metrics->accountSnapshot(),
            'totalBusinesses' => $metrics->totalBusinesses(),
            'registrations' => $registrations,
            'registrationsChange' => PlatformMetrics::change($registrations, $previousRegistrations),
            'registrationHistory' => $metrics->registrationHistory(),
            'conversionRate' => $metrics->conversionRate(),
            'failed' => $failed,
            'failedChange' => PlatformMetrics::change($failed['count'], $previousFailed['count']),
            'cancellations' => $cancellations,
            'cancellationsChange' => PlatformMetrics::change($cancellations['count'], $previousCancellations['count']),
            'revenue' => $metrics->revenue(),
            'revenueByCountry' => $metrics->revenueByCountry(),
            'revenueByProvider' => $metrics->revenueByProvider(),
            'topSubscriptions' => $metrics->topSubscriptions(),
        ]);
    }
}
