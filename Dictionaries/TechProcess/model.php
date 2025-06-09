<?php

use LossLog_Dictionaries_Model as BaseDictionaryModel;

class LossLog_Dictionaries_TechProcess_Model extends BaseDictionaryModel
{
    protected string $table = 'losslog_tech_processes';
    public const ENTITY_NAME = 'TechProcess';
    public string $optionsValueField = 'id';
    public string $optionsTextField = 'tech_process';

    public array $fields = [
        'id'                => ['type' => 'primarykey'],
        'tech_process'      => ['type' => 'string'],
        'tech_process_name' => ['type' => 'string'],
        'unit'              => ['type' => 'string'],
        'ctime'             => ['type' => 'int'],
        'mtime'             => ['type' => 'int'],
        'created_id'        => ['type' => 'int'],
        'user_id'           => ['type' => 'int'],
    ];

    public array $editableFields = ['id', 'tech_process', 'tech_process_name', 'unit'];

    protected array $requiredFields = [
        'id',
        'tech_process',
        'unit',
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
        string $key_field = 'tech_process',
        string $id_field = 'id'
    ): array {
        return parent::getListForLink($filter, $return_records, $order, $key_field, $id_field);
    }
}
