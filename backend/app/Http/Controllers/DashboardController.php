<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    public function index()
    {
        $masterId = config('tenancy.master_tenant');
        if ($masterId && tenancy()->tenant?->id === $masterId) {
            return redirect()->route('admin.cpanel');
        }

        return view('dashboard.index');
    }

    public function lowStock()
    {
        return response()->json($this->dashboardService->lowStockAlerts());
    }

    public function data()
    {
        return response()->json($this->dashboardService->getData());
    }
}
