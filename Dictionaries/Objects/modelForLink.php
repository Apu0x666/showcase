<?php

use LossLog_Dictionaries_Objects_Model as BaseDictionaryModel;

class LossLog_Objects_Link_Model extends BaseDictionaryModel
{
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
        $list = $this->getSimpleList($filter, 'id', 0, 0, 'id, workshop, object, tech_process');
        $linkedList = $this->getLinkedList($list);
        $resultList = [];

        foreach ($linkedList as $row) {
            if ($return_records) {
                $resultList[$row['id']] = $row;
            } else {
                $workshop = $row['workshop'];
                $object = $row['object'] !== '-' ? $row['object'] : '';
                $techProcess = $row['tech_process'] !== '-' ? $row['tech_process'] : '';

                // Формируем строку в зависимости от наличия значений
                $formattedString = $workshop;

                if ($object || $techProcess) {
                    $formattedString .= ' (' . trim($object . ' - ' . $techProcess, ' -') . ')';
                }

                $resultList[$row['id']] = $formattedString;
            }
        }

        return $resultList;
    }
}
