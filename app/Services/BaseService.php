<?php

namespace App\Services;

interface BaseService
{
    public function create(array $data);

    public function update(int|string $id, array $data);

    public function delete(int|string $id): bool;

    public function get(int|string $id);

    public function query(array $filters = [], int $page = 0);
}
