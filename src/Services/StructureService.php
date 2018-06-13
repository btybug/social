<?php

namespace Btybug\Social\Services;

use Btybug\btybug\Models\ContentLayouts\ContentLayouts;
use Btybug\btybug\Models\ExtraModules\Structures;
use Btybug\btybug\Models\Painter\Painter;
use Btybug\btybug\Models\Routes;
use Btybug\btybug\Repositories\MenuRepository;
use Btybug\btybug\Services\CmsItemReader;
use Btybug\btybug\Services\GeneralService;
use Btybug\Social\Models\FormEntries;
use Btybug\Social\Repository\AdminPagesRepository;
use Btybug\Social\Repository\FieldsRepository;
use Btybug\Social\Repository\FormsRepository;
use Btybug\Framework\Repository\VersionsRepository;
use Btybug\btybug\Repositories\AdminsettingRepository;
use Btybug\Uploads\Repository\Plugins;
use Btybug\User\Repository\RoleRepository;

/**
 * Class BackendService
 * @package Btybug\Social\Services
 */
class StructureService extends GeneralService
{
    private static $admin_pages, $roles_repo;
    /**
     * @var null
     */
    private $menu = null;
    private $adminPages, $forms, $fields, $settingRepo, $formService, $formEntries, $rolesRepo, $menuRepository;
    private $url = null;
    private $settings = null;
    private $type = null;
    private $html = null;
    private $plugins = null;

    public function __construct(
        AdminPagesRepository $adminPagesRepository,
        FormsRepository $formsRepository,
        FieldsRepository $fieldsRepository,
        AdminsettingRepository $adminsettingRepository,
        FormService $formService,
        FormEntries $formEntries,
        RoleRepository $roleRepository,
        MenuRepository $menuRepository,
        Plugins $plugins
    )
    {
        self::$admin_pages = $this->adminPages = $adminPagesRepository;
        $this->forms = $formsRepository;
        $this->fields = $fieldsRepository;
        $this->settingRepo = $adminsettingRepository;
        $this->formService = $formService;
        $this->formEntries = $formEntries;
        self::$roles_repo = $this->rolesRepo = $roleRepository;
        $this->menuRepository = $menuRepository;
        $this->plugins = $plugins;
    }

    public static function getAdminPagesChildStatues()
    {
        return [
            'individual' => 'Individual design',
            'inherit' => 'Inherit design',
            'all' => 'All Same'
        ];
    }

    public static function checkAccess($page_id, $role_slug)
    {
        if ($role_slug == SUPERADMIN) return true;
        $page = self::$admin_pages->find($page_id);
        $role = self::$roles_repo->findBy('slug', $role_slug);
        if ($page && $role) {
            $access = $page->permission_role->where('role_id', $role->id)->first();
            if ($access) return true;
        }

        return false;
    }

    public static function AdminPagesParentPermissionWithRole($page_id, $role_id)
    {
        $adminPage = new AdminPagesRepository();
        $result = $adminPage->find($page_id);
        return $result->parent->permission_role()->where('role_id', $role_id)->first();

    }

    /**
     * @return array
     */
    public function getTables()
    {
        return BBGetTables();
    }

    /**
     * @param $request
     * @param VersionsRepository $menuRepository
     * @return mixed|null
     */
    public function getMenuByRequestOrFirst($request)
    {
        $menus = $this->menuRepository->getWhereNotPlugins();
        if ($request->p) {
            $this->menu = $this->menuRepository->find($request->p);
        } elseif (count($menus)) {
            $this->menu = $this->menuRepository->getWhereNotPluginsFirst();
        }

        return $this->menu;
    }

    /**
     * @param $menu
     * @param $role
     * @return string
     */
    public function getMenuItems($menu, $role)
    {
        $items = $menu->items()->where('role_id', $role->id)->where('parent_id', 0)->get();
        return json_encode(self::makeJson($items), true);
    }

    /**
     * @param $items
     * @param bool $parent
     * @return array
     */
    public static function makeJson($items, $parent = true)
    {
        $array = [];

        if (count($items)) {
            foreach ($items as $item) {
                if ($parent) {
                    $array['menuitem'][$item->id] = [
                        'pagegroup' => $item->title,
                        'title' => $item->title,
                        'url' => $item->url,
                        'id' => $item->id,
                    ];

                    if (count($item->childs)) {
                        $array['menuitem'][$item->id]['children'] = self::makeJson($item->childs, false);
                    }
                } else {
                    $array[$item->id] = [
                        'pagegroup' => $item->title,
                        'title' => $item->title,
                        'url' => $item->url,
                        'id' => $item->id,
                    ];

                    if (count($item->childs)) {
                        $array[$item->id]['children'] = self::makeJson($item->childs, false);
                    }
                }
            }
        }

        return $array;
    }

    /**
     * @param $menu
     * @param $role
     * @param $request
     */
    public function editMenu($menu, $role, $request)
    {
        $menu->items()->delete();
        $json_data = json_decode($request->json_data, true);
        if (isset($json_data['menuitem']) && count($json_data['menuitem'])) {
            Menu::saveFromJson($json_data['menuitem'], $menu, $role);
        }
    }

    public function saveMenu($menu, $request)
    {
        return $menu->update(['json_data' => $request->json_data, 'name' => $request->name]);
    }

    /**
     * @param string $slug
     * @param $form
     * @param $fieldsRepository
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAddFieldModal(string $slug, $form, $fieldsRepository)
    {
        if ($form) {
            $fields = $fieldsRepository->getBy('form_group', $form->fields_type);
            if ($slug) {
                $field = $fieldsRepository->findOneByMultiple(['form_group' => $form->fields_type, 'slug', $slug]);
            } elseif (count($fields)) {
                $field = $fieldsRepository->findBy('form_group', $form->fields_type);
            }

            $html = \View::make('console::structure._partials.add_field_modal', compact(['fields', 'field']))->render();
            return \Response::json(['html' => $html]);
        }

        return \Response::json(['error' => true], 500);
    }

    public function getPagePreview($page_id, $request)
    {
        $layout = $request->pl;
        $page = $this->adminPages->findOrFail($page_id);
        $url = $this->url;
        if (!str_contains($page->url, '{param}')) $url = $page->url;

        $layouts = ContentLayouts::findByType('section')->pluck("name", "slug");
        $lay = ContentLayouts::findVariation($layout);

        if (!$lay) {
            return view('console::structure.page-preview',
                ['data' => compact(['page_id', 'layout', 'page', 'url', 'layouts'])]);
        }

        $view['view'] = "console::structure.page-preview";
        $view['variation'] = $lay;
        $data = explode('.', $layout);
        return ContentLayouts::find($data[0])->renderSettings($view,
            compact(['page_id', 'layout', 'page', 'url', 'layouts']));
    }

    public function postPagePreview($page_id, $request)
    {
        $data = $request->except(['pl', 'image']);
        $layout_id = $request->get('layout_id');
        $data['page_id'] = $page_id;
        return $this->adminPages->update($page_id, [
            'settings' => (!empty($data)) ? json_encode($data, true) : null,
            'layout_id' => $layout_id
        ]);
    }

    public function getUnitFieldModal($request)
    {
        $slug = $request->get('p');
        $type = $request->get('type', 'general_fields');
        $mainType = 'text';
        $types = [];
        $ui_elemements = $model = $unit = null;
        $unitTypes = @json_decode(File::get(config('paths.unit_path') . 'configFieldUnitTypes.json'), 1)['types'];
        if (count($unitTypes)) {
            foreach ($unitTypes as $unitType) {
                $types[$unitType['foldername']] = $unitType['title'];
            }

            $main_type = $unitTypes[0]['foldername'];
            if ($type) {
                $main_type = $type;
            }

            $ui_elemements = CmsItemReader::getAllGearsByType('units')->where('place', 'backend')
                ->where('main_type', 'general_fields')->run();
            $specialElements = CmsItemReader::getAllGearsByType('units')->where('place', 'backend')
                ->where('main_type', 'special_fields')->run();

            if ($slug) {
                $unit = CmsItemReader::getAllGearsByType('units')
                    ->where('place', 'backend')
                    ->where('main_type', 'general_fields')
                    ->where('slug', $slug)
                    ->first();

                $specialUnit = CmsItemReader::getAllGearsByType('units')
                    ->where('place', 'backend')
                    ->where('main_type', 'special_fields')
                    ->where('slug', $slug)
                    ->first();
            } elseif (count($ui_elemements)) {
                $unit = CmsItemReader::getAllGearsByType('units')
                    ->where('place', 'backend')
                    ->where('main_type', 'general_fields')
                    ->first();

                $specialUnit = CmsItemReader::getAllGearsByType('units')
                    ->where('place', 'backend')
                    ->where('main_type', 'special_fields')
                    ->first();
            }
            $variations = $unit->variations();

        }
        return \View::make('console::structure._partials.add_field',
            compact(['ui_elemements', 'types', 'unit', 'type', 'specialElements', 'specialUnit', 'mainType', 'variations', 'model']))->render();
    }

    public function getUnitEditFieldModal(array $data)
    {
        $form = $this->forms->getNewCoreFormsBySlug($data['master_slug']);
        $slug = explode('.', $data['uislug']);
        if (count($slug)) {
            $units = CmsItemReader::getAllGearsByType('units')->where('place', 'backend')->where('type', $form->fields_type)->run();
            $uiUnit = CmsItemReader::getAllGearsByType('units')
                ->where('place', 'frontend')
                ->where('type', 'component')
                ->where('slug', array_first($slug))
                ->first();

            $inputSlug = explode('.', $data["inputslug"]);
            if (count($inputSlug)) {
                $unit = CmsItemReader::getAllGearsByType('units')
                    ->where('place', 'backend')
                    ->where('main_type', 'special_fields')
                    ->where('slug', array_first($inputSlug))
                    ->first();
            }

            $variations = ($unit) ? $unit->variations() : [];
        }
        $model = (isset($data['generaltab'])) ? $data['generaltab'] : [];
        return view('console::structure._partials.add_field', compact(['units', 'unit', 'type', 'variations', 'model', 'uiUnit', 'data', 'form']))->render();
    }

    public function getUnitVariations($request)
    {
        $variations = null;
        $unitTypes = @json_decode(File::get(config('paths.unit_path') . 'configFieldUnitTypes.json'), 1)['types'];
        $specialTypes = @json_decode(File::get(config('paths.unit_path') . 'configSpecialUnitTypes.json'), 1)['types'];
        $unit = CmsItemReader::getAllGearsByType('units')
            ->where('place', 'backend')
            ->where('slug', $request->slug)
            ->first();
        if ($unit) {
            if ($unit->main_type == 'general_fields') {
                foreach ($unitTypes as $unitType) {
                    $types[$unitType['foldername']] = $unitType['title'];
                }
            } else {
                foreach ($specialTypes as $unitType) {
                    $types[$unitType['foldername']] = $unitType['title'];
                }
            }

            $variations = count($unit->variations()) ? $unit->variations() : null;
        }
        return \View::make('console::structure._partials.variation_list', compact(['variations', 'unit', 'types']))->render();
    }

    public function getUnitSettingsPage($request)
    {
        $slug = explode('.', $request->unit_id);

        if (count($slug)) {
            $unit = CmsItemReader::getAllGearsByType('units')
                ->where('main_type', 'general_fields')
                ->where('slug', array_first($slug))
                ->first();
            if ($unit) {
                $units = CmsItemReader::getAllGearsByType('units')
                    ->where('main_type', 'general_fields')
                    ->where('type', $unit->type)
                    ->run();
                $this->type = $unit->type;
                $validationRules = $this->fieldValidation->getRules();
                $variations = count($unit->variations()) ? $unit->variations() : null;
                $this->settings = view("console::backend.gears.fields.types.$this->type",
                    compact(['validationRules', 'units', 'variations', 'unit']))->render();
            }
        }

        return ['type' => $this->type, 'settings' => $this->settings];
    }

    public function getComponentSettings($request)
    {
        $slug = explode('.', $request->slug);
        if (count($slug)) {
            $unit = CmsItemReader::getAllGearsByType('units')
                ->where('type', 'component')
                ->where('slug', array_first($slug))
                ->first();
            if ($unit) {
                $html = $unit->render();
                $settings = $unit->renderSettings();
                return \Response::json(['settings' => $settings, 'html' => $html, 'error' => false]);
            }
        }
        return \Response::json(['error' => true]);
    }

    public function getUnitVariationField($request)
    {
        $slug = explode('.', $request->slug);
        if (count($slug)) {
            $unit = CmsItemReader::getAllGearsByType('units')
                ->where('slug', array_first($slug))
                ->first();
            if ($unit) {
                $blade = \File::get("$unit->path" . DS . "$unit->main_file");
                return \Response::json(['html' => BBRenderUnits($request->slug), 'blade' => $blade, 'options' => $unit->options, 'error' => false]);
            }
        }
        return \Response::json(['message' => 'wrong message', 'error' => true]);
    }

    public function getUnitVariationSettings($request)
    {
        $slug = explode('.', $request->id);
        if (count($slug)) {
            $tpl = CmsItemReader::getAllGearsByType('units')
                ->where('slug', array_first($slug))
                ->first();
            if ($tpl) {
                $html = view('console::backend.gears.fields._partials.variation_list_settings', compact(['tpl']))->render();
                return \Response::json(['html' => $html, 'error' => false]);
            }
        }
        return \Response::json(['message' => 'wrong message', 'error' => true]);
    }

    public function saveField($data)
    {
        $dataToSave = [
            'name' => $data['name'],
            'slug' => uniqid(),
            'table_name' => $data['table_name'],
            'column_name' => $data['column_name'],
            'created_by' => \Auth::id(),
            'structured_by' => 'custom',
            'unit' => $data['unit'] != '' ? $data['unit'] : NULL,
            'label' => $data['label'] != '' ? $data['label'] : NULL,
            'placeholder' => $data['placeholder'] != '' ? $data['placeholder'] : NULL,
            'icon' => $data['icon'] != '' ? $data['icon'] : NULL,
            'tooltip' => $data['tooltip'] != '' ? $data['tooltip'] : NULL,
            'custom_html' => $data['custom_html'] != '' ? $data['custom_html'] : NULL,
            'field_html' => $data['field_html'] != '' ? $data['field_html'] : 'no',
            'second_table' => isset($data['second_table']) && $data['second_table'] != '' ? $data['second_table'] : NULL,
            'second_column' => isset($data['second_column']) && $data['second_column'] != '' ? $data['second_column'] : NULL,
            'required' => $data['required'],
            'visibility' => $data['visibility'],
            'default_value' => $data['default_value'] != '' ? $data['default_value'] : NULL,
            'available_for_users' => $data['available_for_users'],
            'before_save' => $data['before_save'],
        ];
        $dataToSave['json_data'] = json_encode($dataToSave, true);
        return $this->fields->create($dataToSave);
    }

    public function fieldUpdate($data, $field)
    {
        $field->update([
            'name' => $data['name'],
            'table_name' => $data['table_name'],
            'column_name' => $data['column_name'],
            'unit' => isset($data['unit']) && $data['unit'] != '' ? $data['unit'] : NULL,
            'required' => $data['required'],
            'before_save' => $data['before_save']
        ]);
    }

    public function createForm(array $data)
    {
        $form = $this->forms->create([
            'slug' => uniqid(),
            'settings' => $data['settings'],
            'name' => $data['name'],
            'fields_type' => $data['fields_type'],
            'form_builder' => $data['form_builder'],
            'form_type' => $data['form_type'],
            'type' => 'edit',
            'created_by' => 'custom',
        ]);

        if ($form) {
            $this->formService->generateBlade($form->id, $data['blade']);
        }
    }

    public function getDefaultHtml()
    {
        $defaultFieldHtml = $this->settingRepo->getSettings('setting_system', 'default_field_html');
        $variationId = $defaultFieldHtml->val;
        $settings = Painter::findByVariation($variationId)->renderSettings();
        $variation = Painter::findVariation($variationId)->toArray();
        $unit = Painter::findByVariation($variationId)->render($variation);

        return ['html' => $unit, 'settings' => htmlentities($settings)];
    }

    public function getCustomHtml($request)
    {
        $variationId = $request->slug;
        $settings = Painter::findByVariation($variationId)->renderSettings();
        $variation = Painter::findVariation($variationId)->toArray();
        $unit = Painter::findByVariation($variationId)->render($variation);

        return ['html' => $unit, 'settings' => htmlentities($settings)];
    }

    public function getSavedHtmlType($request)
    {
        $form = $this->forms->find($request->get('id'));
        $field = $this->fields->findBy('slug', $request->slug);

        if ($form) {
            $form_type = $form->form_type;
        } elseif ($request->get('form_type')) {
            $form_type = $request->get('form_type');
        } else {
            return ['error' => true];
        }

        if (!$field) ['error' => true];

        if ($form_type == 'user') {
            if ($field->available_for_users == 1) {
                $this->html = BBField(['slug' => $request->slug]);
                $this->type = 'render';
            } elseif ($field->available_for_users == 2) {
                $this->html = BBFieldHidden(['slug' => $request->slug]);
                $this->type = 'hidden';
            } elseif ($field->available_for_users == 3) {
                $this->type = 'no_render';
            }
        } else {
            if ($field->visibility) {
                $this->html = BBField(['slug' => $request->slug]);
                $this->type = 'render';
            } else {
                $this->html = BBFieldHidden(['slug' => $request->slug]);
                $this->type = 'hidden';
            }
        }

        return ['field' => $field->toArray(), 'html' => $this->html, 'type' => $this->type, 'error' => false];
    }

    public function postChangeFieldStatus($request)
    {
        $status = $request->status == 'true' ? 1 : 0;
        $field = $this->fields->findBy('slug', $request->slug);
        $result = $this->fields->update($field->id, ['status' => $status]);
        return $result ? true : false;
    }

    public function postNewBuilder($request, $id)
    {
        $this->forms->update($id, $request->except('fields', 'blade', 'token', 'blade_rendered', 'new_builder'));
        $this->formService->syncFields($id, $request->fields);
        $this->formService->generateBlade($id, $request->blade);

        $builder = Structures::find($request->get('new_builder'));
        if ($builder && \File::exists(base_path($builder->path . DS . 'views' . DS . $builder->builder . '.blade.php'))) {
            $file = view("$builder->namespace::" . $builder->builder, compact(['form']))->render();
            return ['error' => false, 'fields' => $this->formService->getFields(true), 'builder' => $file];
        } else {
            return ['error' => true, 'message' => 'Data is kept, but new builder not found'];
        }
    }

    public function postBuilder($request)
    {
        $slug = $request->get('slug');
        $builder = Structures::find($slug);

        if ($builder && \File::exists(base_path($builder->path . DS . 'views' . DS . $builder->builder . '.blade.php'))) {
            $form = $this->forms->find($request->get('form'));
            $file = view("$builder->namespace::" . $builder->builder, compact(['form']))->render();
            if (isset($builder->js) && count($builder->js)) {
                foreach ($builder->js as $js) {
                    \Eventy::action('my.scripts', url('app/ExtraModules/' . $builder->namespace . '/views/js/' . $js));
                }
            }
            return ['error' => false, 'builder' => $file];
        }

        return ['error' => true];
    }

    public function getFieldsByFormType($form)
    {
        if ($form->form_type == 'user') {
            $fields = $this->fields->getByTableNameActiveAndAvailablity($form->fields_type);
        } else {
            $fields = $this->fields->getByTableNameAndActive($form->fields_type);
        }

        return $fields;
    }

    public function postAvailableFields($table)
    {
        $fields = $this->fields->getBy('table_name', $table);
        return view('console::structure._partials.available_fields', compact('fields'))->render();
    }

    public function getEntryData($request)
    {
        $entry = $this->formEntries->findOrFail($request->id);

        ($entry->data) ? $data = unserialize($entry->data) : $data = [];

        if (count($data)) {
            $html = view('console::structure._partials.entry', compact('data'))->render();
            return ['error' => false, 'html' => $html];
        }

        return ['error' => true];
    }

    public function getBuilders($modules, $form, $request)
    {
        $file = null;
        $builders = [];
        if (count($modules)) {
            foreach ($modules as $builder) {
                $builders[$builder->slug] = $builder->name;
            }
        }

        $form->form_builder = $slug = $request->get('slug', $form->form_builder);
        $builder = Structures::find($slug);

        if ($builder && \File::exists(base_path($builder->path . DS . 'views' . DS . $builder->builder . '.blade.php'))) {
            $file = view("$builder->namespace::" . $builder->builder, compact(['form']))->render();
        }

        return ['file' => $file, 'slug' => $slug, 'builder' => $builder];
    }

    public function getUrls($method)
    {
        $this->plugins->modules();
        $modules = $this->plugins->getPlugins();
        $moduleRoutes = $this->collectRoutes($modules, $method);

        $this->plugins->plugins();
        $plugins = $this->plugins->getPlugins();
        $pluginRoutes = $this->collectRoutes($plugins, $method);

        return array_merge($moduleRoutes, $pluginRoutes);
    }

    private function collectRoutes($modules, $method)
    {
        $routes = [];
        if (count($modules)) {
            foreach ($modules as $module) {
                if (isset($module['route'])) {
                    $url = strtolower('admin/' . $module['route']);
                    if ($method == 'all') {
                        $routes['GET'][$module['route']] = Routes::getModuleRoutes('GET', $url);
                        $routes['POST'][$module['route']] = Routes::getModuleRoutes('POST', $url);
                    } else if ($method == 'GET' || $method == 'POST') {
                        $routes[$method][$module['route']] = Routes::getModuleRoutes($method, $url);
                    }
                }
            }
        }

        return $routes;
    }


}