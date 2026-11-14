<?php

namespace Tests\Unit;

use App\Services\GoogleBlogsSheetService;
use PHPUnit\Framework\TestCase;

class GoogleBlogsSheetServiceDomainMatchTest extends TestCase
{
    public function test_canonical_sheet_website_resolution(): void
    {
        // asianmachinery.net resolves to asiamachinery.net (distinct from asiamachinery.org)
        $this->assertEquals('asiamachinery.net', GoogleBlogsSheetService::getCanonicalSheetWebsite('asianmachinery.net'));
        $this->assertEquals('asiamachinery.net', GoogleBlogsSheetService::getCanonicalSheetWebsite('https://asianmachinery.net/road-roller-gradeability-and-slope-safety-protocols/'));
        $this->assertEquals('asiamachinery.org', GoogleBlogsSheetService::getCanonicalSheetWebsite('asiamachinery.org'));
        $this->assertEquals('asiamachinery.org', GoogleBlogsSheetService::getCanonicalSheetWebsite('machineryasia.online'));
        $this->assertEquals('asiamachinery.net', GoogleBlogsSheetService::getCanonicalSheetWebsite('asianmachinery'));

        // Other fleet sites
        $this->assertEquals('americanexcavators.org', GoogleBlogsSheetService::getCanonicalSheetWebsite('americanexcavators.com'));
        $this->assertEquals('americanskidsteers.com', GoogleBlogsSheetService::getCanonicalSheetWebsite('americanskidsteer.com'));
        $this->assertEquals('usaforklift.org', GoogleBlogsSheetService::getCanonicalSheetWebsite('usaforklift.org'));
        $this->assertEquals('stonekrusher.com', GoogleBlogsSheetService::getCanonicalSheetWebsite('stonekrusher.org'));
    }

    public function test_domain_aliases_generation(): void
    {
        $aliases = GoogleBlogsSheetService::getDomainAliases('asianmachinery.net');

        $this->assertContains('asianmachinery.net', $aliases);
        $this->assertContains('asiamachinery.net', $aliases);
    }
}

