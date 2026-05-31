<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Models\Unit;
use App\Traits\ApiResponse;

class UnitController extends Controller
{
    use ApiResponse;

    public function all()
    {
        return $this->success(['units' => Unit::orderBy('name')->get()]);
    }

    public function store(StoreUnitRequest $request)
    {
        $unit = Unit::create($request->validated());

        return $this->success(['unit' => $unit], '', 201);
    }

    public function update(UpdateUnitRequest $request, Unit $unit)
    {
        $unit->update($request->validated());

        return $this->success(['unit' => $unit->fresh()]);
    }

    public function destroy(Unit $unit)
    {
        if ($unit->products()->exists()) {
            return $this->error(__('pos.unit_has_products'), 422);
        }

        $unit->delete();

        return $this->success(message: __('pos.unit_deleted'));
    }
}
