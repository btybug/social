<?php

namespace Btybug\Social\Services;

use Btybug\btybug\Models\Themes\Themes;
use Btybug\btybug\Services\GeneralService;

/**
 * Class ThemeService
 * @package Btybug\Social\Services
 */
class ThemeService extends GeneralService
{

    /**
     * @var
     */
    private $current;
    /**
     * @var
     */
    private $result;

    public function getCurrent($themes, $p)
    {
        if (count($themes) && !$p) {
            $curentTheme = $themes[0];
        }
        if ($p) {
            $curentTheme = Themes::find($p);
        }
    }
}