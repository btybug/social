<?php
/**
 * Copyright (c) 2017.
 * *
 *  * Created by PhpStorm.
 *  * User: Edo
 *  * Date: 10/3/2016
 *  * Time: 10:44 PM
 *
 */

namespace Btybug\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use Btybug\btybug\Models\Painter\Painter;
use Btybug\Social\Services\FieldService;
use Illuminate\Http\Request;
use Btybug\btybug\Models\ContentLayouts\ContentLayouts;
use Btybug\btybug\Models\ExtraModules\Structures;
use Btybug\btybug\Models\Routes;
use Btybug\btybug\Services\CmsItemReader;
use Btybug\Social\Http\Requests\Structure\FieldCreateRequest;
use Btybug\Social\Http\Requests\Structure\FormCreateRequest;
use Btybug\Social\Http\Requests\Structure\FormSettingsUpdateRequest;
use Btybug\Social\Http\Requests\Structure\PageEditRequest;
use Btybug\Social\Http\Requests\Structure\SavePageSettingsRequest;
use Btybug\Social\Repository\AdminPagesRepository;
use Btybug\Social\Repository\FieldsRepository;
use Btybug\Social\Repository\FormsRepository;
use Btybug\Social\Repository\VersionsRepository;
use Btybug\Social\Services\FieldValidationService;
use Btybug\Social\Services\FormService;
use Btybug\Social\Services\StructureService;
use Btybug\btybug\Repositories\AdminsettingRepository;
use Btybug\User\Services\RoleService;
use Btybug\User\Services\UserService;

/**
 * Class ModulesController
 * @package Btybug\Modules\Models\Http\Controllers
 */
class IndexController extends Controller
{
    public function getIndex()
    {
        return view('social::index');
    }


}
