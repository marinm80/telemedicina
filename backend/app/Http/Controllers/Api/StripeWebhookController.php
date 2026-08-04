<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Payments\ProcessStripeWebhookAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StripeWebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhook notifications.
     */
    public function handleWebhook(Request $request, ProcessStripeWebhookAction $action): JsonResponse
    {
        $payload = $request->all();

        if (empty($payload) || !isset($payload['id'], $payload['type'])) {
            return response()->json([
                'error_code' => 'INVALID_WEBHOOK_PAYLOAD',
                'message'    => 'Payload de webhook inválido o incompleto.'
            ], 400);
        }

        $result = $action->handle($payload);

        return response()->json([
            'status'   => $result['status'],
            'event_id' => $result['event_id'],
        ], 200);
    }
}
