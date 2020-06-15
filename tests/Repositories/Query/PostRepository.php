<?php

namespace Larapackages\Tests\Repositories\Query;

use Larapackages\Repository\Query\Repository;
use Larapackages\Tests\Models\Post;

class PostRepository extends Repository
{
    /**
     * @inheritDoc
     */
    protected function baseModel(): string
    {
        return Post::class;
    }

    /**
     * @param $value
     *
     * @return $this
     */
    public function filterByTitle($value)
    {
        $this->query = $this->query->where(
            $this->column('title'),
            $value
        );

        return $this;
    }

    /**
     * @return $this
     */
    public function orderByTitle()
    {
        $this->query = $this->query->orderBy(
            $this->column('title')
        );

        return $this;
    }
}