<?php
/**
 * Created by PhpStorm.
 * User: Comp1
 * Date: 5/5/2017
 * Time: 1:26 PM
 */

namespace Btybug\Social\Services;

use Avatar\Avatar\Repositories\Plugins;


/**
 * Class FieldValidations
 * @package App\Modules\Console\Models
 */
class FieldValidationService
{
    /**
     * @var mixed
     */
    private $rules;
    /**
     * @var array
     */
    private $columnBool = ['YES' => true, 'NO' => false];
    /**
     * @var mixed
     */
    private $db;
    /**
     * @var
     */
    private $column;
    /**
     * @var
     */
    private $columnName;
    /**
     * @var
     */
    private $tableName;
    /**
     * @var array
     */
    private $columnKey = ['UNI' => 'unique', 'PRI' => 'unique'];

    private $plugins;

    /**
     * FieldValidations constructor.
     */
    public function __construct()
    {
        $ds = DS;
        $this->db = env('DB_DATABASE');
    }

    /**
     * @return mixed
     */
    public function getRules()
    {
        $this->plugins = new Plugins();
        $packages = $this->plugins->modules();
        return json_decode(\File::get($packages->find('sahak.avatar/console')
            ->getPath('/src/Validations/rules.json')), true);
    }

    /**
     * @param $table
     * @param $column
     * @return string
     */

    public function getBaseValidationRulse($table, $column, $id = NULL)
    {
        $column_info = (\DB::select("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = '$this->db' AND table_name ='$table'  AND column_name ='$column'"));
        $this->tableName = $table;
        $this->column = array_first($column_info);
        $this->columnName = $column;
        return $this->rulGen($id);
    }

    /**
     * @return string
     */
    public function rulGen($id = NULL)
    {
        $method = $this->column->DATA_TYPE;
        $rules = '';
        $is_nullable = $this->columnBool[$this->column->IS_NULLABLE];
        $unique = (isset($this->columnKey[$this->column->COLUMN_KEY])) ? $this->columnKey[$this->column->COLUMN_KEY] : false;
        $foreign = '';
        if ($this->column->COLUMN_KEY == 'MUL') {
            $foreign = $this->foreign();
        }
        $rules .= ((!$is_nullable && is_null($this->column->COLUMN_DEFAULT)) ? 'required|' : '') . $foreign . (($unique) ? $this->$unique($id) . '|' : '') . $this->$method();
        return $rules;
    }

    public function isRequired($table, $column)
    {
        $column_info = (\DB::select("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = '$this->db' AND table_name ='$table'  AND column_name ='$column'"));
        $this->tableName = $table;
        $this->column = array_first($column_info);

        $is_nullable = $this->columnBool[$this->column->IS_NULLABLE];

        return (!$is_nullable && is_null($this->column->COLUMN_DEFAULT)) ? true : false;
    }

    public function isAutoIncrement($table, $column)
    {
        $column_info = (\DB::select("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = '$this->db' AND table_name ='$table'  AND column_name ='$column' AND EXTRA like '%auto_increment%'"));
        if (count($column_info)) return true;

        return false;
    }

    /**
     * @return string
     */
    public function foreign()
    {
        $relation = \DB::select("SELECT REFERENCED_TABLE_NAME AS r_table, REFERENCED_COLUMN_NAME AS r_column FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = '$this->tableName' AND COLUMN_NAME='$this->columnName'");
        if (is_array($relation) && isset($relation[0]) && isset($relation[0]->r_table) && isset($relation[0]->r_column)) {
            return "exists:" . $relation[0]->r_table . "," . $relation[0]->r_column . '|';
        }
    }

    /**
     * @return string
     */
    public function varchar()
    {
        return ('max:' . $this->column->CHARACTER_MAXIMUM_LENGTH);
    }

    /**
     * @return string
     */
    public function text()
    {
        return ('max:' . $this->column->CHARACTER_MAXIMUM_LENGTH);
    }

    /**
     * @return string
     */
    public function longtext()
    {
        return ('max:' . $this->column->CHARACTER_MAXIMUM_LENGTH);
    }

    /**
     * @return string
     */
    public function decimal()
    {
        return $this->decimalLen();
    }

    /**
     * @return string
     */
    public function decimalLen()
    {
        $PRECISION = $this->column->NUMERIC_PRECISION;
        $SCALE = $this->column->NUMERIC_SCALE;
        return 'between:0,' . $this->str_multyple($PRECISION, '9') . '.' . $this->str_multyple($SCALE, '9');
    }

    /**
     * @param $num
     * @param $str
     * @return number
     */
    public function str_multyple($num)
    {
        $count = pow(10, $num) - 1;
        return $count;
    }

    /**
     * @return string
     */
    public function float()
    {
        return $this->decimalLen();
    }

    /**
     * @return string
     */
    public function int()
    {
        return 'numeric|max:' . $this->str_multyple($this->column->NUMERIC_PRECISION);
    }

    /**
     * @return string
     */
    public function tinyint()
    {
        return 'digits:' . $this->column->NUMERIC_PRECISION;
    }

    /**
     * @return string
     */
    public function smallint()
    {
        return 'digits:' . $this->column->NUMERIC_PRECISION;
    }

    /**
     * @return string
     */
    public function date()
    {
        return 'date';
    }

    /**
     * @return string
     */
    public function timestamp()
    {
        return 'date';
    }

    /**
     * @return string
     */
    public function unique($id = null)
    {
        return isset($id) && $id
            ? 'unique:' . $this->tableName . ',' . $this->columnName . ',' . $id
            : 'unique:' . $this->tableName . ',' . $this->columnName;
    }
}