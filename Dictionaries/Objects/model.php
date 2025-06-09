<?php

use LossLog_Dictionaries_Model as BaseDictionaryModel;

class LossLog_Dictionaries_Objects_Model extends BaseDictionaryModel
{
    protected string $table = 'losslog_objects';
    public const ENTITY_NAME = 'Objects';
    public string $optionsTextField = 'object';
    public array $fields = [
        'id'       => ['type' => 'primarykey'],
        'object'   => ['type' => 'string'],
        'workshop' => [
            'type'    => 'int',
            'link'    => 'LossLog_Dictionaries_Workshops_Model',
            'comment' => 'Цех',
        ],
        'tech_process' => [
            'type'    => 'int',
            'link'    => 'LossLog_Dictionaries_TechProcess_Model',
            'comment' => 'Продукт',
        ],
        'ctime'      => ['type' => 'int'],
        'mtime'      => ['type' => 'int'],
        'created_id' => ['type' => 'int'],
        'user_id'    => ['type' => 'int', 'link' => 'User'],
    ];
    public array $editableFields = ['id', 'object', 'workshop', 'tech_process'];

    /**
     * {@inheritdoc}
     *
     * @override
     */
    public function getListForLink(
        array $filter = [],
        bool $return_records = false,
        string $order = '',
        string $key_field = 'object',
        string $id_field = 'id'
    ): array {
        return parent::getListForLink($filter, $return_records, $order, $key_field, $id_field);
    }
}
