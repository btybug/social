<?php
/**
 * Copyright (c) 2017. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

namespace Btybug\Social\Services;

use Btybug\btybug\Services\GeneralService;
use Btybug\Social\Repository\FieldsRepository;
use Btybug\Social\Repository\FormEntriesRepository;
use Btybug\Social\Repository\FormFieldsRepository;
use Btybug\Social\Repository\FormRolesRepository;
use Btybug\Social\Repository\FormsRepository;

/**
 * Class BackendService
 * @package Btybug\Social\Services
 */
class FormService extends GeneralService
{
    const GET_AUTO = 3;
    public static $form_path = 'resources' . DS . 'views' . DS . 'forms' . DS;
    public static $form_file_ext = '.blade.php';
    public $slug, $conf, $fields, $form_type, $id, $collected, $fields_type, $required_fields, $formObject;
    private $form, $formFields, $fieldValidation, $fieldRepo, $entries,$fieldService,$formRolesRepository;
    public $formData = [];

    public function __construct(
        FormsRepository $formsRepository,
        FormFieldsRepository $formFieldsRepository,
        FieldValidationService $fieldValidationService,
        FieldsRepository $fieldsRepository,
        FormEntriesRepository $entriesRepository,
        FieldService $fieldService,
        FormRolesRepository $formRolesRepository
    )
    {
        $this->form = $formsRepository;
        $this->formFields = $formFieldsRepository;
        $this->fieldValidation = $fieldValidationService;
        $this->fieldRepo = $fieldsRepository;
        $this->entries = $entriesRepository;
        $this->fieldService = $fieldService;
        $this->formRolesRepository = $formRolesRepository;
    }

    public function makeFormObject($id){
        $this->formObject = $this->form->find($id);
        return $this;
    }

    public static function checkFields($json)
    {
        $fields = json_decode($json, true);
        $error = false;
        if (count($fields)) {
            foreach ($fields as $field) {
                if (!isset($field['table']) or !isset($field['columns']) or !$field['table'] or !$field['columns']) {
                    $error = true;
                } else {
                    if (!\Schema::hasTable($field['table']) or !\Schema::hasColumn($field['table'], $field['columns'])) {
                        $error = true;
                    }
                }
            }
        }

        return $error;
    }

    /**
     * @param $form_id
     * @param $table_name
     * @param $html
     */
    public static function synchronizeBlade($form, $html)
    {
        $fields = $form->fields->pluck('id', 'id')->toArray();
        $form_name = $form->id . "_" . $form->table_name . self::$form_file_ext;
        $form_path = base_path(self::$form_path) . $form_name;

        if (!\File::exists($form_path)) \File::put($form_path, "");

        if (count($fields)) {
            foreach ($fields as $value) {
                $start = strpos($html, "<!--$value-->");
                $end = strpos($html, "<!--end$value-->");
                $html = str_replace(substr($html, $start, ($end - $start)), "<!--$value-->" . '{!! BBRenderField(' . $value . ',$form) !!}', $html);
            }
        }

        \File::put($form_path, $html);
    }

    public function generateBlade($id, $blade)
    {
        $this->formObject = $this->form->find($id);

        if ($this->formObject) {
            \File::put(self::$form_path . $this->formObject->slug . self::$form_file_ext, $blade);

            return true;
        }
        return false;
    }

    public function syncFields($form_id, $fields)
    {
        $this->formFields->deleteByCondition('form_id', $form_id);

        if (!empty($fields)) {
            if(! is_array($fields)) $fields = json_decode($fields, true);
            if (count($fields)) {
                foreach ($fields as $key => $value) {
                    if(is_array($value) && count($value)){
                        foreach ($value as $k => $v){
                            $field = $this->fieldRepo->find($v);
                            if($field){
                                $this->formFields->create([
                                    'form_id' => $form_id,
                                    'field_slug' => $field->slug
                                ]);
                            }
                        }
                    }else{
                        $field = $this->fieldRepo->find($value);
                        if($field){
                            $this->formFields->create([
                                'form_id' => $form_id,
                                'field_slug' => $field->slug
                            ]);
                        }
                    }
                }
            }
        }
    }

    public function render($id = null)
    {
        if (!$this->formObject) $this->formObject = $this->form->find($id);

        if (\File::exists(self::$form_path . $this->formObject->slug . self::$form_file_ext)) {
            $content = \File::get(self::$form_path . $this->formObject->slug . self::$form_file_ext);
            return $this->searcer($content);
        }

        return null;
    }

    protected function searcer($content)
    {
        $this->conf = \config::get('shortcode.extra');
        foreach ($this->conf as $val) {
            $key = array_keys($val)[0];
            $fn = $val[$key];
            $content = $this->sortCoder($key, $fn, $content);
            $posCode = "[$key";
//            dd(strpos($content, $posCode));
            if (strpos($content, $posCode)) {
                $content = $this->searcer($content);
            }
        }
        return $content;
    }

    /**
     * @param $fn
     * @param $content
     * @return mixed
     */
    protected function sortCoder($key, $fn, $content)
    {
        $posCode = "[$key";
        $endLen = '';
        $pos = strpos($content, $posCode);
        if (!$pos) {
            return $content;
        }
        $pos = $pos + 1;
        for ($pos; $pos < strlen($content); $pos++) {
            if ($content[$pos] != ']') {
                $endLen .= $content[$pos];
            } else {
                break;
            }
        }
        $result = explode(' ', $endLen);

        //removing function name
        unset($result[0]);
        $final_arg = [];
        foreach (array_filter($result) as $key => $value) {
            $arg = explode('=', $value);
            if (isset($arg[0]) && isset($arg[1]))
                $final_arg[$arg[0]] = $arg[1];
        }
        $code = $fn($final_arg);
        $newContent = str_replace('[' . $endLen . ']', $code, $content);
        return $newContent;
    }

    public function renderBlade($id = null)
    {
        if (!$this->formObject) $this->formObject = $this->form->find($id);
        if (\File::exists(self::$form_path . $this->formObject->slug . self::$form_file_ext)) {
            return \File::get(self::$form_path . $this->formObject->slug . self::$form_file_ext);
        }

        return null;
    }

    public static function renderFormBlade($form,array $attributes = [])
    {
        if ($form){
            $model = null;
            $access = false;
            if ($form->form_access == 0) $access = true;

            if ($form->form_access == 2 && \Auth::check()) $access = true;

            if ($form->form_access == 1 && \Auth::check() && \Auth::user()->role && self::checkAccess($form, \Auth::user()->role->id)) $access = true;

            if ($access && \View::exists("forms.".$form->slug)) {
                if(isset($attributes['data-id'])) {
                    $model = \DB::table($form->fields_type)->where('id',$attributes['data-id'])->first();
                    $data = [];
                    foreach ($model as $key => $value) {
                        if($key == 'edit'){
                            $data[$key] = $value;
                        }else{
                            $is_json = @json_decode($value,true);
                            if(is_array($is_json)){
                                $data[$form->fields_type . "_" . $key] = $is_json;
                            }else{
                                $data[$form->fields_type . "_" . $key] = $value;
                            }
                        }
                    }
                    $model = $data;
                }
                if(isset($attributes['edit'])){
                    \View::share('form_model',$model);
                }

                return view("console::structure.developers.forms.form_layout",compact('form','model','attributes'))->render();
            }
        }

        return null;
    }

    public static function checkAccess ($form, $role_id)
    {
        if ($form && $role_id) {
            $access = $form->form_roles->where('role_id', $role_id)->first();
            if ($access) return true;
        }

        return false;
    }


    public function validate($id, $data)
    {
        $this->formObject = $this->form->find($id);

        $this->formData = $data;
        $this->fields = $this->getFields(false, true);
        $rules = [];
        if (count($this->fields)) {
            foreach ($this->fields as $field) {
                if ($this->formObject->form_type == 'user') {
                    if ($field->available_for_users != self::GET_AUTO) {
                        if (isset($data['edit']) && isset($data[$field->table_name . '_' . $data['edit']])) {
                            $rules[$field->table_name . "_" . $field->column_name] =
                                $this->fieldValidation->getBaseValidationRulse($field->table_name, $field->column_name,
                                    $data[$field->table_name . '_' . $data['edit']]);

                        } else {
                            $rules[$field->table_name . "_" . $field->column_name] =
                                $this->fieldValidation->getBaseValidationRulse($field->table_name, $field->column_name);
                        }
                    }
                } else {
                    if (isset($data['edit']) && isset($data[$field->table_name . '_' . $data['edit']])) {
                        $rules[$field->table_name . "_" . $field->column_name] =
                            $this->fieldValidation->getBaseValidationRulse($field->table_name, $field->column_name,
                                $data[$field->table_name . '_' . $data['edit']]);

                    } else {
                        //TODO: need to write in log all errors
                        try{
                            $rules[$field->table_name . "_" . $field->column_name] =
                                $this->fieldValidation->getBaseValidationRulse($field->table_name, $field->column_name);
                        }catch (\Exception $exception){
                            dd($exception,$field);
                        }
                    }
                }
            }
        }

        $v = \Validator::make($this->formData, $rules);
        if ($v->fails()) {
            return ['errors' => $v->messages()];
        } else {
            return ['errors' => false];
        }
    }

    public function getFields($json = false, $object = false)
    {
        $data = [];
        $fields = $this->formFields->getBy('form_id', $this->formObject->id);
        if (count($fields)) {
            foreach ($fields as $field) {
                if ($object) {
                    $value = $this->fieldRepo->findBy('slug', $field->field_slug);
                    if ($value) $data[$value->column_name] = $value;
                } else {
                    $data[] = $field->field_slug;
                }
            }
        }

        return ($json) ? json_encode($data, true) : ($object) ? collect($data) : $data;
    }

    public function collectTables()
    {
        $this->collected = [];
        $this->fields = $this->getFields(false, true);
        if (count($this->fields)) {
            foreach ($this->fields as $field) {
                $function = $field->before_save;
                if (isset($this->formData[$field->table_name . '_' . $field->column_name])) {
                    $value = $this->formData[$field->table_name . '_' . $field->column_name];
                    if ($function && function_exists($function)) {
                        $value = $function($value);
                    }
                    $this->collected[$field->table_name][$field->column_name] = $value;
                } else {
                    if ($this->formObject->form_type == 'user' && $field->available_for_users == self::GET_AUTO) {
                        $value = $field->default_value;
                        if ($value && function_exists($value)) {
                            if ($function && function_exists($function)) {
                                $saveDefaut = $function($value());
                                $this->collected[$field->table_name][$field->column_name] = $saveDefaut;
                            } else {
                                $this->collected[$field->table_name][$field->column_name] = $value();
                            }

                        }

                    }
                }
            }
        }

        return $this;
    }

    public function saveForm($request)
    {
        $data = [];
        if (count($this->collected)) {
            foreach ($this->collected as $table => $columns) {
                if (\Schema::hasTable($table)) {
                    if (isset($request['edit']) && \Schema::hasColumn($table, $request['edit']) && isset($columns[$request['edit']])) {
                        $model = \DB::table($table)->where($request['edit'], $columns[$request['edit']])->first();
                        if ($model) {
                            \DB::table($table)->where($request['edit'], $columns[$request['edit']])->update(array_except($columns, $request['edit']));
                            $data[$table] = \DB::table($table)->where($request['edit'], $columns[$request['edit']])->first();
                        }
                    } else {
                        if (isset($request['edit']) && $request['edit'] == 'id' && isset($request[$this->fields_type . '_id'])) {
                            $model = \DB::table($table)->find($request[$this->fields_type . '_id']);
                            if ($model) {
                                \DB::table($table)->where($request['edit'], $request[$this->fields_type . '_id'])->update(array_except($columns, $request['edit']));
                                $data[$table] = \DB::table($table)->find($request[$this->fields_type . '_id']);
                            }
                        }
                    }

                    if (count($data) == ZERO) {
                        try {
                            $lastID = \DB::table($table)->insertGetId($columns);
                            $data[$table] = \DB::table($table)->find($lastID);
                        } catch (\Exception $exception) {
                            die($exception->getMessage());
                        }
                    }

                }
            }
        }

        return $data;
    }

    public function validateColumns($id, $fields)
    {
        $this->formObject = $this->form->find($id);
        $error = false;
        if ($this->formObject->required_fields) {
            $columns = $this->getFieldColumns($fields);
            $requiredColumns = json_decode($this->formObject->required_fields, true);

            $data = array_intersect($columns, $requiredColumns);
            if (count($data) != count($requiredColumns)) {
                $error = true;
                $missedColumns = array_diff($requiredColumns, $columns);

                return ['error' => true, 'message' => "Please add fields with these columns: " . implode(', ', $missedColumns)];
            }
        }

        return ['error' => false];
    }

    public function getFieldColumns($fields)
    {
        $data = [];
        if (count($fields)) {
            $fieldsData = json_decode($fields);
            foreach ($fieldsData as $field) {
                $value = $this->fieldRepo->findBy('slug', $field);
                if ($value)
                    $data[] = $value->column_name;
            }
        }

        return $data;
    }

    public function getModel($column)
    {
        $fields = $this->getFields(false, true);

        if (isset($fields[$column])) {
            $col = $fields[$column];
            $value = $col->default_value;
            if ($value && function_exists($value)) {
                $selectValue = $value();
                $model = \DB::table($col->table_name)->where($column, $selectValue)->first();
                return $this->rebuildModel($model);
            }
        }

        return null;
    }

    public function rebuildModel($model)
    {
        if ($model && isset($model->attributes) && count($model->attributes)) {
            $data = [];
            foreach ($model->attributes as $key => $value) {
                $data[$this->fields_type . "_" . $key] = $value;
            }
            $model = $data;
        } else {
            $model = (array)$model;
            $data = [];
            foreach ($model as $key => $value) {
                $data[$this->fields_type . "_" . $key] = $value;
            }
            $model = $data;
        }

        return $model;
    }

    public function getWithUrl($edit)
    {
        $params = \Request::route()->parameters();
        if (isset($params[$edit])) {
            $model = \DB::table($this->fields_type)->where($edit, $params[$edit])->first();
            return $this->rebuildModel($model);
        }
        return null;
    }

    public function newEntry($request, $entries, $form, $model = null)
    {
        $user_id = 0;
        if (\Auth::check()) $user_id = \Auth::user()->id;

        return $this->entries->create([
            'data' => @serialize($entries),
            'form_id' => $form->id,
            'user_id' => $user_id,
            'ip' => $request->getClientIp(),
            'created_at' => date('Y-m-d h:i:s'),
        ]);
    }

    public function createOrUpdate ($data)
    {
        if($data['id']){
            $form = $this->form->find($data['id']);
            if($form){
                $this->form->update($data['id'], [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'original_css' => $data['original_css'],
                    'fields_json' => (is_array(issetReturn($data,'fields_json',[])))
                        ? json_encode(issetReturn($data,'fields_json',[]),true)
                        : issetReturn($data,'fields_json',[]),
                    'original_html' => issetReturn($data,'original_html',null),
                    'form_layout' => issetReturn($data,'form_layout',null),
                    'fields_html' => issetReturn($data,'fields_html',null),
                    'settings' =>  issetReturn($data,'settings',[])
                ]);
            }
        }else{
            $form = $this->form->create([
                'name' => $data['name'],
                'description' => $data['description'],
                'slug' => uniqid(),
                'created_by' => 'custom',
                'original_css' => $data['original_css'],
                'fields_json' => issetReturn($data,'fields_json',null),
                'original_html' => issetReturn($data,'original_html',null),
                'fields_type' => $data['fields_type'],
                'form_layout' => issetReturn($data,'form_layout',null),
                'fields_html' => issetReturn($data,'fields_html',null),
                'settings' =>  issetReturn($data,'settings',[])
            ]);
        }

        if($form){
            if(isset($data['fields_html'])){
                $this->generateBlade($form->id, $data['fields_html']);
            }

            $this->syncFields($form->id, $data['fields_json']);

            return $form;
        }

        return null;
    }

    public function fieldsJson($id, $json = false)
    {
        $this->formObject = $this->form->find($id);
        if($this->formObject) {
            $fields = $this->getExistingFields();
            if(count($fields)){
                $combo = $this->formObject->fields_json;
                if($combo && count(json_decode($combo,true))){
                    $combo = json_decode($combo,true);
                    foreach ($combo as $key => $items){
                        $existing = [];
                        if(count($items)){
                            foreach ($items as $field_id){
                                $field = $this->fieldRepo->find($field_id);
                                if($field) {
                                    $inside_array = [];
                                    $inside_array['object'] = $field;
                                    $inside_array['html'] = $this->fieldService->returnHtml($field);
                                    $inside_array['field_data'] = get_field_data($field->id);
                                    $existing[] = $inside_array;
                                }
                            }
                        }
                        $this->formData[] = $existing;
                    }
                }else{
                    foreach ($fields as $field){
                        $existing = [];
                        $existing['object'] = $field;
                        $existing['html'] = $this->fieldService->returnHtml($field);
                        $existing['field_data'] = get_field_data($field->id);

                        $this->formData[] = $existing;
                    }
                }

            }
        }

        return ($json) ? json_encode($this->formData,true) : $this->formData;
    }

    public function getExistingFields()
    {
        $form_fields = $this->formObject->form_fields;
        $fields = [];
        if(count($form_fields)){
            foreach ($form_fields as $ff){
                $field = $this->fieldRepo->findby('slug',$ff->field_slug);
                if($field) $fields[] = $field;
            }
        }

        return collect($fields);
    }

    public function saveSettings($id,$request)
    {
        $this->formObject = $this->form->findOrFail($id);
        $this->form->update($id,['settings' => $request->except('_token','roles','form_access'),'form_access' => $request->get('form_access')]);

        if ($request->get('form_access') && count($request->roles))
            $this->formRolesRepository->optimizeFormRoles($this->formObject,$request->roles);
    }
}