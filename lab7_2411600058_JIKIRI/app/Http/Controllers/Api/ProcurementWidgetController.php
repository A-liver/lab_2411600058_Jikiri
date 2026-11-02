<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProcurementWidgets;
use Illuminate\Http\JsonResponse;

class ProcurementWidgetController extends Controller
{
    public function __construct(private ProcurementWidgets $widgets) {}

    public function pendingOrders(): JsonResponse
    {
        return $this->respond($this->widgets->pendingOrders());
    }

    public function recentOrders(): JsonResponse
    {
        return $this->respond($this->widgets->recentOrders());
    }

    public function supplierPerformance(): JsonResponse
    {
        return $this->respond($this->widgets->supplierPerformance());
    }

    public function reorderAlerts(): JsonResponse
    {
        return $this->respond($this->widgets->reorderAlerts());
    }

    private function respond(array $data): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => ['generated_at_display' => now()->format('M j, Y g:i:s A')],
        ]);
    }
}