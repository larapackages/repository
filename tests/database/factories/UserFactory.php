<?php

use Faker\Generator;
use Larapackages\Tests\Models\User;

$factory->define(User::class, function (Generator $faker) {
    return [
        'name' => $faker->name,
        'email' => $faker->unique()->email,
        'password' => $faker->password,
    ];
});