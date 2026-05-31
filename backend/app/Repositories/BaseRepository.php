<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository
{
    protected Model $model;

    public function findById(int $id): ?Model
    {
        return $this->model->find($id);
    }

    public function findOrFail(int $id): Model
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->update($data);

        return $model->fresh();
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }
}
