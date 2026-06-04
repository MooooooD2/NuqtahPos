<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Services\ExpenseService;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use App\Traits\AuditLog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpenseController extends Controller
{
    use ApiResponse;
    use AuditLog;

    public function __construct(
        private ExpenseService $expenseService,
        private NotificationService $notifier,
    ) {}

    public function index()
    {
        return view('expenses.index');
    }

    public function all(Request $request)
    {
        $request->validate([
            'category_id' => 'nullable|integer|exists:expense_categories,id',
            'payment_method' => 'nullable|in:cash,card,transfer,wallet',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->success([
            'expenses' => $this->expenseService->all($request->only([
                'category_id', 'payment_method', 'date_from', 'date_to', 'per_page',
            ])),
        ]);
    }

    public function store(StoreExpenseRequest $request)
    {
        $this->authorize('create', Expense::class);

        try {
            $expense = $this->expenseService->create($request->validated());
            $this->audit('expense.created', Expense::class, (int) $expense->id, [
                'expense_number' => $expense->expense_number,
                'amount' => $expense->amount,
            ]);

            // Notify admins for expenses above 100 (configurable threshold)
            $threshold = (float) config('pos.expense_alert_threshold', 100);
            if ((float) $expense->amount >= $threshold) {
                try {
                    $category = $expense->category?->name ?? '';
                    $this->notifier->expenseRecorded($expense->title, (float) $expense->amount, $category);
                } catch (Throwable $e) {
                    Log::warning('expense.notification_failed', ['error' => $e->getMessage()]);
                }
            }

            return $this->success(['expense' => $expense], '', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        $this->authorize('update', $expense);

        try {
            $expense = $this->expenseService->update($expense, $request->validated());

            return $this->success(['expense' => $expense]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function destroy(Expense $expense)
    {
        $this->authorize('delete', $expense);
        $this->expenseService->delete($expense);
        $this->audit('expense.deleted', Expense::class, (int) $expense->id);

        return $this->success([], __('pos.expense_deleted'));
    }

    public function categories()
    {
        return $this->success(['categories' => $this->expenseService->categories()]);
    }

    public function summary(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        return $this->success($this->expenseService->summary(
            $request->date_from,
            $request->date_to,
        ));
    }
}
