<?php

use LossLog_Dictionaries_Model as BaseDictionaryModel;

class LossLog_Dictionaries_RootCause_Model extends BaseDictionaryModel
{
    protected string $table = 'losslog_root_cause';
    public const ENTITY_NAME = 'RootCause';
    public bool $dictionaryDisableAdd = true;
    public bool $dictionaryDisableEdit = true;
    public bool $dictionaryDisableDel = true;

    public array $fields = [
        'id'                => ['type' => 'primarykey'],
        'code'              => ['type' => 'string'],
        'is_historical'     => ['type' => 'bool'],
        'name'              => ['type' => 'string'],
        'description'       => ['type' => 'string'],
        'mark_deleted_toir' => ['type' => 'string'],
        'guid'              => ['type' => 'string'],
        'ctime'             => ['type' => 'int'],
        'mtime'             => ['type' => 'int'],
        'created_id'        => ['type' => 'int'],
        'user_id'           => ['type' => 'int'],
    ];
    public array $editableFields = ['id', 'name', 'description'];
}
