<?php

use Faker\Generator;
use Larapackages\Tests\Models\Post;

$factory->define(Post::class, function (Generator $faker) {
    return [
        'title' => $faker->title,
    ];
});