<?php

namespace App\Services;

use App\Repositories\CategorieRepository;
use Illuminate\Database\Eloquent\Collection;

class CategorieService implements BaseService
{
    /**
     * @var UserRepository
     */
    private $repo;

    public function __construct(CategorieRepository $repo)
    {
        $this->repo = $repo;
    }

    public function create($data)
    {
        return $this->repo->create($data);
    }

    public function update($id, $data)
    {
        return $this->repo->update($id, $data);
    }

    public function delete($id): bool
    {
        return $this->repo->delete($id);
    }

    public function get($id)
    {
        return $this->repo->findByPK($id);
    }

    public function query($params = [])
    {
        return $this->repo->query($params);
    }

    public function all(): Collection
    {
        return $this->repo->all();
    }
}
