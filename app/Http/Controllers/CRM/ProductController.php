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
        $validated['sort_order'] = ((int) ProductCategory::max('sort_order')) + 1;

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

    public function reorderCategory(Request $request, ProductCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ]);

        $categories = ProductCategory::active()->ordered()->get();
        $currentIndex = $categories->search(fn (ProductCategory $item) => $item->id === $category->id);

        if ($currentIndex === false) {
            return back()->with('error', 'Category not found in the active category list.');
        }

        $targetIndex = $validated['direction'] === 'up' ? $currentIndex - 1 : $currentIndex + 1;

        if (! isset($categories[$targetIndex])) {
            return back()->with('success', 'Category is already in that position.');
        }

        $orderedIds = $categories->pluck('id')->values()->all();
        [$orderedIds[$currentIndex], $orderedIds[$targetIndex]] = [$orderedIds[$targetIndex], $orderedIds[$currentIndex]];

        foreach ($orderedIds as $position => $id) {
            ProductCategory::whereKey($id)->update(['sort_order' => $position + 1]);
        }

        return back()->with('success', 'Category order updated.');
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

        $columns = $this->productImportColumns();
        $defaultCategory = ProductCategory::active()->ordered()->value('name') ?? 'Mini Excavator';

        $callback = function () use ($columns, $defaultCategory) {
            $file = fopen('php://output', 'w');
            $this->writeCsvRow($file, $columns);

            $this->writeCsvRow($file, [1, 'TYPHON TERROR ONE', 'SKU-001', $defaultCategory, 'TYPHON', '', 'USD', '']);
            $this->writeCsvRow($file, [2, 'TYPHON TYRANT X', 'SKU-002', $defaultCategory, 'TYPHON', '', 'USD', '']);

            for ($i = 3; $i <= 20; $i++) {
                $this->writeCsvRow($file, [$i, '', '', '', '', '', '', '']);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, array_merge($headers, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]));
    }

    public function downloadGoogleSheetsTemplate()
    {
        if (!class_exists(\ZipArchive::class)) {
            return redirect()->route('crm.products.index')
                ->with('error', 'The XLSX template needs the PHP Zip extension. Please use the CSV template for now.');
        }

        $categories = ProductCategory::active()->ordered()->pluck('name')->values()->all();
        $defaultCategory = $categories[0] ?? 'Mini Excavator';
        $columns = $this->productImportColumns();
        $templateRows = [
            [1, 'TYPHON TERROR ONE', 'SKU-001', $defaultCategory, 'TYPHON', '', 'USD', ''],
            [2, 'TYPHON TYRANT X', 'SKU-002', $defaultCategory, 'TYPHON', '', 'USD', ''],
        ];

        for ($i = 3; $i <= 20; $i++) {
            $templateRows[] = [$i, '', '', '', '', '', '', ''];
        }

        $path = tempnam(sys_get_temp_dir(), 'product-template-');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypes());
        $zip->addFromString('_rels/.rels', $this->xlsxRootRelationships());
        $zip->addFromString('xl/workbook.xml', $this->xlsxWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRelationships());
        $zip->addFromString('xl/styles.xml', $this->xlsxStyles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxProductSheet($columns, $templateRows, max(count($categories), 1)));
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->xlsxCategorySheet($categories ?: [$defaultCategory]));
        $zip->close();

        return response()->download($path, 'products_google_sheets_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function import(Request $request): RedirectResponse
    {
        if ($request->boolean('confirm_import')) {
            return $this->confirmImport();
        }

        $request->validate([
            'csv_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:5120'],
            'google_sheet_url' => ['nullable', 'url'],
        ]);

        if (!$request->hasFile('csv_file') && !$request->filled('google_sheet_url')) {
            return back()->with('error', 'Please provide a CSV file or Google Sheet URL.');
        }

        [$handle, $sourceName, $sourceError] = $this->openProductImportSource($request);

        if ($sourceError) {
            return back()->with('error', $sourceError);
        }

        [$preview, $importableRows] = $this->buildProductImportPreview($handle, $sourceName);
        fclose($handle);

        session([
            'product_import_rows' => $importableRows,
        ]);

        if ($preview['total'] === 0) {
            return back()->with('error', 'No product rows were found. Please use the 8-column template.');
        }

        return redirect()->route('crm.products.index')
            ->with('product_import_preview', $preview)
            ->with('success', 'Preview ready. Please review the rows below, then confirm the import.');
    }

    public function export()
    {
        $products = Product::with('categoryModel')->get();
        $filename = 'products_export_' . date('Ymd_His') . '.csv';
        $headers = ['Content-Type' => 'text/csv'];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');
            $columns = $this->productImportColumns();
            $this->writeCsvRow($file, $columns);

            foreach ($products as $index => $product) {
                $this->writeCsvRow($file, [
                    $index + 1,
                    $product->name,
                    $product->sku,
                    $product->categoryModel?->name ?? $product->category_name,
                    $product->brand,
                    $product->price,
                    $product->currency,
                    $product->image,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, array_merge($headers, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]));
    }

    private function confirmImport(): RedirectResponse
    {
        $rows = session('product_import_rows', []);

        if (empty($rows)) {
            return redirect()->route('crm.products.index')
                ->with('error', 'Import preview expired. Please preview the file again.');
        }

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if ($this->productDuplicateExists($row['sku'], $row['name'])) {
                $skipped++;
                continue;
            }

            Product::create([
                'name'        => $row['name'],
                'sku'         => $row['sku'] ?: null,
                'category_id' => $row['category_id'],
                'category'    => 'other',
                'brand'       => $row['brand'] ?: null,
                'price'       => $row['price'],
                'currency'    => $row['currency'] ?: 'USD',
                'image'       => $row['product_image'] ?: null,
                'condition'   => 'used',
                'is_active'   => true,
                'status'      => 'active',
                'created_by'  => auth()->id(),
                'updated_by'  => auth()->id(),
            ]);

            $created++;
        }

        session()->forget('product_import_rows');

        $message = "Imported {$created} products.";
        if ($skipped > 0) {
            $message .= " {$skipped} duplicates were skipped.";
        }

        return redirect()->route('crm.products.index')->with('success', $message);
    }

    private function productImportColumns(): array
    {
        return ['NO', 'name', 'sku', 'category_name', 'brand', 'price', 'currency', 'product_image'];
    }

    private function readCsvRow($handle): array|false
    {
        return fgetcsv($handle, null, ',', '"', '\\');
    }

    private function writeCsvRow($handle, array $fields): void
    {
        fputcsv($handle, $fields, ',', '"', '\\');
    }

    private function xlsxProductSheet(array $columns, array $rows, int $categoryCount): string
    {
        $sheetRows = [];
        $sheetRows[] = $this->xlsxRow(1, $columns, true);

        foreach ($rows as $index => $row) {
            $sheetRows[] = $this->xlsxRow($index + 2, $row);
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . '<cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="30" customWidth="1"/><col min="3" max="3" width="18" customWidth="1"/><col min="4" max="4" width="22" customWidth="1"/><col min="5" max="5" width="18" customWidth="1"/><col min="6" max="6" width="14" customWidth="1"/><col min="7" max="7" width="14" customWidth="1"/><col min="8" max="8" width="34" customWidth="1"/></cols>'
            . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
            . '<dataValidations count="1"><dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="D2:D501"><formula1>Categories!$A$1:$A$' . $categoryCount . '</formula1></dataValidation></dataValidations>'
            . '</worksheet>';
    }

    private function xlsxCategorySheet(array $categories): string
    {
        $rows = [];

        foreach (array_values($categories) as $index => $category) {
            $rows[] = $this->xlsxRow($index + 1, [$category]);
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . '</worksheet>';
    }

    private function xlsxRow(int $rowNumber, array $values, bool $header = false): string
    {
        $cells = [];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

        foreach ($values as $index => $value) {
            $cells[] = $this->xlsxCell($columns[$index] . $rowNumber, $value, $header);
        }

        return '<row r="' . $rowNumber . '">' . implode('', $cells) . '</row>';
    }

    private function xlsxCell(string $cell, string|int|float|null $value, bool $header = false): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $style = $header ? ' s="1"' : '';

        return '<c r="' . $cell . '" t="inlineStr"' . $style . '><is><t>'
            . htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8')
            . '</t></is></c>';
    }

    private function xlsxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function xlsxRootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function xlsxWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Products" sheetId="1" r:id="rId1"/><sheet name="Categories" sheetId="2" state="hidden" r:id="rId2"/></sheets>'
            . '</workbook>';
    }

    private function xlsxWorkbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function openProductImportSource(Request $request): array
    {
        if ($request->filled('google_sheet_url')) {
            $url = $this->googleSheetCsvUrl($request->input('google_sheet_url'));
            $handle = @fopen($url, 'r');

            if (!$handle) {
                return [null, 'Google Sheet', 'Could not read from Google Sheet URL. Make sure sharing is set to "Anyone with the link can view".'];
            }

            return [$handle, 'Google Sheet', null];
        }

        $file = $request->file('csv_file');

        return [fopen($file->getRealPath(), 'r'), $file->getClientOriginalName(), null];
    }

    private function googleSheetCsvUrl(string $url): string
    {
        if (!str_contains($url, 'docs.google.com/spreadsheets')) {
            return $url;
        }

        preg_match('~/d/([^/]+)~', $url, $sheetMatch);
        if (empty($sheetMatch[1])) {
            return $url;
        }

        $gid = null;
        $parts = parse_url($url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            $gid = $query['gid'] ?? null;
        }

        if (!$gid && !empty($parts['fragment']) && preg_match('/gid=([0-9]+)/', $parts['fragment'], $gidMatch)) {
            $gid = $gidMatch[1];
        }

        return 'https://docs.google.com/spreadsheets/d/' . $sheetMatch[1] . '/export?format=csv'
            . ($gid ? '&gid=' . urlencode($gid) : '');
    }

    private function buildProductImportPreview($handle, string $sourceName): array
    {
        $rawHeaders = $this->readCsvRow($handle);
        if (!$rawHeaders) {
            return [[
                'source' => $sourceName,
                'total' => 0,
                'importable_count' => 0,
                'duplicate_count' => 0,
                'failed_count' => 0,
                'rows' => [],
            ], []];
        }

        $headers = array_map(fn ($header) => $this->normalizeImportHeader((string) $header), $rawHeaders);
        $categories = $this->categoryLookup();
        $rows = [];
        $importableRows = [];
        $seenSkus = [];
        $seenNames = [];
        $rowNumber = 1;

        while (($rawRow = $this->readCsvRow($handle)) !== false) {
            $rowNumber++;

            if (empty(array_filter($rawRow, fn ($value) => trim((string) $value) !== ''))) {
                continue;
            }

            $rawRow = array_slice(array_pad($rawRow, count($headers), ''), 0, count($headers));
            $data = array_combine($headers, $rawRow) ?: [];

            $hasProductData = collect($data)
                ->except('no')
                ->contains(fn ($value) => trim((string) $value) !== '');

            if (!$hasProductData) {
                continue;
            }

            $normalized = $this->normalizeProductImportRow($data, $rowNumber, $categories);
            $skuKey = $this->normalizeImportValue($normalized['sku']);
            $nameKey = $this->normalizeImportValue($normalized['name']);

            if ($normalized['status'] === 'ready' && (($skuKey !== '' && isset($seenSkus[$skuKey])) || isset($seenNames[$nameKey]))) {
                $normalized['status'] = 'duplicate';
                $normalized['message'] = 'Duplicate inside this import file.';
            }

            if ($normalized['status'] === 'ready' && $this->productDuplicateExists($normalized['sku'], $normalized['name'])) {
                $normalized['status'] = 'duplicate';
                $normalized['message'] = $normalized['sku']
                    ? 'SKU already exists. Existing product was not changed.'
                    : 'Product name already exists. Existing product was not changed.';
            }

            if ($normalized['status'] === 'ready') {
                if ($skuKey !== '') {
                    $seenSkus[$skuKey] = true;
                }
                $seenNames[$nameKey] = true;
                $importableRows[] = [
                    'name' => $normalized['name'],
                    'sku' => $normalized['sku'],
                    'category_id' => $normalized['category_id'],
                    'category_name' => $normalized['category_name'],
                    'brand' => $normalized['brand'],
                    'price' => $normalized['price'],
                    'currency' => $normalized['currency'],
                    'product_image' => $normalized['product_image'],
                ];
            }

            $rows[] = $normalized;
        }

        $duplicateCount = collect($rows)->where('status', 'duplicate')->count();
        $failedCount = collect($rows)->where('status', 'failed')->count();

        return [[
            'source' => $sourceName,
            'total' => count($rows),
            'importable_count' => count($importableRows),
            'duplicate_count' => $duplicateCount,
            'failed_count' => $failedCount,
            'rows' => array_slice($rows, 0, 100),
        ], $importableRows];
    }

    private function normalizeImportHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $key = Str::of($header)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();

        return match ($key) {
            'no', 'n0', 'number', 'row' => 'no',
            'product', 'product_name', 'title' => 'name',
            'category', 'categoryname', 'category_name' => 'category_name',
            'image', 'image_url', 'productimage', 'product_image', 'photo', 'photo_url', 'picture', 'picture_url' => 'product_image',
            default => $key,
        };
    }

    private function normalizeProductImportRow(array $data, int $rowNumber, array $categories): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $sku = trim((string) ($data['sku'] ?? ''));
        $categoryInput = trim((string) ($data['category_name'] ?? ''));
        $brand = trim((string) ($data['brand'] ?? ''));
        $price = $this->normalizeImportPrice($data['price'] ?? null);
        $currency = strtoupper(trim((string) ($data['currency'] ?? ''))) ?: 'USD';
        $productImage = trim((string) ($data['product_image'] ?? ''));
        $category = $this->matchImportCategory($categoryInput, $categories);
        $status = 'ready';
        $message = 'Ready to import.';

        if ($name === '') {
            $status = 'failed';
            $message = 'Product name is required.';
        } elseif ($categoryInput === '') {
            $status = 'failed';
            $message = 'Category name is required.';
        } elseif (!$category) {
            $status = 'failed';
            $message = "Category '{$categoryInput}' was not found. Use an existing category name.";
        } elseif ($price === false) {
            $status = 'failed';
            $message = 'Price must be a number or blank.';
        } elseif ($currency !== '' && !preg_match('/^[A-Z]{3}$/', $currency)) {
            $status = 'failed';
            $message = 'Currency must be a 3-letter code like USD.';
        } elseif ($productImage !== '' && !filter_var($productImage, FILTER_VALIDATE_URL)) {
            $status = 'failed';
            $message = 'Product image must be a valid URL or blank.';
        }

        return [
            'row_number' => $rowNumber,
            'no' => trim((string) ($data['no'] ?? '')),
            'name' => $name,
            'sku' => $sku,
            'category_id' => $category?->id,
            'category_name' => $category?->name ?? $categoryInput,
            'brand' => $brand,
            'price' => $price === false ? null : $price,
            'currency' => $currency,
            'product_image' => $productImage,
            'status' => $status,
            'message' => $message,
        ];
    }

    private function categoryLookup(): array
    {
        $lookup = [];

        foreach (ProductCategory::active()->ordered()->get() as $category) {
            foreach ([$category->name, $category->slug] as $value) {
                $lookup[$this->normalizeImportValue($value)] = $category;
            }
        }

        return $lookup;
    }

    private function matchImportCategory(string $value, array $categories): ?ProductCategory
    {
        $normalized = $this->normalizeImportValue($value);
        if ($normalized === '') {
            return null;
        }

        if (isset($categories[$normalized])) {
            return $categories[$normalized];
        }

        $bestCategory = null;
        $bestDistance = null;

        foreach ($categories as $categoryKey => $category) {
            $distance = levenshtein($normalized, $categoryKey);
            if ($bestDistance === null || $distance < $bestDistance) {
                $bestDistance = $distance;
                $bestCategory = $category;
            }
        }

        return $bestDistance !== null && $bestDistance <= 2 ? $bestCategory : null;
    }

    private function normalizeImportValue(?string $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function normalizeImportPrice($value): float|false|null
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $cleaned = str_replace([',', '$'], '', $value);

        return is_numeric($cleaned) ? (float) $cleaned : false;
    }

    private function productDuplicateExists(?string $sku, string $name): bool
    {
        $sku = trim((string) $sku);
        $name = trim($name);

        return Product::withTrashed()
            ->where(function ($query) use ($sku, $name) {
                if ($sku !== '') {
                    $query->whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)]);
                }

                $query->orWhereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
            })
            ->exists();
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
