<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Share;

class BlogController extends Controller
{
    // Get all posts with comments, likes, shares
    public function index()
    {
        $posts = Post::with(['comments.replies', 'likes', 'shares'])->orderBy('created_at', 'desc')->get();
        return response()->json($posts);
    }

    // Get a single post with comments, likes, shares
    public function show($id)
    {
        $post = Post::with(['comments.replies', 'likes', 'shares'])->findOrFail($id);
        return response()->json($post);
    }

    // Add a comment or reply
    public function comment(Request $request)
    {
        $request->validate([
            'post_id' => 'required|exists:posts,id',
            'comment' => 'required|string',
            'name' => 'nullable|string',
            'parent_id' => 'nullable|exists:comments,id'
        ]);

        $comment = Comment::create([
            'post_id' => $request->post_id,
            'parent_id' => $request->parent_id,
            'name' => $request->name ?? 'Anonymous',
            'comment' => $request->comment,
        ]);

        return response()->json($comment);
    }

    // Add a like to post or comment
    public function like(Request $request)
    {
        $request->validate([
            'post_id' => 'nullable|exists:posts,id',
            'comment_id' => 'nullable|exists:comments,id',
        ]);

        $user_ip = $request->ip();

        // Prevent duplicate like
        $existing = Like::where(function($q) use ($request) {
            if ($request->post_id) $q->where('post_id', $request->post_id);
            if ($request->comment_id) $q->where('comment_id', $request->comment_id);
        })->where('user_ip', $user_ip)->first();

        if ($existing) {
            return response()->json(['message' => 'Already liked'], 400);
        }

        $like = Like::create([
            'post_id' => $request->post_id,
            'comment_id' => $request->comment_id,
            'user_ip' => $user_ip,
        ]);

        return response()->json($like);
    }

    // Share a post
    public function share(Request $request)
    {
        $request->validate([
            'post_id' => 'required|exists:posts,id',
            'platform' => 'required|string',
        ]);

        $share = Share::create([
            'post_id' => $request->post_id,
            'platform' => $request->platform,
        ]);

        return response()->json($share);
    }
}
