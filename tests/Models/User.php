<?php

namespace Larapackages\Tests\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property integer                $id
 * @property string                 $name
 * @property string                 $email
 * @property string                 $password
 * @property Carbon                 $created_at
 * @property Carbon                 $updated_at
 * @property Carbon                 $deleted_at
 *
 * @property-read Collection|Post[] $posts
 */
class User extends Model
{
    use SoftDeletes;

    protected $table    = 'users_test';
    protected $fillable = ['name', 'email', 'password'];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}