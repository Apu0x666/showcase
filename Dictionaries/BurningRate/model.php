<?php

use LossLog_Dictionaries_Model as BaseDictionaryModel;

class LossLog_Dictionaries_BurningRate_Model extends BaseDictionaryModel
{
    use Operations_Trait;

    protected string $table = 'losslog_burning_rate_plan';
    public const ENTITY_NAME = 'BurningRate';

    public const ERROR_INVALID_DATE_RANGE = 9;
    public const ERROR_DATE_OVERLAP = 10;
    public const SUCCESS_VALID = 0;

    public array $fields = [
        'id'        => ['type' => 'primarykey'],
        'object_id' => [
            'type'    => 'int',
            'link'    => 'LossLog_Dictionaries_Objects_Model',
            'comment' => 'Установка в которой будет проводиться планирование',
        ],
        'date_start' => ['type' => 'date'],
        'date_end'   => ['type' => 'date'],
        'value'      => ['type' => 'float'],
        'ctime'      => ['type' => 'int'],
        'mtime'      => ['type' => 'int'],
        'created_id' => ['type' => 'int'],
        'user_id'    => ['type' => 'int'],
    ];
    public array $editableFields = ['id', 'object_id', 'date_start', 'date_end', 'value'];

    #[Override]
    public function getListForLink(
        array $filter = [],
        bool $return_records = false,
        string $order = '',
        string $key_field = 'object_id',
        string $id_field = 'id'
    ): array {
        $list = $this->getSimpleList($filter, 'id', 0, 0, 'id, object_id, date_start, date_end, value');
        $linkedList = $this->getLinkedList($list);
        $resultList = [];

        foreach ($linkedList as $row) {
            if ($return_records) {
                $resultList[$row['id']] = $row;
            } else {
                $resultList[$row['id']] = sprintf(
                    '%s (%s - %s) %s',
                    $this->formatValue($row['object_id']),
                    $this->formatDate($row['date_start']),
                    $this->formatDate($row['date_end']),
                    $row['value']
                );
            }
        }

        return $resultList;
    }

    /**
     * Форматирует значение, заменяя '-' на пустую строку.
     *
     * @param int|string $value
     *
     * @return string
     */
    private function formatValue(int | string $value): string
    {
        return $value !== '-' ? (string)$value : '';
    }

    /**
     * Форматирует дату в формат d.m.Y.
     *
     * @param string $date
     *
     * @return string
     */
    private function formatDate(string $date): string
    {
        if ($date === '-') {
            return '';
        }

        return (new DateTimeImmutable($date))->format('d.m.Y');
    }

    /**
     * Валидирует пересечение дат для одной установки.
     *
     * @param array<string, mixed> $params    Данные для проверки
     * @param null|int             $excludeId ID записи, которую нужно исключить из проверки (для update)
     *
     * @throws RuntimeException Если найдено пересечение дат
     */
    private function validateOverlappingDates(array $params, ?int $excludeId = null): void
    {
        $result = $this->validateDatesAndOverlap($params, $excludeId);

        if ($result > self::SUCCESS_VALID) {
            throw new RuntimeException('Ошибка. Найдено пересечение дат для данной установки');
        }
    }

    #[Override]
    public function addData(array $data, string $additional = ''): int
    {
        $this->validateOverlappingDates($data, null);

        return parent::addData($data, $additional);
    }

    #[Override]
    public function updateByFilter(array $data, array $filter, bool $updateNulls = true): bool
    {
        $this->validateOverlappingDates($data, (int)$filter['id']);

        return parent::updateByFilter($data, $filter, $updateNulls);
    }
}
