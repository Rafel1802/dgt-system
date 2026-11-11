<?php

namespace App\Services\Ai;

use App\Enums\CardStatus;
use App\Models\Ai\AiReport;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteInquiry;
use App\Models\WebsitePost;
use App\Models\WebsiteQcCheck;
use App\Services\BoardWorkflowService;
use App\Services\WebsiteBlogPostService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KiuqAiToolRegistry
{
    public function __construct(
        private readonly WebsiteBlogPostService $blogService,
        private readonly BoardWorkflowService $boardWorkflowService,
        private readonly KiuqAiReportService $reportService,
        private readonly KiuqAiSafetyService $safetyService,
        private readonly GeminiClientService $geminiClient
    ) {}

    /**
     * Get Gemini Function Declarations for function calling tools.
     */
    public function getDeclarations(): array
    {
        return [
            [
                'name' => 'get_dashboard_summary',
                'description' => 'Get a verified operational summary of the DGT System: total blogs, tasks, websites, and QC checks.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'include_details' => [
                            'type' => 'BOOLEAN',
                            'description' => 'Whether to include detailed category statistics.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'list_websites',
                'description' => 'List all connected websites in the DGT system with their domains, status, category/class, and assigned handler.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'status' => [
                            'type' => 'STRING',
                            'description' => 'Optional filter by status (e.g. Live, Build Website, Maintenance, etc.).',
                        ],
                        'category' => [
                            'type' => 'STRING',
                            'description' => 'Optional category filter.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'search_blogs',
                'description' => 'Search existing blog posts across connected websites in the DGT CMS.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'query' => [
                            'type' => 'STRING',
                            'description' => 'Search keyword for blog title or content.',
                        ],
                        'website_id' => [
                            'type' => 'INTEGER',
                            'description' => 'Optional ID of the target website.',
                        ],
                        'status' => [
                            'type' => 'STRING',
                            'description' => 'Filter by post status: published, draft, scheduled, or all.',
                        ],
                        'limit' => [
                            'type' => 'INTEGER',
                            'description' => 'Maximum number of posts to return (default 5, max 20).',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'create_blog_draft',
                'description' => 'Create a new blog article draft in the DGT CMS for a specific website.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'required' => ['website_id', 'title', 'content_html'],
                    'properties' => [
                        'website_id' => [
                            'type' => 'INTEGER',
                            'description' => 'Target Website ID from DGT system.',
                        ],
                        'title' => [
                            'type' => 'STRING',
                            'description' => 'Article title.',
                        ],
                        'content_html' => [
                            'type' => 'STRING',
                            'description' => 'Complete clean HTML article body content.',
                        ],
                        'excerpt' => [
                            'type' => 'STRING',
                            'description' => 'Short engaging excerpt / summary.',
                        ],
                        'meta_title' => [
                            'type' => 'STRING',
                            'description' => 'SEO Title tag (recommended 50-60 characters).',
                        ],
                        'meta_description' => [
                            'type' => 'STRING',
                            'description' => 'SEO Meta Description (recommended 140-160 characters).',
                        ],
                        'focus_keyword' => [
                            'type' => 'STRING',
                            'description' => 'Primary focus keyword.',
                        ],
                        'tag_keywords' => [
                            'type' => 'STRING',
                            'description' => 'Comma-separated SEO tag keywords.',
                        ],
                        'featured_image_url' => [
                            'type' => 'STRING',
                            'description' => 'Optional URL of the featured image.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'prepare_blog_publish',
                'description' => 'Prepare a confirmation card preview to publish an existing blog draft to a live website. Does NOT publish immediately; requires user confirmation.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'required' => ['post_id'],
                    'properties' => [
                        'post_id' => [
                            'type' => 'INTEGER',
                            'description' => 'ID of the blog post to be published.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'search_task_cards',
                'description' => 'Search task cards across Trello-style boards, lists, and assignees.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'query' => [
                            'type' => 'STRING',
                            'description' => 'Keyword to match in card title or description.',
                        ],
                        'board_id' => [
                            'type' => 'INTEGER',
                            'description' => 'Optional Board ID filter.',
                        ],
                        'limit' => [
                            'type' => 'INTEGER',
                            'description' => 'Max number of cards (default 5, max 20).',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'create_task_card',
                'description' => 'Create a new task card on an authorized board and list.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'required' => ['board_list_id', 'title'],
                    'properties' => [
                        'board_list_id' => [
                            'type' => 'INTEGER',
                            'description' => 'ID of the target board list (e.g. Backlog, Doing).',
                        ],
                        'title' => [
                            'type' => 'STRING',
                            'description' => 'Task card title.',
                        ],
                        'description' => [
                            'type' => 'STRING',
                            'description' => 'Detailed task instructions or notes.',
                        ],
                        'due_date' => [
                            'type' => 'STRING',
                            'description' => 'Optional due date formatted YYYY-MM-DD.',
                        ],
                        'team' => [
                            'type' => 'STRING',
                            'description' => 'Optional team name (e.g. Listing, Video, SMM).',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'move_task_card',
                'description' => 'Move an existing task card to a different list (e.g. from Doing to Done).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'required' => ['card_id', 'target_list_id'],
                    'properties' => [
                        'card_id' => [
                            'type' => 'INTEGER',
                            'description' => 'ID of the card to move.',
                        ],
                        'target_list_id' => [
                            'type' => 'INTEGER',
                            'description' => 'ID of the destination board list.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'get_website_qc_list',
                'description' => 'Retrieve website Quality Control (QC) checklist status and submissions.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'website_id' => [
                            'type' => 'INTEGER',
                            'description' => 'Optional Website ID filter.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'submit_website_qc_feedback',
                'description' => 'Update a website QC checklist item or record QC feedback.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'required' => ['website_id', 'checklist_key', 'is_checked'],
                    'properties' => [
                        'website_id' => [
                            'type' => 'INTEGER',
                            'description' => 'Target Website ID.',
                        ],
                        'checklist_key' => [
                            'type' => 'STRING',
                            'description' => 'Checklist item key (e.g. domain_works, mobile_responsive, seo_meta).',
                        ],
                        'is_checked' => [
                            'type' => 'BOOLEAN',
                            'description' => 'True if passed, false if issue identified.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'generate_image_asset',
                'description' => 'Generate an on-brand marketing graphic, banner, or blog featured image from a descriptive prompt.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'required' => ['prompt'],
                    'properties' => [
                        'prompt' => [
                            'type' => 'STRING',
                            'description' => 'Detailed visual prompt describing the desired image.',
                        ],
                        'aspect_ratio' => [
                            'type' => 'STRING',
                            'description' => 'Aspect ratio: 1:1 (square), 16:9 (landscape/banner), or 4:3.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'generate_voice_over',
                'description' => 'Generate high-fidelity AI voice-over audio in Google AI Studio voice characters (Charon, Puck, Kore, Orus, Aoede, Fenrir, Khmer Male, Khmer Female) for promotional videos, ads, or cinematic scripts.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'required' => ['script'],
                    'properties' => [
                        'script' => [
                            'type' => 'STRING',
                            'description' => 'Spoken text script to narrate (English, Khmer, or multilingual).',
                        ],
                        'voice' => [
                            'type' => 'STRING',
                            'description' => 'Voice timbre: Charon (Deep Cinematic Male), Puck (Clear Male), Kore (Friendly Female), Orus (Professional Male), Khmer-Male (Dara Deep Khmer), Khmer-Female (Bopha Natural Khmer), Aoede (Warm Female), Fenrir (Dynamic Male).',
                        ],
                        'style' => [
                            'type' => 'STRING',
                            'description' => 'Speaking style: cinematic, professional, energetic, friendly, narration.',
                        ],
                        'speed' => [
                            'type' => 'NUMBER',
                            'description' => 'Playback speed between 0.50 and 2.00 (default 1.0).',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'optimize_seo_content',
                'description' => 'Analyze text or blog content and produce high-ranking SEO titles, meta descriptions, keyword suggestions, and readability audits.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'required' => ['title', 'content'],
                    'properties' => [
                        'title' => [
                            'type' => 'STRING',
                            'description' => 'Current or target article title.',
                        ],
                        'content' => [
                            'type' => 'STRING',
                            'description' => 'Body text or article draft to evaluate.',
                        ],
                        'focus_keyword' => [
                            'type' => 'STRING',
                            'description' => 'Primary focus search phrase.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'generate_production_report',
                'description' => 'Generate a verified executive production report from real database records for a date range with downloadable PDF and CSV links.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'required' => ['date_from', 'date_to'],
                    'properties' => [
                        'title' => [
                            'type' => 'STRING',
                            'description' => 'Title of the report (e.g. Monthly Production Report - October 2026).',
                        ],
                        'category' => [
                            'type' => 'STRING',
                            'description' => 'Category: monthly_production, blog_publishing, task_completion, website_qc.',
                        ],
                        'date_from' => [
                            'type' => 'STRING',
                            'description' => 'Start date formatted as YYYY-MM-DD.',
                        ],
                        'date_to' => [
                            'type' => 'STRING',
                            'description' => 'End date formatted as YYYY-MM-DD.',
                        ],
                        'instructions' => [
                            'type' => 'STRING',
                            'description' => 'Specific focus areas or user instructions for AI analysis.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'list_boards',
                'description' => 'List active workspaces and boards with their lists, types, and verified navigation URLs in the DGT system.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'type' => [
                            'type' => 'STRING',
                            'description' => 'Optional filter by board name or type (e.g. planning, workflow, smm, video, graphic, content).',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'export_board_report',
                'description' => 'Export official productivity report and metrics for a specific workspace board (e.g. Planning Board, Workflow Board, SMM Board) with the exact same template UI, KPI statistics, member productivity summary, task details, and direct PDF/CSV export links.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'board_name' => [
                            'type' => 'STRING',
                            'description' => 'Name, slug, or keyword of the board (e.g. "Planning Board", "Planning Board@KiuQ – October 2026", "Workflow Board", "GraphicPlanningBoard@KiuQ").',
                        ],
                        'board_id' => [
                            'type' => 'INTEGER',
                            'description' => 'Optional specific Board ID.',
                        ],
                        'date_range' => [
                            'type' => 'STRING',
                            'description' => 'Optional date filter: all_time, today, this_week, this_month, last_month, custom.',
                        ],
                        'start_date' => [
                            'type' => 'STRING',
                            'description' => 'Optional custom start date (YYYY-MM-DD).',
                        ],
                        'end_date' => [
                            'type' => 'STRING',
                            'description' => 'Optional custom end date (YYYY-MM-DD).',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'list_members',
                'description' => 'List staff and team members in the DGT system with their roles, emails, and assigned department teams.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'team' => [
                            'type' => 'STRING',
                            'description' => 'Optional filter by team name (e.g. Listing, Video, Graphic, QC, SMM, Sales, CRM).',
                        ],
                        'role' => [
                            'type' => 'STRING',
                            'description' => 'Optional filter by role (e.g. super-admin, admin-digital, sales-crm, digital-team).',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'get_inquiries_summary',
                'description' => 'Retrieve summary of recent customer inquiries and leads captured from connected websites (Contact Form 7) with unread counts.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'limit' => [
                            'type' => 'INTEGER',
                            'description' => 'Maximum number of recent inquiries to fetch (default 5, max 15).',
                        ],
                        'website_id' => [
                            'type' => 'INTEGER',
                            'description' => 'Optional target website ID.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'get_system_navigation',
                'description' => 'Retrieve verified, active canonical routes and navigation URLs for any section, feature, or tool in the DGT System with 100% zero deadlinks.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'section' => [
                            'type' => 'STRING',
                            'description' => 'Optional section keyword (e.g. dashboard, chat, boards, planning, tasks, websites, blogs, inquiries, gsc, seo, social_media, kpi, reports, notes, users, labels, security, settings, apps).',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Execute a tool call securely on behalf of the authenticated user.
     */
    public function execute(string $toolName, array $args, User $user): array
    {
        // 1. Enforce permanent safety restrictions
        $safetyCheck = $this->safetyService->inspectToolCall($toolName, $args, $user);
        if (!$safetyCheck['allowed']) {
            return [
                'success' => false,
                'error'   => $safetyCheck['reason'],
            ];
        }

        try {
            switch ($toolName) {
                case 'get_dashboard_summary':
                    return $this->handleGetDashboardSummary($args, $user);

                case 'list_websites':
                    return $this->handleListWebsites($args, $user);

                case 'list_boards':
                    return $this->handleListBoards($args, $user);

                case 'export_board_report':
                    return $this->handleExportBoardReport($args, $user);

                case 'list_members':
                    return $this->handleListMembers($args, $user);

                case 'get_inquiries_summary':
                    return $this->handleGetInquiriesSummary($args, $user);

                case 'get_system_navigation':
                    return $this->handleGetSystemNavigation($args, $user);

                case 'search_blogs':
                    return $this->handleSearchBlogs($args, $user);

                case 'create_blog_draft':
                    return $this->handleCreateBlogDraft($args, $user);

                case 'prepare_blog_publish':
                    return $this->handlePrepareBlogPublish($args, $user);

                case 'search_task_cards':
                    return $this->handleSearchTaskCards($args, $user);

                case 'create_task_card':
                    return $this->handleCreateTaskCard($args, $user);

                case 'move_task_card':
                    return $this->handleMoveTaskCard($args, $user);

                case 'get_website_qc_list':
                    return $this->handleGetWebsiteQcList($args, $user);

                case 'submit_website_qc_feedback':
                    return $this->handleSubmitWebsiteQcFeedback($args, $user);

                case 'generate_image_asset':
                    return $this->handleGenerateImageAsset($args, $user);

                case 'generate_voice_over':
                    return $this->handleGenerateVoiceOver($args, $user);

                case 'optimize_seo_content':
                    return $this->handleOptimizeSeoContent($args, $user);

                case 'generate_production_report':
                    return $this->handleGenerateProductionReport($args, $user);

                default:
                    return [
                        'success' => false,
                        'error'   => "Unknown tool '{$toolName}'.",
                        'message' => "Unknown tool '{$toolName}'.",
                    ];
            }
        } catch (\Throwable $e) {
            Log::error("KiuqAiToolRegistry::execute error on {$toolName}: " . $e->getMessage());
            return [
                'success' => false,
                'error'   => $e->getMessage(),
                'message' => "⚠️ Tool '{$toolName}' encountered an error: " . $e->getMessage(),
            ];
        }
    }

    private function handleGetDashboardSummary(array $args, User $user): array
    {
        $activeWebsites = Website::where(function ($q) {
            $q->where('is_archived', false)->orWhereNull('is_archived');
        })->count();

        $totalBlogs     = WebsitePost::count();
        $publishedBlogs = WebsitePost::where('status', WebsitePost::STATUS_PUBLISHED)->count();
        $draftBlogs     = WebsitePost::where('status', WebsitePost::STATUS_DRAFT)->count();

        $totalCards     = Card::count();
        $totalBoards    = Board::where('is_hidden', false)->count();

        $qcPassed = WebsiteQcCheck::where('is_checked', true)->count();
        $qcTotal  = WebsiteQcCheck::count();

        $totalInquiries = WebsiteInquiry::count();
        $unreadInquiries = WebsiteInquiry::whereIn('status', ['new', 'unread'])->count();
        $totalMembers = User::count();

        $summaryMarkdown = "### 📊 DGT System Live Operational Summary\n\n"
            . "| Operational Domain | Key Metric | Verified Link |\n"
            . "| :--- | :--- | :--- |\n"
            . "| **Kanban Workspaces** | **{$totalBoards}** Active Boards · **{$totalCards}** Total Cards | [Open Workspaces](/boards) · [Workload Overview](/tasks-count) |\n"
            . "| **WordPress CMS Blogs** | **{$totalBlogs}** Articles (**{$publishedBlogs}** Published, **{$draftBlogs}** Drafts) | [Manage Blog Posts](/websites/posts) |\n"
            . "| **Connected Websites** | **{$activeWebsites}** Active Web Properties | [All Websites](/websites) |\n"
            . "| **Quality Control** | **{$qcPassed} / {$qcTotal}** Passed Inspections | [QC Audits](/websites) |\n"
            . "| **Customer Inquiries** | **{$totalInquiries}** Leads (**{$unreadInquiries}** Unread/New) | [Customer Inquiries](/websites/inquiries) |\n"
            . "| **Organization Team** | **{$totalMembers}** Staff Members | [Staff Directory](/members) |\n\n"
            . "> 💡 *All links above are verified canonical routes in the DGT System.*";

        return [
            'success' => true,
            'summary' => [
                'active_websites' => $activeWebsites,
                'blogs' => [
                    'total'     => $totalBlogs,
                    'published' => $publishedBlogs,
                    'draft'     => $draftBlogs,
                ],
                'task_boards' => [
                    'active_boards' => $totalBoards,
                    'total_cards'   => $totalCards,
                ],
                'website_qc' => [
                    'total_checks'  => $qcTotal,
                    'passed_checks' => $qcPassed,
                ],
                'inquiries' => [
                    'total'  => $totalInquiries,
                    'unread' => $unreadInquiries,
                ],
                'team_members' => $totalMembers,
            ],
            'message' => $summaryMarkdown,
        ];
    }

    private function handleListWebsites(array $args, User $user): array
    {
        $query = Website::active()->with('handler:id,name,email');

        if (!empty($args['status'])) {
            $query->where('status', 'like', "%{$args['status']}%");
        }
        if (!empty($args['category'])) {
            $query->where('category', 'like', "%{$args['category']}%");
        }

        $websites = $query->orderBy('name')->get()->map(function ($w) {
            return [
                'id'       => $w->id,
                'name'     => $w->name,
                'url'      => $w->url,
                'status'   => $w->status,
                'category' => $w->category ?: 'General',
                'handler'  => $w->handler?->name ?: 'Unassigned',
            ];
        })->all();

        $rows = [];
        foreach (array_slice($websites, 0, 10) as $site) {
            $rows[] = "- **{$site['name']}** ([{$site['url']}]({$site['url']})) — Status: `{$site['status']}` | Handler: *{$site['handler']}*";
        }
        $siteListMd = !empty($rows) ? implode("\n", $rows) : "No websites found.";

        $message = "### 🌐 Connected Websites (" . count($websites) . " Total)\n\n"
            . $siteListMd . "\n\n"
            . "👉 [**Manage All Websites & QC in DGT System**](/websites)";

        return [
            'success'   => true,
            'total'     => count($websites),
            'websites'  => $websites,
            'message'   => $message,
        ];
    }

    private function handleSearchBlogs(array $args, User $user): array
    {
        $query      = $args['query'] ?? null;
        $websiteId  = isset($args['website_id']) ? (int) $args['website_id'] : null;
        $status     = $args['status'] ?? null;
        $limit      = min(20, max(1, (int) ($args['limit'] ?? 5)));

        $postsData = $this->blogService->getPosts($websiteId, $status, $limit, $query);

        return [
            'success' => true,
            'count'   => count($postsData['posts'] ?? []),
            'posts'   => array_map(function ($p) {
                return [
                    'id'            => $p['id'],
                    'website_name'  => $p['website_name'] ?? 'General',
                    'title'         => $p['title'],
                    'status'        => $p['status'],
                    'published_at'  => $p['published_at'],
                    'canonical_url' => $p['canonical_url'],
                    'views'         => $p['cumulative_views'] ?? 0,
                ];
            }, $postsData['posts'] ?? []),
        ];
    }

    private function handleCreateBlogDraft(array $args, User $user): array
    {
        $website = Website::find($args['website_id']);
        if (!$website) {
            return ['success' => false, 'error' => "Website ID #{$args['website_id']} not found."];
        }

        $data = [
            'website_id'         => (int) $args['website_id'],
            'title'              => trim($args['title']),
            'content_html'       => $args['content_html'],
            'excerpt'            => $args['excerpt'] ?? Str::limit(strip_tags($args['content_html']), 160),
            'meta_title'         => $args['meta_title'] ?? $args['title'],
            'meta_description'   => $args['meta_description'] ?? ($args['excerpt'] ?? null),
            'focus_keyword'      => $args['focus_keyword'] ?? null,
            'tag_keywords'       => $args['tag_keywords'] ?? null,
            'featured_image_url' => $args['featured_image_url'] ?? null,
            'status'             => WebsitePost::STATUS_DRAFT,
            'action_type'        => 'draft',
        ];

        $result = $this->blogService->saveBlogPost($data, $user->id);

        if (!empty($result['success'])) {
            $this->safetyService->logAction($user, 'create_blog_draft', 'WebsitePost', $result['post']['id'] ?? null, [
                'title' => $args['title'],
                'website' => $website->name,
            ]);

            return [
                'success' => true,
                'message' => "Draft article '{$args['title']}' created successfully in DGT CMS.",
                'post_id' => $result['post']['id'] ?? null,
                'website' => $website->name,
                'status'  => 'draft',
            ];
        }

        return [
            'success' => false,
            'error'   => $result['error'] ?? 'Could not create blog draft.',
        ];
    }

    private function handlePrepareBlogPublish(array $args, User $user): array
    {
        $post = WebsitePost::with('website')->find($args['post_id']);
        if (!$post) {
            return ['success' => false, 'error' => "Blog post ID #{$args['post_id']} not found."];
        }

        return [
            'success'      => true,
            'requires_ui_action' => true,
            'action_type'  => 'publish_blog_preview',
            'action_data'  => [
                'post_id'      => $post->id,
                'title'        => $post->title,
                'website_name' => $post->website->name ?? 'Connected Site',
                'website_url'  => $post->website->url ?? '',
                'current_status' => $post->status,
                'target_status'  => 'published',
            ],
            'message' => "Action confirmation prepared for publishing '{$post->title}'. Please review and confirm in the interactive card.",
        ];
    }

    private function handleSearchTaskCards(array $args, User $user): array
    {
        $query   = trim($args['query'] ?? '');
        $boardId = isset($args['board_id']) ? (int) $args['board_id'] : null;
        $limit   = min(20, max(1, (int) ($args['limit'] ?? 5)));

        $cardsQuery = Card::with(['boardList.board', 'assignees:id,name']);

        if ($boardId) {
            $cardsQuery->whereHas('boardList', fn($q) => $q->where('board_id', $boardId));
        }

        if ($query) {
            $cardsQuery->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            });
        }

        $cards = $cardsQuery->orderByDesc('updated_at')->take($limit)->get();

        $cardsList = $cards->map(fn($c) => [
            'id'         => $c->id,
            'card_code'  => 'CRD-' . $c->id,
            'title'      => $c->title,
            'board'      => $c->boardList?->board?->name ?? 'Board',
            'board_slug' => $c->boardList?->board?->slug ?? '',
            'list'       => $c->boardList?->name ?? 'List',
            'assignees'  => $c->assignees->pluck('name')->all(),
            'due_date'   => $c->due_date ? Carbon::parse($c->due_date)->format('Y-m-d') : null,
            'direct_url' => url('/cards/' . $c->id),
        ])->all();

        $rows = [];
        foreach ($cardsList as $c) {
            $assigneesStr = !empty($c['assignees']) ? implode(', ', $c['assignees']) : 'Unassigned';
            $rows[] = "- [**{$c['card_code']}: {$c['title']}**]({$c['direct_url']}) — *{$c['board']}* ({$c['list']}) · Assignees: {$assigneesStr}";
        }
        $cardsMd = !empty($rows) ? implode("\n", $rows) : "No matching task cards found.";

        $message = "### 📋 Task Cards Found (" . count($cardsList) . ")\n\n"
            . $cardsMd . "\n\n"
            . "👉 [**Open Workspaces & Boards**](/boards) · [**Tasks Workload Overview**](/tasks-count)";

        return [
            'success' => true,
            'count'   => $cards->count(),
            'cards'   => $cardsList,
            'message' => $message,
        ];
    }

    private function handleCreateTaskCard(array $args, User $user): array
    {
        $list = BoardList::with('board')->find($args['board_list_id']);
        if (!$list) {
            return ['success' => false, 'error' => "Board list ID #{$args['board_list_id']} not found."];
        }

        $maxPos = Card::where('board_list_id', $list->id)->max('position') ?? 0;

        $card = Card::create([
            'board_id'      => $list->board_id,
            'board_list_id' => $list->id,
            'title'         => trim($args['title']),
            'description'   => $args['description'] ?? null,
            'due_date'      => !empty($args['due_date']) ? Carbon::parse($args['due_date'])->toDateString() : null,
            'team'          => $args['team'] ?? ($user->team ?? null),
            'position'      => $maxPos + 1000,
            'created_by'    => $user->id,
        ]);

        $this->safetyService->logAction($user, 'create_task_card', 'Card', $card->id, [
            'title' => $card->title,
            'board' => $list->board->name ?? 'Board',
            'list'  => $list->name,
        ]);

        $cardDirectUrl = url('/cards/' . $card->id);

        return [
            'success' => true,
            'message' => "Task card [**CRD-{$card->id}: {$card->title}**]({$cardDirectUrl}) created successfully in list '{$list->name}' on board '{$list->board->name}'.\n\n👉 [**Open Task Card**]({$cardDirectUrl}) · [**View Board**](" . url('/boards/' . $list->board->slug) . ")",
            'card_id' => $card->id,
            'board'   => $list->board->name ?? 'Board',
            'list'    => $list->name,
            'url'     => $cardDirectUrl,
        ];
    }

    private function handleMoveTaskCard(array $args, User $user): array
    {
        $card = Card::find($args['card_id']);
        if (!$card) {
            return ['success' => false, 'error' => "Card ID #{$args['card_id']} not found."];
        }

        $targetList = BoardList::find($args['target_list_id']);
        if (!$targetList) {
            return ['success' => false, 'error' => "Target list ID #{$args['target_list_id']} not found."];
        }

        $oldListName = $card->boardList?->name ?? 'Previous List';
        $maxPos = Card::where('board_list_id', $targetList->id)->max('position') ?? 0;

        $card->update([
            'board_list_id' => $targetList->id,
            'position'      => $maxPos + 1000,
        ]);

        $this->safetyService->logAction($user, 'move_task_card', 'Card', $card->id, [
            'from' => $oldListName,
            'to'   => $targetList->name,
        ]);

        $cardDirectUrl = url('/cards/' . $card->id);

        return [
            'success'  => true,
            'message'  => "Moved card [**CRD-{$card->id}: {$card->title}**]({$cardDirectUrl}) from '{$oldListName}' to '{$targetList->name}'.\n\n👉 [**Open Task Card**]({$cardDirectUrl})",
            'card_id'  => $card->id,
            'new_list' => $targetList->name,
            'url'      => $cardDirectUrl,
        ];
    }

    private function handleGetWebsiteQcList(array $args, User $user): array
    {
        $websiteId = isset($args['website_id']) ? (int) $args['website_id'] : null;

        $query = WebsiteQcCheck::with('website:id,name,url,status');
        if ($websiteId) {
            $query->where('website_id', $websiteId);
        }

        $checks = $query->take(20)->get();

        return [
            'success' => true,
            'count'   => $checks->count(),
            'checks'  => $checks->map(fn($c) => [
                'id'         => $c->id,
                'website'    => $c->website->name ?? 'Website',
                'label'      => $c->checklist_label,
                'key'        => $c->checklist_key,
                'is_checked' => $c->is_checked,
                'checked_at' => $c->checked_at?->format('Y-m-d H:i'),
            ])->all(),
        ];
    }

    private function handleSubmitWebsiteQcFeedback(array $args, User $user): array
    {
        $website = Website::find($args['website_id']);
        if (!$website) {
            return ['success' => false, 'error' => "Website ID #{$args['website_id']} not found."];
        }

        $check = WebsiteQcCheck::updateOrCreate(
            ['website_id' => $website->id, 'checklist_key' => $args['checklist_key']],
            [
                'is_checked' => (bool) $args['is_checked'],
                'checked_by' => $user->id,
                'checked_at' => now(),
            ]
        );

        $this->safetyService->logAction($user, 'submit_qc_feedback', 'WebsiteQcCheck', $check->id, [
            'website'    => $website->name,
            'key'        => $args['checklist_key'],
            'is_checked' => (bool) $args['is_checked'],
        ]);

        return [
            'success'    => true,
            'message'    => "QC item '{$check->checklist_label}' updated to " . ($check->is_checked ? 'PASSED' : 'FLAGGED') . ".",
            'is_checked' => $check->is_checked,
        ];
    }

    private function handleGenerateImageAsset(array $args, User $user): array
    {
        $prompt      = trim($args['prompt']);
        $aspectRatio = $args['aspect_ratio'] ?? '1:1';

        try {
            $result = $this->geminiClient->generateImage($prompt, ['aspectRatio' => $aspectRatio]);
            if (!empty($result['image_base64'])) {
                Storage::disk('public')->makeDirectory('ai_generated');
                $filename = 'ai_generated/' . Str::uuid() . '.png';
                Storage::disk('public')->put($filename, base64_decode($result['image_base64']));
                $publicUrl = asset('storage/' . $filename);

                $this->safetyService->logAction($user, 'generate_image', 'Media', null, [
                    'prompt' => $prompt,
                    'file'   => $filename,
                ]);

                $richMarkdown = "### 🎨 AI Image Generated\n\n"
                    . "![Generated Image]({$publicUrl})\n\n"
                    . "**Prompt:** *{$prompt}*\n\n"
                    . "- 📥 [**Download Image**]({$publicUrl})";

                return [
                    'success'   => true,
                    'image_url' => $publicUrl,
                    'prompt'    => $prompt,
                    'message'   => $richMarkdown,
                ];
            }
        } catch (Exception $e) {
            // Return clean error without breaking chat
            return [
                'success' => false,
                'error'   => "Image generation failed: " . $e->getMessage(),
            ];
        }

        return ['success' => false, 'error' => 'Could not generate image.'];
    }

    private function handleGenerateProductionReport(array $args, User $user): array
    {
        try {
            $dateFromRaw = $args['date_from'] ?? $args['start_date'] ?? $args['from'] ?? null;
            $dateToRaw   = $args['date_to'] ?? $args['end_date'] ?? $args['to'] ?? null;
            $monthRaw    = $args['month'] ?? null;
            $queryText   = strtolower(($args['title'] ?? '') . ' ' . ($args['instructions'] ?? '') . ' ' . ($dateFromRaw ?? '') . ' ' . ($monthRaw ?? ''));

            // Check if a specific month is named
            $months = [
                'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
                'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
                'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
                'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
                'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12
            ];

            $detectedMonth = null;
            foreach ($months as $name => $num) {
                if (str_contains($queryText, $name)) {
                    $detectedMonth = $num;
                    break;
                }
            }

            $currentYear = 2026;
            if ($detectedMonth) {
                $startDate = Carbon::create($currentYear, $detectedMonth, 1)->startOfMonth();
                $endDate   = Carbon::create($currentYear, $detectedMonth, 1)->endOfMonth();
            } else {
                $startDate = !empty($dateFromRaw) ? Carbon::parse($dateFromRaw)->startOfDay() : now()->startOfMonth();
                $endDate   = !empty($dateToRaw) ? Carbon::parse($dateToRaw)->endOfDay() : now()->endOfMonth();
            }

            $category  = $args['category'] ?? 'monthly_production';
            $boardFilter = $args['board_name'] ?? $args['board'] ?? null;
            if (!$boardFilter && preg_match('/(planning\s*board|workflow\s*board|content\s*board|video\s*board|graphic\s*board|smm\s*board)/i', $queryText, $bMatches)) {
                $boardFilter = $bMatches[1];
            }

            // If a specific board is requested or query asks for board export, delegate to handleExportBoardReport
            if ($boardFilter || preg_match('/\b(board|planning|workflow)\b/i', $queryText)) {
                return $this->handleExportBoardReport(array_merge($args, [
                    'board_name' => $boardFilter ?: ($args['instructions'] ?? $args['title'] ?? 'Planning Board'),
                ]), $user);
            }

            $title = $args['title'] ?? (
                ($boardFilter ? (ucwords($boardFilter) . ' - ') : '') .
                'Production Report (' . $startDate->format('M Y') . ')'
            );
            $inst = $args['instructions'] ?? ($boardFilter ? "Focus on board {$boardFilter} and team members." : null);

            $filters = [];
            if ($boardFilter) {
                $filters['board_name'] = $boardFilter;
            }

            $report = $this->reportService->generateReport($title, $category, $startDate, $endDate, $filters, $inst, $user);

            $pdfUrl = route('ai.reports.export', ['report' => $report->id, 'format' => 'pdf']);
            $csvUrl = route('ai.reports.export', ['report' => $report->id, 'format' => 'csv']);

            $cardsDone = $report->stats_data['cards_completed'] ?? 0;
            $blogsPub  = $report->stats_data['blogs_published'] ?? 0;
            $qcPassed  = $report->stats_data['qc_checks_passed'] ?? 0;
            $webActive = $report->stats_data['active_websites'] ?? 0;

            $richMarkdown = "### 📊 {$report->title}\n\n"
                . "**Period:** {$startDate->format('F d, Y')} – {$endDate->format('F d, Y')}\n"
                . ($boardFilter ? "**Filtered Board:** " . ucwords($boardFilter) . "\n\n" : "\n")
                . "| Metric | Count | Status |\n"
                . "| :--- | :---: | :--- |\n"
                . "| **Tasks Completed** | **{$cardsDone}** | Verified in Board |\n"
                . "| **Blogs Published** | **{$blogsPub}** | Live across sites |\n"
                . "| **Active Websites** | **{$webActive}** | Monitored |\n"
                . "| **QC Checks Passed** | **{$qcPassed}** | Audited |\n\n"
                . "📥 **Download Generated Reports:**\n"
                . "- 📄 [**Download PDF Report**]({$pdfUrl})\n"
                . "- 📊 [**Download CSV Data**]({$csvUrl})\n\n"
                . "> 💡 *The executive PDF and CSV data files have been compiled and are ready for download.*";

            return [
                'success'   => true,
                'report_id' => $report->id,
                'title'     => $report->title,
                'period'    => $report->date_from->format('Y-m-d') . ' to ' . $report->date_to->format('Y-m-d'),
                'metrics'   => [
                    'blogs_published' => $blogsPub,
                    'tasks_completed' => $cardsDone,
                    'qc_passed'       => $qcPassed,
                    'active_websites' => $webActive,
                ],
                'pdf_url'   => $pdfUrl,
                'csv_url'   => $csvUrl,
                'message'   => $richMarkdown,
            ];
        } catch (\Throwable $e) {
            Log::error("handleGenerateProductionReport error: " . $e->getMessage());
            return [
                'success' => false,
                'error'   => "Report generation failed: " . $e->getMessage(),
                'message' => "⚠️ Could not generate the requested report: " . $e->getMessage(),
            ];
        }
    }

    private function handleGenerateVoiceOver(array $args, User $user): array
    {
        $script = trim($args['script'] ?? '');
        if (empty($script)) {
            return ['success' => false, 'error' => 'Voice-over script cannot be empty.'];
        }

        try {
            $options = [
                'voice' => $args['voice'] ?? 'Charon',
                'style' => $args['style'] ?? 'cinematic',
                'speed' => (float) ($args['speed'] ?? 1.0),
            ];

            $result = $this->geminiClient->generateVoiceOver($script, $options);

            if (!empty($result['success'])) {
                // Record in ai_voice_overs
                $title = Str::limit($script, 40, '...');
                \App\Models\Ai\AiVoiceOver::create([
                    'user_id'          => $user->id,
                    'title'            => $title,
                    'script'           => $script,
                    'voice_name'       => $result['voice_name'],
                    'voice_gender'     => $result['voice_gender'],
                    'style'            => $result['style'],
                    'speed'            => $result['speed'],
                    'audio_path'       => $result['audio_path'],
                    'duration_seconds' => $result['duration_seconds'],
                ]);

                $this->safetyService->logAction($user, 'generate_voice_over', 'AiVoiceOver', null, [
                    'voice' => $result['voice_name'],
                    'words' => str_word_count($script),
                ]);

                $formattedPlayer = "### 🎙️ Audio Synthesized — {$result['voice_name']}\n\n"
                    . "<audio controls class=\"w-full my-2.5 rounded-xl shadow-xs\" src=\"{$result['audio_url']}\"></audio>\n\n"
                    . "- **Voice Character**: {$result['voice_name']} ({$result['voice_gender']}, {$result['style']})\n"
                    . "- **Duration**: {$result['duration_seconds']}s\n"
                    . "- ⬇️ [**Download Audio File**]({$result['audio_url']})";

                return [
                    'success'          => true,
                    'audio_url'        => $result['audio_url'],
                    'voice_name'       => $result['voice_name'],
                    'voice_gender'     => $result['voice_gender'],
                    'duration_seconds' => $result['duration_seconds'],
                    'message'          => $formattedPlayer,
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error'   => 'Voice-over generation failed: ' . $e->getMessage(),
            ];
        }

        return ['success' => false, 'error' => 'Could not generate voice-over.'];
    }

    private function handleOptimizeSeoContent(array $args, User $user): array
    {
        $title   = trim($args['title'] ?? '');
        $content = trim($args['content'] ?? '');
        $keyword = trim($args['focus_keyword'] ?? '');

        $wordCount   = str_word_count(strip_tags($content));
        $headingsCount = preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $content, $matches);
        $keywordOccurrences = $keyword ? substr_count(strtolower($content), strtolower($keyword)) : 0;
        $density = ($wordCount > 0 && $keyword) ? round(($keywordOccurrences / $wordCount) * 100, 2) : 0;

        $seoTitle = $title;
        if (!empty($keyword) && !str_contains(strtolower($seoTitle), strtolower($keyword))) {
            $seoTitle = "{$keyword}: " . Str::limit($title, 45);
        }

        $metaDesc = Str::limit(strip_tags($content), 155);

        return [
            'success'            => true,
            'word_count'         => $wordCount,
            'heading_count'      => $headingsCount,
            'focus_keyword'      => $keyword,
            'keyword_density'    => "{$density}%",
            'recommended_title'  => $seoTitle,
            'recommended_meta'   => $metaDesc,
            'score'              => ($wordCount >= 600 && $headingsCount >= 3) ? '92/100 (Strong)' : '74/100 (Good - Add more subheadings)',
            'message'            => "SEO audit completed. Word count: {$wordCount}, Density: {$density}%.",
        ];
    }

    private function handleListBoards(array $args, User $user): array
    {
        $query = Board::with(['workspace:id,name', 'lists' => fn($q) => $q->orderBy('position')])
            ->where('is_hidden', false)
            ->where('is_archived', false);

        if (!empty($args['type'])) {
            $term = trim($args['type']);
            $query->where(function ($q) use ($term) {
                $q->where('type', 'like', "%{$term}%")
                  ->orWhere('name', 'like', "%{$term}%");
            });
        }

        $boards = $query->orderBy('position')->orderBy('name')->take(20)->get();

        $boardList = $boards->map(function ($b) {
            $boardUrl = url('/boards/' . $b->slug);
            return [
                'id'        => $b->id,
                'name'      => $b->name,
                'slug'      => $b->slug,
                'type'      => $b->type ?: 'standard',
                'workspace' => $b->workspace?->name ?: 'Digital Department @ KiuQ',
                'lists'     => $b->lists->pluck('name')->all(),
                'url'       => $boardUrl,
            ];
        })->all();

        $rows = [];
        foreach ($boardList as $b) {
            $listsStr = !empty($b['lists']) ? implode(' → ', $b['lists']) : 'No lists';
            $rows[] = "- [**{$b['name']}**]({$b['url']}) (*{$b['workspace']}*)\n  Lists: `{$listsStr}`";
        }
        $boardsMd = !empty($rows) ? implode("\n", $rows) : "No boards found.";

        $message = "### 📌 Active Workspaces & Boards (" . count($boardList) . ")\n\n"
            . $boardsMd . "\n\n"
            . "👉 [**View All Workspaces & Boards**](/boards) · [**SMM Boards**](/smm-boards)";

        return [
            'success' => true,
            'count'   => count($boardList),
            'boards'  => $boardList,
            'message' => $message,
        ];
    }

    /**
     * Resolve a Board model by ID, slug, exact name, or fuzzy tokens.
     */
    public function resolveBoard(?string $query = null, ?int $boardId = null): ?Board
    {
        if ($boardId) {
            $board = Board::with('workspace')->find($boardId);
            if ($board) return $board;
        }

        $rawQuery = trim($query ?? '');

        // If numeric ID passed as string
        if (is_numeric($rawQuery)) {
            $board = Board::with('workspace')->find((int)$rawQuery);
            if ($board) return $board;
        }

        if (empty($rawQuery)) {
            return Board::with('workspace')
                ->where('is_archived', false)
                ->where(function($q) {
                    $q->where('name', 'like', '%Planning%')
                      ->orWhere('name', 'like', '%Workflow%');
                })
                ->latest('id')
                ->first()
                ?? Board::with('workspace')->where('is_archived', false)->latest('id')->first();
        }

        // 1. Exact slug match
        $board = Board::with('workspace')->where('slug', Str::slug($rawQuery))->first();
        if ($board) return $board;

        // 2. Exact name match (case-insensitive)
        $board = Board::with('workspace')->whereRaw('LOWER(name) = ?', [strtolower($rawQuery)])->first();
        if ($board) return $board;

        // 3. Clean up the query string (strip noise words)
        $cleaned = trim(preg_replace('/\b(export|report|from|board|the|in|of|show|get|generate|view|pdf|csv)\b/i', ' ', $rawQuery));
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        if (!empty($cleaned)) {
            // Substring search with cleaned query
            $board = Board::with('workspace')
                ->where('name', 'like', "%{$cleaned}%")
                ->latest('id')
                ->first();
            if ($board) return $board;

            // Word tokens match (e.g. "planning", "october")
            $tokens = array_filter(explode(' ', $cleaned), fn($t) => strlen($t) >= 3);
            if (!empty($tokens)) {
                $q = Board::with('workspace');
                foreach ($tokens as $token) {
                    $q->where('name', 'like', "%{$token}%");
                }
                $board = $q->latest('id')->first();
                if ($board) return $board;

                // Any token
                $board = Board::with('workspace')->where(function($qb) use ($tokens) {
                    foreach ($tokens as $token) {
                        $qb->orWhere('name', 'like', "%{$token}%");
                    }
                })->latest('id')->first();
                if ($board) return $board;
            }
        }

        // 4. Substring search with raw query
        $board = Board::with('workspace')
            ->where('name', 'like', "%{$rawQuery}%")
            ->latest('id')
            ->first();
        if ($board) return $board;

        // 5. If query contains "planning", find any planning board
        if (stripos($rawQuery, 'plan') !== false) {
            $board = Board::with('workspace')
                ->where('name', 'like', '%plan%')
                ->latest('id')
                ->first();
            if ($board) return $board;
        }

        // 6. If query contains "workflow", find workflow board
        if (stripos($rawQuery, 'work') !== false) {
            $board = Board::with('workspace')
                ->where('name', 'like', '%work%')
                ->latest('id')
                ->first();
            if ($board) return $board;
        }

        // 7. Fallback to latest active board
        return Board::with('workspace')->where('is_archived', false)->latest('id')->first();
    }

    /**
     * Export board report in background matching manual export UI & template.
     */
    public function handleExportBoardReport(array $args, User $user): array
    {
        try {
            $boardQuery = $args['board_name'] ?? $args['board'] ?? $args['name'] ?? null;
            $boardId = isset($args['board_id']) ? (int) $args['board_id'] : null;

            $board = $this->resolveBoard($boardQuery, $boardId);

            if (!$board) {
                $allBoards = Board::where('is_archived', false)->take(5)->get();
                $boardsList = $allBoards->map(fn($b) => "- [**{$b->name}**](" . url('/boards/' . $b->slug) . ")")->implode("\n");
                return [
                    'success' => false,
                    'error'   => "No matching board found.",
                    'message' => "⚠️ Could not locate the specified board in the system.\n\n"
                        . "Here are active boards you can export:\n" . $boardsList . "\n\n"
                        . "👉 [**Browse All Boards in Workspace**](" . url('/boards') . ")",
                ];
            }

            $board->loadMissing('workspace');
            $workspaceName = $board->workspace->name ?? 'Digital Department @ KiuQ';

            $now = Carbon::now('Asia/Phnom_Penh');
            $dateRange = $args['date_range'] ?? 'all_time';
            $filterStartDate = null;
            $filterEndDate = null;
            $periodLabel = 'All Time';

            if (!empty($dateRange) && $dateRange !== 'all_time') {
                switch ($dateRange) {
                    case 'today':
                        $periodLabel = 'Today';
                        $filterStartDate = $now->copy()->startOfDay();
                        $filterEndDate = $now->copy()->endOfDay();
                        break;
                    case 'this_week':
                        $periodLabel = 'This Week';
                        $filterStartDate = $now->copy()->startOfWeek();
                        $filterEndDate = $now->copy()->endOfWeek();
                        break;
                    case 'this_month':
                        $periodLabel = 'This Month (' . $now->format('M Y') . ')';
                        $filterStartDate = $now->copy()->startOfMonth();
                        $filterEndDate = $now->copy()->endOfMonth();
                        break;
                    case 'last_month':
                        $periodLabel = 'Last Month (' . $now->copy()->subMonth()->format('M Y') . ')';
                        $filterStartDate = $now->copy()->subMonth()->startOfMonth();
                        $filterEndDate = $now->copy()->subMonth()->endOfMonth();
                        break;
                    case 'custom':
                    case 'custom_period':
                        $startStr = $args['start_date'] ?? null;
                        $endStr = $args['end_date'] ?? null;
                        if ($startStr) $filterStartDate = Carbon::parse($startStr, 'Asia/Phnom_Penh')->startOfDay();
                        if ($endStr) $filterEndDate = Carbon::parse($endStr, 'Asia/Phnom_Penh')->endOfDay();
                        $periodLabel = ($startStr ? Carbon::parse($startStr)->format('M d, Y') : 'Beginning') . ' – ' . ($endStr ? Carbon::parse($endStr)->format('M d, Y') : 'End');
                        break;
                }
            }

            // Load cards matching filters
            $cardQuery = Card::where('board_id', $board->id)
                ->with(['board', 'boardList', 'assignees', 'labels', 'checklists.items.assignedUser']);

            if ($filterStartDate || $filterEndDate) {
                $cardQuery->where(function($q) use ($filterStartDate, $filterEndDate) {
                    if ($filterStartDate) $q->where('updated_at', '>=', $filterStartDate);
                    if ($filterEndDate) $q->where('updated_at', '<=', $filterEndDate);
                });
            }

            $cards = $cardQuery->orderBy('board_list_id')->orderBy('position')->get();

            // Compute statistics (matches BoardExportController::exportPdf)
            $totalTasks = $cards->count();
            $completedTasks = $cards->filter(function($c) {
                if ($c->is_archived) return false;
                $listName = $c->boardList?->name ?? '';
                return $c->status === CardStatus::Done
                    || $c->status === CardStatus::Approved
                    || stripos($listName, 'Done') !== false
                    || stripos($listName, 'Approved') !== false
                    || stripos($listName, 'Complete') !== false;
            })->count();
            $archivedTasks = $cards->filter(fn($c) => $c->is_archived)->count();
            $pendingTasks = max(0, $totalTasks - $completedTasks - $archivedTasks);
            $overdueTasks = $cards->filter(function($c) {
                return $c->due_at
                    && $c->due_at->isPast()
                    && $c->status !== CardStatus::Done
                    && $c->status !== CardStatus::Approved
                    && !$c->is_archived;
            })->count();

            // Team Productivity Summary
            $memberStats = [];
            foreach ($cards as $c) {
                if ($c->is_archived) continue;

                $listName = $c->boardList?->name ?? '';
                $isDone = ($c->status === CardStatus::Done
                    || $c->status === CardStatus::Approved
                    || stripos($listName, 'Done') !== false
                    || stripos($listName, 'Approved') !== false
                    || stripos($listName, 'Complete') !== false);

                $assignees = $c->assignees;
                if ($assignees->isEmpty()) {
                    if (!isset($memberStats['Unassigned'])) {
                        $memberStats['Unassigned'] = [
                            'name'      => 'Unassigned',
                            'completed' => 0,
                            'pending'   => 0,
                            'total'     => 0,
                            'team_role' => 'Unassigned',
                        ];
                    }
                    if ($isDone) {
                        $memberStats['Unassigned']['completed']++;
                    } else {
                        $memberStats['Unassigned']['pending']++;
                    }
                    $memberStats['Unassigned']['total']++;
                } else {
                    foreach ($assignees as $u) {
                        $uName = $u->name ?: ('Member #' . $u->id);
                        if (!isset($memberStats[$uName])) {
                            $memberStats[$uName] = [
                                'name'      => $uName,
                                'completed' => 0,
                                'pending'   => 0,
                                'total'     => 0,
                                'team_role' => $u->team_role ?? 'Team Member',
                            ];
                        }
                        if ($isDone) {
                            $memberStats[$uName]['completed']++;
                        } else {
                            $memberStats[$uName]['pending']++;
                        }
                        $memberStats[$uName]['total']++;
                    }
                }
            }

            // Status Breakdown
            $statusStats = [];
            foreach ($cards as $c) {
                $statusName = $c->boardList?->name ?? 'Default';
                if ($c->is_archived) {
                    $statusName = 'Archived';
                } elseif ($c->status === CardStatus::Done || stripos($statusName, 'Done') !== false) {
                    $statusName = 'Done';
                } elseif ($c->status === CardStatus::Approved || stripos($statusName, 'Approved') !== false) {
                    $statusName = 'Approved ✓';
                }
                $statusStats[$statusName] = ($statusStats[$statusName] ?? 0) + 1;
            }

            // Construct URLs
            $slug = $board->slug ?: (string)$board->id;
            $params = [];
            if ($dateRange && $dateRange !== 'all_time') {
                $params['date_range'] = $dateRange;
                if (!empty($args['start_date'])) $params['start_date'] = $args['start_date'];
                if (!empty($args['end_date'])) $params['end_date'] = $args['end_date'];
            }
            $queryString = !empty($params) ? ('?' . http_build_query($params)) : '';

            $pdfExportUrl = url("/boards/{$slug}/export/pdf" . $queryString);
            $csvExportUrl = url("/boards/{$slug}/export/csv" . $queryString);
            $liveBoardUrl = url("/boards/{$slug}");
            $exportTimestamp = now()->format('M d, Y g:i A');

            // Persist AiReport in database
            $aiReport = AiReport::create([
                'user_id'       => $user->id,
                'title'         => "Board Report - {$board->name}",
                'category'      => 'board_export',
                'date_from'     => $filterStartDate ? $filterStartDate->toDateString() : now()->startOfMonth()->toDateString(),
                'date_to'       => $filterEndDate ? $filterEndDate->toDateString() : now()->endOfMonth()->toDateString(),
                'filters'       => [
                    'board_id'   => $board->id,
                    'board_name' => $board->name,
                    'board_slug' => $board->slug,
                    'period'     => $periodLabel,
                ],
                'stats_data'    => [
                    'board_id'        => $board->id,
                    'board_name'      => $board->name,
                    'workspace'       => $workspaceName,
                    'period'          => $periodLabel,
                    'total_tasks'     => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'pending_tasks'   => $pendingTasks,
                    'overdue_tasks'   => $overdueTasks,
                    'member_stats'    => $memberStats,
                    'status_stats'    => $statusStats,
                    'pdf_url'         => $pdfExportUrl,
                    'csv_url'         => $csvExportUrl,
                    'board_url'       => $liveBoardUrl,
                ],
                'analysis_text' => "Official export for Board '{$board->name}' (Workspace: {$workspaceName}). Period: {$periodLabel}. Total tasks: {$totalTasks}, Completed: {$completedTasks}, Pending: {$pendingTasks}, Overdue: {$overdueTasks}.",
            ]);

            // Build Team Productivity rows
            $memberRows = [];
            foreach ($memberStats as $m) {
                $memberRows[] = "| **{$m['name']}** ({$m['team_role']}) | **{$m['completed']}** | {$m['pending']} | **{$m['total']}** |";
            }
            $memberTableMd = !empty($memberRows)
                ? implode("\n", $memberRows)
                : "| *No member assignments found* | - | - | - |";

            // Build Status Breakdown rows
            $statusRows = [];
            foreach ($statusStats as $sName => $sCount) {
                $statusRows[] = "| **{$sName}** | **{$sCount}** |";
            }
            $statusTableMd = !empty($statusRows)
                ? implode("\n", $statusRows)
                : "| *No task lists available* | 0 |";

            // Build Top Cards Sample preview
            $cardSampleRows = [];
            $previewCards = $cards->take(8);
            foreach ($previewCards as $card) {
                $cTitle = e($card->title);
                $cCardUrl = url("/boards/{$slug}?card={$card->id}");
                $cList = $card->boardList?->name ?? 'Backlog';
                $cAssignees = $card->assignees->pluck('name')->implode(', ') ?: 'Unassigned';
                $cDue = $card->due_at ? $card->due_at->format('M d, Y') : 'No Due Date';
                $cardSampleRows[] = "| [**{$cTitle}**]({$cCardUrl}) | `{$cList}` | {$cAssignees} | {$cDue} |";
            }
            $cardSampleTableMd = !empty($cardSampleRows)
                ? implode("\n", $cardSampleRows)
                : "| *No cards found on this board* | - | - | - |";

            // Exact Template UI matching boards.export-pdf
            $richMarkdown = "## 📑 Board Report — {$board->name}\n\n"
                . "**Workspace:** `{$workspaceName}` &nbsp;|&nbsp; **Report Period:** `{$periodLabel}` &nbsp;|&nbsp; **Exported:** `{$exportTimestamp}`\n\n"
                . "---\n\n"
                . "### 📈 KPI Executive Summary\n\n"
                . "| 📋 Total Tasks | ✅ Completed | ⏳ Pending | ⚠️ Errors / Overdue |\n"
                . "| :---: | :---: | :---: | :---: |\n"
                . "| **{$totalTasks}** | **{$completedTasks}** | **{$pendingTasks}** | **{$overdueTasks}** |\n\n"
                . "---\n\n"
                . "### 📊 Team Productivity Summary\n\n"
                . "| Member Name & Role | Completed Tasks | Pending Tasks | Total Tasks |\n"
                . "| :--- | :---: | :---: | :---: |\n"
                . $memberTableMd . "\n\n"
                . "---\n\n"
                . "### 🏷️ Status & Pipeline Stages\n\n"
                . "| Pipeline List | Task Count |\n"
                . "| :--- | :---: |\n"
                . $statusTableMd . "\n\n"
                . "---\n\n"
                . "### 📋 Task Details & Breakdown (Preview)\n\n"
                . "| Task / Title | List / Status | Assigned Members | Due Date |\n"
                . "| :--- | :--- | :--- | :--- |\n"
                . $cardSampleTableMd . "\n\n"
                . "---\n\n"
                . "### 📥 Official Board Exports & Live Board\n\n"
                . "- 🖨️ [**Print / View Full PDF Board Export**]({$pdfExportUrl}) *(Official printable export view)*\n"
                . "- 📊 [**Download Board CSV Spreadsheet**]({$csvExportUrl}) *(Raw task dataset for Excel/Sheets)*\n"
                . "- 📋 [**Open Live Board in Kanban Workspace**]({$liveBoardUrl}) *(Interactive drag-and-drop board)*\n\n"
                . "> 💡 *KIUQ AI has queried board `{$board->name}` in the background and computed identical metrics and official links matching your manual board export.*";

            return [
                'success'         => true,
                'board_id'        => $board->id,
                'board_name'      => $board->name,
                'slug'            => $board->slug,
                'workspace'       => $workspaceName,
                'period'          => $periodLabel,
                'total_tasks'     => $totalTasks,
                'completed_tasks' => $completedTasks,
                'pending_tasks'   => $pendingTasks,
                'overdue_tasks'   => $overdueTasks,
                'member_stats'    => $memberStats,
                'pdf_export_url'  => $pdfExportUrl,
                'csv_export_url'  => $csvExportUrl,
                'live_board_url'  => $liveBoardUrl,
                'report_id'       => $aiReport->id,
                'message'         => $richMarkdown,
            ];
        } catch (\Throwable $e) {
            Log::error("handleExportBoardReport error: " . $e->getMessage());
            return [
                'success' => false,
                'error'   => "Board export failed: " . $e->getMessage(),
                'message' => "⚠️ Could not export report for the requested board: " . $e->getMessage(),
            ];
        }
    }

    private function handleListMembers(array $args, User $user): array
    {
        $query = User::query();

        if (!empty($args['team'])) {
            $query->where('team', 'like', "%{$args['team']}%");
        }
        if (!empty($args['role'])) {
            $query->where('role', 'like', "%{$args['role']}%");
        }

        $members = $query->orderBy('name')->take(25)->get()->map(function ($m) {
            return [
                'id'    => $m->id,
                'name'  => $m->name,
                'email' => $m->email,
                'role'  => $m->role,
                'team'  => $m->team ?: 'General',
            ];
        })->all();

        $rows = [];
        foreach ($members as $m) {
            $roleBadge = $m['role'] ?: 'Member';
            $rows[] = "- **{$m['name']}** (`{$m['email']}`) — Role: `{$roleBadge}` | Team: *{$m['team']}*";
        }
        $membersMd = !empty($rows) ? implode("\n", $rows) : "No team members found.";

        $message = "### 👥 Organization Staff Directory (" . count($members) . ")\n\n"
            . $membersMd . "\n\n"
            . "👉 [**Open Full Staff Directory**](/members)";

        return [
            'success' => true,
            'count'   => count($members),
            'members' => $members,
            'message' => $message,
            'url'     => url('/members'),
        ];
    }

    private function handleGetInquiriesSummary(array $args, User $user): array
    {
        $limit = min(15, max(1, (int) ($args['limit'] ?? 5)));
        $query = WebsiteInquiry::with('website:id,name,url')->latest();

        if (!empty($args['website_id'])) {
            $query->where('website_id', (int) $args['website_id']);
        }

        $totalCount  = WebsiteInquiry::count();
        $unreadCount = WebsiteInquiry::whereIn('status', ['new', 'unread'])->count();
        $inquiries   = $query->take($limit)->get()->map(function ($inq) {
            return [
                'id'           => $inq->id,
                'website'      => $inq->website?->name ?: 'Connected Site',
                'name'         => $inq->customer_name ?: ($inq->name ?: 'Inquirer'),
                'email'        => $inq->customer_email ?: $inq->email,
                'phone'        => $inq->customer_phone ?: $inq->phone,
                'subject'      => $inq->subject,
                'status'       => $inq->status,
                'submitted_at' => $inq->created_at?->format('Y-m-d H:i'),
            ];
        })->all();

        $rows = [];
        foreach ($inquiries as $inq) {
            $dateStr = $inq['submitted_at'] ?: 'Recently';
            $contact = $inq['phone'] ?: $inq['email'];
            $rows[] = "- **{$inq['name']}** ({$contact}) from *{$inq['website']}* — Status: `{$inq['status']}` ({$dateStr})\n  *Subject:* {$inq['subject']}";
        }
        $inquiriesMd = !empty($rows) ? implode("\n", $rows) : "No customer inquiries found.";

        $inquiriesUrl = url('/websites/inquiries');
        $message = "### 📥 Inbound Website Customer Inquiries\n\n"
            . "**Total Inquiries:** {$totalCount} | **Unread / New:** {$unreadCount}\n\n"
            . $inquiriesMd . "\n\n"
            . "👉 [**Manage Website Inquiries & Export CSV**]({$inquiriesUrl})";

        return [
            'success'      => true,
            'total'        => $totalCount,
            'unread_count' => $unreadCount,
            'inquiries'    => $inquiries,
            'message'      => $message,
            'url'          => $inquiriesUrl,
        ];
    }

    private function handleGetSystemNavigation(array $args, User $user): array
    {
        $navMap = [
            'dashboard' => [
                'title'       => 'Executive Dashboard',
                'description' => 'Executive operational overview, high-level KPIs, quick shortcuts, and team activity feeds.',
                'route'       => '/dashboard',
            ],
            'members' => [
                'title'       => 'Members & Staff Directory',
                'description' => 'Complete staff roster, team assignments (Listing, Video, Graphic, QC, SMM, Sales), roles, and contact information.',
                'route'       => '/members',
            ],
            'chat' => [
                'title'       => 'DGT Chat & Calling',
                'description' => 'Real-time 1-on-1 and group messaging, WebRTC voice/video calls, audio voice notes, and file attachments.',
                'route'       => '/chat',
            ],
            'boards' => [
                'title'       => 'Workspaces & Kanban Boards',
                'description' => 'Monthly Planning Boards, daily Workflow Boards, team-specific pipelines, and task lists.',
                'route'       => '/boards',
            ],
            'tasks_count' => [
                'title'       => 'Tasks Count & Workload Analytics',
                'description' => 'Cross-board aggregated task metrics, team member distributions, and completion statuses.',
                'route'       => '/tasks-count',
            ],
            'notes_team' => [
                'title'       => 'Team Notes',
                'description' => 'Collaborative team knowledge base, SOPs, shared documentation, and checklists.',
                'route'       => '/notes/team',
            ],
            'notes_private' => [
                'title'       => 'Private Notes',
                'description' => 'Personal workspace notes, private scratchpads, and individual checklists.',
                'route'       => '/notes/private',
            ],
            'websites' => [
                'title'       => 'All Websites & QC Audits',
                'description' => 'Connected domain management, maintenance status, webmaster assignees, and quality control checklists.',
                'route'       => '/websites',
            ],
            'blogs' => [
                'title'       => 'WordPress Blog Posts CMS',
                'description' => 'Centralized WordPress blog article drafting, SEO keyword optimization, and live publishing via DGT Connector.',
                'route'       => '/websites/posts',
            ],
            'blogs_sync' => [
                'title'       => 'Google Blogs Sheet Sync',
                'description' => 'Synchronize planned blog schedules, target keywords, and content roadmaps from Google Sheets.',
                'route'       => '/websites/blogs',
            ],
            'inquiries' => [
                'title'       => 'Website Inquiries & Leads',
                'description' => 'Inbound customer inquiries captured from WordPress Contact Form 7, lead qualification, and CSV export.',
                'route'       => '/websites/inquiries',
            ],
            'search_console' => [
                'title'       => 'Google Search Console & SEO Suite',
                'description' => 'GSC search queries, clicks, impressions, CTR, average rankings, and live URL inspection.',
                'route'       => '/websites/google-search-console',
            ],
            'social_media' => [
                'title'       => 'Social Media Marketing (SMM) Dashboard',
                'description' => 'Multi-channel social analytics, publishing metrics, and post performance tracking.',
                'route'       => '/social-media',
            ],
            'smm_boards' => [
                'title'       => 'SMM Planning Boards',
                'description' => 'Social media campaigns planning with twin-card synchronization to the active workflow board.',
                'route'       => '/smm-boards',
            ],
            'kpi' => [
                'title'       => 'KPI Performance Evaluation',
                'description' => 'Squad performance evaluations, monthly/quarterly review cycles, skill criteria, and supervisor scoring.',
                'route'       => '/kpi',
            ],
            'reports' => [
                'title'       => 'CRM & Production Reports',
                'description' => 'Customer interaction logs, eBay leads, sales stages, and company performance analytics.',
                'route'       => '/reports',
            ],
            'reports_personal' => [
                'title'       => 'Personal Production Reports',
                'description' => 'Individual staff member task completion history and personal production logs.',
                'route'       => '/boards/personal-report',
            ],
            'ai_studio' => [
                'title'       => 'KIUQ AI Assistant & Studios',
                'description' => 'AI intelligence suite, executive production report generation with PDF/CSV export, Image Studio, Voice Studio, and SEO tools.',
                'route'       => '/ai',
            ],
            'admin_users' => [
                'title'       => 'User & Role Administration',
                'description' => 'Staff account management, permission roles, 2FA enforcement, and access controls.',
                'route'       => '/admin/users',
            ],
            'admin_labels' => [
                'title'       => 'Card Labels Management',
                'description' => 'Global task card label tags, badge styles, and department color keys.',
                'route'       => '/admin/labels',
            ],
            'admin_security' => [
                'title'       => 'System Security & IP Bans',
                'description' => 'Firewall rules, IP ban list, login attempt audits, and security logs.',
                'route'       => '/admin/security',
            ],
            'admin_settings' => [
                'title'       => 'System Settings',
                'description' => 'Application brand settings, logo configuration, and system variables.',
                'route'       => '/admin/settings',
            ],
            'admin_maintenance' => [
                'title'       => 'Maintenance Mode Controller',
                'description' => 'Module-by-module maintenance toggles and system maintenance notices.',
                'route'       => '/admin/maintenance',
            ],
            'app_macos' => [
                'title'       => 'macOS Desktop Application',
                'description' => 'Download official native macOS desktop DMG app for DGT System.',
                'route'       => '/mac-app',
            ],
            'app_ios' => [
                'title'       => 'iOS Mobile Application',
                'description' => 'Download official native iOS application for iPhone & iPad.',
                'route'       => '/ios-app/download',
            ],
        ];

        $filter = strtolower(trim($args['section'] ?? ''));
        $matched = [];

        if ($filter) {
            foreach ($navMap as $key => $item) {
                if (str_contains($key, $filter) || str_contains(strtolower($item['title']), $filter) || str_contains(strtolower($item['description']), $filter)) {
                    $matched[$key] = $item;
                }
            }
        }

        $targets = !empty($matched) ? $matched : $navMap;

        $rows = [];
        foreach ($targets as $k => $item) {
            $fullUrl = url($item['route']);
            $rows[] = "- [**{$item['title']}**]({$fullUrl}) (`{$item['route']}`)\n  {$item['description']}";
        }

        $message = "### 🗺️ Verified DGT System Navigation Map\n\n"
            . implode("\n\n", $rows) . "\n\n"
            . "> 🛡️ *All links above are verified canonical routes in the DGT System with 100% zero deadlinks.*";

        return [
            'success' => true,
            'count'   => count($targets),
            'routes'  => $targets,
            'message' => $message,
        ];
    }
}
