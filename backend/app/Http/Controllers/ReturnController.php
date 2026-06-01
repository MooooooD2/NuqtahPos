<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReturnRequest;
use App\Models\SalesReturn;
use App\Services\Printing\ThermalPrinterService;
use App\Services\ReturnService;
use App\Services\SettingService;
use App\Traits\ApiResponse;
use App\Traits\AuditLog;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ReturnController extends Controller
{
    use ApiResponse;
    use AuditLog;

    public function __construct(
        private ReturnService $returnService,
        private ThermalPrinterService $printerService,
        private SettingService $settingService,
    ) {}

    public function index()
    {
        return view('returns.index');
    }

    public function all(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:10|max:200',
        ]);

        $query = SalesReturn::with('items')
            ->when($request->search, function ($q, $s) {
                $safe = addcslashes($s, '\%_');
                $q->where('return_number', 'like', "%{$safe}%")
                    ->orWhere('invoice_number', 'like', "%{$safe}%")
                    ->orWhere('customer_name', 'like', "%{$safe}%");
            })
            ->latest();

        $perPage = (int) ($request->per_page ?? 20);
        $returns = $query->paginate($perPage);

        return $this->success([
            'returns' => $returns->items(),
            'total' => $returns->total(),
            'current_page' => $returns->currentPage(),
            'last_page' => $returns->lastPage(),
        ]);
    }

    public function store(StoreReturnRequest $request)
    {
        // FIX-3: إضافة authorization check داخل الـ controller
        // (إضافة طبقة ثانية فوق الـ route middleware)
        $this->authorize('create', SalesReturn::class);

        try {
            $return = $this->returnService->processReturn($request->validated());
            $this->audit('return.created', SalesReturn::class, $return->id, [
                'total' => $return->total_amount,
                'invoice_id' => $return->invoice_id,
                'invoice_number' => $return->invoice_number,
            ]);

            $printResult = null;
            if ($this->settingService->get('print_on_return', false)) {
                try {
                    $printResult = $this->printerService->printReturnReceipt($return);
                } catch (Throwable $e) {
                    Log::warning('Auto-print failed for return', [
                        'return_id' => $return->id,
                        'error' => $e->getMessage(),
                    ]);
                    $printResult = ['success' => false, 'fallback' => 'browser', 'message' => $e->getMessage()];
                }
            }

            return $this->success(array_filter([
                'return' => $return,
                'print_result' => $printResult,
            ]), '', 201);
        } catch (QueryException $e) {
            Log::error('return.create_db_error', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return $this->error(__('pos.return_creation_failed'), 500);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
