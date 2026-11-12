<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Services\StatutoryReportWorkbookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class StatutoryReportController extends Controller
{
    public function index(StatutoryReportWorkbookService $workbooks): View
    {
        return view('accounting.statutory.index', [
            'reports' => $workbooks->reports(),
            'periods' => AccountingPeriod::orderByDesc('starts_on')->get(),
        ]);
    }

    public function download(Request $request, string $report, StatutoryReportWorkbookService $workbooks): Response
    {
        abort_unless(array_key_exists($report, $workbooks->reports()), 404);

        $period = $request->filled('period_id')
            ? AccountingPeriod::findOrFail($request->integer('period_id'))
            : null;

        return response($workbooks->workbook($report, $period), 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$workbooks->filename($report).'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
