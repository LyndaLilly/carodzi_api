<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'content', 'image'];

    // A post has many comments
    public function comments()
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->orderBy('created_at', 'desc');
    }

    // A post has many likes
    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    // A post has many shares
    public function shares()
    {
        return $this->hasMany(Share::class);
    }
}
