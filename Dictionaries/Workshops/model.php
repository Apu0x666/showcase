<?php

use LossLog_Dictionaries_Model as BaseDictionaryModel;

class LossLog_Dictionaries_Workshops_Model extends BaseDictionaryModel
{
    protected string $table = 'losslog_workshops';
    public const ENTITY_NAME = 'Workshops';
    public string $optionsTextField = 'workshop';

    public array $fields = [
        'id'             => ['type' => 'primarykey'],
        'workshop'       => ['type' => 'string'],
        'licensed_areas' => [
            'type'    => 'int',
            'link'    => 'LossLog_Dictionaries_LicensedAreas_Model',
            'comment' => 'Лицензионный участок',
        ],
        'ctime'      => ['type' => 'int'],
        'mtime'      => ['type' => 'int'],
        'created_id' => ['type' => 'int'],
        'user_id'    => ['type' => 'int'],
    ];
    public array $editableFields = ['id', 'workshop', 'licensed_areas'];

    /**
     * {@inheritdoc}
     *
     * @override
     */
    public function getListForLink(
        array $filter = [],
        bool $return_records = false,
        string $order = '',
        string $key_field = 'workshop',
        string $id_field = 'id'
    ): array {
        $key_field = 'workshop';

        return parent::getListForLink($filter, $return_records, $order, $key_field, $id_field);
    }

    public function getLuId(int $workshopId): int
    {
        $workshopItem = $this->getItem($workshopId);

        if (empty($workshopItem)) {
            return 0;
        }

        return $workshopItem['licensed_areas'];
    }
}
