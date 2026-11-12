<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\DepreciationRun;
use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Accounting\Services\DepreciationService;
use App\Rules\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepreciationController extends Controller
{
    public function index(Request $request, DepreciationService $depreciation): View
    {
        $periods = AccountingPeriod::where('status', 'open')->orderBy('starts_on')->get();
        $done = DepreciationRun::pluck('accounting_period_id')->all();
        $selected = $request->filled('period_id')
            ? AccountingPeriod::findOrFail($request->integer('period_id'))
            : $periods->first(fn ($p) => !in_array($p->id, $done, true));

        return view('accounting.fixed_assets.depreciation', [
            'assets' => FixedAsset::orderBy('asset_number')->get(),
            'periods' => $periods,
            'selected' => $selected,
            'alreadyRun' => $selected && in_array($selected->id, $done, true),
            'preview' => $selected ? $depreciation->preview($selected) : collect(),
            'runs' => DepreciationRun::with('period')->withCount('lines')->latest('run_date')->get(),
        ]);
    }

    public function store(Request $request, DepreciationService $depreciation): RedirectResponse
    {
        $data = $request->validate(['accounting_period_id' => ['required', new TenantExists('accounting_periods')]]);
        $run = $depreciation->run(AccountingPeriod::findOrFail($data['accounting_period_id']));

        return redirect()->route('accounting.fixed-assets.depreciation.index')
            ->with('success', 'Depreciation of '.number_format((float) $run->total, 2).' posted for '.$run->period->name.'.');
    }
}
