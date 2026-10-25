<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Http\Controllers\Board\BoardExportController;
use App\Models\CardChecklistItem;

class ChecklistPersonalReportTest extends TestCase
{
    public function test_extract_number_from_checklist_item()
    {
        $this->assertEquals(1, BoardExportController::extractNumberFromChecklistItem('1. Poster : 12TH-OCT'));
        $this->assertEquals(2, BoardExportController::extractNumberFromChecklistItem('2.Video Short : 13TH-OCT'));
        $this->assertEquals(2, BoardExportController::extractNumberFromChecklistItem('2. Video Short : 13TH-OCT'));
        $this->assertEquals(2, BoardExportController::extractNumberFromChecklistItem('2) Video Short : 13TH-OCT'));
        $this->assertEquals(2, BoardExportController::extractNumberFromChecklistItem('2: Video Short : 13TH-OCT'));
        $this->assertEquals(2, BoardExportController::extractNumberFromChecklistItem('2 - Video Short : 13TH-OCT'));
        $this->assertEquals(2, BoardExportController::extractNumberFromChecklistItem('#2 Video Short'));
        $this->assertEquals(10, BoardExportController::extractNumberFromChecklistItem('10. Poster : 20TH-OCT'));
        $this->assertEquals(3, BoardExportController::extractNumberFromChecklistItem('3.Poster : 14TH-OCT'));
        $this->assertEquals(4, BoardExportController::extractNumberFromChecklistItem('4.Video Long : 15TH-OCT'));
        $this->assertEquals(5, BoardExportController::extractNumberFromChecklistItem('5.Poster : 16TH-OCT'));
        $this->assertEquals(6, BoardExportController::extractNumberFromChecklistItem('6.Video Short : 17TH-OCT'));
        $this->assertEquals(7, BoardExportController::extractNumberFromChecklistItem('7.Poster : 18TH-OCT'));
        $this->assertNull(BoardExportController::extractNumberFromChecklistItem('Video Short : 13TH-OCT'));
    }

    public function test_match_description_by_number()
    {
        $desc = "1.TYPH-0802 : Can be adjust\n" .
                "2.TYPH-0509 Vs. TYPH-0565 : Power and size comparison\n" .
                "3.TYPH-30 + TYPH-40 : Jobsite comparison\n" .
                "4.TYPH-KUVUO4 + Attachments : Walkaround with attachment demos\n" .
                "5.TYPH-GRX1 : Can be adjust\n" .
                "6.TYPH-K1 Vs. TYPH-K2 : Winner by use case\n" .
                "7.MZVT : Can be adjust";

        $this->assertEquals('1.TYPH-0802 : Can be adjust', BoardExportController::matchDescriptionByNumber($desc, 1));
        $this->assertEquals('2.TYPH-0509 Vs. TYPH-0565 : Power and size comparison', BoardExportController::matchDescriptionByNumber($desc, 2));
        $this->assertEquals('3.TYPH-30 + TYPH-40 : Jobsite comparison', BoardExportController::matchDescriptionByNumber($desc, 3));
        $this->assertEquals('4.TYPH-KUVUO4 + Attachments : Walkaround with attachment demos', BoardExportController::matchDescriptionByNumber($desc, 4));
        $this->assertEquals('5.TYPH-GRX1 : Can be adjust', BoardExportController::matchDescriptionByNumber($desc, 5));
        $this->assertEquals('6.TYPH-K1 Vs. TYPH-K2 : Winner by use case', BoardExportController::matchDescriptionByNumber($desc, 6));
        $this->assertEquals('7.MZVT : Can be adjust', BoardExportController::matchDescriptionByNumber($desc, 7));
        $this->assertNull(BoardExportController::matchDescriptionByNumber($desc, 8));
    }

    public function test_match_description_with_html_tags_and_formatting()
    {
        $htmlDesc1 = "<p>1.TYPH-0802 : Can be adjust</p>" .
                     "<p>2.TYPH-0509 Vs. TYPH-0565 : Power and size comparison</p>";
        $this->assertEquals('2.TYPH-0509 Vs. TYPH-0565 : Power and size comparison', BoardExportController::matchDescriptionByNumber($htmlDesc1, 2));

        $htmlDesc2 = "<div>1. TYPH-0802 : Can be adjust<br>2.&nbsp;TYPH-0509 Vs. TYPH-0565 : Power and size comparison</div>";
        $this->assertEquals('2. TYPH-0509 Vs. TYPH-0565 : Power and size comparison', BoardExportController::matchDescriptionByNumber($htmlDesc2, 2));

        $descVariations = "1) First task\n2: Second task\n3 - Third task\n#4 Fourth task";
        $this->assertEquals('1) First task', BoardExportController::matchDescriptionByNumber($descVariations, 1));
        $this->assertEquals('2: Second task', BoardExportController::matchDescriptionByNumber($descVariations, 2));
        $this->assertEquals('3 - Third task', BoardExportController::matchDescriptionByNumber($descVariations, 3));
        $this->assertEquals('#4 Fourth task', BoardExportController::matchDescriptionByNumber($descVariations, 4));
    }

    public function test_detect_category_video_and_graphic()
    {
        $this->assertEquals('graphic', CardChecklistItem::detectCategory('1. Poster : 12TH-OCT'));
        $this->assertEquals('video', CardChecklistItem::detectCategory('2.Video Short : 13TH-OCT'));
        $this->assertEquals('graphic', CardChecklistItem::detectCategory('3.Poster : 14TH-OCT'));
        $this->assertEquals('video', CardChecklistItem::detectCategory('4.Video Long : 15TH-OCT'));
        $this->assertEquals('graphic', CardChecklistItem::detectCategory('5.Poster : 16TH-OCT'));
        $this->assertEquals('video', CardChecklistItem::detectCategory('6.Video Short : 17TH-OCT'));
        $this->assertEquals('graphic', CardChecklistItem::detectCategory('7.Poster : 18TH-OCT'));
        $this->assertEquals('graphic', CardChecklistItem::detectCategory('Social Media Graphic'));
        $this->assertEquals('graphic', CardChecklistItem::detectCategory('Graphic Design'));
        $this->assertEquals('video', CardChecklistItem::detectCategory('Landscape Video'));
        $this->assertEquals('video', CardChecklistItem::detectCategory('Reels'));
    }

    public function test_match_description_by_category()
    {
        $desc = "Graphic: TYPH-0802 Poster Design & Flyer\n" .
                "Video: Short Reel 15s for Instagram\n" .
                "Description: eBay Specifications & Tech Details";

        $this->assertEquals('Graphic: TYPH-0802 Poster Design & Flyer', BoardExportController::matchDescriptionByCategory($desc, 'Graphic', 'Graphic'));
        $this->assertEquals('Video: Short Reel 15s for Instagram', BoardExportController::matchDescriptionByCategory($desc, 'Video', 'Video'));
        $this->assertEquals('Description: eBay Specifications & Tech Details', BoardExportController::matchDescriptionByCategory($desc, 'Description', 'Description'));
        $this->assertNull(BoardExportController::matchDescriptionByCategory($desc, 'Content', 'Content'));
    }

    public function test_format_checklist_task_title()
    {
        // Normal card where title does not contain the task type
        $this->assertEquals('PRJ: KUVUO WITH BUNDLE - Graphic', BoardExportController::formatChecklistTaskTitle('PRJ: KUVUO WITH BUNDLE', 'Graphic', 'Graphic'));
        $this->assertEquals('PRJ: KUVUO WITH BUNDLE - Video', BoardExportController::formatChecklistTaskTitle('PRJ: KUVUO WITH BUNDLE', 'Video', 'Video'));
        $this->assertEquals('PRJ: KUVUO WITH BUNDLE - Description', BoardExportController::formatChecklistTaskTitle('PRJ: KUVUO WITH BUNDLE', 'Description', 'Description'));
        $this->assertEquals('PRJ: KUVUO WITH BUNDLE - 1. Poster : 12TH-OCT', BoardExportController::formatChecklistTaskTitle('PRJ: KUVUO WITH BUNDLE', '1. Poster : 12TH-OCT', 'Graphic'));

        // Normal card where title already contains the task type / variant
        $this->assertEquals('PRJ: KUVUO - Graphic', BoardExportController::formatChecklistTaskTitle('PRJ: KUVUO - Graphic', 'Graphic', 'Graphic'));
        $this->assertEquals('TYPH-30 - Short Reel', BoardExportController::formatChecklistTaskTitle('TYPH-30 - Short Reel', 'Video', 'Video'));
        $this->assertEquals('TYPH-KUVUO27 - Poster Design', BoardExportController::formatChecklistTaskTitle('TYPH-KUVUO27 - Poster Design', 'Graphic', 'Graphic'));
        $this->assertEquals('PRJ: KUVUO - Ebay listing', BoardExportController::formatChecklistTaskTitle('PRJ: KUVUO - Ebay listing', 'Description', 'Listing'));
    }
}

