<?php

class LossLog_ActsTechnicalRepair_Model extends Model_Extended
{
    protected string $table = 'losslog_acts_technical_repair';

    public array $fields = [
        'id'              => ['type' => 'primarykey'],
        'doc_uid'         => ['type' => 'string'], // Ссылка
        'doc_num'         => ['type' => 'string'], // Номер
        'doc_date'        => ['type' => 'datetime'], // ДатаОбнаружения
        'atr_date'        => ['type' => 'datetime'], // Дата/время АТР
        'is_arp'          => ['type' => 'bool'], // Акт Расследования Происшествия
        'is_nz'           => ['type' => 'bool'], // Флаг, указывающий что запись создана из Наряд-Заказа
        'root_cause'      => ['type' => 'string'], // КореннаяПричинаНаименование
        'root_cause_guid' => ['type' => 'string'], // КореннаяПричинаНаименование
        'edo_status'      => ['type' => 'string'], // СтатусЭДО
        'deny_status'     => ['type' => 'string'], // СтатусРасследованияОтказа
        'description'     => ['type' => 'string'], // Описание отказа
        'doc_link'        => ['type' => 'string'], // НавигационнаяСсылка
        'object_guid'     => ['type' => 'string'], // ФактическиОтказавшаяЕО
        'object_id'       => ['type' => 'int'], // Объект ремонта, Связь с таблицей
        // repair_objects по полю object_guid

        'workshop_guid' => ['type' => 'string'], // Подразделение
        'workshop_id'   => ['type' => 'int'], // Подразделение

        // ----------------------------------
        'ctime'      => ['type' => 'int'],
        'mtime'      => ['type' => 'int'],
        'created_id' => ['type' => 'int'],
        'user_id'    => ['type' => 'int', 'link' => 'User'],
    ];

    // Значение ID из словаря коренных причин
    // Со значением "Техническое расследование не закончено"
    public const int DEFAULT_ROOT_CAUSE = 50;

    /**
     * {@inheritdoc}
     *
     * @override
     */
    public function getListForLink(
        array $filter = [],
        bool $return_records = false,
        string $order = '',
        string $key_field = 'doc_uid',
        string $id_field = 'id'
    ): array {
        return parent::getListForLink($filter, $return_records, $order, $key_field, $id_field);
    }

    /**
     * Сохранение АТР. Создание/обновление записи в таблице losslog_acts_technical_repair.
     *
     * @param array<string, mixed> $rawData
     */
    public function store(array $rawData): void
    {
        if (empty($rawData['doc_uid'])) {
            $errors[] = tr('EmptyDocUid') . ' model.php' . print_r($rawData, true);

            return;
        }

        $failureObjectModel = new FailureObjects_Model();
        $failureWorkshopModel = new FailureObjects_Inner_Workshops_Model();

        $object = $failureObjectModel->getItemByFilter(['object_guid' => trim($rawData['object_guid'])], '', 'id');
        $workshop = $failureWorkshopModel->getItemByFilter(['guid' => trim($rawData['workshop_guid'])], '', 'id');

        $data = [
            'doc_uid'         => trim($rawData['doc_uid']),
            'doc_num'         => trim($rawData['doc_num']),
            'doc_date'        => trim($rawData['doc_date']),
            'is_arp'          => trim($rawData['is_arp']),
            'root_cause'      => trim($rawData['root_cause']),
            'root_cause_guid' => trim($rawData['root_cause_guid']),
            'edo_status'      => trim($rawData['edo_status']),
            'deny_status'     => trim($rawData['deny_status']),
            'object_guid'     => trim($rawData['object_guid']),
            'workshop_guid'   => trim($rawData['workshop_guid']),
            'doc_link'        => trim($rawData['doc_link']),
            'atr_date'        => trim($rawData['atr_date']),
            'object_id'       => $object['id'] ?? 0,
            'workshop_id'     => $workshop['id'] ?? 0,
            'description'     => trim($rawData['description']),
            'is_nz'           => trim($rawData['is_nz']),
        ];

        $item = $this->getItemByFilter(['doc_uid' => $rawData['doc_uid']]);
        $id = (int)$item['id'];

        if (!empty($id)) {
            $this->update($data, $id);
            $this->updateRootCauses($id);
        } elseif ($data['doc_date'] >= '2022-01-01' || $data['doc_date'] === '') {
            // не добавлять документы старше 2022-01-01
            $this->addData($data);
        }
    }

    private function updateRootCauses(int $atrId): void
    {
        $atrModel = new self();
        $atrItem = $atrModel->getItemByFilter(['id' => $atrId], '', 'id, root_cause_guid, edo_status, deny_status');

        if (empty($atrItem['id'])) {
            return;
        }
        $decompositionsPlanModel = new LossLog_DecompositionPlan_Model();
        $decompositionsFactModel = new LossLog_DecompositionFact_Model();

        if (
            mb_strtolower($atrItem['edo_status']) == 'исполнено'
            || mb_strtolower($atrItem['deny_status']) == 'согласован'
        ) {
            $dictionaryRootCauseModel = new LossLog_Dictionaries_RootCause_Model();
            $rootCauseItem = $dictionaryRootCauseModel->getItemByFilter(
                ['guid' => $atrItem['root_cause_guid']],
                '',
                'id'
            )['id'];

            if (!empty($rootCauseItem)) {
                $decompositionsPlanModel->updateByFilter(
                    ['root_cause' => $rootCauseItem],
                    ['document' => $atrItem['id']]
                );
                $decompositionsFactModel->updateByFilter(
                    ['root_cause' => $rootCauseItem],
                    ['document' => $atrItem['id']]
                );
            }
        } else {
            $decompositionsPlanModel->updateByFilter(
                ['root_cause' => self::DEFAULT_ROOT_CAUSE],
                ['document' => $atrItem['id']]
            );
            $decompositionsFactModel->updateByFilter(
                ['root_cause' => self::DEFAULT_ROOT_CAUSE],
                ['document' => $atrItem['id']]
            );
        }
    }
}
