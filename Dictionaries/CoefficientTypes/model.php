<?php

use LossLog_Dictionaries_Model as BaseDictionaryModel;

class LossLog_Dictionaries_CoefficientTypes_Model extends BaseDictionaryModel
{
    protected string $table = 'losslog_coefficient_types';
    public const ENTITY_NAME = 'CoefficientTypes';
    public string $optionsValueField = 'id';
    public string $optionsTextField = 'coefficient_type';

    public array $fields = [
        'id'               => ['type' => 'primarykey'],
        'coefficient_type' => ['type' => 'string'],
        'ctime'            => ['type' => 'int'],
        'mtime'            => ['type' => 'int'],
        'created_id'       => ['type' => 'int'],
        'user_id'          => ['type' => 'int'],
    ];

    public array $editableFields = ['id', 'coefficient_type'];

    protected array $requiredFields = [
        'id',
        'coefficient_type',
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
        string $key_field = 'coefficient_type',
        string $id_field = 'id'
    ): array {
        return parent::getListForLink($filter, $return_records, $order, $key_field, $id_field);
    }
}
