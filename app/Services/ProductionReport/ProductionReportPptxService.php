<?php

namespace App\Services\ProductionReport;

use App\Models\ProductionReport\ProductionReport;
use App\Models\ProductionReport\ProductionReportMedia;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ProductionReportPptxService
{
    /**
     * Generate a complete 12-slide corporate OpenXML PowerPoint (.pptx) file
     * and store it in private storage. Returns the absolute path to the generated .pptx file.
     */
    public function generatePptx(ProductionReport $report): string
    {
        $report->loadMissing(['user', 'items', 'media.creator', 'revisions']);

        $relativeDir = "{$report->storage_folder}/exports";
        Storage::disk('local')->makeDirectory($relativeDir);

        $fileName = "{$report->report_code}.pptx";
        $relativePath = "{$relativeDir}/{$fileName}";
        $fullPath = Storage::disk('local')->path($relativePath);

        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }

        $slidesData = $this->buildTwelveSlidesContent($report);

        $zip = new ZipArchive();
        if ($zip->open($fullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create PowerPoint (.pptx) archive.');
        }

        $slideCount = count($slidesData);
        $embeddedMedia = []; // slideIndex (1-based) => [ ['rId' => 'rId2', 'target' => '../media/image1.png', 'zipPath' => 'ppt/media/image1.png', 'bytes' => ..., 'ext' => 'png', 'title' => ...] ]

        // Collect up to 3 real image files for Slide 7 (Best Posters & Graphics)
        $posterMedia = $report->media
            ->filter(fn (ProductionReportMedia $m) => $m->isImage() && $m->file_path && Storage::disk($m->disk ?: 'local')->exists($m->file_path))
            ->take(3)
            ->values();

        $mediaCounter = 1;
        foreach ($posterMedia as $idx => $mediaItem) {
            $absImgPath = Storage::disk($mediaItem->disk ?: 'local')->path($mediaItem->file_path);
            $ext = strtolower(pathinfo($absImgPath, PATHINFO_EXTENSION) ?: 'png');
            if (!in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
                continue;
            }
            $zipMediaName = "image{$mediaCounter}.{$ext}";
            $zip->addFile($absImgPath, "ppt/media/{$zipMediaName}");
            $embeddedMedia[7][] = [
                'rId' => 'rIdImg' . $mediaCounter,
                'target' => "../media/{$zipMediaName}",
                'title' => $mediaItem->title,
                'creator' => $mediaItem->creator?->name ?? $report->user?->name ?? 'Production Team',
                'index' => $idx,
            ];
            $mediaCounter++;
        }

        // 1. [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', $this->buildContentTypesXml($slideCount));

        // 2. _rels/.rels
        $zip->addFromString('_rels/.rels', $this->buildRootRelsXml());

        // 3. docProps/app.xml & docProps/core.xml
        $zip->addFromString('docProps/app.xml', $this->buildAppPropsXml($slideCount));
        $zip->addFromString('docProps/core.xml', $this->buildCorePropsXml($report));

        // 4. ppt/presentation.xml & ppt/_rels/presentation.xml.rels & ppt/presProps.xml & ppt/viewProps.xml & ppt/tableStyles.xml
        $zip->addFromString('ppt/presentation.xml', $this->buildPresentationXml($slideCount));
        $zip->addFromString('ppt/_rels/presentation.xml.rels', $this->buildPresentationRelsXml($slideCount));
        $zip->addFromString('ppt/presProps.xml', $this->buildPresPropsXml());
        $zip->addFromString('ppt/viewProps.xml', $this->buildViewPropsXml());
        $zip->addFromString('ppt/tableStyles.xml', $this->buildTableStylesXml());

        // 5. Theme, Slide Master, Slide Layout
        $zip->addFromString('ppt/theme/theme1.xml', $this->buildThemeXml());
        $zip->addFromString('ppt/slideMasters/slideMaster1.xml', $this->buildSlideMasterXml());
        $zip->addFromString('ppt/slideMasters/_rels/slideMaster1.xml.rels', $this->buildSlideMasterRelsXml());
        $zip->addFromString('ppt/slideLayouts/slideLayout1.xml', $this->buildSlideLayoutXml());
        $zip->addFromString('ppt/slideLayouts/_rels/slideLayout1.xml.rels', $this->buildSlideLayoutRelsXml());

        // 6. Individual Slides (1..12)
        foreach ($slidesData as $i => $slide) {
            $slideNum = $i + 1;
            $slideImages = $embeddedMedia[$slideNum] ?? [];
            $zip->addFromString("ppt/slides/slide{$slideNum}.xml", $this->buildSlideXml($slideNum, $slide, $slideImages));
            $zip->addFromString("ppt/slides/_rels/slide{$slideNum}.xml.rels", $this->buildSlideRelsXml($slideImages));
        }

        $zip->close();

        $report->update([
            'pptx_path' => $relativePath,
            'last_exported_at' => now('Asia/Phnom_Penh'),
        ]);

        return $fullPath;
    }

    /**
     * Build the structured content for all 12 slides.
     */
    public function buildTwelveSlidesContent(ProductionReport $report): array
    {
        $summary = is_array($report->compiled_summary_json) ? $report->compiled_summary_json : [];
        $posters = $report->media->filter(fn ($m) => in_array($m->media_type, ['poster', 'graphic', 'screenshot'], true) || $m->isImage())->values();
        $videos = $report->media->filter(fn ($m) => $m->media_type === 'video' || $m->isVideo() || filled($m->external_url))->values();

        $memberRows = $summary['member_summary_table'] ?? [];
        $memberLines = [];
        if (!empty($memberRows)) {
            foreach ($memberRows as $row) {
                $memberLines[] = sprintf(
                    "%s — Completed: %s | Submitted: %d/%d days | Achievements: %s",
                    $row['member_name'] ?? 'Member',
                    $row['completed_tasks_count'] ?? 0,
                    $row['submitted_days'] ?? 0,
                    $row['expected_days'] ?? 0,
                    $this->truncateText((string) ($row['key_achievements'] ?? '—'), 90)
                );
            }
        } else {
            $memberLines[] = ($report->user?->name ?? 'Author') . ' — Individual ' . ucfirst($report->report_type) . ' Report';
            foreach ($report->items->take(6) as $item) {
                $memberLines[] = "{$item->task_title} ({$item->quantity} {$item->unit}) — [" . strtoupper($item->status) . "]";
            }
        }

        $posterLines = [];
        foreach ($posters->take(6) as $p) {
            $creatorName = $p->creator?->name ?? $report->user?->name ?? 'Team';
            $dateStr = $p->production_date ? $p->production_date->format('M d, Y') : 'N/A';
            $posterLines[] = "{$p->title} | Creator: {$creatorName} | Category: " . ($p->project_category ?: 'Design') . " | Date: {$dateStr}";
        }
        if (empty($posterLines)) {
            $posterLines[] = 'No poster or graphic files attached to this report.';
        }

        $videoLines = [];
        foreach ($videos->take(6) as $v) {
            $creatorName = $v->creator?->name ?? $report->user?->name ?? 'Team';
            $platform = $v->platform ?: 'Video';
            $urlInfo = $v->external_url ? " — {$v->external_url}" : ($v->original_filename ? " — {$v->original_filename}" : '');
            $videoLines[] = "{$v->title} ({$platform}) | Creator: {$creatorName}{$urlInfo}";
        }
        if (empty($videoLines)) {
            $videoLines[] = 'No video deliverables or external links attached to this report.';
        }

        $kpiLines = [
            'Report Type: ' . strtoupper($report->report_type) . ' PRODUCTION REPORT',
            'Team / Unit: ' . ($report->team_name ?: ('Team ' . ($report->team_code ?: 'General'))),
            'Reporting Period: ' . $report->period_label,
            'Submission Status: ' . $report->status_badge_label . ($report->is_late ? ' (Late Submission)' : ' (On Time)'),
            'Total Completed Tasks / Deliverables: ' . ($summary['total_completed_tasks'] ?? $report->items->where('status', 'completed')->count()),
            'Uploaded Posters & Graphics: ' . $posters->count() . ' | Videos & Links: ' . $videos->count(),
            'Missing Daily Reports in Period: ' . ($summary['missing_submissions_count'] ?? $summary['missing_days_count'] ?? 0),
        ];

        $ratingValue = $report->performance_rating;
        $ratingLines = [
            'Overall Performance Rating: ' . ($ratingValue ? "{$ratingValue} / 5 ({$report->performance_rating_label})" : 'Not Applicable / Not Rated'),
            'Rating Scale Reference: 1 = Needs Improvement | 2 = Below Expectations | 3 = Meets Expectations | 4 = Strong Performance | 5 = Excellent',
            'Head of Production Comments: ' . ($report->head_comments ?: 'No additional Head comments recorded.'),
            'Supervisor Comments: ' . ($report->supervisor_comments ?: 'Pending / View-only oversight by Supervisor Somalika.'),
        ];

        return [
            [
                'slide_no' => 1,
                'is_cover' => true,
                'kicker' => 'KIUQ SYSTEM • PRODUCTION REPORT MANAGEMENT',
                'title' => $report->title,
                'subtitle' => ($report->team_name ?: 'Production Team') . ' • ' . $report->period_label,
                'bullets' => [
                    'Prepared By: ' . ($report->user?->name ?? 'Production Team'),
                    'Report Code: ' . $report->report_code,
                    'Submitted At: ' . ($report->submitted_at ? $report->submitted_at->timezone('Asia/Phnom_Penh')->format('M d, Y g:i A') . ' (Cambodia Time)' : 'Draft / Not Yet Submitted'),
                    'Status: ' . $report->status_badge_label . ($ratingValue ? "  |  Rating: {$ratingValue}/5 ({$report->performance_rating_label})" : ''),
                ],
            ],
            [
                'slide_no' => 2,
                'kicker' => 'SECTION 01 • EXECUTIVE OVERVIEW',
                'title' => 'Executive Summary',
                'subtitle' => $report->period_label,
                'bullets' => $this->splitTextIntoBullets($report->executive_summary ?: $report->tasks_completed ?: 'Executive summary not provided.', 6),
            ],
            [
                'slide_no' => 3,
                'kicker' => 'SECTION 02 • KEY METRICS & COMPLIANCE',
                'title' => 'Production Overview & KPIs',
                'subtitle' => 'Actual Recorded Reporting Metrics',
                'bullets' => $kpiLines,
            ],
            [
                'slide_no' => 4,
                'kicker' => 'SECTION 03 • TEAM & INDIVIDUAL BREAKDOWN',
                'title' => 'Team Members & Individual Output',
                'subtitle' => $report->team_name ?: 'Production Team Roster',
                'bullets' => array_slice($memberLines, 0, 7),
            ],
            [
                'slide_no' => 5,
                'kicker' => 'SECTION 04 • DELIVERABLES COMPLETED',
                'title' => 'Completed Tasks & Production Output',
                'subtitle' => 'Verified Completed Tasks for Period',
                'bullets' => $this->splitTextIntoBullets($report->tasks_completed ?: 'No completed tasks recorded.', 7),
            ],
            [
                'slide_no' => 6,
                'kicker' => 'SECTION 05 • ONGOING & QUEUED PIPELINE',
                'title' => 'Work in Progress & Pending Tasks',
                'subtitle' => 'Active Production Pipeline',
                'bullets' => array_merge(
                    ['[WORK IN PROGRESS]'],
                    $this->splitTextIntoBullets($report->work_in_progress ?: 'None reported.', 3),
                    ['[PENDING TASKS]'],
                    $this->splitTextIntoBullets($report->pending_tasks ?: 'None reported.', 3)
                ),
            ],
            [
                'slide_no' => 7,
                'kicker' => 'SECTION 06 • VISUAL ASSET HIGHLIGHTS',
                'title' => 'Best Posters & Graphics',
                'subtitle' => 'Selected Visual Production Highlights',
                'bullets' => $posterLines,
            ],
            [
                'slide_no' => 8,
                'kicker' => 'SECTION 07 • VIDEO & MULTIMEDIA OUTPUT',
                'title' => 'Best Videos & Media Highlights',
                'subtitle' => 'Video Deliverables & Published Links',
                'bullets' => $videoLines,
            ],
            [
                'slide_no' => 9,
                'kicker' => 'SECTION 08 • RISK & OBSTACLE LOG',
                'title' => 'Challenges & Blockers',
                'subtitle' => 'Production Bottlenecks & Technical Issues',
                'bullets' => $this->splitTextIntoBullets($report->blockers ?: 'No blockers or production obstacles reported during this period.', 6),
            ],
            [
                'slide_no' => 10,
                'kicker' => 'SECTION 09 • QUALITY & PERFORMANCE EVALUATION',
                'title' => 'Team Performance Rating & Review',
                'subtitle' => $report->rating_display,
                'bullets' => $ratingLines,
            ],
            [
                'slide_no' => 11,
                'kicker' => 'SECTION 10 • FORWARD ROADMAP',
                'title' => 'Next Period Priorities & Achievements',
                'subtitle' => 'Key Achievements & Upcoming Action Plan',
                'bullets' => array_merge(
                    ['[TOP ACHIEVEMENTS]'],
                    $this->splitTextIntoBullets($report->achievements ?: 'Standard production milestones achieved.', 3),
                    ['[NEXT PERIOD PRIORITIES]'],
                    $this->splitTextIntoBullets($report->next_priorities ?: 'Continue scheduled production deliverables.', 3)
                ),
            ],
            [
                'slide_no' => 12,
                'kicker' => 'SECTION 11 • SIGN-OFF & ARCHIVE RECORD',
                'title' => 'Final Summary & Governance Sign-Off',
                'subtitle' => 'KIUQ SYSTEM Production Archive',
                'bullets' => [
                    'Report Code: ' . $report->report_code,
                    'Author / Submitted By: ' . ($report->user?->name ?? 'N/A'),
                    'Production Team: ' . ($report->team_name ?: 'N/A'),
                    'Supervisor Oversight: Somalika (Production Supervisor)',
                    'Revision Count: ' . $report->revisions->count() . ' auditable revision(s)',
                    'Generated On: ' . now('Asia/Phnom_Penh')->format('M d, Y g:i A') . ' (Asia/Phnom_Penh)',
                ],
            ],
        ];
    }

    protected function splitTextIntoBullets(string $text, int $maxItems = 6): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
        $cleaned = [];
        foreach ($lines as $line) {
            $trimmed = trim(ltrim(trim($line), "•-* \t"));
            if ($trimmed !== '') {
                $cleaned[] = $this->truncateText($trimmed, 150);
            }
        }

        if (empty($cleaned)) {
            return ['—'];
        }

        return array_slice($cleaned, 0, $maxItems);
    }

    protected function truncateText(string $text, int $limit = 140): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        return mb_substr($text, 0, $limit - 1) . '…';
    }

    protected function xmlEscape(string $str): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str) ?? '';
        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected function buildContentTypesXml(int $slideCount): string
    {
        $overrides = '';
        for ($i = 1; $i <= $slideCount; $i++) {
            $overrides .= '<Override PartName="/ppt/slides/slide' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/>'
            . '<Default Extension="jpg" ContentType="image/jpeg"/>'
            . '<Default Extension="jpeg" ContentType="image/jpeg"/>'
            . '<Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>'
            . '<Override PartName="/ppt/presProps.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presProps+xml"/>'
            . '<Override PartName="/ppt/viewProps.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.viewProps+xml"/>'
            . '<Override PartName="/ppt/tableStyles.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.tableStyles+xml"/>'
            . '<Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>'
            . '<Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/>'
            . '<Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . $overrides
            . '</Types>';
    }

    protected function buildRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    protected function buildAppPropsXml(int $slideCount): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>KIUQ SYSTEM Production Report Engine</Application>'
            . '<PresentationFormat>On-screen Show (16:9)</PresentationFormat>'
            . '<Slides>' . $slideCount . '</Slides>'
            . '<Notes>0</Notes>'
            . '<HiddenSlides>0</HiddenSlides>'
            . '<Company>KIUQ SYSTEM</Company>'
            . '<AppVersion>16.0000</AppVersion>'
            . '</Properties>';
    }

    protected function buildCorePropsXml(ProductionReport $report): string
    {
        $title = $this->xmlEscape($report->title);
        $creator = $this->xmlEscape($report->user?->name ?? 'KIUQ SYSTEM');
        $nowIso = now('UTC')->format('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . "<dc:title>{$title}</dc:title>"
            . "<dc:creator>{$creator}</dc:creator>"
            . "<cp:lastModifiedBy>KIUQ SYSTEM</cp:lastModifiedBy>"
            . "<dcterms:created xsi:type=\"dcterms:W3CDTF\">{$nowIso}</dcterms:created>"
            . "<dcterms:modified xsi:type=\"dcterms:W3CDTF\">{$nowIso}</dcterms:modified>"
            . '</cp:coreProperties>';
    }

    protected function buildPresentationXml(int $slideCount): string
    {
        $sldIdLst = '';
        for ($i = 1; $i <= $slideCount; $i++) {
            $id = 255 + $i;
            $rId = 'rId' . ($i + 5);
            $sldIdLst .= "<p:sldId id=\"{$id}\" r:id=\"{$rId}\"/>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" saveSubsetFonts="1">'
            . '<p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst>'
            . "<p:sldIdLst>{$sldIdLst}</p:sldIdLst>"
            . '<p:sldSz cx="12192000" cy="6858000"/>'
            . '<p:notesSz cx="6858000" cy="9144000"/>'
            . '</p:presentation>';
    }

    protected function buildPresentationRelsXml(int $slideCount): string
    {
        $rels = '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/presProps" Target="presProps.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/viewProps" Target="viewProps.xml"/>'
            . '<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>'
            . '<Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/tableStyles" Target="tableStyles.xml"/>';

        for ($i = 1; $i <= $slideCount; $i++) {
            $rId = 'rId' . ($i + 5);
            $rels .= "<Relationship Id=\"{$rId}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide\" Target=\"slides/slide{$i}.xml\"/>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>';
    }

    protected function buildPresPropsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:presentationPr xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"/>';
    }

    protected function buildViewPropsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:viewPr xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"/>';
    }

    protected function buildTableStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<a:tblStyleLst xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" def="{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}"/>';
    }

    protected function buildSlideMasterXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
            . '<p:cSld><p:spTree>'
            . '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
            . '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
            . '</p:spTree></p:cSld>'
            . '<p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/>'
            . '<p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst>'
            . '</p:sldMaster>';
    }

    protected function buildSlideMasterRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/>'
            . '</Relationships>';
    }

    protected function buildSlideLayoutXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="blank" preserve="1">'
            . '<p:cSld name="Blank"><p:spTree>'
            . '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
            . '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
            . '</p:spTree></p:cSld>'
            . '<p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>'
            . '</p:sldLayout>';
    }

    protected function buildSlideLayoutRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/>'
            . '</Relationships>';
    }

    protected function buildThemeXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="KIUQ Corporate Theme">'
            . '<a:themeElements>'
            . '<a:clrScheme name="KIUQ">'
            . '<a:dk1><a:srgbClr val="0F172A"/></a:dk1>'
            . '<a:lt1><a:srgbClr val="FFFFFF"/></a:lt1>'
            . '<a:dk2><a:srgbClr val="1E293B"/></a:dk2>'
            . '<a:lt2><a:srgbClr val="F8FAFC"/></a:lt2>'
            . '<a:accent1><a:srgbClr val="4F46E5"/></a:accent1>'
            . '<a:accent2><a:srgbClr val="0EA5E9"/></a:accent2>'
            . '<a:accent3><a:srgbClr val="10B981"/></a:accent3>'
            . '<a:accent4><a:srgbClr val="F59E0B"/></a:accent4>'
            . '<a:accent5><a:srgbClr val="EC4899"/></a:accent5>'
            . '<a:accent6><a:srgbClr val="6366F1"/></a:accent6>'
            . '<a:hlink><a:srgbClr val="2563EB"/></a:hlink>'
            . '<a:folHlink><a:srgbClr val="7C3AED"/></a:folHlink>'
            . '</a:clrScheme>'
            . '<a:fontScheme name="KIUQ Fonts">'
            . '<a:majorFont><a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface=""/></a:majorFont>'
            . '<a:minorFont><a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface=""/></a:minorFont>'
            . '</a:fontScheme>'
            . '<a:fmtScheme name="KIUQ Format">'
            . '<a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:fillStyleLst>'
            . '<a:lnStyleLst><a:ln w="9525"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln><a:ln w="25400"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln><a:ln w="38100"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln></a:lnStyleLst>'
            . '<a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst>'
            . '<a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst>'
            . '</a:fmtScheme>'
            . '</a:themeElements>'
            . '</a:theme>';
    }

    protected function buildSlideRelsXml(array $slideImages = []): string
    {
        $rels = '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>';
        foreach ($slideImages as $img) {
            $rels .= '<Relationship Id="' . $img['rId'] . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="' . $img['target'] . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>';
    }

    protected function buildSlideXml(int $slideNum, array $slide, array $slideImages = []): string
    {
        $isCover = !empty($slide['is_cover']);
        $bgHex = $isCover ? '0F172A' : 'F8FAFC';
        $headerBarHex = $isCover ? '4F46E5' : '1E293B';
        $titleColor = $isCover ? 'FFFFFF' : '0F172A';
        $subColor = $isCover ? '93C5FD' : '4F46E5';
        $bodyColor = $isCover ? 'E2E8F0' : '1E293B';

        $kicker = $this->xmlEscape((string) ($slide['kicker'] ?? 'KIUQ SYSTEM'));
        $title = $this->xmlEscape((string) ($slide['title'] ?? 'Slide'));
        $subtitle = $this->xmlEscape((string) ($slide['subtitle'] ?? ''));

        $bulletParagraphs = '';
        foreach (($slide['bullets'] ?? []) as $b) {
            $escapedBullet = $this->xmlEscape('• ' . $b);
            $bulletParagraphs .= '<a:p>'
                . '<a:pPr marL="180000"><a:spcBef><a:spcPts val="600"/></a:spcBef></a:pPr>'
                . '<a:r><a:rPr lang="en-US" sz="1600" dirty="0"><a:solidFill><a:srgbClr val="' . $bodyColor . '"/></a:solidFill></a:rPr>'
                . "<a:t>{$escapedBullet}</a:t></a:r>"
                . '</a:p>';
        }

        $bodyWidth = !empty($slideImages) ? '6200000' : '10972800';

        $imageShapes = '';
        foreach ($slideImages as $idx => $img) {
            $picId = 50 + $idx;
            $xPos = 7000000 + ($idx * 1650000);
            $yPos = 2200000;
            $w = 1500000;
            $h = 2000000;
            $imageShapes .= '<p:pic>'
                . '<p:nvPicPr><p:cNvPr id="' . $picId . '" name="' . $this->xmlEscape($img['title'] ?? 'Poster') . '"/><p:cNvPicPr><a:picLocks noChangeAspect="1"/></p:cNvPicPr><p:nvPr/></p:nvPicPr>'
                . '<p:blipFill><a:blip r:embed="' . $img['rId'] . '"/><a:stretch><a:fillRect/></a:stretch></p:blipFill>'
                . '<p:spPr><a:xfrm><a:off x="' . $xPos . '" y="' . $yPos . '"/><a:ext cx="' . $w . '" cy="' . $h . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr>'
                . '</p:pic>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
            . '<p:cSld>'
            . '<p:bg><p:bgPr><a:solidFill><a:srgbClr val="' . $bgHex . '"/></a:solidFill><a:effectLst/></p:bgPr></p:bg>'
            . '<p:spTree>'
            . '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
            . '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
            // Top Accent Bar
            . '<p:sp>'
            . '<p:nvSpPr><p:cNvPr id="2" name="AccentBar"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>'
            . '<p:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="12192000" cy="140000"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:solidFill><a:srgbClr val="' . $headerBarHex . '"/></a:solidFill></p:spPr>'
            . '</p:sp>'
            // Header & Title Box
            . '<p:sp>'
            . '<p:nvSpPr><p:cNvPr id="3" name="TitleBox"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>'
            . '<p:spPr><a:xfrm><a:off x="609600" y="360000"/><a:ext cx="10972800" cy="1450000"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/></p:spPr>'
            . '<p:txBody><a:bodyPr wrap="square"/><a:lstStyle/>'
            . '<a:p><a:r><a:rPr lang="en-US" sz="1100" b="1"><a:solidFill><a:srgbClr val="' . $subColor . '"/></a:solidFill></a:rPr><a:t>' . $kicker . '</a:t></a:r></a:p>'
            . '<a:p><a:r><a:rPr lang="en-US" sz="2800" b="1"><a:solidFill><a:srgbClr val="' . $titleColor . '"/></a:solidFill></a:rPr><a:t>' . $title . '</a:t></a:r></a:p>'
            . '<a:p><a:r><a:rPr lang="en-US" sz="1400"><a:solidFill><a:srgbClr val="' . $subColor . '"/></a:solidFill></a:rPr><a:t>' . $subtitle . '</a:t></a:r></a:p>'
            . '</p:txBody>'
            . '</p:sp>'
            // Main Content Box
            . '<p:sp>'
            . '<p:nvSpPr><p:cNvPr id="4" name="BodyBox"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>'
            . '<p:spPr><a:xfrm><a:off x="609600" y="1950000"/><a:ext cx="' . $bodyWidth . '" cy="4200000"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/></p:spPr>'
            . '<p:txBody><a:bodyPr wrap="square"/><a:lstStyle/>'
            . $bulletParagraphs
            . '</p:txBody>'
            . '</p:sp>'
            // Embedded Poster Images (if any)
            . $imageShapes
            // Footer Box
            . '<p:sp>'
            . '<p:nvSpPr><p:cNvPr id="5" name="FooterBox"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>'
            . '<p:spPr><a:xfrm><a:off x="609600" y="6300000"/><a:ext cx="10972800" cy="400000"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/></p:spPr>'
            . '<p:txBody><a:bodyPr wrap="square"/><a:lstStyle/>'
            . '<a:p><a:r><a:rPr lang="en-US" sz="1000"><a:solidFill><a:srgbClr val="64748B"/></a:solidFill></a:rPr><a:t>KIUQ SYSTEM • Production Report Management System   |   Slide ' . $slideNum . ' of 12</a:t></a:r></a:p>'
            . '</p:txBody>'
            . '</p:sp>'
            . '</p:spTree>'
            . '</p:cSld>'
            . '<p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>'
            . '</p:sld>';
    }
}
