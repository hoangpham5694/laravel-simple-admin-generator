<?php

namespace HoangPhamDev\SimpleAdminGenerator\Enums;

enum AdminMenuLinkType: string
{
    case None = 'none';
    case Route = 'route';
    case RootPath = 'root_path';
    case AdminPath = 'admin_path';
    case Url = 'url';

    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases()
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::None => 'No link',
            self::Route => 'Laravel route',
            self::RootPath => 'Root path',
            self::AdminPath => 'Admin path',
            self::Url => 'Direct URL',
        };
    }
}
