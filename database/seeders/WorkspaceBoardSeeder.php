<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Label;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * PHASE BOARD-1: WorkspaceBoardSeeder
 *
 * Seeds four example workspaces with realistic boards, lists, labels,
 * and sample cards so the UI has something to show right away.
 *
 * Run with:
 *   php artisan db:seed --class=WorkspaceBoardSeeder
 */
class WorkspaceBoardSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::role('super-admin')->first()
                     ?? User::first();

        if (! $superAdmin) {
            $this->command->warn('No users found – skipping WorkspaceBoardSeeder.');
            return;
        }

        // ── 1. DIGITAL TEAM WORKSPACE ─────────────────────────────────────────
        $digitalWs = $this->createWorkspace(
            owner: $superAdmin,
            name:  'Digital Team',
            color: '#6366f1',
            desc:  'Content, design, video production and web projects.',
        );

        $planningBoard = $this->createBoard($digitalWs, $superAdmin, 'Planning Board', '#0f172a');
        $this->seedDefaultLists($planningBoard);
        $this->seedLabels($planningBoard, [
            ['name' => 'Urgent',        'color' => '#ef4444'],
            ['name' => 'Design',        'color' => '#8b5cf6'],
            ['name' => 'Video',         'color' => '#f59e0b'],
            ['name' => 'High Priority', 'color' => '#f97316'],
            ['name' => 'Review',        'color' => '#06b6d4'],
        ]);
        $this->seedSampleCards($planningBoard, $superAdmin);

        $this->createBoard($digitalWs, $superAdmin, 'Content Calendar', '#1e3a5f');
        $this->createBoard($digitalWs, $superAdmin, 'Website Project', '#064e3b');

        // ── 2. CRM WORKSPACE ──────────────────────────────────────────────────
        $crmWs = $this->createWorkspace(
            owner: $superAdmin,
            name:  'CRM Team',
            color: '#10b981',
            desc:  'Customer relationships, leads, and eBay workflow.',
        );

        $websiteCrmBoard = $this->createBoard($crmWs, $superAdmin, 'Website CRM Board', '#065f46');
        $this->seedCrmLists($websiteCrmBoard);
        $this->seedLabels($websiteCrmBoard, [
            ['name' => 'Hot Lead',   'color' => '#ef4444'],
            ['name' => 'Follow Up',  'color' => '#f59e0b'],
            ['name' => 'Converted',  'color' => '#10b981'],
            ['name' => 'CRM',        'color' => '#6366f1'],
        ]);

        $ebayBoard = $this->createBoard($crmWs, $superAdmin, 'eBay CRM Board', '#1e40af');
        $this->seedEbayLists($ebayBoard);
        $this->seedLabels($ebayBoard, [
            ['name' => 'eBay',         'color' => '#e11d48'],
            ['name' => 'Authorized',   'color' => '#10b981'],
            ['name' => 'Pending Auth', 'color' => '#f59e0b'],
            ['name' => 'Shipped',      'color' => '#6366f1'],
        ]);

        $this->createBoard($crmWs, $superAdmin, 'Customer Follow-up Board', '#7c3aed');

        // ── 3. LOGISTICS WORKSPACE ────────────────────────────────────────────
        $logisticWs = $this->createWorkspace(
            owner: $superAdmin,
            name:  'Logistics Team',
            color: '#f59e0b',
            desc:  'Shipment tracking and delivery management.',
        );

        $shipBoard = $this->createBoard($logisticWs, $superAdmin, 'Shipment Tracking Board', '#78350f');
        $this->seedLogisticLists($shipBoard);
        $this->seedLabels($shipBoard, [
            ['name' => 'Logistic',    'color' => '#f59e0b'],
            ['name' => 'Delivered',   'color' => '#10b981'],
            ['name' => 'Delayed',     'color' => '#ef4444'],
            ['name' => 'Express',     'color' => '#6366f1'],
        ]);

        $this->createBoard($logisticWs, $superAdmin, 'Delivery Management Board', '#92400e');

        // ── 4. ADMIN WORKSPACE ────────────────────────────────────────────────
        $adminWs = $this->createWorkspace(
            owner: $superAdmin,
            name:  'Admin Team',
            color: '#64748b',
            desc:  'Internal admin tasks, HR, and system management.',
        );

        $adminBoard = $this->createBoard($adminWs, $superAdmin, 'Admin Tasks Board', '#1e293b');
        $this->seedDefaultLists($adminBoard);
        $this->seedLabels($adminBoard, [
            ['name' => 'HR',       'color' => '#6366f1'],
            ['name' => 'Finance',  'color' => '#10b981'],
            ['name' => 'IT',       'color' => '#f59e0b'],
            ['name' => 'Legal',    'color' => '#ef4444'],
        ]);

        $this->command->info('✅ WorkspaceBoardSeeder done – 4 workspaces, 9 boards created.');
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function createWorkspace(User $owner, string $name, string $color, string $desc): Workspace
    {
        return Workspace::firstOrCreate(
            ['name' => $name],
            [
                'slug'        => Str::slug($name) . '-' . Str::random(4),
                'description' => $desc,
                'color'       => $color,
                'owner_id'    => $owner->id,
                'visibility'  => 'team',
                'is_active'   => true,
            ]
        );
    }

    private function createBoard(Workspace $ws, User $creator, string $name, string $bg): Board
    {
        return Board::firstOrCreate(
            ['name' => $name, 'workspace_id' => $ws->id],
            [
                'slug'             => Str::slug($name) . '-' . Str::random(4),
                'workspace_id'     => $ws->id,
                'background_type'  => 'color',
                'background_value' => $bg,
                'visibility'       => 'workspace',
                'created_by'       => $creator->id,
                'position'         => Board::where('workspace_id', $ws->id)->count(),
            ]
        );
    }

    private function seedDefaultLists(Board $board): void
    {
        $lists = ['Backlog', 'To Do', 'In Progress', 'Review', 'Waiting Approval', 'Done'];
        foreach ($lists as $i => $name) {
            BoardList::firstOrCreate(
                ['board_id' => $board->id, 'name' => $name],
                ['position' => $i]
            );
        }
    }

    private function seedCrmLists(Board $board): void
    {
        $lists = ['New Leads', 'Contacted', 'Proposal Sent', 'Negotiation', 'Won', 'Lost'];
        foreach ($lists as $i => $name) {
            BoardList::firstOrCreate(
                ['board_id' => $board->id, 'name' => $name],
                ['position' => $i]
            );
        }
    }

    private function seedEbayLists(Board $board): void
    {
        $lists = ['New Inquiry', 'Under Review', 'Authorized', 'Order Placed', 'Shipped', 'Delivered'];
        foreach ($lists as $i => $name) {
            BoardList::firstOrCreate(
                ['board_id' => $board->id, 'name' => $name],
                ['position' => $i]
            );
        }
    }

    private function seedLogisticLists(Board $board): void
    {
        $lists = ['New Shipment', 'Picked Up', 'In Transit', 'Out for Delivery', 'Delivered', 'Problem'];
        foreach ($lists as $i => $name) {
            BoardList::firstOrCreate(
                ['board_id' => $board->id, 'name' => $name],
                ['position' => $i]
            );
        }
    }

    private function seedLabels(Board $board, array $defs): void
    {
        foreach ($defs as $def) {
            Label::firstOrCreate(
                ['board_id' => $board->id, 'name' => $def['name']],
                ['color'    => $def['color']]
            );
        }
    }

    private function seedSampleCards(Board $board, User $user): void
    {
        $todoList = $board->lists()->where('name', 'To Do')->first();
        $inProgressList = $board->lists()->where('name', 'In Progress')->first();

        if (! $todoList || ! $inProgressList) return;

        $cards = [
            ['title' => '📹 Create product promo video', 'list' => $todoList, 'priority' => 'high'],
            ['title' => '🎨 Design new website banner', 'list' => $todoList, 'priority' => 'medium'],
            ['title' => '📝 Write eBay product descriptions', 'list' => $inProgressList, 'priority' => 'high'],
            ['title' => '📊 Prepare monthly report', 'list' => $inProgressList, 'priority' => 'urgent'],
        ];

        foreach ($cards as $i => $data) {
            Card::firstOrCreate(
                ['title' => $data['title'], 'board_id' => $board->id],
                [
                    'board_list_id' => $data['list']->id,
                    'status'        => 'todo',
                    'priority'      => $data['priority'],
                    'label'         => 'video',
                    'position'      => $i,
                    'created_by'    => $user->id,
                    'due_at'        => now()->addDays(rand(3, 14)),
                ]
            );
        }
    }
}
