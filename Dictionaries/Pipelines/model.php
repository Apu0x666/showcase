<?php

use LossLog_Dictionaries_Model as BaseDictionaryModel;

class LossLog_Dictionaries_Pipelines_Model extends BaseDictionaryModel
{
    protected string $table = 'losslog_pipelines';
    public const ENTITY_NAME = 'Pipelines';

    public array $fields = [
        'id'         => ['type' => 'primarykey'],
        'name'       => ['type' => 'string'],
        'ctime'      => ['type' => 'int'],
        'mtime'      => ['type' => 'int'],
        'created_id' => ['type' => 'int'],
        'user_id'    => ['type' => 'int', 'link' => 'User'],
    ];
    public array $editableFields = ['id', 'name'];
}
