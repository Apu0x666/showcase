<?php

use LossLog_Dictionaries_Model as BaseDictionaryModel;

class LossLog_Dictionaries_Coefficients_Model extends BaseDictionaryModel
{
    protected string $table = 'losslog_coefficients';
    public const ENTITY_NAME = 'Coefficients';
    public string $optionsValueField = 'id';
    public string $optionsTextField = 'coefficient_name';

    public array $fields = [
        'id' => ['type' => 'primarykey'],
        // Человеко-понятное наименование коээфициента (отображается в таблицах)
        'coefficient_name' => ['type' => 'string'],
        // Тип коэффициента (технологический/геологический), для типов отдельный словарь
        'coefficient_type' => [
            'type'    => 'int',
            'link'    => 'LossLog_Dictionaries_CoefficientTypes_Model',
            'comment' => 'Тип коэффициента',
        ],
        // аббревиатура используемая в формулах
        'coefficient_abbreviate' => ['type' => 'string'],
        // доступность коэффициента в результирующей таблице
        'is_enabled' => ['type' => 'bool'],

        'ctime'      => ['type' => 'int'],
        'mtime'      => ['type' => 'int'],
        'created_id' => ['type' => 'int'],
        'user_id'    => ['type' => 'int'],
    ];

    public array $editableFields = [
        'id',
        'is_enabled',
        'coefficient_name',
        'coefficient_type',
        'coefficient_abbreviate',
    ];

    protected array $requiredFields = [
        'coefficient_name',
        'coefficient_type',
        'coefficient_abbreviate',
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
        string $key_field = 'coefficient_name',
        string $id_field = 'id'
    ): array {
        return parent::getListForLink($filter, $return_records, $order, $key_field, $id_field);
    }
}
