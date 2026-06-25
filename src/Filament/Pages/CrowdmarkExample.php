<?php

namespace Waterloobae\CrowdmarkApiLaravel\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CrowdmarkExample extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected static ?string $navigationLabel = 'Crowdmark Example';

    protected static string|UnitEnum|null $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Crowdmark Example';

    protected static ?string $slug = 'crowdmark-example';

    protected string $view = 'crowdmark-api-laravel::filament.pages.crowdmark-example';
}
