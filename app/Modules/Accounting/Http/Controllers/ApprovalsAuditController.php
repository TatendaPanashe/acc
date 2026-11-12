<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Accounting\Models\ApprovalRequest;
use App\Modules\Accounting\Models\ApprovalRule;
use App\Modules\Accounting\Models\AuditLog;
use App\Modules\Accounting\Models\Concerns\Auditable;
use App\Modules\Accounting\Services\Approvals\ApprovalDocuments;
use App\Modules\Accounting\Services\Approvals\ApprovalPolicy;
use App\Modules\Accounting\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Approval inbox, approval rules and the audit trail. */
class ApprovalsAuditController extends Controller
{
    public function __construct(
        private readonly ApprovalPolicy $policy,
        private readonly AuditTrailService $audit,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $canApprove = $user->can('approve');
        $items = $this->pendingItems($user);

        // Accountants see their own submissions only.
        if (!$canApprove) {
            $items = $items->where('creator_id', $user->id)->values();
        }

        $decisions = ApprovalRequest::with(['resolver', 'requester'])->latest('id')->limit(25)->get();

        return view('accounting.approvals.index', [
            'items' => $items,
            'canApprove' => $canApprove,
            'decisions' => $decisions,
            'counts' => [
                'pending' => $items->whereNull('rejection')->count(),
                'returned' => $items->whereNotNull('rejection')->count(),
                'approved_30' => ApprovalRequest::where('status', 'approved')->where('resolved_at', '>=', now()->subDays(30))->count(),
                'rejected_30' => ApprovalRequest::where('status', 'rejected')->where('resolved_at', '>=', now()->subDays(30))->count(),
            ],
            'types' => collect(ApprovalDocuments::TYPES)->map(fn ($d) => $d['label']),
        ]);
    }

    public function reject(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(isset(ApprovalDocuments::TYPES[$type]), 404);
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);
        $document = ApprovalDocuments::TYPES[$type]['model']::findOrFail($id);
        abort_unless($document->status === 'draft', 422, 'Only documents awaiting approval can be returned.');

        $this->policy->record($document, $type, 'rejected', $request->user(), $data['note']);
        $this->audit->record('approval.rejected', $document, [], ['note' => $data['note']], $request);

        return back()->with('success', ApprovalDocuments::label($type).' '.ApprovalDocuments::number($type, $document).' returned to its preparer.');
    }

    // ---- rules ---------------------------------------------------------------

    public function rules(): View
    {
        return view('accounting.approvals.rules', [
            'rules' => ApprovalRule::orderBy('document_type')->orderBy('min_amount')->get(),
            'types' => ['all' => 'All documents'] + collect(ApprovalDocuments::TYPES)->map(fn ($d) => $d['label'])->all(),
            'roles' => ApprovalRule::ROLES,
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        ApprovalRule::create($this->validatedRule($request));

        return back()->with('success', 'Approval rule added.');
    }

    public function updateRule(Request $request, ApprovalRule $rule): RedirectResponse
    {
        $rule->update($this->validatedRule($request));

        return back()->with('success', 'Approval rule updated.');
    }

    public function destroyRule(ApprovalRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('success', 'Approval rule removed.');
    }

    private function validatedRule(Request $request): array
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::in(array_merge(['all'], array_keys(ApprovalDocuments::TYPES)))],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'approver_role' => ['required', Rule::in(array_keys(ApprovalRule::ROLES))],
            'require_different_user' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]);

        return $data + ['require_different_user' => $request->boolean('require_different_user'), 'status' => $request->boolean('status')];
    }

    // ---- audit trail -----------------------------------------------------------

    public function auditLog(Request $request): View
    {
        $filters = $this->auditFilters($request);

        return view('accounting.approvals.audit', [
            'logs' => $this->auditQuery($request, $filters)->with('user')->latest('id')->paginate(40)->withQueryString(),
            'filters' => $filters,
            'users' => User::where('company_id', $request->user()->company_id)->orderBy('name')->get(['id', 'name']),
            'subjects' => AuditLog::where('company_id', $request->user()->company_id)->whereNotNull('auditable_type')->distinct()->pluck('auditable_type')->mapWithKeys(fn ($t) => [$t => class_basename($t)])->sort(),
            'verification' => $request->boolean('verify') ? $this->audit->verify($request->user()->company_id) : null,
        ]);
    }

    public function auditShow(Request $request, int $log): View
    {
        $entry = AuditLog::with('user')->where('company_id', $request->user()->company_id)->findOrFail($log);
        $history = $entry->auditable_type
            ? AuditLog::with('user')->where('company_id', $entry->company_id)->where('auditable_type', $entry->auditable_type)->where('auditable_id', $entry->auditable_id)->orderBy('id')->get()
            : collect([$entry]);

        return view('accounting.approvals.audit-show', ['entry' => $entry, 'history' => $history]);
    }

    public function auditExport(Request $request): StreamedResponse
    {
        $query = $this->auditQuery($request, $this->auditFilters($request))->with('user')->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'When', 'User', 'Event', 'Record', 'Old values', 'New values', 'IP address', 'Checksum']);
            foreach ($query->lazy(500) as $log) {
                fputcsv($out, [$log->id, $log->created_at?->format('Y-m-d H:i:s'), $log->user?->name, $log->event, $log->subjectLabel(), json_encode($log->old_values), json_encode($log->new_values), $log->ip_address, $log->checksum]);
            }
            fclose($out);
        }, 'audit-trail-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function auditFilters(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'integer'],
            'event' => ['nullable', 'string', 'max:60'],
            'subject' => ['nullable', 'string', 'max:190'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
    }

    private function auditQuery(Request $request, array $filters)
    {
        return AuditLog::where('company_id', $request->user()->company_id)
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['event'] ?? null, fn ($q, $v) => $q->where('event', 'like', '%'.$v.'%'))
            ->when($filters['subject'] ?? null, fn ($q, $v) => $q->where('auditable_type', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('new_values', 'like', '%'.$v.'%')->orWhere('old_values', 'like', '%'.$v.'%')));
    }

    // ---- inbox data ------------------------------------------------------------

    /** @return Collection<int, array> */
    private function pendingItems(User $user): Collection
    {
        $items = collect();
        $names = User::where('company_id', $user->company_id)->pluck('name', 'id');

        foreach (ApprovalDocuments::TYPES as $type => $definition) {
            $documents = ApprovalDocuments::pending($type)->limit(100)->get();
            if ($documents->isEmpty()) {
                continue;
            }

            // One query for the creators of this document type.
            $creators = isset($documents->first()->created_by)
                ? $documents->pluck('created_by', 'id')
                : AuditLog::where('company_id', $user->company_id)->where('auditable_type', $definition['model'])
                    ->whereIn('auditable_id', $documents->pluck('id'))->where('event', Auditable::auditEvent($documents->first(), 'created'))
                    ->pluck('user_id', 'auditable_id');

            foreach ($documents as $document) {
                $creatorId = $creators[$document->id] ?? null;
                $baseAmount = $this->policy->baseAmount($type, $document);
                $rule = $this->policy->ruleFor($type, $baseAmount, (int) $document->company_id);
                $items->push([
                    'type' => $type,
                    'label' => $definition['label'],
                    'document' => $document,
                    'number' => ApprovalDocuments::number($type, $document),
                    'date' => $document->{$definition['date']},
                    'amount' => ApprovalDocuments::amount($type, $document),
                    'currency' => $document->currency ?? null,
                    'base_amount' => $baseAmount,
                    'creator_id' => $creatorId,
                    'creator' => $creatorId ? ($names[$creatorId] ?? 'User #'.$creatorId) : null,
                    'age_days' => (int) $document->created_at?->diffInDays(now()),
                    'url' => ApprovalDocuments::url($type, $document),
                    'approve_route' => route($definition['route'], $document),
                    'action' => $definition['action'],
                    'needs' => $rule?->approver_role === 'admin' ? 'Company Admin' : 'Finance Manager / Admin',
                    'blocked' => $user->can('approve') ? $this->policy->blockReason($user, $document, $type) : null,
                    'rejection' => $this->policy->openRejection($document),
                ]);
            }
        }

        return $items->sortBy(fn ($item) => [$item['rejection'] ? 1 : 0, -$item['age_days']])->values();
    }
}
