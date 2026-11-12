<?php

namespace App\Http\Middleware;

use App\Modules\Accounting\Services\Approvals\ApprovalDocuments;
use App\Modules\Accounting\Services\Approvals\ApprovalPolicy;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies company approval rules to every approve/post route (see ApprovalDocuments) and logs the decision.
 * Role permissions are checked first by the `permission` middleware.
 */
class EnforceApprovalRules
{
    public function __construct(private readonly ApprovalPolicy $policy)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $type = ApprovalDocuments::forRoute($request->route()?->getName());
        $document = $type ? $request->route(ApprovalDocuments::TYPES[$type]['param']) : null;

        if (!$document instanceof Model || $request->isMethodSafe() || !$request->user()) {
            return $next($request);
        }

        if ($reason = $this->policy->blockReason($request->user(), $document, $type)) {
            return $request->expectsJson()
                ? response()->json(['message' => $reason], 403)
                : back()->with('error', $reason);
        }

        $before = $document->status;
        $response = $next($request);

        $after = $document->newQuery()->whereKey($document->getKey())->value('status');
        if ($before === 'draft' && $after !== null && $after !== 'draft') {
            $this->policy->record($document->fresh(), $type, 'approved', $request->user());
        }

        return $response;
    }
}
