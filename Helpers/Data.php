<?php

use LossLog\LossLog_Interface;

class LossLog_Data_Helper
{
    /**
     * convertArrayKeysToCamelCase
     * Рекурсивно переводит все ключи массива из snake_case в camelCase.
     *
     * @param array<mixed> $array
     *
     * @return array<mixed>
     */
    public static function convertArrayKeysToCamelCase(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = snakeToCamel($key);

            if (is_array($value)) {
                $value = self::convertArrayKeysToCamelCase($value);
            }

            $result[$newKey] = $value;
        }

        return $result;
    }

    /**
     * convertArrayKeysToSnakeCase
     * Рекурсивно переводит все ключи массива из camelCase в snake_case.
     *
     * @param array<string, mixed> $array массив, ключи которого требуется преобразовать
     *
     * @return array<string, mixed>
     */
    public static function convertArrayKeysToSnakeCase(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = camelToSnake($key);

            if (is_array($value)) {
                $value = self::convertArrayKeysToSnakeCase($value);
            }

            $result[$newKey] = $value;
        }

        return $result;
    }

    /**
     * Проверяет обязательные поля.
     *
     * @param array<string, int>   $requiredFields
     * @param array<string, mixed> $params
     *
     * @return false|int
     */
    public static function checkRequiredFields(array $requiredFields, array $params): bool | int
    {
        foreach ($requiredFields as $field => $errorCode) {
            // Проверяем наличие поля и его значение
            if (!array_key_exists($field, $params) || $params[$field] === '' || $params[$field] === null) {
                return $errorCode;
            }
        }

        return false;
    }

    /**
     * Получение доступных объектов для селекта.
     *
     * @param array<int, string> $ids
     *
     * @return array<string, mixed>
     */
    public static function getObjects(array $ids = []): array
    {
        $object = new LossLog_Dictionaries_Objects_Model();
        $objectsList = $object->getSimpleList(
            empty($ids) ?
                [] :
                ['id' => ['IN', $ids]]
        );
        $objectsLinkedList = $object->getLinkedList($objectsList, true);

        $result = [];

        foreach ($objectsLinkedList as $item) {
            $workshop = $item['workshop']['workshop'];
            $object = $item['object'] !== '-' ?
                $item['object'] :
                '';
            $techProcess = $item['tech_process']['tech_process_name'] !== '-' ?
                $item['tech_process']['tech_process_name'] :
                '';

            // Формируем строку в зависимости от наличия значений
            $formattedString = $workshop;

            if ($object || $techProcess) {
                $formattedString .= ' (' . trim($object . ' - ' . $techProcess, ' -') . ')';
            }

            $result[] = [
                'text'  => $formattedString,
                'value' => $item['id'],
            ];
        }

        return self::convertArrayKeysToCamelCase($result);
    }

    /**
     * @param int $documentId Входящий id документа
     *
     * @return int
     */
    public function getRootCauseByAtr(int $documentId): int
    {
        if (empty($documentId)) {
            return 0;
        }

        $atrModel = new LossLog_ActsTechnicalRepair_Model();
        $atrItem = $atrModel->getItemByFilter(
            ['id' => $documentId],
            '',
            'id, root_cause_guid, edo_status, deny_status'
        );

        if (empty($atrItem['id'])) {
            return 0;
        }

        if (
            mb_strtolower($atrItem['edo_status']) == 'исполнено'
            || mb_strtolower($atrItem['deny_status']) == 'согласован'
        ) {
            $dictionaryRootCauseModel = new LossLog_Dictionaries_RootCause_Model();
            $rootCauseItem = $dictionaryRootCauseModel->getItemByFilter(
                ['guid' => $atrItem['root_cause_guid']],
                '',
                'id'
            );
            $rootCauseId = $rootCauseItem['id'] ?? null;

            if (!empty($rootCauseId)) {
                return $rootCauseId;
            }

            return 0;
        }

        // Установка значения по умолчанию
        return LossLog_ActsTechnicalRepair_Model::DEFAULT_ROOT_CAUSE;
    }

    /**
     * @param array<string, mixed> $object
     * @param null|string          $date   Если передана, используется указанная дата, иначе текущая
     *
     * @return array<string, mixed>
     */
    public function getCoefficientsAndEconomyData(array $object, ?string $date = null): array
    {
        $currentDate = $date ?? date('Y-m-d'); // Используем переданную дату или текущую

        // Создаем экземпляры моделей
        $techModel = new LossLog_CoefficientsTechInfo_Model();
        $geoModel = new LossLog_CoefficientsGeoInfo_Model();

        // Получение технических коэффициентов
        $techCoefficients = $techModel->getCoefficientsByObjectId($currentDate, $object['id'], $techModel);

        // Получение геологических коэффициентов
        $geoCoefficients = $geoModel->getCoefficientsByObjectId($currentDate, $object['id'], $geoModel);

        // Получение данных для экономики
        $workshopModel = new LossLog_Dictionaries_Workshops_Model();
        $workshopItem = $workshopModel->getLuId($object['workshop']);

        $economyModel = new LossLog_Economy_Model();
        $economyData = $economyModel->getEconomyData($currentDate, $workshopItem);

        // Возвращаемый массив данных
        return [
            'tech'    => $techCoefficients,
            'geo'     => $geoCoefficients,
            'economy' => $economyData,
        ];
    }

    public static function convertToDate(string $inputDate): ?string
    {
        // Преобразовать строку в метку времени (timestamp)
        $timestamp = strtotime($inputDate);

        // Если timestamp успешно получен, форматируем в 'YYYY-MM-DD'
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        // Если не удалось распознать дату, возвращаем null или сообщение об ошибке
        return null;
    }

    /**
     * Проверка, можно ли удалить запись с учетом настроек таба.
     *
     * @param int               $decompositionId
     * @param LossLog_Interface $model
     *
     * @return bool
     */
    public function isRemoveAvailible(int $decompositionId, LossLog_Interface $model): bool
    {
        // Нет записи для проверки
        if (!$decompositionId) {
            return true;
        }

        // модель не декомпозиция план/факт
        if (!$this->isAccountingAcceptedModel($model)) {
            return true;
        }

        $decomposition = $model->getItem(
            $decompositionId,
            'id, losslog_main_data_id, is_own_needs_accounting, is_flaring_accounting'
        );

        $isItemOwnNeedsAccounting = (bool)$decomposition['is_own_needs_accounting'];
        $isItemFlaringAccounting = (bool)$decomposition['is_flaring_accounting'];

        // Запись не создана автоматически с атрибутами учета
        if (!$isItemOwnNeedsAccounting && !$isItemFlaringAccounting) {
            return true;
        }

        $gatewayObjectDataHelper = new LossLog_GatewayObject_Data_Helper();
        $settings = $gatewayObjectDataHelper->tabSettingsByMainDataId((int)$decomposition['losslog_main_data_id']);

        // Запись может быть создана одновременно только с одним из признаков, поэтому проверка по отдельности
        $countSameItems = 0;
        $shouldCountOwnNeeds = $isItemOwnNeedsAccounting && $settings['isOwnNeedsAccounting'];
        $shouldCountFlaring = $isItemFlaringAccounting && $settings['isFlaringAccounting'];

        if ($shouldCountOwnNeeds || $shouldCountFlaring) {
            $accountingKey = $shouldCountOwnNeeds ? 'is_own_needs_accounting' : 'is_flaring_accounting';
            $countSameItems = $this->countSameByMainDataId(
                (int)$decomposition['losslog_main_data_id'],
                $accountingKey,
                $model
            );
        }

        if ($countSameItems === 1) {
            return false;
        }

        return true;
    }

    /**
     * Подсчитывает количество записей с указанным ключом учета для переданного идентификатора главной записи.
     *
     * @param int               $id            Идентификатор главной записи
     * @param string            $accountingKey Ключ учета, который должен быть активен в записях
     * @param LossLog_Interface $model
     *
     * @return int
     */
    public function countSameByMainDataId(int $id, string $accountingKey, LossLog_Interface $model): int
    {
        if (
            !in_array($accountingKey, ['is_own_needs_accounting', 'is_flaring_accounting'], true)
            || !$this->isAccountingAcceptedModel($model)
        ) {
            return 0;
        }

        return $model->getCount(
            [
                'losslog_main_data_id' => $id,
                $accountingKey         => true,
            ]
        );
    }

    /**
     * Проверка, является ли переданная модель декомпозицией плана или факта.
     *
     * @param Model_Extended_Interface $model
     *
     * @return bool
     */
    public static function isAccountingAcceptedModel(Model_Extended_Interface $model): bool
    {
        return $model instanceof LossLog_DecompositionPlan_Model
            || $model instanceof LossLog_DecompositionFact_Model;
    }
}
