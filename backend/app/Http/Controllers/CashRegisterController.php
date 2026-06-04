<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterSession;
use App\Services\CashRegisterService;
use App\Services\Printing\ThermalPrinterService;
use App\Services\SettingService;
use App\Traits\ApiResponse;
use App\Traits\AuditLog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CashRegisterController extends Controller
{
    use ApiResponse;
    use AuditLog;

    public function __construct(
        private CashRegisterService $cashRegisterService,
        private ThermalPrinterService $printerService,
        private SettingService $settingService,
    ) {}

    public function currentSession()
    {
        $session = $this->cashRegisterService->currentSession();
        if (! $session) {
            return $this->success(['session' => null]);
        }

        $stats = $session->getRelation('stats');

        return $this->success(['session' => array_merge($session->toArray(), (array) $stats)]);
    }

    public function open(Request $request)
    {
        $request->validate([
            'opening_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $session = $this->cashRegisterService->open($request->only(['opening_amount', 'notes']));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        }

        $this->audit('cash_session.opened', CashRegisterSession::class, $session->id, [
            'opening_amount' => $request->opening_amount,
        ]);

        return $this->success(['session' => $session], '', 201);
    }

    public function close(Request $request, int $id)
    {
        $request->validate([
            'actual_cash' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $session = CashRegisterSession::findOrFail($id);

        if ($session->cashier_id !== auth()->id() && ! auth()->user()?->hasRole('admin')) {
            return $this->error(__('pos.cash_session_not_yours'), 403);
        }

        try {
            $closed = $this->cashRegisterService->close($session, $request->only(['actual_cash', 'notes']));
        } catch (Exception $e) {
            $code = str_contains($e->getMessage(), 'آخر') ? 403 : 422;

            return $this->error($e->getMessage(), $code);
        }

        $this->audit('cash_session.closed', CashRegisterSession::class, $closed->id, [
            'actual' => $request->actual_cash,
        ]);

        $printResult = null;
        if ($this->settingService->get('print_on_shift_close', false)) {
            try {
                $printResult = $this->printerService->printShiftReport($closed);
            } catch (Throwable $e) {
                Log::warning('Auto-print failed for shift report', [
                    'session_id' => $closed->id,
                    'error' => $e->getMessage(),
                ]);
                $printResult = ['success' => false, 'fallback' => 'browser', 'message' => $e->getMessage()];
            }
        }

        return $this->success(array_filter([
            'session' => $closed,
            'print_result' => $printResult,
        ]));
    }

    public function recordMovement(Request $request, int $id)
    {
        $request->validate([
            'type' => 'required|in:deposit,withdrawal',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:500',
        ]);

        $session = CashRegisterSession::findOrFail($id);

        if ($session->cashier_id !== auth()->id() && ! auth()->user()?->hasRole('admin')) {
            return $this->error(__('pos.cash_session_not_yours'), 403);
        }

        try {
            ['movement' => $movement, 'warnings' => $warnings] = $this->cashRegisterService->recordMovement(
                $session,
                $request->type,
                (float) $request->amount,
                $request->reason,
            );
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        }

        $this->audit('cash_session.movement', CashRegisterSession::class, $session->id, [
            'type' => $request->type,
            'amount' => $request->amount,
        ]);

        return $this->success(['movement' => $movement, 'warnings' => $warnings], '', 201);
    }

    public function history(Request $request)
    {
        $request->validate([
            'cashier_id' => 'nullable|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'status' => 'nullable|in:open,closed',
        ]);

        $paginator = $this->cashRegisterService->history(
            $request->only(['cashier_id', 'date_from', 'date_to', 'status']),
        );

        return $this->success([
            'sessions' => $paginator->items(),
            'total'    => $paginator->total(),
        ]);
    }
}
