<?php

use LossLog\LossLog_Interface;

class LossLog_CoefficientsTechInfo_Model extends Model_Extended implements LossLog_Interface
{
    use LossLog_Coefficients_Model_Trait;
    use Operations_Trait;

    // строго совпадает со значением справочника типов коэффициента
    public const int COEFFICIENT_TYPE = 2;

    protected string $table = 'losslog_coefficients_tech_info';

    public array $fields = [
        'id' => ['type' => 'primarykey'],

        'object_id' => [
            'type'    => 'int',
            'link'    => 'LossLog_Dictionaries_Objects_Model',
            'comment' => 'Установка',
        ],

        'responsible' => [
            'type'    => 'string',
            'link'    => 'User_WithPosition',
            'comment' => 'Ответственный за предоставление технологической информации',
        ],
        'date_start' => ['type' => 'date'],
        'date_end'   => ['type' => 'date'],

        'ctime'      => ['type' => 'int'],
        'mtime'      => ['type' => 'int'],
        'created_id' => ['type' => 'int'],
        'user_id'    => [
            'type'    => 'int',
            'link'    => 'User',
            'comment' => 'Последнее изменение сделал',
        ],
    ];

    public const ERROR_EMPTY_OBJECT = 1;
    public const ERROR_EMPTY_RESPONSIBLE = 13;
    public const ERROR_EMPTY_DATE_START = 14;
    public const ERROR_EMPTY_DATE_END = 15;
    public const ERROR_INVALID_DATE_RANGE = 16;
    public const ERROR_DATE_OVERLAP = 17;
    public const SUCCESS_VALID = 0;
    public const ERROR_NOT_FOUND = 404;

    /**
     * Возвращает текст ошибки.
     *
     * @param int $errorId
     *
     * @return string
     */
    public static function getError(int $errorId): string
    {
        return self::getErrors()[$errorId] ?? 'Ошибка не найдена';
    }

    /**
     * Метод возвращает мап ошибок модели.
     *
     * @return array<int, string>
     */
    public static function getErrors(): array
    {
        return [
            self::ERROR_EMPTY_OBJECT      => 'Значение поля Объект не установлено',
            self::ERROR_EMPTY_RESPONSIBLE => 'Значение поля
            Ответственный за предоставление технологической информации не установлено',
            self::ERROR_EMPTY_DATE_START   => 'Значение поля Дата начала действия не установлено',
            self::ERROR_EMPTY_DATE_END     => 'Значение поля Дата окончания действия не установлено',
            self::ERROR_INVALID_DATE_RANGE => 'Дата окончания должна быть больше Даты начала действия ',
            self::ERROR_DATE_OVERLAP       => 'Значения периода пересекаются с другой записью',
            self::ERROR_NOT_FOUND          => 'Ошибка не найдена',
        ];
    }

    /**
     * Получает и встраивает коэффициенты в переданный список данных.
     *
     * @param array<int, array<string, mixed>> $list список данных, содержащий идентификаторы записей
     *
     * @return array<int, array<string, mixed>> обновленный список данных с добавленными коэффициентами
     *
     * @throws RuntimeException если у переданной модели отсутствует метод getCoefficientsByRowIds()
     */
    public function getCustomData(array $list): array
    {
        $rowIds = array_column($list, 'id');

        // Получаем все rowId из $list
        if (!empty($rowIds)) {
            $coefficientsByRow = $this->getCoefficientsByRowIds($rowIds, $this);

            // Встраиваем коэффициенты в $list
            foreach ($list as &$item) {
                $item['coefficients'] = $coefficientsByRow[$item['id']] ?? [];
            }
        }

        return $list;
    }

    /**
     * Получает данные с применением фильтра по дате.
     *
     * @param array<string, mixed> $filter   Массив фильтров
     * @param string               $ordering Параметры сортировки
     * @param array<string, mixed> $request  Дополнительные параметры запроса
     *
     * @return array<string, array<string, mixed>> Отфильтрованные данные
     */
    #[Override]
    public function getData(array $filter, string $ordering, $request = []): array
    {
        return $this->getFilteredByDateData($filter, $ordering, $request);
    }
}
