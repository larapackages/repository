<?php

namespace Larapackages\Tests\Repositories\Eloquent;

use Larapackages\Repository\Eloquent\Repository;
use Larapackages\Tests\Models\User;

class UserRepository extends Repository
{
    /**
     * @inheritDoc
     */
    public function model()
    {
        return User::class;
    }

    /**
     * @param $value
     *
     * @return $this
     */
    public function filterByName($value)
    {
        $this->model = $this->model->whereName($value);

        return $this;
    }
}