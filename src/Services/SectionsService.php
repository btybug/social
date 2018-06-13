<?php
/**
 * Copyright (c) 2017. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

namespace Btybug\Social\Services;

use Btybug\btybug\Models\ContentLayouts\ContentLayouts;
use Btybug\btybug\Services\CmsItemReader;
use Btybug\btybug\Services\CmsItemUploader;
use Btybug\btybug\Services\GeneralService;


class SectionsService extends GeneralService
{

    private $uplaod;
    private $result;

    public function __construct()
    {
        $this->upload = new CmsItemUploader('sections');
    }

    public function getPageSections()
    {
        return CmsItemReader::getAllGearsByType('page_sections')
            ->where('place', 'backend')
            ->run();
    }

    public function getPageSection($slug)
    {
        return CmsItemReader::getAllGearsByType('page_sections')
            ->where('place', 'backend')
            ->where('slug', $slug)
            ->first();
    }

    public function upload($request)
    {
        return $this->upload->run($request);
    }

    public function postMakeActive(array $data)
    {
        $this->result = false;
        if ($data['type'] == 'page_section') {
            ContentLayouts::active()->makeInActive()->save();
            $page_section = ContentLayouts::find($data['slug']);
            if ($page_section) $result = $page_section->setAttributes("active", true)->save() ? false : true;
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