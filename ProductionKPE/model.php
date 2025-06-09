<?php

declare(strict_types=1);

class LossLog_ProductionKPE_Model extends Model_Extended
{
    /** @var array<int, array{title: string, key: string}> */
    private array $monthMap = [
        1  => ['title' => 'Январь', 'key' => 'jan'],
        2  => ['title' => 'Февраль', 'key' => 'feb'],
        3  => ['title' => 'Март', 'key' => 'mar'],
        4  => ['title' => 'Апрель', 'key' => 'apr'],
        5  => ['title' => 'Май', 'key' => 'may'],
        6  => ['title' => 'Июнь', 'key' => 'jun'],
        7  => ['title' => 'Июль', 'key' => 'jul'],
        8  => ['title' => 'Август', 'key' => 'aug'],
        9  => ['title' => 'Сентябрь', 'key' => 'sep'],
        10 => ['title' => 'Октябрь', 'key' => 'oct'],
        11 => ['title' => 'Ноябрь', 'key' => 'nov'],
        12 => ['title' => 'Декабрь', 'key' => 'dec'],
    ];

    private DateTime $startDate;
    private DateTime $endDate;
    private int $hours;

    /** @var array<int, float> */
    private array $potentialByObjectIds;

    /**
     * Основной метод для получения данных.
     *
     * @param array<string, mixed> $filter
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getData(array $filter): array
    {
        $this->calculatePeriods($filter);

        $objectsLinkedList = $this->prepareObjectsLinkedList();
        $filters = $this->buildFilters($filter);
        $lossLogMainData = $this->getLossLogMainDataObjectList($filters);

        $monthList = $this->buildMonthList($filter);
        $aggregatedData = $this->aggregateDataByObject($lossLogMainData);

        $objectsLinkedList = $this->calculateLossData(
            $objectsLinkedList,
            $aggregatedData
        );

        $objectsLinkedList = $this->calculateUsageCoefficient(
            $objectsLinkedList,
            $lossLogMainData
        );

        $objectsLinkedList = $this->calculateAeksAPQcoefficients(
            $objectsLinkedList,
            $lossLogMainData
        );

        $objectsLinkedList = $this->calculateOeeOaeTeep(
            $objectsLinkedList,
        );

        return [
            'monthList'         => $monthList,
            'objectsLinkedList' => $objectsLinkedList,
        ];
    }

    /**
     * Вычисляет временные периоды.
     *
     * @param array<string, mixed> $filter
     *
     * @throws Exception
     */
    private function calculatePeriods(array $filter): void
    {
        $this->startDate = new DateTime($this->determineStartDate($filter));
        $this->endDate = new DateTime($this->determineEndDate($filter));

        // Добавляем 1 день к конечной дате, чтобы включить её в подсчёт
        $this->endDate->modify('+1 day');

        $interval = $this->startDate->diff($this->endDate);

        $this->hours = $interval->days * 24;
    }

    /**
     * @param array<string, mixed> $filter
     *
     * @return string
     */
    private function determineStartDate(array $filter): string
    {
        return !empty($filter['date'][0])
            ? $filter['date'][0] . '-01'
            : date('Y') . '-01-01';
    }

    /**
     * @param array<string, mixed> $filter
     *
     * @return string
     *
     * @throws Exception
     */
    private function determineEndDate(array $filter): string
    {
        if (empty($filter['date'][1])) {
            return date('Y-m-t');
        }

        $endTimestamp = strtotime($filter['date'][1] . '-01');

        if ($endTimestamp === false) {
            throw new Exception(
                "Неправильный формат даты в filter['date'][1]"
            );
        }

        return date('Y-m-t', $endTimestamp);
    }

    /**
     * Подготавливает связанный список объектов.
     *
     * @return array<string, mixed>
     */
    private function prepareObjectsLinkedList(): array
    {
        $objectsModel = new LossLog_Dictionaries_Objects_Model();
        $objectsList = $objectsModel->getIndexedSimpleList();

        return LossLog_Data_Helper::convertArrayKeysToCamelCase(
            $objectsModel->getLinkedList($objectsList, true)
        );
    }

    /**
     * Формирует фильтры для запроса.
     *
     * @param array<string, mixed> $filter
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function buildFilters(array $filter): array
    {
        $filters = [];

        if (!empty($filter['date'])) {
            $filters['date'] = [
                'BETWEEN',
                $this->determineStartDate($filter),
                $this->determineEndDate($filter),
            ];
        }

        if (!empty($filter['orderBy'])) {
            $filters['ORDER_BY'] = [
                $filter['orderBy'] => $filter['orderDirection'] ?? 'ASC',
            ];
        }

        return $filters;
    }

    /**
     * Получает основные записи.
     *
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    private function getLossLogMainDataObjectList(array $filters): array
    {
        $lossLogMainDataObject = new LossLog_GatewayObject_Model();

        return $lossLogMainDataObject->getSimpleList(
            filter: $filters,
            fields: 'id, date, fact, object'
        );
    }

    /**
     * Формирует список месяцев.
     *
     * @param array<string, mixed> $filter
     *
     * @return array<int, mixed>
     *
     * @throws Exception
     */
    private function buildMonthList(array $filter): array
    {
        $startDate = $this->determineStartDate($filter);
        $endDate = $this->determineEndDate($filter);

        $current = new DateTime($startDate);
        $endObj = new DateTime($endDate);
        $groupedMonths = [];

        while ($current <= $endObj) {
            $monthNum = (int)$current->format('n');
            $yearNum = (int)$current->format('Y');
            $yearNumTwoDigits = $current->format('y');

            if (!isset($groupedMonths[$yearNum])) {
                $groupedMonths[$yearNum] = [
                    'title'  => 'Фактическое выполнение плана, тыс.м3',
                    'class'  => 'center',
                    'childs' => [],
                ];
            }

            $groupedMonths[$yearNum]['childs'][] = [
                'title' => $this->monthMap[$monthNum]['title'] . "'" . $yearNumTwoDigits,
                'key'   => $yearNum . $this->monthMap[$monthNum]['key'],
            ];

            $current->modify('+1 month');
        }

        return array_values($groupedMonths);
    }

    /**
     * Агрегирует данные по объектам.
     *
     * @param array<string, mixed> $lossLogMainData
     *
     * @return array<string, mixed>
     */
    private function aggregateDataByObject(array $lossLogMainData): array
    {
        $aggregatedData = [];

        foreach ($lossLogMainData as $record) {
            $objectId = $record['object'];
            $monthNum = (int)date('n', strtotime($record['date']));

            if (!isset($aggregatedData[$objectId])) {
                $aggregatedData[$objectId] = [];
            }

            if (!isset($aggregatedData[$objectId][$monthNum])) {
                $aggregatedData[$objectId][$monthNum] = 0;
            }

            $aggregatedData[$objectId][$monthNum] += (float)$record['fact'];
        }

        return $aggregatedData;
    }

    /**
     * Обогащает объекты данными о потерях.
     *
     * @param array<string, mixed> $objectsLinkedList
     * @param array<string, mixed> $aggregatedData
     *
     * @return array<string, mixed>
     */
    private function calculateLossData(
        array $objectsLinkedList,
        array $aggregatedData
    ): array {
        foreach ($objectsLinkedList as $id => $object) {
            $objectId = $object['id'];
            $objectsLinkedList[$id]['loss'] = [];

            $currentDate = clone $this->startDate;

            while ($currentDate <= $this->endDate) {
                $monthNum = (int)$currentDate->format('n');
                $yearNum = (int)$currentDate->format('Y');

                $key = $yearNum . $this->monthMap[$monthNum]['key'];
                $value = $aggregatedData[$objectId][$monthNum] ?? 0;

                $objectsLinkedList[$id]['loss'][$key] = $value;
                $currentDate->modify('+1 month');
            }
        }

        return $objectsLinkedList;
    }

    /**
     * Вычисляет коэффициент использования.
     *
     * @param array<string, mixed> $objectsLinkedList
     * @param array<string, mixed> $lossLogMainData
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function calculateUsageCoefficient(
        array $objectsLinkedList,
        array $lossLogMainData
    ): array {
        $immediateCauseIds = $this->getImmediateCauseIds(1);

        foreach ($objectsLinkedList as $id => $object) {
            $objectId = $object['id'];
            $mainRecords = $this->filterRecordsByObject($lossLogMainData, $objectId);

            $objectsLinkedList[$id] = $this->processObject(
                $object,
                $mainRecords,
                $immediateCauseIds,
            );
        }

        return $objectsLinkedList;
    }

    /**
     * Получает идентификаторы причин с переданным типом.
     *
     * @param int $typeNum
     *
     * @return array<int>
     *
     * @throws Exception
     */
    private function getImmediateCauseIds(int $typeNum): array
    {
        /*
         1 U
         2 Аэкс
         3 A
         4 P
         5 Q
         */

        $immediateCauseModel = new LossLog_Dictionaries_ImmediateCause_Model();
        $immediateCauseList = $immediateCauseModel->getSimpleList(
            ['cause_type' => $typeNum],
            '',
            0,
            0,
            'id'
        );

        return array_column($immediateCauseList, 'id');
    }

    /**
     * Фильтрует записи по объекту.
     *
     * @param array<string, mixed> $lossLogMainData
     * @param int                  $objectId
     *
     * @return array<string, mixed>
     */
    private function filterRecordsByObject(array $lossLogMainData, int $objectId): array
    {
        return array_filter(
            $lossLogMainData,
            static fn ($item) => $item['object'] == $objectId
        );
    }

    /**
     * Обрабатывает объект.
     *
     * @param array<string, mixed> $object
     * @param array<string, mixed> $records
     * @param array<int>           $causeIds
     *
     * @return array<string, mixed>
     */
    private function processObject(
        array $object,
        array $records,
        array $causeIds,
    ): array {
        $decompositionFactModel = new LossLog_DecompositionFact_Model();
        $decompositionPlanModel = new LossLog_DecompositionPlan_Model();
        $potentialModel = new LossLog_ObjectPotential_Model();

        $object['inactivity_loss'] = $this->calculateInactivityLoss(
            $decompositionPlanModel,
            $records,
            $causeIds,
            $object['id'],
        );

        $object['inactivity_loss'] += $this->calculateInactivityLoss(
            $decompositionFactModel,
            $records,
            $causeIds,
            $object['id'],
        );

        $potential = $this->calculatePotential(
            $potentialModel,
            $object['id'],
            $records
        );

        $this->potentialByObjectIds[$object['id']] = $potential;
        $inactivityTime = 0.0;

        if ($potential > 0) {
            $inactivityTime = ($object['inactivity_loss'] * $this->hours) / $potential;
        }

        $object['inactivity_time'] = $inactivityTime;

        $object['U'] = $this->calculateUsageCoefficientValue(
            $object['inactivity_time']
        );

        return $object;
    }

    /**
     * Вычисляет потери бездействия.
     *
     * @param Model_Extended       $decompositionModel
     * @param array<string, mixed> $records
     * @param array<int>           $causeIds
     * @param int                  $objectId
     *
     * @return float
     */
    private function calculateInactivityLoss(
        Model_Extended $decompositionModel,
        array $records,
        array $causeIds,
        int $objectId
    ): float {
        $usedIds = array_column($records, 'id');

        if (empty($usedIds)) {
            return 0.0;
        }

        $decompositionList = $decompositionModel->getSimpleList(
            [
                'losslog_main_data_id' => ['IN', $usedIds],
                'immediate_cause'      => ['IN', $causeIds],
            ],
            '',
            0,
            0,
            'deviation'
        );

        return (float)array_sum(array_column($decompositionList, 'deviation'));
    }

    /**
     * Вычисляет потенциал.
     *
     * @param LossLog_ObjectPotential_Model $potentialModel
     * @param int                           $objectId
     * @param array<string, mixed>          $records
     *
     * @return float
     */
    private function calculatePotential(
        LossLog_ObjectPotential_Model $potentialModel,
        int $objectId,
        array $records
    ): float {
        $potential = 0.0;
        $objectPotential = $potentialModel->getObjectPotential($objectId);

        foreach ($records as $record) {
            $potentialData = $potentialModel->filterObjectPotentialByDate(
                $objectPotential,
                $record['date']
            );

            if ($potentialData && isset($potentialData['performance'], $potentialData['mdp'])) {
                $potential += ($potentialData['performance'] > $potentialData['mdp'])
                    ? $potentialData['performance']
                    : $potentialData['mdp'];
            }
        }

        return $potential;
    }

    /**
     * Вычисляет коэффициент использования.
     *
     * @param float $inactivityTime
     *
     * @return float
     */
    private function calculateUsageCoefficientValue(float $inactivityTime): float
    {
        return ($this->hours - $inactivityTime) / $this->hours;
    }

    /**
     * @param array<string, mixed> $objectsLinkedList
     * @param array<string, mixed> $lossLogMainData
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function calculateAeksAPQcoefficients(array $objectsLinkedList, array $lossLogMainData): array
    {
        $causeIdsAEKS = $this->getImmediateCauseIds(2);
        $causeIdsA = $this->getImmediateCauseIds(3);
        $causeIdsP = $this->getImmediateCauseIds(4);
        $causeIdsQ = $this->getImmediateCauseIds(5);

        foreach ($objectsLinkedList as $id => $object) {
            $objectId = $object['id'];
            $mainRecords = $this->filterRecordsByObject($lossLogMainData, $objectId);
            $usedIds = array_column($mainRecords, 'id');

            // Расчет для внепланового простоя в часах (AEKS)

            $unscheduledDowntimeHours = $this->calculateDowntimeHours($usedIds, $causeIdsAEKS, $objectId);
            $objectsLinkedList[$id]['unscheduled_downtime_hours'] = $unscheduledDowntimeHours;

            /*
             * AEKS
             * = (Кол-во дней за выбранный период*24ч - "Внеплановый простой в часах Аэкс-"Время бездействия"")
             * /(Кол-во дней за выбранный период*24ч - "Время бездействия")
             */
            $objectsLinkedList[$id]['AEKS'] = $this->calculateAEKS(
                $unscheduledDowntimeHours,
                $objectsLinkedList[$id]['inactivity_time']
            );

            // Расчет для планового простоя в часах (A)
            $plannedDowntimeHours = $this->calculateDowntimeHours($usedIds, $causeIdsA, $objectId);
            $objectsLinkedList[$id]['planned_downtime_hours'] = $plannedDowntimeHours;

            /* Расчёт коэффициента доступности A
             * = (Кол-во дней за выбранный период*24ч - Внеплановый простой в часах
             * - время бездействия-плановый простой в часах)
             * /(Кол-во дней за выбранный период*24ч-время бездействия)
             */
            $objectsLinkedList[$id]['A'] = $this->calculateAvailability(
                $unscheduledDowntimeHours,
                $plannedDowntimeHours,
                $objectsLinkedList[$id]['inactivity_time']
            );

            /*
             * Коэффициент производительности (Р) -
             * = (Сумма по столбцу "ПОТЕНЦИАЛ"
             * – (минус) декомпозиция отклонения в тыс. м3/тонны/литры по непосредственным причинам «P»
             * (по вкладкам «анализ выполнения плана» + «плановые потери»))
             * / Сумма по столбцу «Потенциала»
             */
            $planModel = new LossLog_DecompositionPlan_Model();
            $factModel = new LossLog_DecompositionFact_Model();
            $totalDeviationPlanForP = $this->getTotalDeviation($planModel, $usedIds, $causeIdsP);
            $totalDeviationFactForP = $this->getTotalDeviation($factModel, $usedIds, $causeIdsP);

            $objectsLinkedList[$id]['P'] = $this->potentialByObjectIds[$objectId] > 0
                ? ($this->potentialByObjectIds[$objectId] - ($totalDeviationPlanForP +
                    $totalDeviationFactForP)) / $this->potentialByObjectIds[$objectId]
                : 0;

            /*
             * Коэффициент качества (Q)
             * (сумма по столбцу  «Факт выработки» за Кол-во дней за выбранный период
             * – (минус) декомпозиция отклонения в тыс. м3/тонны/литры по «непосредственным причинам «Q»»
             * (по вкладкам «анализ выполнения плана» + «плановые потери» за Кол-во дней за выбранный период)
             * / сумма по столбцу  «Факт выработки» за Кол-во дней за выбранный период
             */
            $factTotal = array_sum(array_column($mainRecords, 'fact'));

            $totalDeviationPlanForQ = $this->getTotalDeviation($planModel, $usedIds, $causeIdsQ);
            $totalDeviationFactForQ = $this->getTotalDeviation($factModel, $usedIds, $causeIdsQ);

            $objectsLinkedList[$id]['Q'] = $factTotal > 0
                ? ($factTotal - ($totalDeviationPlanForQ + $totalDeviationFactForQ)) / $factTotal
                : 0;
        }

        return $objectsLinkedList;
    }

    /**
     * @param LossLog_DecompositionFact_Model|LossLog_DecompositionPlan_Model $model
     * @param list<array>                                                     $usedIds
     * @param array<string, mixed>                                            $causeIds
     *
     * @return float
     */
    private function getTotalDeviation(
        LossLog_DecompositionFact_Model | LossLog_DecompositionPlan_Model $model,
        array $usedIds,
        array $causeIds
    ): float {
        $list = $model->getSimpleList([
            'losslog_main_data_id' => ['IN', $usedIds],
            'immediate_cause'      => ['IN', $causeIds],
        ], '', 0, 0, 'deviation');

        return (float)array_sum(array_column($list, 'deviation'));
    }

    /**
     * @param list<array>          $usedIds
     * @param array<string, mixed> $causeIds
     * @param int                  $objectId
     *
     * @return float
     */
    private function calculateDowntimeHours(array $usedIds, array $causeIds, int $objectId): float
    {
        $planModel = new LossLog_DecompositionPlan_Model();
        $factModel = new LossLog_DecompositionFact_Model();

        $planDeviation = $this->getTotalDeviation($planModel, $usedIds, $causeIds);
        $factDeviation = $this->getTotalDeviation($factModel, $usedIds, $causeIds);

        $totalDeviation = $planDeviation + $factDeviation;

        if ($this->potentialByObjectIds[$objectId] <= 0) {
            return 0.0;
        }

        return ($totalDeviation * $this->hours) / $this->potentialByObjectIds[$objectId];
    }

    private function calculateAEKS(float $downtimeHours, float $inactivityTime): float
    {
        $denominator = $this->hours - $inactivityTime;

        return $denominator > 0
            ? ($this->hours - $downtimeHours - $inactivityTime) / $denominator
            : 0;
    }

    private function calculateAvailability(float $unscheduledHours, float $plannedHours, float $inactivityTime): float
    {
        $denominator = $this->hours - $inactivityTime;

        return $denominator > 0
            ? ($this->hours - $unscheduledHours - $plannedHours - $inactivityTime) / $denominator
            : 0;
    }

    /**
     * @param array<string, mixed> $objectsLinkedList
     *
     * @return array<string, float>
     */
    private function calculateOeeOaeTeep(array $objectsLinkedList): array
    {
        $targetCoefficientsModel = new LossLog_Dictionaries_TargetKPECoefficients_Model();
        $targetCoefficientsList = $targetCoefficientsModel->getSimpleList();

        foreach ($objectsLinkedList as $id => $object) {
            $objectId = $object['id'];

            // Извлекаем индексы элементов по object_id
            $index = array_search($objectId, array_column($targetCoefficientsList, 'object_id'));

            if ($index !== false) {
                $objectsLinkedList[$id]['targetOEE'] = $targetCoefficientsList[$index]['oee'];
                $objectsLinkedList[$id]['targetOAE'] = $targetCoefficientsList[$index]['oae'];
                $objectsLinkedList[$id]['targetTEEP'] = $targetCoefficientsList[$index]['teep'];
            }

            /*
             * Расчёт факта по формулам:
             * ОЕЕ=АэксPQ =
             * Коэффициент эксплуатационной доступности (Aэкс) * Коэффициент производительности (Р) *
             * Коэффициент качества (Q);
             *
             * ОАЕ=APQ = Коэффициент доступности (A) * Коэффициент производительности (Р) * Коэффициент качества (Q);
             *
             * ТЕЕР=UAP*Q =
             * Коэффициент использования (U) * Коэффициент доступности (A) * Коэффициент производительности (Р) *
             * Коэффициент качества (Q).
             */
            $objectsLinkedList[$id]['OEE'] = $object['AEKS'] * $object['P'] * $object['Q'];
            $objectsLinkedList[$id]['OAE'] = $object['A'] * $object['P'] * $object['Q'];
            $objectsLinkedList[$id]['TEEP'] = $object['U'] * $object['A'] * $object['P'] * $object['Q'];
        }

        return $objectsLinkedList;
    }
}
