<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\ReportDefinition;
use App\Modules\Accounting\Services\Reporting\ReportingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Analytics dashboard, report library, report runs (screen / print / CSV) and saved reports. */
class ReportingController extends Controller
{
    public const PRESETS = ['this_month' => 'This month', 'last_month' => 'Last month', 'this_quarter' => 'This quarter', 'ytd' => 'Year to date', 'last_12' => 'Last 12 months', 'last_year' => 'Last year', 'custom' => 'Custom'];
    public const COMPARE = ['none' => 'No comparison', 'previous_period' => 'Previous period', 'previous_year' => 'Same period last year'];

    public function __construct(private readonly ReportingService $reports)
    {
    }

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        [$from, $to, $label] = ReportingService::window($filters['range'] ?? 'ytd', $filters['from'] ?? null, $filters['to'] ?? null);
        $compareMode = $filters['compare'] ?? 'previous_period';
        $cmp = ReportingService::comparison($from, $to, $compareMode === 'none' ? 'previous_period' : $compareMode);

        $trendFrom = $to->copy()->startOfMonth()->subMonths(11);
        $monthly = $this->reports->monthly($trendFrom, $to);

        return view('accounting.reporting.dashboard', [
            'filters' => $filters + ['range' => 'ytd', 'compare' => $compareMode],
            'label' => $label,
            'compareLabel' => $cmp[2],
            'kpi' => $this->reports->kpis($from, $to),
            'prior' => $this->reports->kpis($cmp[0], $cmp[1]),
            'trend' => [
                'labels' => array_column($monthly, 'label'),
                'series' => [
                    ['name' => 'Revenue', 'values' => array_map(fn ($m) => round($m['revenue'], 2), array_values($monthly))],
                    ['name' => 'Expenses', 'values' => array_column($monthly, 'total_expenses')],
                ],
            ],
            'topCustomers' => $this->reports->topCustomers($from, $to),
            'topExpenses' => $this->reports->topExpenses($from, $to),
            'aging' => $this->reports->aging('ar', now()->startOfDay())['buckets'],
            'saved' => ReportDefinition::where('status', true)->latest('last_run_at')->limit(6)->get(),
            'presets' => self::PRESETS,
            'compareOptions' => array_slice(self::COMPARE, 1, null, true),
            'from' => $from, 'to' => $to,
        ]);
    }

    public function library(): View
    {
        return view('accounting.reporting.library', [
            'catalog' => collect(ReportingService::REPORTS)->groupBy(fn ($r) => $r[2], true),
            'saved' => ReportDefinition::with('creator')->orderBy('name')->get(),
        ]);
    }

    public function run(Request $request, string $type): View|StreamedResponse
    {
        abort_unless(isset(ReportingService::REPORTS[$type]), 404);
        $filters = $this->filters($request);
        $report = $this->reports->run($type, $filters);

        if ($request->query('format') === 'csv') {
            return $this->csv($report);
        }

        return view($request->boolean('print') ? 'accounting.reporting.print' : 'accounting.reporting.run', [
            'type' => $type,
            'report' => $report,
            'filters' => $filters,
            'uses' => ReportingService::REPORTS[$type][3],
            'presets' => self::PRESETS,
            'compareOptions' => self::COMPARE,
            'accounts' => in_array('account', ReportingService::REPORTS[$type][3], true) ? Account::orderBy('code')->get(['id', 'code', 'name']) : collect(),
        ]);
    }

    public function openSaved(ReportDefinition $report): RedirectResponse
    {
        $report->forceFill(['last_run_at' => now()])->saveQuietly();

        return redirect()->route('accounting.reporting.run', ['type' => $report->type] + ($report->filters ?? []));
    }

    public function storeSaved(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(ReportingService::REPORTS))],
        ]);
        $filters = collect($this->filters($request))->filter(fn ($v) => $v !== null && $v !== '')->all();

        $report = ReportDefinition::create([
            'code' => Str::upper(Str::slug($data['name'], '_')).'_'.Str::upper(Str::random(4)),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'format' => 'table',
            'filters' => $filters,
            'status' => true,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('accounting.reporting.saved.show', $report)->with('success', 'Report "'.$report->name.'" saved.');
    }

    public function destroySaved(ReportDefinition $report): RedirectResponse
    {
        $report->delete();

        return redirect()->route('accounting.reporting.library')->with('success', 'Saved report removed.');
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'range' => ['nullable', Rule::in(array_keys(self::PRESETS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'as_at' => ['nullable', 'date'],
            'compare' => ['nullable', Rule::in(array_keys(self::COMPARE))],
            'account_id' => ['nullable', 'integer'],
        ]);
    }

    private function csv(array $report): StreamedResponse
    {
        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [$report['title'], $report['subtitle']]);
            fputcsv($out, array_map(fn ($c) => $c[0], $report['columns']));
            foreach ($report['rows'] as $row) {
                fputcsv($out, array_map(fn ($key) => $row['cells'][$key] ?? '', array_keys($report['columns'])));
            }
            fclose($out);
        }, Str::slug($report['title']).'-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }
}
