<?php

namespace Btybug\Social\Services;

use Btybug\btybug\Models\ContentLayouts\ContentLayouts;
use Btybug\btybug\Services\GeneralService;

/**
 * Class BackendService
 * @package Btybug\Social\Services
 */
class BackendService extends GeneralService
{

    /**
     * @var
     */
    private $curentLayout;
    /**
     * @var
     */
    private $result;

    /**
     * @param $request
     * @param $layouts
     * @return null
     */
    public function getTemplates($request, $layouts)
    {
        $p = $request->get('p', 0);
        $this->curentLayout = null;
        if ($p) {
            $this->curentLayout = ContentLayouts::find($p);
        } else {
            if (count($layouts)) {
                $this->curentLayout = $layouts[0];
            }
        }
        return $this->curentLayout;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function makeActive(array $data)
    {
        $this->result = false;
        if ($data['type'] == 'page_section') {
            ContentLayouts::active()->makeInActive()->save();
            $page_section = ContentLayouts::find($data['slug']);
            if ($page_section) $this->result = $page_section->setAttributes("active", true)->save() ? false : true;

            if (!ContentLayouts::activeVariation($data['slug'])) {
                $main = $page_section->variations()[0];
                $this->result = $main->setAttributes("active", true)->save() ? false : true;
            }
        } else if ($data['type'] == 'page_section_variation') {
            ContentLayouts::activeVariation($data['slug'])->makeInActiveVariation()->save();
            $pageSectionVariation = ContentLayouts::findVariation($data['slug']);
            $pageSectionVariation->setAttributes('active', true);
            $this->result = $pageSectionVariation->save() ? false : true;
        }

        return $this->result;
    }
}