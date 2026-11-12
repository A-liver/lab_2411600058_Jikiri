<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(Request $request): View
    {
        $brands = Brand::query()
            ->withCount('products')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%' . $request->input('search') . '%'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('brands.index', compact('brands'));
    }

    public function create(): View
    {
        return view('brands.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $brand = Brand::create($this->validated($request));

        return redirect()
            ->route('brands.index')
            ->with('success', "Brand \"{$brand->name}\" was added.");
    }

    public function edit(Brand $brand): View
    {
        return view('brands.edit', compact('brand'));
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $brand->update($this->validated($request, $brand));

        return redirect()
            ->route('brands.index')
            ->with('success', "Brand \"{$brand->name}\" was updated.");
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $count = $brand->products()->count();

        if ($count > 0) {
            return redirect()
                ->route('brands.index')
                ->with('error', "\"{$brand->name}\" still has {$count} product(s). Move or delete them first.");
        }

        $brand->delete();

        return redirect()
            ->route('brands.index')
            ->with('success', "Brand \"{$brand->name}\" was deleted.");
    }

    private function validated(Request $request, ?Brand $brand = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('brands', 'name')->ignore($brand)],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.unique' => 'A brand with that name already exists.',
        ]);
    }
}
