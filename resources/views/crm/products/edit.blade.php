@extends('layouts.app')
@section('title', 'Edit Product')
@section('page_title', 'Edit Product')

@section('content')
<div class="max-w-2xl animate-fade-in">
  <div class="mb-5">
    <a href="{{ route('crm.products.index') }}" class="text-sm text-slate-400 hover:text-indigo-600">← Back to Products</a>
  </div>

  <div class="card">
    <div class="px-6 pt-6 pb-4 border-b border-slate-100">
      <h2 class="font-display font-bold text-slate-800 text-lg">Edit Product</h2>
      <p class="text-slate-400 text-sm mt-1">{{ $product->name }}</p>
    </div>

    <form id="update-product-form" method="POST" action="{{ route('crm.products.update', $product) }}" enctype="multipart/form-data" class="divide-y divide-slate-100">
      @csrf @method('PUT')

      @if($errors->any())
      <div class="px-6 py-3 bg-red-50 border-b border-red-100">
        <ul class="text-sm text-red-600 space-y-1">
          @foreach($errors->all() as $err)<li>• {{ $err }}</li>@endforeach
        </ul>
      </div>
      @endif

      <div class="px-6 py-5 space-y-4">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Product Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $product->name) }}"
                   class="form-input @error('name') error @enderror" required>
            @error('name')<p class="form-error">{{ $message }}</p>@enderror
          </div>
          <div>
            <label class="form-label">SKU</label>
            <input type="text" name="sku" value="{{ old('sku', $product->sku) }}"
                   class="form-input font-mono @error('sku') error @enderror">
            @error('sku')<p class="form-error">{{ $message }}</p>@enderror
          </div>
        </div>

        <div>
          <label class="form-label">Category</label>
          <select name="category_id" class="form-input">
            <option value="">— Uncategorized —</option>
            @foreach($categories as $cat)
              <option value="{{ $cat->id }}" {{ old('category_id', $product->category_id) == $cat->id ? 'selected' : '' }}>
                {{ $cat->name }}
              </option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="form-label">Description</label>
          <textarea name="description" rows="3" class="form-input">{{ old('description', $product->description) }}</textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="form-label">Brand</label>
            <input type="text" name="brand" value="{{ old('brand', $product->brand) }}" class="form-input">
          </div>
          <div>
            <label class="form-label">Model</label>
            <input type="text" name="model" value="{{ old('model', $product->model) }}" class="form-input">
          </div>
          <div>
            <label class="form-label">Year</label>
            <input type="text" name="year" value="{{ old('year', $product->year) }}" class="form-input">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="form-label">Condition</label>
            <select name="condition" class="form-input">
              @foreach(['used','new','refurbished'] as $c)
              <option value="{{ $c }}" {{ old('condition', $product->condition) === $c ? 'selected' : '' }}>
                {{ ucfirst($c) }}
              </option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label">Price</label>
            <input type="number" name="price" value="{{ old('price', $product->price) }}" class="form-input" step="0.01" min="0">
          </div>
          <div>
            <label class="form-label">Currency</label>
            <select name="currency" class="form-input">
              @foreach(['AUD','USD','EUR','GBP','SGD'] as $cur)
              <option value="{{ $cur }}" {{ old('currency', $product->currency) === $cur ? 'selected' : '' }}>{{ $cur }}</option>
              @endforeach
            </select>
          </div>
        </div>

        @if($product->image)
        <div>
          <label class="form-label">Current Image</label>
          <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
               class="w-24 h-24 rounded-xl object-cover border border-slate-200">
        </div>
        @endif
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">{{ $product->image ? 'Replace Image (Upload)' : 'Product Image (Upload)' }}</label>
            <input type="file" name="image" accept="image/*" class="form-input">
          </div>
          <div>
            <label class="form-label">Or Image URL</label>
            <input type="url" name="image_url" placeholder="https://.../image.jpg" class="form-input">
          </div>
        </div>

        <div class="flex items-center gap-3">
          <input type="hidden" name="is_active" value="0">
          <input type="checkbox" name="is_active" id="is_active" value="1"
                 {{ old('is_active', $product->is_active ? '1' : '0') ? 'checked' : '' }}
                 class="accent-indigo-600 w-4 h-4">
          <label for="is_active" class="text-sm text-slate-600 cursor-pointer">Active (visible in dropdowns)</label>
        </div>

      </div>

      <div class="px-6 py-4 flex items-center justify-between bg-slate-50">
        <button type="submit" form="delete-product-form" class="btn btn-secondary text-red-500 hover:text-red-600 text-sm">
          Delete Product
        </button>
        <div class="flex gap-3">
          <a href="{{ route('crm.products.index') }}" class="btn btn-secondary">Cancel</a>
          <button type="submit" form="update-product-form" class="btn btn-primary">Save Changes</button>
        </div>
      </div>
    </form>
    
    <form id="delete-product-form" method="POST" action="{{ route('crm.products.destroy', $product) }}" data-confirm="Delete this product?">
        @csrf @method('DELETE')
    </form>
  </div>
</div>
@endsection
