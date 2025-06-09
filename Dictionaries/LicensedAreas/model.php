<?php

use LossLog_Dictionaries_Model as BaseDictionaryModel;

class LossLog_Dictionaries_LicensedAreas_Model extends BaseDictionaryModel
{
    protected string $table = 'losslog_licensed_areas';
    public const ENTITY_NAME = 'LicensedAreas';

    public array $fields = [
        'id'         => ['type' => 'primarykey'],
        'code'       => ['type' => 'string'],
        'name'       => ['type' => 'string'],
        'ctime'      => ['type' => 'int'],
        'mtime'      => ['type' => 'int'],
        'created_id' => ['type' => 'int'],
        'user_id'    => ['type' => 'int', 'link' => 'User'],
    ];
    public array $editableFields = ['id', 'code', 'name'];

    /**
     * {@inheritdoc}
     *
     * @override
     */
    public function getListForLink(
        array $filter = [],
        bool $return_records = false,
        string $order = '',
        string $key_field = 'code',
        string $id_field = 'id'
    ): array {
        return parent::getListForLink($filter, $return_records, $order, $key_field, $id_field);
    }
}
