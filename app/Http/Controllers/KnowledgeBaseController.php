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

    public function create()
    {
        $categories = Article::distinct()->pluck('category');

        return Inertia::render('KnowledgeBase/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'content' => 'required|string',
            'is_published' => 'nullable|boolean',
        ]);

        $article = Article::create([
            'title' => $validated['title'],
            'category' => $validated['category'],
            'content' => $validated['content'],
            'is_published' => $validated['is_published'] ?? true,
            'slug' => Str::slug($validated['title']) . '-' . rand(1000, 9999),
            'author_id' => $request->user()->id,
        ]);

        return redirect()->route('kb.show', $article->slug)->with('success', 'Artikel berhasil dibuat.');
    }

    public function edit(Request $request, Article $article)
    {
        $user = $request->user();
        if ($article->author_id !== $user->id && !$user->is_admin) {
            abort(403, 'Anda tidak memiliki akses untuk mengedit artikel ini.');
        }

        $categories = Article::distinct()->pluck('category');

        return Inertia::render('KnowledgeBase/Edit', [
            'article' => $article->load('author:id,name'),
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Article $article)
    {
        $user = $request->user();
        if ($article->author_id !== $user->id && !$user->is_admin) {
            abort(403, 'Anda tidak memiliki akses untuk mengedit artikel ini.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'content' => 'required|string',
            'is_published' => 'nullable|boolean',
        ]);

        $updateData = [
            'title' => $validated['title'],
            'category' => $validated['category'],
            'content' => $validated['content'],
            'is_published' => $validated['is_published'] ?? $article->is_published,
        ];

        if ($article->title !== $validated['title']) {
            $updateData['slug'] = Str::slug($validated['title']) . '-' . rand(1000, 9999);
        }

        $article->update($updateData);

        return redirect()->route('kb.show', $article->fresh()->slug)->with('success', 'Artikel berhasil diperbarui.');
    }

    public function destroy(Request $request, Article $article)
    {
        $user = $request->user();
        if ($article->author_id !== $user->id && !$user->is_admin) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus artikel ini.');
        }

        $article->delete();

        return redirect()->route('kb.index')->with('success', 'Artikel berhasil dihapus.');
    }
}
