<?php

namespace App\Http\Controllers\SocialMedia;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaClass;
use App\Models\SocialMediaItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SocialMediaItemController extends Controller
{
    public function store(Request $request, SocialMediaClass $class)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255', Rule::unique('social_media_items', 'name')->where('social_media_class_id', $class->id)],
            'icon_url'  => ['nullable', 'string', 'max:2048'],
            'icon_file' => ['nullable', 'image', 'max:2048'],
            'status'    => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $icon = null;
        if ($request->hasFile('icon_file')) {
            $path = $request->file('icon_file')->store('item_icons', 'public');
            $icon = '/storage/' . $path;
        } elseif (!empty($data['icon_url'])) {
            $icon = $data['icon_url'];
        }

        // Set sort_order to next available
        $maxOrder = $class->items()->max('sort_order') ?? 0;

        $class->items()->create([
            'name'       => $data['name'],
            'icon'       => $icon,
            'status'     => $data['status'],
            'sort_order' => $maxOrder + 10,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', '"' . $data['name'] . '" added to class.');
    }

    public function storeTemplate(SocialMediaClass $class)
    {
        $templates = [
            ['name' => 'Facebook', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/b/b8/2021_Facebook_icon.svg'],
            ['name' => 'Instagram', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/e/e7/Instagram_logo_2016.svg'],
            ['name' => 'X(Twitter)', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/c/ce/X_logo_2023.svg'],
            ['name' => 'Pinterest', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/0/08/Pinterest-logo.png'],
            ['name' => 'YouTube', 'icon' => 'https://upload.wikimedia.org/wikipedia/commons/0/09/YouTube_full-color_icon_%282017%29.svg'],
            ['name' => 'TikTok', 'icon' => 'https://sf-static.tiktokcdn.com/obj/eden-sg/uhtyvueh7nulogpoguhm/tiktok-icon2.png'],
        ];

        $maxOrder = $class->items()->max('sort_order') ?? 0;

        foreach ($templates as $t) {
            if (!$class->items()->where('name', $t['name'])->exists()) {
                $maxOrder += 10;
                $class->items()->create([
                    'name'       => $t['name'],
                    'icon'       => $t['icon'],
                    'status'     => 'active',
                    'sort_order' => $maxOrder,
                    'created_by' => auth()->id(),
                ]);
            }
        }

        return back()->with('success', 'Social media templates added to class.');
    }

    public function update(Request $request, SocialMediaItem $item)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255', Rule::unique('social_media_items', 'name')->where('social_media_class_id', $item->social_media_class_id)->ignore($item->id)],
            'icon_url'  => ['nullable', 'string', 'max:2048'],
            'icon_file' => ['nullable', 'image', 'max:2048'],
            'status'    => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $icon = $item->icon;
        if ($request->hasFile('icon_file')) {
            $path = $request->file('icon_file')->store('item_icons', 'public');
            $icon = '/storage/' . $path;
        } elseif ($request->has('icon_url')) {
            $icon = $data['icon_url'];
        }

        $item->update([
            'name'   => $data['name'],
            'icon'   => $icon,
            'status' => $data['status'],
        ]);
        return back()->with('success', 'Social media item updated.');
    }

    public function destroy(SocialMediaItem $item)
    {
        if ($item->posts()->exists()) {
            return back()->with('error', 'Cannot delete: this item has existing posts. Deactivate it instead.');
        }
        $item->delete();
        return back()->with('success', 'Social media item deleted.');
    }

    public function toggleStatus(SocialMediaItem $item)
    {
        $item->update(['status' => $item->status === 'active' ? 'inactive' : 'active']);
        return back()->with('success', 'Item status updated.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer|exists:social_media_items,id',
        ]);

        foreach ($request->order as $index => $itemId) {
            SocialMediaItem::where('id', $itemId)->update(['sort_order' => ($index + 1) * 10]);
        }

        return response()->json(['success' => true]);
    }
}
