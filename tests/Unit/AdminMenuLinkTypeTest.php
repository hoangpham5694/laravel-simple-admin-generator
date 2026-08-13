<?php

namespace HoangPhamDev\SimpleAdminGenerator\Tests\Unit;

use HoangPhamDev\SimpleAdminGenerator\Enums\AdminMenuLinkType;
use PHPUnit\Framework\TestCase;

class AdminMenuLinkTypeTest extends TestCase
{
    public function testItExposesAllDatabaseValues(): void
    {
        $this->assertSame([
            'none',
            'route',
            'root_path',
            'admin_path',
            'url',
        ], AdminMenuLinkType::values());
    }
}
