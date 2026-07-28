# Add Custom External Links to All Sections

The goal is to allow users to add custom external links dynamically to all sections in the External Systems menu ("board", "generator", "workspace", etc.), and be able to reorder them.

## Proposed Changes

1. **`app/Models/Setting.php`**
   - Update `externalToolsForGroup()` to fetch and append custom tools for the given group (`custom_board_tools`, `custom_generator_tools`, `custom_workspace_tools`).
   - Merge them seamlessly so the Dashboard and Layout automatically show the custom tools.

2. **`app/Http/Controllers/Admin/SettingController.php`**
   - Add validation rules for `custom_board_tools`, `custom_generator_tools`, `custom_workspace_tools`.
   - Update the `store()` method to decode and save these custom arrays just like it currently does for `custom_ai_tools`.

3. **`resources/views/admin/settings/index.blade.php`**
   - We will refactor the UI to use a generic Alpine component for the "eBay & Web Supporter", "System Supporter", and "Google Workspace" sections, similar to the existing AI Tools component.
   - For static tools (e.g. `hosting_image_url`), the Alpine component will output hidden input names correctly so they save to the database as distinct keys.
   - For custom tools, the component will allow adding, editing, removing, and reordering, saving the result into `custom_board_tools` JSON.
   - We will ensure `board_tools_order` (and others) are still populated correctly so that the unified list maintains its dragged order.

## Open Questions

None. This approach maintains existing static data fields while adding full custom link support natively to every group.
