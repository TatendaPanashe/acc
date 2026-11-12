<?php

namespace App\Modules\Accounting\Services\Approvals;

use App\Models\User;
use App\Modules\Accounting\Models\ApprovalRequest;
use App\Modules\Accounting\Models\ApprovalRule;
use App\Modules\Accounting\Services\AuditTrailService;
use App\Modules\Accounting\Services\ExchangeRateService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Company approval rules on top of role permissions:
 *  - amount thresholds (in base currency) that need a Company Admin rather than a Finance Manager;
 *  - segregation of duties: whoever captured a document may not approve it.
 * The rule with the highest threshold not above the document amount applies; a rule for the specific
 * document type wins over an "all documents" rule at the same threshold.
 */
class ApprovalPolicy
{
    public function __construct(
        private readonly AuditTrailService $audit,
        private readonly ExchangeRateService $rates,
    ) {
    }

    public function ruleFor(string $type, float $baseAmount, int $companyId): ?ApprovalRule
    {
        return ApprovalRule::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', true)
            ->whereIn('document_type', [$type, 'all'])
            ->where('min_amount', '<=', $baseAmount)
            ->get()
            ->sortByDesc(fn (ApprovalRule $rule) => [(float) $rule->min_amount, $rule->document_type === $type ? 1 : 0])
            ->first();
    }

    public function baseAmount(string $type, Model $document): float
    {
        $amount = ApprovalDocuments::amount($type, $document) ?? 0.0;
        $currency = $document->currency ?? null;
        if (!$currency || $amount == 0.0) {
            return $amount;
        }

        try {
            $date = $document->{ApprovalDocuments::TYPES[$type]['date']} ?? now();

            return round($amount * $this->rates->rate($currency, (int) $document->company_id, $date), 2);
        } catch (ValidationException) {
            return $amount; // no rate on file: compare the face amount
        }
    }

    /** Why $user may not approve $document, or null when allowed. */
    public function blockReason(User $user, Model $document, string $type): ?string
    {
        if ($user->is_superuser) {
            return null;
        }

        $amount = $this->baseAmount($type, $document);
        $rule = $this->ruleFor($type, $amount, (int) $document->company_id);
        if (!$rule) {
            return null;
        }

        $label = ApprovalDocuments::label($type).' '.ApprovalDocuments::number($type, $document);
        if ($rule->approver_role === 'admin' && $user->role !== 'admin') {
            return $label.' needs Company Admin approval'.((float) $rule->min_amount > 0 ? ' (amounts from '.number_format((float) $rule->min_amount, 2).')' : '').'.';
        }
        if ($rule->require_different_user && $this->audit->creatorOf($document) === $user->id) {
            return 'You captured '.$label.'; another approver must approve it (segregation of duties).';
        }

        return null;
    }

    public function record(Model $document, string $type, string $decision, User $user, ?string $note = null): ApprovalRequest
    {
        $request = new ApprovalRequest([
            'approvable_type' => $document::class,
            'approvable_id' => $document->getKey(),
            'workflow_key' => $type,
            'amount' => ApprovalDocuments::amount($type, $document),
            'reference' => ApprovalDocuments::number($type, $document),
            'status' => $decision,
            'requested_by' => $this->audit->creatorOf($document),
            'resolved_by' => $user->id,
            'resolved_at' => now(),
            'resolution_note' => $note,
        ]);
        $request->company_id = $document->company_id;
        $request->save();

        return $request;
    }

    /** Latest rejection that the document has not been edited since. */
    public function openRejection(Model $document): ?ApprovalRequest
    {
        $rejection = ApprovalRequest::withoutGlobalScopes()
            ->where('company_id', $document->company_id)
            ->where('approvable_type', $document::class)
            ->where('approvable_id', $document->getKey())
            ->latest('id')
            ->first();

        return $rejection && $rejection->status === 'rejected' && $document->updated_at <= $rejection->resolved_at ? $rejection : null;
    }
}
