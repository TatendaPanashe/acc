<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ApprovalWorkflowService
{
    public function submit(Model $model, string $workflowKey): void
    {
        DB::table('accounting_approval_requests')->insert([
            'company_id' => $model->company_id,
            'approvable_type' => $model::class,
            'approvable_id' => $model->getKey(),
            'workflow_key' => $workflowKey,
            'status' => 'pending',
            'requested_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (in_array('status', $model->getFillable(), true)) {
            $model->update(['status' => 'pending_approval']);
        }
    }
}
