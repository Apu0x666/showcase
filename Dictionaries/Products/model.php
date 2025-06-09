<?php

use LossLog_Dictionaries_Model as BaseDictionaryModel;

class LossLog_Dictionaries_Products_Model extends BaseDictionaryModel
{
    protected string $table = 'losslog_products';
    public const ENTITY_NAME = 'Products';
    public string $optionsValueField = 'id';
    public string $optionsTextField = 'product_name';

    public array $fields = [
        'id' => ['type' => 'primarykey'],
        // Человеко-понятное наименование продукта (отображается в таблицах)
        'product_name' => ['type' => 'string'],
        // Численная единица измерения продукта
        'currency_unit' => ['type' => 'string'],
        // Денежная единица измерения продукта
        'measurement_unit' => ['type' => 'string'],
        // идентификатор для формул
        'formula_abbreviate' => ['type' => 'string'],
        // аббревиатура используемая в формулах
        'is_enabled' => ['type' => 'bool'],

        'ctime'      => ['type' => 'int'],
        'mtime'      => ['type' => 'int'],
        'created_id' => ['type' => 'int'],
        'user_id'    => ['type' => 'int'],
    ];

    public array $editableFields = [
        'id',
        'is_enabled',
        'product_name',
        'measurement_unit',
        'currency_unit',
        'formula_abbreviate',
    ];

    protected array $requiredFields = [
        'product_name',
        'currency_unit',
        'measurement_unit',
        'formula_abbreviate',
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
        string $key_field = 'product_name',
        string $id_field = 'id'
    ): array {
        return parent::getListForLink($filter, $return_records, $order, $key_field, $id_field);
    }
}
