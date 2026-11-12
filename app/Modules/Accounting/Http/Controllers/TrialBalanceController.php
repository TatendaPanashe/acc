<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Services\TrialBalanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrialBalanceController extends Controller
{
    public function __invoke(Request $request, TrialBalanceService $service): View
    {
        $period = $request->filled('period_id')
            ? AccountingPeriod::findOrFail($request->integer('period_id'))
            : null;

        $rows = $service->forPeriod($period);

        return view('accounting.gl.reports.trial_balance', [
            'rows' => $rows,
            'period' => $period,
            'periods' => AccountingPeriod::orderByDesc('starts_on')->get(),
            'totals' => [
                'debit' => round((float) $rows->sum('debit_total'), 2),
                'credit' => round((float) $rows->sum('credit_total'), 2),
            ],
        ]);
    }
}
