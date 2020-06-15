<?php

namespace Larapackages\Tests\Repositories\Eloquent;

use Larapackages\Repository\Eloquent\Repository;
use Larapackages\Tests\Models\Post;

class PostRepository extends Repository
{
    /**
     * @inheritDoc
     */
    public function model()
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
        $this->model = $this->model->whereTitle($value);

        return $this;
    }

    /**
     * @return $this
     */
    public function orderByTitle()
    {
        $this->model = $this->model->orderBy('title', 'desc');

        return $this;
    }
}