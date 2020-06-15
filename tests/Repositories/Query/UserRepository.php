<?php

namespace Larapackages\Tests\Repositories\Query;

use Larapackages\Repository\Query\Repository;
use Larapackages\Tests\Models\User;

class UserRepository extends Repository
{
    /**
     * @inheritDoc
     */
    protected function baseModel(): string
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
        $this->query = $this->query->where('name', $value);

        return $this;
    }
}