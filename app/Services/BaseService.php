<?php
 
namespace App\Services;

interface BaseService 
{
    public function create(array $data);

    public function update(string|int $id, array $data);

    public function delete(string|int $id): bool;

    public function get(string|int $id);

    public function query(array $filters = []);
}