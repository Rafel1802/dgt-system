@extends('layouts.app')
@section('title', 'Products')
@section('page_title', 'Products')

@push('styles')
<style>
.product-card {
    transition: all 0.18s ease;
    border: 1px solid transparent;
}
.product-card:hover {
    border-color: var(--color-indigo-200, #c7d2fe);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(99,102,241,0.10);
}
.category-badge {
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    padding: 2px 8px;
    border-radius: 999px;
}
</style>
@endpush

@section('content')
<div class="animate-fade-in" x-data="productPage()">

  {{-- ── Page Header ─────────────────────────────────────────────────────── --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
      <h1 class="text-xl font-bold text-slate-800">Product Catalog</h1>
      <p class="text-slate-400 text-sm mt-0.5">Manage products used across all CRM modules</p>
    </div>
    <div class="flex gap-2 flex-wrap">
      {{-- Import --}}
      <button @click="showImport = !showImport" type="button" class="btn btn-secondary text-sm">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
        Import
      </button>
      {{-- Manage Categories --}}
      <button @click="openNewCat()" type="button" class="btn btn-secondary text-sm" id="btn-manage-categories">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 13.5V3.75m0 9.75a1.5 1.5 0 0 1 0 3m0-3a1.5 1.5 0 0 0 0 3m0 3.75V16.5m12-3V3.75m0 9.75a1.5 1.5 0 0 1 0 3m0-3a1.5 1.5 0 0 0 0 3m0 3.75V16.5m-6-9V3.75m0 3.75a1.5 1.5 0 0 1 0 3m0-3a1.5 1.5 0 0 0 0 3m0 9.75V10.5"/></svg>
        Manage Categories
      </button>
      {{-- Add Product --}}
      <a href="{{ route('crm.products.create') }}" class="btn btn-primary text-sm" id="btn-new-product">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        New Product
      </a>
    </div>
  </div>

  {{-- ── Flash Messages ──────────────────────────────────────────────────── --}}
  @if(session('success'))
  <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm font-medium flex items-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
    {{ session('success') }}
  </div>
  @endif
  @if(session('error'))
  <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 text-sm font-medium flex items-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
    {{ session('error') }}
  </div>
  @endif

  {{-- ── Import Form (collapsible) ──────────────────────────────────────── --}}
  <div x-show="showImport" x-transition x-cloak class="card p-5 mb-5">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
      <div>
        <h3 class="font-semibold text-slate-800">Import Products</h3>
        <p class="text-xs text-slate-400 mt-1">Preview Google Sheets or CSV rows before they are saved.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a href="{{ route('crm.products.import.template.xlsx') }}" class="btn btn-primary text-sm">Google Sheets Template</a>
        <a href="{{ route('crm.products.import.template') }}" class="btn btn-secondary text-sm">CSV Template</a>
      </div>
    </div>
    <form method="POST" action="{{ route('crm.products.import') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
      @csrf
      <div class="flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[200px]">
          <label class="form-label">Google Sheets URL (Public Link)</label>
          <input type="url" name="google_sheet_url" placeholder="https://docs.google.com/spreadsheets/d/.../edit?usp=sharing" class="form-input">
        </div>
        <div class="flex items-center gap-4 text-slate-400 font-bold uppercase text-xs">
            OR
        </div>
        <div class="flex-1 min-w-[200px]">
          <label class="form-label">Upload CSV File</label>
          <input type="file" name="csv_file" accept=".csv,.txt" class="form-input">
        </div>
      </div>
      
      <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3">
        <button type="submit" class="btn btn-primary">Preview Import</button>
        <span class="text-xs text-slate-400">Nothing is saved until you confirm after preview.</span>
      </div>
    </form>
    <div class="mt-4 rounded-xl bg-slate-50 border border-slate-200 p-3">
      <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Required template columns</p>
      <p class="text-sm text-slate-700 mt-1 font-mono">NO, name, sku, category_name, brand, price, currency, product_image</p>
      <p class="text-xs text-slate-400 mt-2">Duplicate products are skipped. Category names must match existing categories; case, spacing, and small typos are handled automatically.</p>
      <p class="text-xs text-slate-400 mt-1">For Google Sheets, download the template above, upload/open it in Sheets, then paste its public share URL here when ready.</p>
      @if($categories->count())
      <div class="flex flex-wrap gap-1.5 mt-3">
        @foreach($categories as $cat)
          <span class="category-badge bg-white text-slate-600 border border-slate-200">{{ $cat->name }}</span>
        @endforeach
      </div>
      @endif
    </div>
  </div>

  @if($preview = session('product_import_preview'))
  <div class="card p-5 mb-5 border border-indigo-100">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">Import Preview</p>
        <h3 class="font-bold text-slate-800 mt-1">{{ $preview['source'] }}</h3>
        <p class="text-xs text-slate-400 mt-1">Review the result before products are added to CRM.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a href="{{ route('crm.products.index') }}" class="btn btn-secondary">Cancel</a>
        <form method="POST" action="{{ route('crm.products.import') }}" data-confirm="Import the ready products now? Duplicates and failed rows will not be saved.">
          @csrf
          <input type="hidden" name="confirm_import" value="1">
          <button type="submit" class="btn btn-primary" {{ $preview['importable_count'] < 1 ? 'disabled' : '' }}>
            Import {{ $preview['importable_count'] }} Ready Products
          </button>
        </form>
      </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-4 mb-4">
      <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
        <p class="text-xs font-bold text-slate-400 uppercase">Rows</p>
        <p class="text-2xl font-black text-slate-800">{{ $preview['total'] }}</p>
      </div>
      <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3">
        <p class="text-xs font-bold text-emerald-500 uppercase">Ready</p>
        <p class="text-2xl font-black text-emerald-700">{{ $preview['importable_count'] }}</p>
      </div>
      <div class="rounded-xl bg-amber-50 border border-amber-200 p-3">
        <p class="text-xs font-bold text-amber-500 uppercase">Duplicates</p>
        <p class="text-2xl font-black text-amber-700">{{ $preview['duplicate_count'] }}</p>
      </div>
      <div class="rounded-xl bg-rose-50 border border-rose-200 p-3">
        <p class="text-xs font-bold text-rose-500 uppercase">Failed</p>
        <p class="text-2xl font-black text-rose-700">{{ $preview['failed_count'] }}</p>
      </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200">
      <table class="w-full text-xs">
        <thead class="bg-slate-50 text-slate-500 uppercase tracking-wide">
          <tr>
            <th class="px-3 py-2 text-left">Row</th>
            <th class="px-3 py-2 text-left">Status</th>
            <th class="px-3 py-2 text-left">Product</th>
            <th class="px-3 py-2 text-left">SKU</th>
            <th class="px-3 py-2 text-left">Category</th>
            <th class="px-3 py-2 text-left">Brand</th>
            <th class="px-3 py-2 text-left">Price</th>
            <th class="px-3 py-2 text-left">Image</th>
            <th class="px-3 py-2 text-left">Message</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @foreach($preview['rows'] as $row)
          <tr class="bg-white">
            <td class="px-3 py-2 text-slate-400">{{ $row['row_number'] }}</td>
            <td class="px-3 py-2">
              @if($row['status'] === 'ready')
                <span class="category-badge bg-emerald-100 text-emerald-700">Ready</span>
              @elseif($row['status'] === 'duplicate')
                <span class="category-badge bg-amber-100 text-amber-700">Duplicate</span>
              @else
                <span class="category-badge bg-rose-100 text-rose-700">Failed</span>
              @endif
            </td>
            <td class="px-3 py-2 font-semibold text-slate-700">{{ $row['name'] ?: '—' }}</td>
            <td class="px-3 py-2 font-mono text-slate-500">{{ $row['sku'] ?: '—' }}</td>
            <td class="px-3 py-2 text-slate-600">{{ $row['category_name'] ?: '—' }}</td>
            <td class="px-3 py-2 text-slate-500">{{ $row['brand'] ?: '—' }}</td>
            <td class="px-3 py-2 text-slate-500">
              {{ $row['price'] !== null ? number_format((float) $row['price'], 2) . ' ' . $row['currency'] : '—' }}
            </td>
            <td class="px-3 py-2 text-slate-500">
              @if(!empty($row['product_image']))
                <a href="{{ $row['product_image'] }}" target="_blank" class="text-indigo-600 hover:underline">View</a>
              @else
                —
              @endif
            </td>
            <td class="px-3 py-2 text-slate-500">{{ $row['message'] }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @if($preview['total'] > count($preview['rows']))
      <p class="text-xs text-slate-400 mt-2">Showing the first {{ count($preview['rows']) }} rows only.</p>
    @endif
  </div>
  @endif

  {{-- ── Manage Categories Modal ────────────────────────────────────────── --}}
  <div x-show="showCatModal" x-transition x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col" @click.away="showCatModal = false">
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-bold text-slate-800 text-lg">Manage Categories</h3>
        <button type="button" @click="showCatModal = false" class="text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-6 overflow-y-auto">
        {{-- Add/Edit Form --}}
        <div class="mb-8 p-5 bg-slate-50/50 rounded-xl border border-slate-200">
          <div class="flex items-center justify-between mb-4">
              <h4 class="font-bold text-slate-700" x-text="editCatId ? 'Edit Category' : 'Create New Category'"></h4>
              <button x-show="editCatId" @click="openNewCat()" type="button" class="text-xs text-blue-600 hover:underline">Cancel Edit</button>
          </div>
          <form method="POST" :action="editCatId ? `/crm/products/categories/${editCatId}` : '{{ route('crm.products.categories.store') }}'" class="flex flex-wrap items-end gap-3">
            @csrf
            <input type="hidden" name="_method" value="PUT" :disabled="!editCatId">
            <div class="flex-1 min-w-[180px]">
              <label class="form-label">Category Name</label>
              <input type="text" name="name" x-model="catForm.name" class="form-input" placeholder="e.g. Excavators" required>
            </div>
            <div class="min-w-[170px]">
              <label class="form-label">Color</label>
              <div class="flex items-center gap-2">
                <span class="h-10 w-10 rounded-xl border border-slate-200 shadow-inner" :style="`background:${catForm.color || '#6366f1'}`"></span>
                <input type="text" name="color" x-model="catForm.color" class="form-input w-28 py-2 text-sm font-mono" placeholder="#6366f1" maxlength="20">
              </div>
              <div class="mt-2 flex flex-wrap gap-1.5">
                <template x-for="swatch in colorSwatches" :key="swatch">
                  <button type="button" @click="catForm.color = swatch" class="h-6 w-6 rounded-lg border border-white shadow ring-1 ring-slate-200" :style="`background:${swatch}`" :title="swatch"></button>
                </template>
              </div>
            </div>
            <div class="flex-1 min-w-[200px]">
              <label class="form-label">Icon URL (optional)</label>
              <input type="url" name="icon_url" x-model="catForm.icon_url" class="form-input" placeholder="https://.../icon.png">
            </div>
            <div class="w-full">
              <label class="form-label">Description (optional)</label>
              <input type="text" name="description" x-model="catForm.description" class="form-input" placeholder="Short description">
            </div>
            <button type="submit" class="btn btn-primary mt-2" x-text="editCatId ? 'Update Category' : 'Save Category'"></button>
          </form>
        </div>

        {{-- List of Categories --}}
        <h4 class="font-bold text-slate-700 mb-3">Available Categories</h4>
        <div class="border border-slate-200 rounded-xl overflow-hidden divide-y divide-slate-100">
          @forelse($categories as $cat)
          <div class="flex items-center justify-between p-3.5 bg-white hover:bg-slate-50 transition-colors">
            <div class="flex items-center gap-3">
              <span class="w-4 h-4 rounded-full flex-shrink-0 shadow-inner" style="background: {{ $cat->color }}"></span>
              @if($cat->icon_url)
                <img src="{{ $cat->icon_url }}" class="h-5 w-5 object-contain flex-shrink-0" alt="">
              @endif
              <div>
                <p class="font-medium text-slate-800 text-sm">{{ $cat->name }}</p>
                <p class="text-xs text-slate-400">{{ $cat->products()->count() }} products</p>
              </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <div class="flex items-center rounded-lg border border-slate-200 bg-slate-50 overflow-hidden">
                <form action="{{ route('crm.products.categories.reorder', $cat) }}" method="POST">
                  @csrf @method('PATCH')
                  <input type="hidden" name="direction" value="up">
                  <button type="submit"
                          title="Move up"
                          class="h-8 w-8 inline-flex items-center justify-center text-slate-500 hover:bg-white hover:text-indigo-600 disabled:opacity-35 disabled:hover:bg-transparent disabled:hover:text-slate-500"
                          {{ $loop->first ? 'disabled' : '' }}>
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m5 15 7-7 7 7"/></svg>
                  </button>
                </form>
                <form action="{{ route('crm.products.categories.reorder', $cat) }}" method="POST" class="border-l border-slate-200">
                  @csrf @method('PATCH')
                  <input type="hidden" name="direction" value="down">
                  <button type="submit"
                          title="Move down"
                          class="h-8 w-8 inline-flex items-center justify-center text-slate-500 hover:bg-white hover:text-indigo-600 disabled:opacity-35 disabled:hover:bg-transparent disabled:hover:text-slate-500"
                          {{ $loop->last ? 'disabled' : '' }}>
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/></svg>
                  </button>
                </form>
              </div>
              <button type="button" @click="editCat({{ $cat->id }}, '{{ addslashes($cat->name) }}', '{{ $cat->color }}', '{{ $cat->icon_url }}', '{{ addslashes($cat->description) }}')" class="btn btn-secondary btn-sm text-xs px-3">Edit</button>
              <form action="{{ route('crm.products.categories.destroy', $cat) }}" method="POST" data-confirm="Are you sure you want to delete the category '{{ addslashes($cat->name) }}'? (Products inside will NOT be deleted, they will just become uncategorized)">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm text-xs px-3">Delete</button>
              </form>
            </div>
          </div>
          @empty
          <div class="p-8 text-center text-sm text-slate-500">No categories created yet.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>

  {{-- ── Filters & Search ─────────────────────────────────────────────── --}}
  <form method="GET" action="{{ route('crm.products.index') }}" class="card p-4 mb-5" x-data>
    <div class="flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-[200px]">
        <label class="form-label text-xs">Search</label>
        <div class="relative">
          <input type="search" name="search" value="{{ request('search') }}"
                 @input.debounce.500ms="$el.closest('form').submit()"
                 placeholder="Name or SKU…" class="form-input pl-9 py-2 text-sm" id="product-search">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
        </div>
      </div>
      <div>
        <label class="form-label text-xs">Category</label>
        <select name="category_id" class="form-input py-2 text-sm" id="filter-category" @change="$el.closest('form').submit()">
          <option value="">All Categories</option>
          @foreach($categories as $cat)
            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
              {{ $cat->name }}
            </option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="form-label text-xs">Status</label>
        <select name="status" class="form-input py-2 text-sm" id="filter-status" @change="$el.closest('form').submit()">
          <option value="">All</option>
          <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
          <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
      </div>
    </div>
  </form>

  {{-- ── Category Filter Pills ─────────────────────────────────────────── --}}
  @if($categories->count())
  <div class="flex gap-2 flex-wrap mb-4">
    <a href="{{ route('crm.products.index', array_merge(request()->query(), ['category_id' => ''])) }}"
       class="category-badge {{ !request('category_id') ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
      All
    </a>
    @foreach($categories as $cat)
    <a href="{{ route('crm.products.index', array_merge(request()->query(), ['category_id' => $cat->id])) }}"
       class="category-badge flex items-center gap-1.5 w-max {{ request('category_id') == $cat->id ? 'text-white' : 'text-slate-600 hover:opacity-80' }}"
       style="{{ request('category_id') == $cat->id ? 'background:'.$cat->color.';' : 'background:'.$cat->color.'22;border:1px solid '.$cat->color.'44;' }}">
      @if($cat->icon_url)
      <img src="{{ $cat->icon_url }}" class="h-3.5 w-3.5 object-contain" alt="">
      @endif
      {{ $cat->name }}
      <span class="opacity-70">({{ $cat->products()->count() }})</span>
    </a>
    @endforeach
  </div>
  @endif

  {{-- ── Product Table ─────────────────────────────────────────────────── --}}
  <div class="card p-0 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-5 py-3 text-left">Product</th>
            <th class="px-4 py-3 text-left">SKU</th>
            <th class="px-4 py-3 text-left">Category</th>
            <th class="px-4 py-3 text-left">Price</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($products as $product)
          <tr class="hover:bg-slate-50/70 transition-colors">
            <td class="px-5 py-3">
              <div class="flex items-center gap-3">
                @if($product->image_url)
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
                     @dblclick="window.open('{{ $product->image_url }}', '_blank')"
                     title="Double click to view full image"
                     class="w-9 h-9 rounded-lg object-cover flex-shrink-0 bg-slate-100 cursor-pointer hover:opacity-80 transition">
                @else
                <div class="w-9 h-9 rounded-lg flex-shrink-0 bg-slate-100 border border-slate-200 border-dashed"></div>
                @endif
                <div>
                  <p class="font-semibold text-slate-800">{{ $product->name }}</p>
                  @if($product->brand || $product->model)
                  <p class="text-xs text-slate-400">{{ $product->brand }} {{ $product->model }}</p>
                  @endif
                </div>
              </div>
            </td>
            <td class="px-4 py-3 font-mono text-xs text-slate-600">
              {{ $product->sku ?? '—' }}
            </td>
            <td class="px-4 py-3">
              @if($product->categoryModel)
              <span class="category-badge text-white flex items-center gap-1.5 w-max" style="background:{{ $product->categoryModel->color }}">
                @if($product->categoryModel->icon_url)
                  <img src="{{ $product->categoryModel->icon_url }}" class="h-3 w-3 object-contain" alt="">
                @endif
                {{ $product->categoryModel->name }}
              </span>
              @elseif($product->category)
              <span class="category-badge bg-slate-100 text-slate-600 w-max">
                {{ $product->category_name }}
              </span>
              @else
              <span class="text-slate-300 text-xs">Uncategorized</span>
              @endif
            </td>
            <td class="px-4 py-3 text-xs text-slate-600">
              @if($product->price)
                {{ number_format($product->price, 2) }} {{ $product->currency }}
              @else
                <span class="text-slate-300">—</span>
              @endif
            </td>
            <td class="px-4 py-3">
              @if($product->is_active)
                <span class="category-badge bg-emerald-100 text-emerald-700">Active</span>
              @else
                <span class="category-badge bg-slate-100 text-slate-500">Inactive</span>
              @endif
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-1 justify-end">
                <a href="{{ route('crm.products.edit', $product) }}"
                   class="btn btn-secondary btn-icon" style="width:28px;height:28px;" title="Edit">
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                </a>
                <form method="POST" action="{{ route('crm.products.destroy', $product) }}"
                      data-confirm="Delete {{ addslashes($product->name) }}?" class="inline">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-secondary btn-icon text-red-400 hover:text-red-600"
                          style="width:28px;height:28px;" title="Delete">
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center py-16">
              <div class="text-5xl mb-3">📦</div>
              <p class="text-slate-500 font-medium">No products found</p>
              <p class="text-slate-400 text-xs mt-1">Create your first product or import from CSV</p>
              <div class="flex gap-3 justify-center mt-4">
                <a href="{{ route('crm.products.create') }}" class="btn btn-primary text-sm">+ New Product</a>
                <a href="{{ route('crm.products.import.template') }}" class="btn btn-secondary text-sm">Download Template</a>
              </div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($products->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">{{ $products->links() }}</div>
    @endif
  </div>

</div>

@push('scripts')
<script>
function productPage() {
    return {
        showImport: false,
        showCatModal: false,
        editCatId: null,
        catForm: {
            name: '',
            color: '#6366f1',
            icon_url: '',
            description: ''
        },
        colorSwatches: ['#6366f1', '#2563eb', '#0ea5e9', '#14b8a6', '#22c55e', '#f59e0b', '#ef4444', '#111827'],
        openNewCat() {
            this.editCatId = null;
            this.catForm = { name: '', color: '#6366f1', icon_url: '', description: '' };
            this.showCatModal = true;
        },
        editCat(id, name, color, icon_url, description) {
            this.editCatId = id;
            this.catForm = { name, color: color || '#6366f1', icon_url: icon_url || '', description: description || '' };
            this.showCatModal = true;
        }
    };
}
</script>
@endpush
@endsection
