<?php

use LossLog\LossLog_Interface;
use Symfony\Component\HttpFoundation\Response;

class LossLog_GatewayObject_Data_Helper
{
    private const int INNER_SIDE_CAUSE = 1;
    private bool $isOwnNeedsAccounting = false;
    private bool $isFlaringAccounting = false;

    /**
     * Возвращает настройки для текущего объекта (страницы/таба).
     *
     * @return array<string, mixed>
     */
    public function gatewaySettings(): array
    {
        return [
            'isOwnNeedsAccounting' => $this->isOwnNeedsAccounting,
            'isFlaringAccounting'  => $this->isFlaringAccounting,
        ];
    }

    /**
     * Возвращает настройки вкладки для указанного id главной записи.
     *
     * @param int $lossLogMainDataId
     *
     * @return array<string, mixed>
     */
    public function tabSettingsByMainDataId(int $lossLogMainDataId): array
    {
        // Получаем главную запись, и берём нужные поля для расчётов
        $gatewayObjectModel = new LossLog_GatewayObject_Model();
        $objectId = (int)$gatewayObjectModel->getOneField(
            ['id' => $lossLogMainDataId],
            'object'
        );

        $this->loadGatewaySettingsByObjectId($objectId);

        return $this->gatewaySettings();
    }

    /**
     * Загружает настройки для объекта.
     *
     * @param int $objectId
     *
     * @return self
     */
    public function loadGatewaySettingsByObjectId(int $objectId): self
    {
        $setupGatewayModel = new LossLog_SetupGateway_Model();
        $fsPage = $setupGatewayModel->getItemByFilter(
            ['object_id' => $objectId],
            '',
            'own_needs_accounting, flaring_accounting'
        );

        if (isset($fsPage['own_needs_accounting'], $fsPage['flaring_accounting'])) {
            $this->isOwnNeedsAccounting = (bool)$fsPage['own_needs_accounting'];
            $this->isFlaringAccounting = (bool)$fsPage['flaring_accounting'];
        }

        return $this;
    }

    /**
     * Включен ли особый учет на объекте в настройках.
     *
     * @return bool
     */
    public function isActiveAccounting(): bool
    {
        return $this->isOwnNeedsAccounting || $this->isFlaringAccounting;
    }

    /**
     * Включен ли особый учет на объекте в настройках на основе переданного.
     * Так же учитывает, является ли декомпозиция последствием, для пропуска учёта.
     *
     * @param array<string, mixed> $decompositionItem
     *
     * @return bool
     */
    public static function checkIsAccountingByItem(array $decompositionItem): bool
    {
        $isOwnNeedsAccounting = $decompositionItem['isOwnNeedsAccounting'] ?? false;
        $isFlaringAccounting = $decompositionItem['isFlaringAccounting'] ?? false;
        $isConsequencesAccounting = $decompositionItem['isConsequencesAccounting'] ?? false;

        return $isOwnNeedsAccounting || $isFlaringAccounting || $isConsequencesAccounting;
    }

    /**
     * Создает декомпозиции плана и факта на основе активных типов учета.
     * Для каждого активного типа учета устанавливаются соответствующие причины и флаги.
     *
     * @param int $mainDataId идентификатор основных данных, для которых создаются декомпозиции
     *
     * @throws Exception
     */
    public function createDecompositions(int $mainDataId): void
    {
        $decompositionFactModel = new LossLog_DecompositionFact_Model();
        $decompositionPlanModel = new LossLog_DecompositionPlan_Model();

        $basePlanData = $this->prepareBaseDecompositionData($decompositionPlanModel, $mainDataId);
        $baseFactData = $this->prepareBaseDecompositionData($decompositionFactModel, $mainDataId);

        $planItems = $this->generateDecompositionItems($basePlanData);
        $factItems = $this->generateDecompositionItems($baseFactData);

        $this->saveDecompositionItems($decompositionPlanModel, $planItems);
        $this->saveDecompositionItems($decompositionFactModel, $factItems);
    }

    /**
     * Подготавливает базовые данные для декомпозиции.
     *
     * @param LossLog_Interface $model      Модель декомпозиции
     * @param int               $mainDataId Идентификатор основных данных
     *
     * @return array<string, mixed>
     */
    private function prepareBaseDecompositionData(LossLog_Interface $model, int $mainDataId): array
    {
        $data = $this->clearServiceFields($model->getEmptyItem());
        $data['losslog_main_data_id'] = $mainDataId;

        return $data;
    }

    /**
     * Генерирует элементы декомпозиции на основе базовых данных.
     *
     * @param array<string, mixed> $baseData Базовые данные декомпозиции
     *
     * @return list<array<string, mixed>>
     */
    private function generateDecompositionItems(array $baseData): array
    {
        $items = [$baseData]; // Основная декомпозиция, без доп. признаков
        $accountingTypes = [
            'is_own_needs_accounting' => $this->isOwnNeedsAccounting,
            'is_flaring_accounting'   => $this->isFlaringAccounting,
        ];

        $immediateCauseMap = [
            'is_own_needs_accounting' => LossLog_Dictionaries_ImmediateCause_Model::OWN_NEEDS_ACCOUNTING_ID,
            'is_flaring_accounting'   => LossLog_Dictionaries_ImmediateCause_Model::FLARING_ACCOUNTING_ID,
        ];

        foreach ($accountingTypes as $field => $isActive) {
            if (!$isActive) {
                continue;
            }

            $item = $baseData;
            $item['immediate_cause'] = $immediateCauseMap[$field];
            $item['side_cause'] = self::INNER_SIDE_CAUSE;
            $item[$field] = true;
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Сохраняет элементы декомпозиции в базу данных.
     *
     * @param LossLog_Interface          $model Модель декомпозиции (план/факт)
     * @param list<array<string, mixed>> $items Элементы для сохранения
     *
     * @throws Exception
     */
    private function saveDecompositionItems(LossLog_Interface $model, array $items): void
    {
        if (count($items) <= 1) {
            return; // Если только основная декомпозиция (без сжигания на факеле и тд), то не создаем запись в БД
        }

        foreach ($items as $item) {
            if (!$model->addData($item)) {
                throw new Exception(tr('ERROR_INSERT_ENTRY'), Response::HTTP_BAD_REQUEST);
            }
        }
    }

    /**
     * Очищает служебные поля.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function clearServiceFields(array $data): array
    {
        unset(
            $data['id'],
            $data['ctime'],
            $data['mtime'],
            $data['created_id'],
            $data['user_id']
        );

        return $data;
    }

    /**
     * Возвращает массив плановых значений сжигания по дням за указанный период.
     *
     * @param int    $objectId
     * @param string $dateStart
     * @param string $dateEnd
     *
     * @return array<string, float>
     */
    public static function getBurningRatePlanData(int $objectId, string $dateStart, string $dateEnd): array
    {
        try {
            $filter = [
                'object_id'  => $objectId,
                'date_start' => ['<=', $dateEnd],
                'date_end'   => ['>=', $dateStart],
            ];

            $burningRateModel = new LossLog_Dictionaries_BurningRate_Model();
            $burningPlanData = $burningRateModel->getList(
                filter: $filter,
                fields: 'date_start, date_end, value'
            );

            $startDate = new DateTime($dateStart);
            $endDate = new DateTime($dateEnd);
            $endDate->modify('+1 day');

            $result = [];
            $period = new DatePeriod($startDate, new DateInterval('P1D'), $endDate);

            foreach ($period as $date) {
                $result[$date->format('Y-m-d')] = 0;
            }

            foreach ($burningPlanData as $item) {
                $itemStartDate = new DateTime($item['date_start']);
                $itemEndDate = new DateTime($item['date_end']);
                $itemEndDate->modify('+1 day');

                $itemPeriod = new DatePeriod($itemStartDate, new DateInterval('P1D'), $itemEndDate);

                foreach ($itemPeriod as $date) {
                    $dateStr = $date->format('Y-m-d');

                    if (isset($result[$dateStr])) {
                        $result[$dateStr] = $item['value'];
                    }
                }
            }

            return $result;
        } catch (Exception $e) {
            elkLog('GatewayObject: ошибка получения значений сжигания по дням. ' . $e->getMessage());

            return [];
        }
    }
}
