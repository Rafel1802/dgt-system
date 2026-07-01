<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::with(['categoryModel', 'creator']);

        if ($s = $request->get('search')) {
            $query->search($s);
        }
        if ($cat = $request->get('category_id')) {
            $query->where('category_id', $cat);
        }
        if ($status = $request->get('status')) {
            $query->where('is_active', $status === 'active');
        }

        $products   = $query->orderBy('name')->paginate(30)->withQueryString();
        $categories = ProductCategory::active()->ordered()->get();

        return view('crm.products.index', compact('products', 'categories'));
    }

    public function create(): View
    {
        return view('crm.products.create', [
            'categories' => ProductCategory::active()->ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'sku'         => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'description' => ['nullable', 'string'],
            'brand'       => ['nullable', 'string', 'max:100'],
            'model'       => ['nullable', 'string', 'max:100'],
            'year'        => ['nullable', 'string', 'max:10'],
            'condition'   => ['nullable', 'string', 'in:new,used,refurbished'],
            'price'       => ['nullable', 'numeric', 'min:0'],
            'currency'    => ['nullable', 'string', 'max:3'],
            'is_active'   => ['boolean'],
            'image'       => ['nullable', 'image', 'max:2048'],
            'image_url'   => ['nullable', 'url', 'max:2000'],
        ]);

        $validated['is_active']  = $request->boolean('is_active', true);
        $validated['status']     = $validated['is_active'] ? 'active' : 'inactive';
        $validated['created_by'] = auth()->id();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        } elseif ($request->filled('image_url')) {
            $validated['image'] = $request->input('image_url');
        }
        unset($validated['image_url']);

        $product = Product::create($validated);

        return redirect()->route('crm.products.index')
            ->with('success', "Product '{$product->name}' created.");
    }

    public function edit(Product $product): View
    {
        return view('crm.products.edit', [
            'product'    => $product,
            'categories' => ProductCategory::active()->ordered()->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'sku'         => ['nullable', 'string', 'max:100', 'unique:products,sku,' . $product->id],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'description' => ['nullable', 'string'],
            'brand'       => ['nullable', 'string', 'max:100'],
            'model'       => ['nullable', 'string', 'max:100'],
            'year'        => ['nullable', 'string', 'max:10'],
            'condition'   => ['nullable', 'string', 'in:new,used,refurbished'],
            'price'       => ['nullable', 'numeric', 'min:0'],
            'currency'    => ['nullable', 'string', 'max:3'],
            'is_active'   => ['boolean'],
            'image'       => ['nullable', 'image', 'max:2048'],
            'image_url'   => ['nullable', 'url', 'max:2000'],
        ]);

        $validated['is_active']  = $request->boolean('is_active', true);
        $validated['status']     = $validated['is_active'] ? 'active' : 'inactive';
        $validated['updated_by'] = auth()->id();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        } elseif ($request->filled('image_url')) {
            $validated['image'] = $request->input('image_url');
        }
        unset($validated['image_url']);

        $product->update($validated);

        return redirect()->route('crm.products.index')
            ->with('success', "Product '{$product->name}' updated.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();
        return redirect()->route('crm.products.index')
            ->with('success', 'Product deleted.');
    }

    // ── Category management ───────────────────────────────────────────────────

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:product_categories,name'],
            'description' => ['nullable', 'string'],
            'color'       => ['nullable', 'string', 'max:20'],
            'icon_url'    => ['nullable', 'url', 'max:1000'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        ProductCategory::create($validated);

        return back()->with('success', "Category '{$validated['name']}' created.");
    }

    public function updateCategory(Request $request, ProductCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:product_categories,name,' . $category->id],
            'description' => ['nullable', 'string'],
            'color'       => ['nullable', 'string', 'max:20'],
            'icon_url'    => ['nullable', 'url', 'max:1000'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $category->update($validated);

        return back()->with('success', "Category '{$validated['name']}' updated.");
    }

    public function destroyCategory(ProductCategory $category): RedirectResponse
    {
        $name = $category->name;
        $category->delete();
        return back()->with('success', "Category '{$name}' deleted. Products in this category were kept.");
    }

    // ── Import ────────────────────────────────────────────────────────────────

    public function downloadTemplate()
    {
        $headers = ['Content-Type' => 'text/csv'];
        $filename = 'products_template.csv';

        $columns = ['name', 'sku', 'category_name', 'description', 'brand', 'model', 'year', 'condition', 'price', 'currency'];
        $example = ['Example Product', 'SKU-001', 'Category Name', 'Product description', 'BrandName', 'ModelX', '2023', 'new', '1000.00', 'AUD'];

        $callback = function () use ($columns, $example) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fputcsv($file, $example);
            fclose($file);
        };

        return response()->stream($callback, 200, array_merge($headers, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]));
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:5120'],
            'google_sheet_url' => ['nullable', 'url'],
        ]);

        if (!$request->hasFile('csv_file') && !$request->filled('google_sheet_url')) {
            return back()->with('error', 'Please provide a CSV file or Google Sheet URL.');
        }

        $handle = null;
        if ($request->filled('google_sheet_url')) {
            $url = $request->input('google_sheet_url');
            if (str_contains($url, '/edit?usp=sharing') || str_contains($url, '/edit#gid=') || str_contains($url, '/edit')) {
                $url = preg_replace('/\/edit.*$/', '/export?format=csv', $url);
            }
            $handle = @fopen($url, 'r');
            if (!$handle) {
                return back()->with('error', 'Could not read from Google Sheet URL. Make sure it is public (Anyone with the link can view).');
            }
        } else {
            $file    = $request->file('csv_file');
            $handle  = fopen($file->getRealPath(), 'r');
        }

        $headers = fgetcsv($handle); // skip header row

        $count  = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            // handle empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            try {
                // If headers count doesn't match row count, pad or slice the row array.
                $row = array_slice(array_pad($row, count($headers), ''), 0, count($headers));
                $data = array_combine($headers, $row);

                // Find or create category
                $catId = null;
                $catNameRaw = trim($data['category_name'] ?? '');
                if (!empty($catNameRaw)) {
                    $cat = ProductCategory::firstOrCreate(
                        ['slug' => Str::slug($catNameRaw)],
                        ['name' => $catNameRaw, 'color' => '#6366f1']
                    );
                    $catId = $cat->id;
                }

                $sku = !empty($data['sku']) ? $data['sku'] : null;
                $name = !empty($data['name']) ? $data['name'] : 'Unnamed Product';

                // If SKU is provided, match by SKU. Otherwise, match by name and null SKU to avoid updating random products.
                $matchAttrs = $sku ? ['sku' => $sku] : ['name' => $name, 'sku' => null];

                $values = [
                    'name'        => $name,
                    'category_id' => $catId,
                    'category'    => 'other', // fallback for old enum column
                    'description' => $data['description'] ?? null,
                    'brand'       => $data['brand'] ?? null,
                    'model'       => $data['model'] ?? null,
                    'year'        => $data['year'] ?? null,
                    'condition'   => in_array($data['condition'] ?? '', ['new','used','refurbished']) ? $data['condition'] : 'used',
                    'price'       => is_numeric($data['price'] ?? null) ? $data['price'] : null,
                    'currency'    => !empty($data['currency']) ? $data['currency'] : 'AUD',
                    'is_active'   => true,
                    'status'      => 'active',
                    'created_by'  => auth()->id(),
                    'updated_by'  => auth()->id(),
                ];

                if (!empty($data['image_url'])) {
                    $values['image'] = $data['image_url'];
                }

                $product = Product::withTrashed()->updateOrCreate(
                    $matchAttrs,
                    $values
                );

                if ($product->trashed()) {
                    $product->restore();
                }
                $count++;
            } catch (\Exception $e) {
                $errors[] = "Row error: " . $e->getMessage();
            }
        }
        fclose($handle);

        $msg = "Imported {$count} products.";
        if ($errors) {
            $msg .= ' Some rows had errors: ' . implode('; ', array_slice($errors, 0, 3));
        }

        return redirect()->route('crm.products.index')->with('success', $msg);
    }

    public function export()
    {
        $products = Product::with('categoryModel')->get();
        $filename = 'products_export_' . date('Ymd_His') . '.csv';
        $headers = ['Content-Type' => 'text/csv'];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');
            $columns = ['name', 'sku', 'category_name', 'description', 'brand', 'model', 'year', 'condition', 'price', 'currency'];
            fputcsv($file, $columns);

            foreach ($products as $product) {
                fputcsv($file, [
                    $product->name,
                    $product->sku,
                    $product->categoryModel?->name ?? $product->category_name,
                    $product->description,
                    $product->brand,
                    $product->model,
                    $product->year,
                    $product->condition,
                    $product->price,
                    $product->currency,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, array_merge($headers, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]));
    }

    // ── AJAX search ───────────────────────────────────────────────────────────

    public function search(Request $request): JsonResponse
    {
        $term = $request->get('q', '');
        $products = Product::active()
            ->when($term, fn($q) => $q->search($term))
            ->with('categoryModel')
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'sku', 'category_id', 'price', 'currency']);

        return response()->json($products->map(fn($p) => [
            'id'            => $p->id,
            'name'          => $p->name,
            'sku'           => $p->sku,
            'category_name' => $p->category_name,
            'label'         => $p->name . ($p->sku ? ' (' . $p->sku . ')' : ''),
        ]));
    }
}
