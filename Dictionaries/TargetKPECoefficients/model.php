<?php

use LossLog_Dictionaries_Model as BaseDictionaryModel;

class LossLog_Dictionaries_TargetKPECoefficients_Model extends BaseDictionaryModel
{
    protected string $table = 'losslog_target_kpe_coefficients';
    public const string ENTITY_NAME = 'TargetKPECoefficients';
    public string $optionsValueField = 'id';
    public string $optionsTextField = 'target';

    public string $permissionName = 'losslog_dictionary_kpe_targets';

    public array $fields = [
        'id'        => ['type' => 'primarykey'],
        'object_id' => [
            'type'    => 'int',
            'link'    => 'LossLog_Objects_Link_Model',
            'comment' => 'Объект для кого формируются правила',
        ],
        'oee'        => ['type' => 'float'],
        'oae'        => ['type' => 'float'],
        'teep'       => ['type' => 'float'],
        'ctime'      => ['type' => 'int'],
        'mtime'      => ['type' => 'int'],
        'created_id' => ['type' => 'int'],
        'user_id'    => ['type' => 'int'],
    ];

    public array $editableFields = [
        'id',
        'object_id',
        'oee',
        'oae',
        'teep',
    ];

    protected array $requiredFields = [
        'id',
        'object_id',
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
        string $key_field = 'object_id',
        string $id_field = 'id'
    ): array {
        return parent::getListForLink($filter, $return_records, $order, $key_field, $id_field);
    }
}
