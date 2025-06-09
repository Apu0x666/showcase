<?php

use LossLog\LossLog_Interface;
use Monolog\Level;

/**
 * Class LossLog_GatewayObject_Model.
 */
class LossLog_GatewayObject_Model extends Model_Extended implements LossLog_Interface
{
    protected string $table = 'losslog_main_data';

    public array $fields = [
        'id'     => ['type' => 'primarykey'],
        'date'   => ['type' => 'date'], // Дата
        'object' => [
            'type'    => 'int',
            'link'    => 'LossLog_Dictionaries_Objects_Model',
            'comment' => 'Объекты',
        ],
        'plan'       => ['type' => 'decimal'],
        'fact'       => ['type' => 'decimal'],
        'ctime'      => ['type' => 'int'],
        'mtime'      => ['type' => 'int'],
        'created_id' => ['type' => 'int'],
        'user_id'    => ['type' => 'int', 'link' => 'User'],
    ];

    public const int ERROR_EMPTY_DATE = 1;
    public const int ERROR_DUPLICATE_ENTRY = 2;
    public const int ERROR_EMPTY_OBJECT = 4;
    public const int ERROR_EMPTY_PLAN = 6;
    public const int SUCCESS_VALID = 0;

    // Массив обязательных полей и соответствующих им кодов и сообщений об ошибках
    private const array REQUIRED_FIELDS = [
        'date' => [
            'code'    => self::ERROR_EMPTY_DATE,
            'message' => 'Значение поля Дата не установлено',
        ],
        'date_object' => [
            'code'    => self::ERROR_DUPLICATE_ENTRY,
            'message' => 'Запись с таким сочетанием даты и объекта уже существует',
        ],
        'object' => [
            'code'    => self::ERROR_EMPTY_OBJECT,
            'message' => 'Значение поля Объект не установлено',
        ],
        'plan' => [
            'code'    => self::ERROR_EMPTY_PLAN,
            'message' => 'Значение поля План не установлено',
        ],
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Получает данные с учетом фильтрации и сортировки.
     *
     * @param array<string, mixed> $filter
     * @param string               $ordering
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed> возвращает массив данных
     */
    public function getData(array $filter, string $ordering, $request = []): array
    {
        $filter['object'] = (int)$request['initial']['object'];

        $list = $this->getIndexedSimpleList($filter, 'id', $ordering);
        $linkedList = $this->getLinkedList($list, true);

        $modelDecompositionPlan = new LossLog_DecompositionPlan_Model();
        $modelDecompositionFact = new LossLog_DecompositionFact_Model();
        $itemIdMap = array_column($list, 'id');

        $decompositionPlanItems = !empty($itemIdMap) ?
            $this->getListByModel($modelDecompositionPlan, $itemIdMap) :
            [];
        $decompositionFactItems = !empty($itemIdMap) ?
            $this->getListByModel($modelDecompositionFact, $itemIdMap) :
            [];

        foreach ($linkedList as $key => $item) {
            $linkedList[$key]['decompositionPlanItems'] = $decompositionPlanItems[$item['id']] ?? [];
            $linkedList[$key]['decompositionFactItems'] = $decompositionFactItems[$item['id']] ?? [];

            $dateTime = DateTime::createFromFormat('Y-m-d', $item['date']);
            $linkedList[$key]['date'] = $dateTime ? $dateTime->format('d.m.Y') : $item['date'];
        }

        $linkedList = LossLog_Data_Helper::convertArrayKeysToCamelCase($linkedList);

        return [
            'list'       => array_values($list),
            'linkedList' => array_values($linkedList),
        ];
    }

    /**
     * Возвращает массив ошибок модели.
     *
     * @return array<int, string>
     */
    public static function getErrors(): array
    {
        $errors = [];

        foreach (self::REQUIRED_FIELDS as $error) {
            $errors[$error['code']] = $error['message'];
        }

        return $errors;
    }

    /**
     * Возвращает текст ошибки по её идентификатору.
     *
     * @param int $errorId идентификатор ошибки
     *
     * @return string
     */
    public static function getError(int $errorId): string
    {
        return self::getErrors()[$errorId] ?? 'Ошибка не найдена';
    }

    /**
     * Проверяет параметры модели на валидность.
     *
     * @param array<string, mixed> $params массив параметров для проверки
     *
     * @return int
     */
    public function validateData(array $params): int
    {
        $params = LossLog_Data_Helper::convertArrayKeysToSnakeCase($params);

        foreach (self::REQUIRED_FIELDS as $field => $error) {
            if ($field == 'date' && empty($params['id'])) {
                // Проверка на дублирование по уникальному ключу date + object
                // в случае создания новой записи, редактирование скипаем
                $existingRecord = $this->checkDuplicateRecord($params['date'], $params['object']);

                if ($existingRecord) {
                    return self::REQUIRED_FIELDS['date_object']['code'];
                }
            }

            if ($field == 'date_object') {
                continue;
            }

            if (!array_key_exists($field, $params) || $params[$field] === null || $params[$field] === '') {
                return $error['code'];
            }
        }

        return self::SUCCESS_VALID;
    }

    /**
     * Проверка на наличие записи с таким сочетанием date и object.
     *
     * @param string $date   Дата
     * @param int    $object Объект
     *
     * @return bool
     */
    private function checkDuplicateRecord(string $date, int $object): bool
    {
        $query = '
        SELECT id 
        FROM losslog_main_data 
        WHERE date = :date 
        AND object = :object
    ';

        $params = [
            ':date'   => $date,
            ':object' => $object,
        ];

        $existingRecord = $this->getPdo()->fetch($query, $params);

        return !empty($existingRecord);
    }

    /**
     * Возвращает список записей по связанному идентификатору.
     *
     * @param Model_Extended $model экземпляр модели
     * @param array<int>     $ids   массив идентификаторов
     *
     * @return array<string, mixed>
     */
    public function getListByModel(Model_Extended $model, array $ids = []): array
    {
        $filter = ['losslog_main_data_id' => ['IN', $ids]];
        $itemList = $model->getSimpleList($filter);
        $linkedList = $model->getLinkedList($itemList, true);

        return make_hash($linkedList, 'losslog_main_data_id', true);
    }

    /**
     * Получает список технологических процессов для определенного цеха и объекта.
     *
     * @param array<string,mixed> $request
     *
     * @return array<string, mixed>
     */
    public function getDefaultData(array $request): array
    {
        $request = LossLog_Data_Helper::convertArrayKeysToSnakeCase($request);
        $objectsModel = new LossLog_Dictionaries_Objects_Model();
        $object = $objectsModel->getItem($request['object_id']);

        $workshopModel = new LossLog_Dictionaries_Workshops_Model();
        $workshop = $workshopModel->getItem($object['workshop']);

        $techProcessModel = new LossLog_Dictionaries_TechProcess_Model();
        $unit = $techProcessModel->getItem($object['tech_process']);

        $gateWayModel = new LossLog_SetupGateway_Model();
        $fsPage = $gateWayModel->getItemByFilter(
            ['object_id' => $object['id']],
            '',
            'functional_page'
        );

        return [
            'workshop'       => (int)$object['workshop'],
            'lu'             => (int)$workshop['licensed_areas'],
            'object'         => (int)$request['object_id'],
            'techProcess'    => (int)$object['tech_process'],
            'unit'           => (string)$unit['unit'],
            'functionalPage' => (bool)$fsPage['functional_page'],
        ];
    }

    /**
     * Добавляет новую запись в модель с учетом настроек таба,
     * а также создает декомпозии, если в настройках включены опции.
     *
     * {@inheritdoc}
     */
    public function addData(array $data, string $additional = ''): int
    {
        $id = 0;

        $gatewayObjectDataHelper = new LossLog_GatewayObject_Data_Helper();
        $objectId = (int)($data['object'] ?? 0);
        $isActiveAccounting = $gatewayObjectDataHelper
            ->loadGatewaySettingsByObjectId($objectId)
            ->isActiveAccounting();

        $this->startTransaction();

        try {
            $id = parent::addData($data);

            if ($isActiveAccounting && $id) {
                $gatewayObjectDataHelper->createDecompositions($id);
            }
        } catch (Exception $e) {
            $this->rollbackTransaction();
            elkLog('Ошибка при авто-создании декомпозиции: ' . $e->getMessage(), Level::Warning);

            return $id;
        }

        $this->commitTransaction();

        return $id;
    }
}
