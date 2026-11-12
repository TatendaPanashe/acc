<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookEventController extends Controller
{
    public function store(Request $request, string $source): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['nullable', 'max:120'],
            'event_type' => ['required', 'max:120'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'data' => ['required', 'array'],
        ]);

        $event = WebhookEvent::create([
            'company_id' => $data['company_id'] ?? null,
            'event_type' => $data['event_type'],
            'source' => $source,
            'external_id' => $data['event_id'] ?? null,
            'payload' => $data['data'],
            'status' => 'received',
        ]);

        return response()->json([
            'id' => $event->id,
            'status' => $event->status,
            'received_at' => $event->created_at?->toISOString(),
        ], 202);
    }
}
