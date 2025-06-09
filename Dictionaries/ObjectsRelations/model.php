<?php

use LossLog_Dictionaries_Model as BaseDictionaryModel;

class LossLog_Dictionaries_ObjectsRelations_Model extends BaseDictionaryModel
{
    protected string $table = 'losslog_objects_relations';
    public const ENTITY_NAME = 'ObjectsRelations';

    public array $fields = [
        'id'        => ['type' => 'primarykey'],
        'object_id' => [
            'type'    => 'int',
            'link'    => 'LossLog_Objects_Link_Model',
            'comment' => 'Объект для кого формируются правила',
        ],
        'relation_object_id' => [
            'type'    => 'int',
            'link'    => 'LossLog_Objects_Link_Model',
            'comment' => 'Объект на который ссылается',
        ],
        'formula' => [
            'type' => 'string',
        ],
        'bt_formula' => [
            'type' => 'string',
        ],
        'pt_formula' => [
            'type' => 'string',
        ],
        'sgk_formula' => [
            'type' => 'string',
        ],
        'oil_formula' => [
            'type' => 'string',
        ],
        'helium_formula' => [
            'type' => 'string',
        ],
        'ctime'      => ['type' => 'int'],
        'mtime'      => ['type' => 'int'],
        'created_id' => ['type' => 'int'],
        'user_id'    => ['type' => 'int', 'link' => 'User'],
    ];
    public array $editableFields = [
        'id',
        'object_id',
        'relation_object_id',
        'formula',
        'oil_formula',
        'sgk_formula',
        'pt_formula',
        'bt_formula',
        'helium_formula',
    ];

    public string $dictionaryDescription = '';

    public function __construct()
    {
        parent::__construct();

        $coefficients = $this->getCoefficientsHint();

        $this->dictionaryDescription = '
        <b><h2>Список доступных переменных:</h2></b> <br>
        <b>Значение декомпозиции: </b>
        <pre>Декомпозиция, ДО</pre><br>
        ' . $coefficients . '
        <b>Экономика:</b>
        <pre>э_нефть, э_сгк, э_пт, э_бт, э_гелий, э_полиэтилен, э_литий</pre><br>
        <i> Так же, возможно использование написания вида К_ПТ ГПЗ, к_ШФЛУ, к_СГК ГПЗ, э_НЕФТЬ, э_Сгк и т.п., 
        пробелы и регистр не учитываются.</i><br><br>';
    }

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

    public function getCoefficientsHint(): string
    {
        $coefficientsModel = new LossLog_Dictionaries_Coefficients_Model();

        // Получаем технологические коэффициенты
        $techCoefficients = $coefficientsModel->getList(
            [
                'coefficient_type' => 2, // Тип коэффициента для технологических коэффициентов
                'is_enabled'       => 1,  // Только включенные коэффициенты
            ]
        );
        $techAbbreviates = array_column($techCoefficients, 'coefficient_abbreviate');

        // Получаем геологические коэффициенты
        $geoCoefficients = $coefficientsModel->getList(
            [
                'coefficient_type' => 1,
                'is_enabled'       => 1,
            ]
        );
        $geoAbbreviates = array_column($geoCoefficients, 'coefficient_abbreviate');

        // Возвращаем уже готовый код, для встраивания в подсказку
        return '
        <b>Технологические коэффициенты:</b><br><pre>' . implode(', ', $techAbbreviates) . '</pre><br>
        <b>Геологические коэффициенты:</b><br><pre>' . implode(', ', $geoAbbreviates) . '</pre><br>';
    }
}
