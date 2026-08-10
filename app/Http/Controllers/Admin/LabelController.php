<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Label;
use App\Models\Workspace;
use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class LabelController extends Controller
{
    /**
     * Display a listing of the labels.
     */
    public function index(): View
    {
        // Load all labels with their workspace and board relationships
        $labels = Label::with(['workspace', 'board'])->orderBy('position')->orderBy('name')->get();
        $workspaces = Workspace::orderBy('name')->get();
        // Only fetch boards that aren't archived for the dropdown
        $boards = Board::where('is_archived', false)->orderBy('name')->get();

        return view('admin.labels.index', compact('labels', 'workspaces', 'boards'));
    }

    /**
     * Store a newly created label in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:50'],
            'color'        => ['required', 'string', 'max:7', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'workspace_id' => ['nullable', 'exists:workspaces,id'],
            'board_id'     => ['nullable', 'exists:boards,id'],
        ]);

        Label::create($validated);

        return redirect()->route('admin.labels.index')
            ->with('success', "Label \"{$validated['name']}\" created successfully.");
    }

    /**
     * Update the specified label in storage.
     */
    public function update(Request $request, Label $label): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:50'],
            'color'        => ['required', 'string', 'max:7', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'workspace_id' => ['nullable', 'exists:workspaces,id'],
            'board_id'     => ['nullable', 'exists:boards,id'],
        ]);

        $label->update($validated);

        return redirect()->route('admin.labels.index')
            ->with('success', "Label \"{$label->name}\" updated successfully.");
    }

    /**
     * Remove the specified label from storage.
     */
    public function destroy(Label $label): RedirectResponse
    {
        $name = $label->name;
        $label->delete();

        return redirect()->route('admin.labels.index')
            ->with('success', "Label \"{$name}\" deleted successfully.");
    }
    /**
     * Reorder labels based on drag and drop.
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'ordered_ids'   => ['required', 'array'],
            'ordered_ids.*' => ['integer', 'exists:labels,id'],
        ]);

        foreach ($request->ordered_ids as $index => $id) {
            Label::where('id', $id)->update(['position' => $index]);
        }

        return response()->json(['success' => true]);
    }
}
