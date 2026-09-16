<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleSheetXlsxParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use ZipArchive;

class BlogReportHyperlinkTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'view-blog-reports', 'guard_name' => 'web']);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->givePermissionTo('view-blog-reports');
    }

    private function createMockXlsx(): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'mock_xlsx_');
        $zip = new ZipArchive();
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // xl/sharedStrings.xml
        $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="7" uniqueCount="7">'
            . '<si><t>Class</t></si>'
            . '<si><t>Doc Link</t></si>'
            . '<si><t>Public Link</t></si>'
            . '<si><t>Dated</t></si>'
            . '<si><t>Website link</t></si>'
            . '<si><t>link</t></si>'
            . '<si><t>newdiggerforsale.com</t></si>'
            . '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $ssXml);

        // xl/workbook.xml
        $wbXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Blogs" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
        $zip->addFromString('xl/workbook.xml', $wbXml);

        // xl/_rels/workbook.xml.rels
        $wbRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRelsXml);

        // xl/worksheets/_rels/sheet1.xml.rels
        $sheetRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId10" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://docs.google.com/document/d/real-doc-id/edit" TargetMode="External"/>'
            . '<Relationship Id="rId11" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://newdiggerforsale.com/real-article-slug" TargetMode="External"/>'
            . '</Relationships>';
        $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', $sheetRelsXml);

        // xl/worksheets/sheet1.xml
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetData>'
            . '<row r="1">'
            . '<c r="A1" t="s"><v>0</v></c>'
            . '<c r="B1" t="s"><v>1</v></c>'
            . '<c r="C1" t="s"><v>2</v></c>'
            . '<c r="D1" t="s"><v>3</v></c>'
            . '<c r="E1" t="s"><v>4</v></c>'
            . '</row>'
            . '<row r="2">'
            . '<c r="A2"><v>1</v></c>'
            . '<c r="B2" t="s"><v>5</v></c>' // "link" with rId10 hyperlink
            . '<c r="C2" t="s"><v>5</v></c>' // "link" with rId11 hyperlink
            . '<c r="D2"><v>08/12</v></c>'
            . '<c r="E2" t="s"><v>6</v></c>' // newdiggerforsale.com
            . '</row>'
            . '</sheetData>'
            . '<hyperlinks>'
            . '<hyperlink ref="B2" r:id="rId10"/>'
            . '<hyperlink ref="C2" r:id="rId11"/>'
            . '</hyperlinks>'
            . '</worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        $zip->close();
        $content = file_get_contents($tempPath);
        @unlink($tempPath);

        return $content;
    }

    public function test_google_sheet_xlsx_parser_extracts_real_hyperlinks(): void
    {
        $mockContent = $this->createMockXlsx();
        $parsed = GoogleSheetXlsxParser::parse($mockContent, 'Blogs');

        $this->assertSame('Blogs', $parsed['sheet_name']);
        $this->assertSame(1, $parsed['header_row_index']);
        $this->assertCount(1, $parsed['blocks']);

        // Row 2 cells
        $row2 = $parsed['rows'][2];
        $this->assertSame('1', $row2[0]['value']);
        $this->assertSame('link', $row2[1]['value']);
        $this->assertSame('https://docs.google.com/document/d/real-doc-id/edit', $row2[1]['link']);
        $this->assertSame('https://newdiggerforsale.com/real-article-slug', $row2[2]['link']);
    }

    public function test_preview_renders_real_hyperlinks_from_xlsx(): void
    {
        $mockXlsx = $this->createMockXlsx();

        Http::fake([
            'https://docs.google.com/spreadsheets/d/test-sheet-id/export?format=xlsx' => Http::response($mockXlsx, 200),
        ]);

        $response = $this->actingAs($this->user)->post(route('blog-reports.preview'), [
            'sheet_url' => 'https://docs.google.com/spreadsheets/d/test-sheet-id/edit#gid=0',
            'month_label' => 'Blog Reports - September',
        ]);

        $response->assertOk();
        $response->assertSee('https://docs.google.com/document/d/real-doc-id/edit', false);
        $response->assertSee('https://newdiggerforsale.com/real-article-slug', false);
        $response->assertDontSee('href="http://link"', false);
    }
}
