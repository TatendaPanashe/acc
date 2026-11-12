<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Services\FinancialStatementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialStatementController extends Controller
{
    public function profitAndLoss(Request $request, FinancialStatementService $statements): View
    {
        $period = $this->period($request);
        $window = $statements->window($period);

        return view('accounting.gl.reports.profit_and_loss', $this->common($period) + [
            'window' => $window,
            'report' => $statements->profitAndLoss($window['from'], $window['to']),
        ]);
    }

    public function balanceSheet(Request $request, FinancialStatementService $statements): View
    {
        $period = $this->period($request);

        return view('accounting.gl.reports.balance_sheet', $this->common($period) + [
            'report' => $statements->balanceSheet($statements->asAt($period)),
        ]);
    }

    public function cashFlow(Request $request, FinancialStatementService $statements): View
    {
        $period = $this->period($request);
        $window = $statements->window($period);

        return view('accounting.gl.reports.cash_flow', $this->common($period) + [
            'window' => $window,
            'report' => $statements->cashFlow($window['from'], $window['to']),
        ]);
    }

    private function period(Request $request): ?AccountingPeriod
    {
        return $request->filled('period_id') ? AccountingPeriod::findOrFail($request->integer('period_id')) : null;
    }

    private function common(?AccountingPeriod $period): array
    {
        return [
            'period' => $period,
            'periods' => AccountingPeriod::orderByDesc('starts_on')->get(),
        ];
    }
}
