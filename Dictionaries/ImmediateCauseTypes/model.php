<?php

use LossLog_Dictionaries_Model as BaseDictionaryModel;

class LossLog_Dictionaries_ImmediateCauseTypes_Model extends BaseDictionaryModel
{
    protected string $table = 'losslog_immediate_cause_types';
    public const string ENTITY_NAME = 'ImmediateCauseTypes';
    public string $optionsValueField = 'id';
    public string $optionsTextField = 'immediate_cause_type';

    public array $fields = [
        'id'                   => ['type' => 'primarykey'],
        'immediate_cause_type' => ['type' => 'string'],
        'ctime'                => ['type' => 'int'],
        'mtime'                => ['type' => 'int'],
        'created_id'           => ['type' => 'int'],
        'user_id'              => ['type' => 'int'],
    ];

    public array $editableFields = ['id', 'immediate_cause_type'];

    protected array $requiredFields = [
        'id',
        'immediate_cause_type',
    ];

    /**
     * {@inheritdoc}
     *
     * @override
     */
    public function getListForLink(
        array $filter = [],
        bool $return_records = false,
        string $order = '',
        string $key_field = 'immediate_cause_type',
        string $id_field = 'id'
    ): array {
        return parent::getListForLink($filter, $return_records, $order, $key_field, $id_field);
    }
}
