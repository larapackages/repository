<?php

namespace Larapackages\Tests\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer   $id
 * @property string    $title
 * @property Carbon    $created_at
 * @property Carbon    $updated_at
 *
 * @property-read User $user
 */
class Post extends Model
{
    protected $table    = 'posts_test';
    protected $fillable = ['title'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}