<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Category::query()
            ->withCount('products')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%' . $request->input('search') . '%'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $category = Category::create($this->validated($request));

        return redirect()
            ->route('categories.index')
            ->with('success', "Category \"{$category->name}\" was added.");
    }

    public function edit(Category $category): View
    {
        return view('categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return redirect()
            ->route('categories.index')
            ->with('success', "Category \"{$category->name}\" was updated.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        $count = $category->products()->count();

        if ($count > 0) {
            return redirect()
                ->route('categories.index')
                ->with('error', "\"{$category->name}\" still has {$count} product(s). Move or delete them first.");
        }

        $category->delete();

        return redirect()
            ->route('categories.index')
            ->with('success', "Category \"{$category->name}\" was deleted.");
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category)],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.unique' => 'A category with that name already exists.',
        ]);
    }
}
