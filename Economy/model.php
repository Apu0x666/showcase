<?php

use LossLog\LossLog_Interface;

class LossLog_Economy_Model extends Model_Extended implements LossLog_Interface
{
    use Operations_Trait;

    protected string $table = 'losslog_economy';

    public array $fields = [
        'id'               => ['type' => 'primarykey'],
        'licensed_area_id' => [
            'type'    => 'int',
            'link'    => 'LossLog_Dictionaries_LicensedAreas_Model',
            'comment' => 'Лицензионный участок',
        ],
        'responsible' => [
            'type'    => 'int',
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
            'link'    => 'User_WithPosition',
            'comment' => 'Последнее изменение сделал',
        ],
    ];

    public const ERROR_EMPTY_LU = 1;
    public const ERROR_EMPTY_RESPONSIBLE = 6;
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
            self::ERROR_EMPTY_LU          => 'Значение поля Наименование Компании/участка не установлено',
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
            'licensed_area_id' => self::ERROR_EMPTY_LU,
            'responsible'      => self::ERROR_EMPTY_RESPONSIBLE,
            'date_start'       => self::ERROR_EMPTY_DATE_START,
            'date_end'         => self::ERROR_EMPTY_DATE_END,
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
     * @param array<string, mixed> $filter   Массив фильтров
     * @param string               $ordering Порядок сортировки
     * @param array<string, mixed> $request
     *
     * @return array<string, array<string, mixed>> Возвращает массив данных
     */
    public function getData(array $filter, string $ordering, $request = []): array
    {
        $fromDateFormated = $request['filters']['date'][0] ?? null;
        $toDateFormated = $request['filters']['date'][1] ?? null;

        if (
            $fromDateFormated
            && $toDateFormated
            && $this->areValidDateFormats($fromDateFormated, $toDateFormated)
        ) {
            $dateFilter = $this->createDateRangeFilter($fromDateFormated, $toDateFormated);
            $filter['date_start'] = ['OR', $dateFilter];
        }

        $list = $this->getIndexedSimpleList($filter, 'id', $ordering);
        $linkedList = $this->getLinkedList($list, true);

        // Получаем все rowId из $list
        $rowIds = array_column($list, 'id');

        if (!empty($rowIds)) {
            $coefficientsByRow = $this->getProductsByRowIds($rowIds);

            // Встраиваем коэффициенты в $list
            foreach ($list as &$item) {
                $item['products'] = $coefficientsByRow[$item['id']] ?? [];
            }
        }

        // Преобразование даты и ключей в linkedList
        $linkedList = $this->processLinkedList($linkedList);

        return compact('list', 'linkedList');
    }

    /**
     * Преобразует данные linkedList: форматирует даты и ключи.
     *
     * @param array<string, mixed>[] $linkedList Список данных
     *
     * @return array<string, mixed> Преобразованный список
     */
    private function processLinkedList(array $linkedList): array
    {
        return array_map(function ($item) {
            $item = $this->formatDates($item, ['date', 'date_start', 'date_end']);

            return LossLog_Data_Helper::convertArrayKeysToCamelCase($item);
        }, $linkedList);
    }

    /**
     * Форматирует даты в массиве.
     *
     * @param array<string, mixed> $item Массив данных
     * @param string[]             $keys Ключи для форматирования дат
     *
     * @return array<string, mixed> Массив с отформатированными датами
     */
    private function formatDates(array $item, array $keys): array
    {
        foreach ($keys as $key) {
            if (!empty($item[$key])) {
                $item[$key] = date('d.m.Y', strtotime($item[$key]));
            }
        }

        return $item;
    }

    /**
     * @param array<int> $rowIds Массив идентификаторов записей
     *
     * @return array<string, mixed>
     */
    public function getProductsByRowIds(array $rowIds): array
    {
        if (!empty($rowIds)) {
            $placeholders = implode(',', array_fill(0, count($rowIds), '?'));
            $query = "
            SELECT economy_row_id, product_id, product_value
            FROM losslog_economy_values
            WHERE economy_row_id IN ({$placeholders})";

            $result = $this->current_db->fetchAll($query, $rowIds);

            $productsByRow = [];

            foreach ($result as $row) {
                $productsByRow[$row['economy_row_id']][$row['product_id']] = $row['product_value'];
            }

            return $productsByRow;
        }

        return [];
    }

    /**
     * @param string $date Дата в формате 'Y-m-d'
     * @param int    $luId Идентификатор ЛУ
     *
     * @return array<mixed>
     */
    public function getEconomyData(string $date, int $luId): array
    {
        $filter = [
            'date_start'       => ['<=', $date],
            'date_end'         => ['>=', $date],
            'licensed_area_id' => $luId,
        ];

        $economyModel = new self();
        $economyDataId = $economyModel->getOneField($filter, 'id');

        $products = $this->getProductsByRowIds([$economyDataId]);
        // Получаем вложенный массив
        $nestedArray = reset($products);

        $productsModel = new LossLog_Dictionaries_Products_Model();
        $rawProducts = $productsModel->getList(
            ['id' => ['IN', array_keys($nestedArray)]],
            '',
            0,
            0,
            'id, formula_abbreviate'
        );

        // Преобразуем массив $rawProducts в мапу id -> formula_abbreviate
        $formulaMap = array_column($rawProducts, 'formula_abbreviate', 'id');

        // Заменяем ключи в $nestedArray на значения из $formulaMap и значения в float
        $result = array_combine(
            array_map(static fn ($id) => $formulaMap[$id] ?? $id, array_keys($nestedArray)),
            array_map('floatval', $nestedArray)
        );

        return LossLog_Data_Helper::convertArrayKeysToCamelCase($result);
    }

    /**
     * Метод "подмешивает" значения экономики в основную запись
     * при редактировании/создании.
     *
     * @param array<string, mixed>     $item  Массив данных, который обновляется
     * @param Model_Extended_Interface $model Модель, с которой взаимодействуем
     *
     * @return array<string, mixed> Возвращаем обновленный массив с коэффициентами
     */
    public function productsAdd(array $item, Model_Extended_Interface $model): array
    {
        // Проверка на наличие метода получения коэффициентов для строк
        if (method_exists($model, 'getProductsByRowIds')) {
            /** @var LossLog_Economy_Model $model */
            $res = $model->getProductsByRowIds([$item['id']]);

            // Переносим коэффициенты в $item
            foreach ($res as $row) {
                foreach ($row as $productId => $value) {
                    $item['products'][$productId] = $value;
                }
            }
        }

        $productsModel = new LossLog_Dictionaries_Products_Model();
        // Получаем список продуктов
        $productsList = $productsModel->getList(['is_enabled' => 1]);

        $item['products'] = array_reduce($productsList, static function ($acc, $product) use ($item) {
            $id = $product['id'];

            // Обрабатываем каждый коэффициент и добавляем его в итоговый массив
            $acc[$id] = [
                'title' => $product['product_name'],
                'value' => $item['products'][$id] ?? '0.00000', // Если коэффициент не найден, ставим 0
            ];

            return $acc;
        }, []);

        return $item;
    }

    /**
     * Обновляет значения экономики в таблице losslog_economy_values.
     *
     * @param array<string, mixed> $item Данные для чтения, исходный массив не модифицируем
     *
     * @throws Exception
     */
    public function updateEconomyValues(array $item): void
    {
        // Получаем ID основной записи
        $economyRowId = $item['id'] ?? null;

        if (!$economyRowId) {
            throw new Exception('Не указан ID основной записи');
        }

        // Получаем список коэффициентов
        $products = $item['products'] ?? [];

        if (!is_array($products)) {
            throw new Exception('Коэффициенты для записи не указаны');
        }

        // Обрабатываем каждый коэффициент
        foreach ($products as $productId => $productData) {
            $value = $productData['value'] ?? '0.00000';

            // Проверяем, существует ли запись для данного coefficient_id, source_id и type
            $query = '
            SELECT id
            FROM losslog_economy_values
            WHERE economy_row_id = :economyRowId
                AND product_id = :productId
            ';

            $params = [
                ':economyRowId' => $economyRowId,
                ':productId'    => $productId,
            ];

            $existingRecord = $this->getCurrentDb()->fetch($query, $params);

            if (!empty($existingRecord)) {
                // Обновляем существующую запись
                $this->getCurrentDb()->update(
                    'losslog_economy_values',
                    ' AND id = ' . $existingRecord['id'],
                    ['product_value' => $value]
                );
            } else {
                // Создаем новую запись
                $this->getCurrentDb()->insert(
                    'losslog_economy_values',
                    [
                        'economy_row_id' => $economyRowId,
                        'product_id'     => $productId,
                        'product_value'  => $value,
                    ]
                );
            }
        }
    }
}
