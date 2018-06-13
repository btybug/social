<?php

namespace Btybug\Social\Services;

use Btybug\btybug\Services\GeneralService;
use Btybug\Social\Repository\ColumnsRepository;
use Btybug\Social\Repository\FieldsRepository;
use Btybug\Social\Repository\FormEntriesRepository;
use Btybug\Social\Repository\FormFieldsRepository;
use Btybug\Social\Repository\FormsRepository;


class ColumnService extends GeneralService
{

    public $formsRepository,$formFieldsRepository,$fieldsRepository,$entries,$columns;

    public function __construct(
        FormsRepository $formsRepository,
        FormFieldsRepository $formFieldsRepository,
        FieldsRepository $fieldsRepository,
        FormEntriesRepository $entriesRepository,
        ColumnsRepository $columnsRepository
    )
    {
        $this->formsRepository = $formsRepository;
        $this->formFieldsRepository = $formFieldsRepository;
        $this->fieldsRepository = $fieldsRepository;
        $this->entries = $entriesRepository;
        $this->columns = $columnsRepository;
    }

    public static function columnExists(string $table,string $column)
    {
        $col = new ColumnsRepository();
        return $col->findOneByMultiple(['db_table' => $table,'table_column' => $column]);
    }

}