<?php

use LossLog_Dictionaries_Model as BaseDictionaryModel;

class LossLog_Dictionaries_ImmediateCause_Model extends BaseDictionaryModel
{
    protected string $table = 'losslog_immediate_cause';
    public const string ENTITY_NAME = 'ImmediateCause';
    public const int OWN_NEEDS_ACCOUNTING_ID = 27;
    public const int FLARING_ACCOUNTING_ID = 28;

    public array $fields = [
        'id'                => ['type' => 'primarykey'],
        'name'              => ['type' => 'string'],
        'document_required' => ['type' => 'bool'],
        'cause_type'        => [
            'type'    => 'int',
            'link'    => 'LossLog_Dictionaries_ImmediateCauseTypes_Model',
            'comment' => 'Тип непосредственной причины',
        ],
        'ctime'      => ['type' => 'int'],
        'mtime'      => ['type' => 'int'],
        'created_id' => ['type' => 'int'],
        'user_id'    => ['type' => 'int', 'link' => 'User'],
    ];
    public array $editableFields = ['id', 'name', 'document_required', 'cause_type'];
}
