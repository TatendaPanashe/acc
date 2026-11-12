<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AiForecast;
use App\Modules\Accounting\Models\AiInsight;
use App\Modules\Accounting\Services\Ai\AnomalyDetectionService;
use App\Modules\Accounting\Services\Ai\AutoCodingService;
use App\Modules\Accounting\Services\Ai\CashCollectionsService;
use App\Modules\Accounting\Services\Ai\ForecastService;
use App\Modules\Accounting\Services\Approvals\ApprovalDocuments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Forecasting, payment prediction, anomaly detection and GL auto-coding (all computed locally). */
class AiAutomationController extends Controller
{
    public function __construct(
        private readonly ForecastService $forecasts,
        private readonly AnomalyDetectionService $anomalies,
        private readonly AutoCodingService $coding,
        private readonly CashCollectionsService $cash,
    ) {
    }

    public function index(): View
    {
        $open = AiInsight::where('status', 'open');

        return view('accounting.ai.index', [
            'metrics' => [
                ['Open findings', (clone $open)->count(), 'alert-triangle', 'col-orange', (clone $open)->where('severity', 'high')->count().' high severity'],
                ['Forecasts to review', AiForecast::where('status', 'review')->count(), 'trending-up', 'col-blue'],
                ['Avg forecast confidence', number_format((float) AiForecast::whereDate('forecast_date', '>=', today()->startOfMonth())->avg('confidence'), 1).'%', 'activity', 'col-green'],
                ['Auto-coding training lines', $this->coding->samples()->count(), 'cpu', 'col-purple'],
            ],
            'chart' => $this->chart('revenue'),
            'outlook' => $this->cash->outlook(),
            'insights' => AiInsight::where('status', 'open')->orderByRaw("CASE severity WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")->latest('id')->limit(6)->get(),
        ]);
    }

    // ---- forecasts -----------------------------------------------------------------

    public function forecasts(Request $request): View
    {
        $type = $request->query('type', 'revenue');
        abort_unless(isset(ForecastService::TYPES[$type]), 404);

        return view('accounting.ai.forecasts', [
            'type' => $type,
            'chart' => $this->chart($type),
            'rows' => AiForecast::with('reviewer')->where('type', $type)->whereDate('forecast_date', '>=', today()->startOfMonth())->orderBy('forecast_date')->get(),
        ]);
    }

    public function generateForecasts(Request $request): RedirectResponse
    {
        $data = $request->validate(['horizon' => ['nullable', 'integer', 'min:1', 'max:12']]);
        $result = collect($this->forecasts->generate((int) ($data['horizon'] ?? 6)));
        $made = $result->filter(fn ($r) => isset($r['method']));

        return back()->with($made->isEmpty() ? 'warning' : 'success', $made->isEmpty()
            ? 'Not enough history yet: '.$result->first()['skipped']
            : 'Forecasts updated for '.$made->keys()->map(fn ($k) => strtolower(ForecastService::TYPES[$k]))->join(', ').'. Review and approve them to use them as the plan.');
    }

    public function reviewForecasts(Request $request): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(array_keys(ForecastService::TYPES))], 'decision' => ['required', Rule::in(['approved', 'rejected'])]]);
        $count = AiForecast::where('type', $data['type'])->where('status', 'review')->update(['status' => $data['decision'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        app(\App\Modules\Accounting\Services\AuditTrailService::class)->record('ai.forecast.'.$data['decision'], null, [], ['type' => $data['type'], 'months' => $count], $request);

        return back()->with('success', $count.' '.strtolower(ForecastService::TYPES[$data['type']]).' forecast months '.$data['decision'].'.');
    }

    private function chart(string $type): array
    {
        $history = $this->forecasts->history()[$type];
        $hist = array_slice($history['values'], -12);
        $labels = array_slice($history['labels'], -12);
        $future = AiForecast::where('type', $type)->whereIn('status', ['review', 'approved'])->whereDate('forecast_date', '>=', today()->startOfMonth())->orderBy('forecast_date')->get();

        $actual = $hist;
        $forecast = array_fill(0, count($hist), null);
        if ($hist && $future->isNotEmpty()) {
            $forecast[count($hist) - 1] = end($hist); // joins the two lines
        }
        foreach ($future as $row) {
            $labels[] = $row->forecast_date->format('M y');
            $actual[] = null;
            $forecast[] = (float) $row->predicted_value;
        }

        return [
            'labels' => $labels,
            'series' => [
                ['name' => ForecastService::TYPES[$type].' (actual)', 'values' => $actual],
                ['name' => 'Forecast', 'values' => $forecast, 'dashed' => true],
            ],
            'has_forecast' => $future->isNotEmpty(),
            'confidence' => $future->first()?->confidence,
            'method' => $future->first()?->model_key,
            'status' => $future->first()?->status,
            'months' => count($history['values']),
        ];
    }

    // ---- cash & collections ----------------------------------------------------------

    public function collections(): View
    {
        return view('accounting.ai.collections', [
            'outlook' => $this->cash->outlook(),
            'receivables' => $this->cash->predictions('ar'),
            'payables' => $this->cash->predictions('ap'),
            'behaviour' => ['ar' => $this->cash->behaviour('ar'), 'ap' => $this->cash->behaviour('ap')],
        ]);
    }

    // ---- anomaly detection -------------------------------------------------------------

    public function insights(Request $request): View
    {
        $status = $request->query('status', 'open');

        return view('accounting.ai.insights', [
            'insights' => AiInsight::with('reviewer')->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
                ->orderByRaw("CASE severity WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")->latest('id')->paginate(30)->withQueryString(),
            'status' => $status,
            'type' => $request->query('type'),
            'counts' => AiInsight::where('status', 'open')->selectRaw('type, COUNT(*) as n')->groupBy('type')->pluck('n', 'type'),
        ]);
    }

    public function runDetection(): RedirectResponse
    {
        $result = $this->anomalies->detect();

        return back()->with('success', 'Checked transactions since '.Carbon::parse($result['checked_since'])->format('d M Y').': '.$result['new'].' new '.str('finding')->plural($result['new']).'.');
    }

    public function reviewInsight(Request $request, AiInsight $insight): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['dismissed', 'confirmed', 'open'])], 'note' => ['nullable', 'string', 'max:255']]);
        $insight->update(['status' => $data['status'], 'review_note' => $data['note'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        app(\App\Modules\Accounting\Services\AuditTrailService::class)->record('ai.insight.'.$data['status'], $insight, [], ['title' => $insight->title, 'note' => $data['note'] ?? null], $request);

        return back()->with('success', 'Finding marked '.$data['status'].'.');
    }

    public static function subjectUrl(AiInsight $insight): ?string
    {
        if (!$insight->subject_type || !$insight->subject_id) {
            return null;
        }
        $type = collect(ApprovalDocuments::TYPES)->search(fn ($d) => $d['model'] === $insight->subject_type);

        return $type && ApprovalDocuments::TYPES[$type]['show'] ? route(ApprovalDocuments::TYPES[$type]['show'], $insight->subject_id) : null;
    }

    // ---- auto-coding -----------------------------------------------------------------

    public function autoCoding(Request $request): View
    {
        $text = (string) $request->query('text', '');

        return view('accounting.ai.auto-coding', [
            'evaluation' => $this->coding->evaluate(),
            'text' => $text,
            'suggestions' => $text !== '' ? $this->coding->suggest($text, $request->integer('supplier_id') ?: null, 0.0) : [],
            'suppliers' => \App\Modules\Accounting\Models\Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:255'], 'supplier_id' => ['nullable', 'integer']]);

        return response()->json(['suggestions' => $this->coding->suggest($data['text'], isset($data['supplier_id']) ? (int) $data['supplier_id'] : null)]);
    }
}
