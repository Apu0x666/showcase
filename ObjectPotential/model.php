<?php

use LossLog\LossLog_Interface;

/**
 * Class LossLog_ObjectPotential_Model.
 */
class LossLog_ObjectPotential_Model extends Model_Extended implements LossLog_Interface
{
    use Operations_Trait;

    protected string $table = 'losslog_object_potential';

    public array $fields = [
        'id'        => ['type' => 'primarykey'],
        'object_id' => [
            'type'    => 'int',
            'link'    => 'LossLog_Dictionaries_Objects_Model',
            'comment' => 'Установка',
        ],
        'performance' => ['type' => 'int'],
        'mdp'         => ['type' => 'int'],
        'users'       => [
            'type'     => 'string',
            'link'     => 'User_WithPosition',
            'multiple' => 1,
            'comment'  => 'Ответственный за учет потерь эксплуатационной дирекции/департамента',
        ],
        'date_start' => ['type' => 'date'],
        'date_end'   => ['type' => 'date'],

        'ctime'      => ['type' => 'int'],
        'mtime'      => ['type' => 'int'],
        'created_id' => ['type' => 'int'],
        'user_id'    => [
            'type'    => 'int',
            'link'    => 'User_WithPosition',
            'comment' => 'Последнее изменение сделал',
        ],
    ];

    public const ERROR_EMPTY_OBJECT = 2;
    public const ERROR_EMPTY_PERFORMANCE = 4;
    public const ERROR_EMPTY_MDP = 5;
    public const ERROR_EMPTY_USERS = 6;
    public const ERROR_EMPTY_DATE_START = 7;
    public const ERROR_EMPTY_DATE_END = 8;
    public const ERROR_INVALID_DATE_RANGE = 9;
    public const ERROR_DATE_OVERLAP = 10;
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
            self::ERROR_EMPTY_OBJECT       => 'Значение поля Объект не установлено',
            self::ERROR_EMPTY_PERFORMANCE  => 'Значение поля Производительность не установлено',
            self::ERROR_EMPTY_MDP          => 'Значение поля МДП не установлено',
            self::ERROR_EMPTY_USERS        => 'Значение поля Ответственный за учет потерь не установлено',
            self::ERROR_EMPTY_DATE_START   => 'Значение поля Дата от не установлено',
            self::ERROR_EMPTY_DATE_END     => 'Значение поля Дата до не установлено',
            self::ERROR_INVALID_DATE_RANGE => 'Дата окончания должна быть больше Даты начала действия ',
            self::ERROR_DATE_OVERLAP       => 'Значения периода пересекаются с другой записью',
            self::ERROR_NOT_FOUND          => 'Ошибка не найдена',
        ];
    }

    /**
     * Метод проверяет модель на валидность.
     *
     * @param array<string, mixed> $params
     *
     * @return int
     *
     * @throws Exception
     */
    public function validateData(array $params): int
    {
        $params = LossLog_Data_Helper::convertArrayKeysToSnakeCase($params);

        $requiredFields = [
            'object_id'   => self::ERROR_EMPTY_OBJECT,
            'performance' => self::ERROR_EMPTY_PERFORMANCE,
            'mdp'         => self::ERROR_EMPTY_MDP,
            'users'       => self::ERROR_EMPTY_USERS,
            'date_start'  => self::ERROR_EMPTY_DATE_START,
            'date_end'    => self::ERROR_EMPTY_DATE_END,
        ];

        $required = LossLog_Data_Helper::checkRequiredFields($requiredFields, $params);

        if ($required) {
            return $required;
        }

        // Получаем id, если он есть в параметрах
        $id = isset($params['id']) ?
            (int)$params['id'] :
            null;

        // Используем трейт для проверки дат и пересечения периодов
        return $this->validateDatesAndOverlap($params, $id);
    }

    /**
     * Получает значение Производительности + МДП по трём параметрам (цех/объект/производственный процесс).
     *
     * @param null|int $objectId название объекта (установки)
     *
     * @return array<string, mixed>
     */
    public function getPerformanceAndMdp(
        ?int $objectId = null,
    ): array {
        if (!isset($objectId)) {
            return [
                'performance' => '',
                'mdp'         => '',
            ];
        }

        $params = [
            ':object' => $objectId,
        ];

        $query = "
        SELECT performance, mdp
        FROM {$this->table}
        WHERE object_id = :object";

        $result = $this->current_db->fetch($query, $params);

        return [
            'performance' => isset($result['performance']) ?
                (int)$result['performance'] :
                '',
            'mdp' => isset($result['mdp']) ?
                (int)$result['mdp'] :
                '',
        ];
    }

    /**
     * @param int $objectId Идентификатор объекта
     *
     * @return array<string, mixed>
     */
    public function getObjectPotential(int $objectId): array
    {
        $filter = [
            'object_id' => $objectId,
        ];

        $objectPotentialModel = new self();
        $objectPotentialData = $objectPotentialModel->getList(
            $filter,
            '',
            0,
            0,
            'object_id,performance,mdp,date_start,date_end'
        );

        return LossLog_Data_Helper::convertArrayKeysToCamelCase($objectPotentialData);
    }

    /**
     * Фильтрует потенциал объекта по указанной дате.
     *
     * Массив потенциалов объекта с указанием диапазонов дат
     *
     * @param array<array{dateStart: string, dateEnd: string}> $data
     *
     * Целевая дата для фильтрации в формате YYYY-MM-DD
     * @param string $date
     *
     * ассоциативный массив, соответствующий переданной дате, или null, если диапазон не найден
     *
     * @return null|array<string, mixed>
     */
    public function filterObjectPotentialByDate(array $data, string $date): ?array
    {
        foreach ($data as $item) {
            if (
                strtotime($item['dateStart']) <= strtotime($date)
                && strtotime($date) <= strtotime($item['dateEnd'])
            ) {
                return $item;
            }
        }

        return null;
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
