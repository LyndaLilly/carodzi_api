<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = ['post_id', 'parent_id', 'name', 'comment'];

    // A comment belongs to a post
    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    // A comment can have replies
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at', 'asc');
    }

    // A comment can have likes
    public function likes()
    {
        return $this->hasMany(Like::class);
    }
}
