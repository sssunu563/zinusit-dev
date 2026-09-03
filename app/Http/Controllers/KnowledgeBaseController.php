<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request)
    {
        $articles = Article::query()
            ->with('author:id,name')
            ->where('is_published', true)
            ->when($request->search, function($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($request->category, fn ($query, $category) => $query->where('category', $category))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $categories = Article::distinct()->pluck('category');

        return Inertia::render('KnowledgeBase/Index', [
            'articles' => $articles,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category'])
        ]);
    }

    public function show(Article $article)
    {
        $article->increment('view_count');
        $article->load('author:id,name');

        return Inertia::render('KnowledgeBase/Show', [
            'article' => $article,
            'related' => Article::where('category', $article->category)
                ->where('id', '!=', $article->id)
                ->limit(3)
                ->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'content' => 'required|string',
        ]);

        $article = Article::create([
            ...$validated,
            'slug' => Str::slug($validated['title']) . '-' . rand(1000, 9999),
            'author_id' => $request->user()->id,
        ]);

        return redirect()->route('kb.index')->with('success', 'Artikel berhasil dibuat.');
    }
}
