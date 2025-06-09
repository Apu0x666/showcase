<?php

declare(strict_types=1);

use Ep\App\Core\Request\CoreRequest;
use LossLog\LossLog_Interface;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;

class LossLogPrepareDataForToir_Helper
{
    /** @var string */
    #[SerializedName('ИДАТР')]
    #[SerializedPath('[ИДАТР]')]
    private string $idATR = '';

    /** @var int */
    #[SerializedName('ПланФакт')]
    #[SerializedPath('[ПланФакт]')]
    private int $planFact;

    /** @var datetime */
    #[SerializedName('Дата')]
    #[SerializedPath('[Дата]')]
    private DateTime $date;

    /** @var int */
    #[SerializedName('ИДЗаписи')]
    #[SerializedPath('[ИДЗаписи]')]
    private int $idDecomposition;

    /** @var string */
    #[SerializedName('ТехПроцесс')]
    #[SerializedPath('[ТехПроцесс]')]
    private string $techProcess;

    /** @var float */
    #[SerializedName('Потери')]
    #[SerializedPath('[Потери]')]
    private float $economicLoss;

    /** @var string */
    #[SerializedName('СсылкаНаЗапись')]
    #[SerializedPath('[СсылкаНаЗапись]')]
    private string $link;

    public function getEconomicLoss(): float
    {
        return $this->economicLoss;
    }

    public function setEconomicLoss(float $economicLoss): void
    {
        $this->economicLoss = $economicLoss;
    }

    public function getIdATR(): string
    {
        return $this->idATR;
    }

    public function setIdATR(string $idATR): void
    {
        $this->idATR = $idATR;
    }

    public function getPlanFact(): int
    {
        return $this->planFact;
    }

    public function setPlanFact(int $planFact): void
    {
        $this->planFact = $planFact;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    public function setDate(DateTime $date): void
    {
        $this->date = $date;
    }

    public function getIdDecomposition(): int
    {
        return $this->idDecomposition;
    }

    public function setIdDecomposition(int $idDecomposition): void
    {
        $this->idDecomposition = $idDecomposition;
    }

    public function getTechProcess(): string
    {
        return $this->techProcess;
    }

    public function setTechProcess(string $techProcess): void
    {
        $this->techProcess = $techProcess;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function setLink(string $link): void
    {
        $this->link = $link;
    }

    /**
     * @param array<string, mixed> $data
     * @param LossLog_Interface    $model
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function forToir(array $data, LossLog_Interface $model): array
    {
        $baseData = $this->getBaseData($data, $model);

        // Определяем ключ для документа
        $docKey = !empty($baseData['is_nz']) ? 'ИДНЗ' : 'ИДАТР';

        // Если это операция удаления, возвращаем специальный формат
        if ($data['operation_type'] === 'delete') {
            return [
                $docKey      => $baseData['old_document_uid'],
                'ПланФакт'   => $baseData['decomposition_type'],
                'Дата'       => $baseData['date'],
                'ИДЗаписи'   => $baseData['id'],
                'ТехПроцесс' => $baseData['tech_process'],
                'Удален'     => 1,
            ];
        }

        // Для остальных операций возвращаем полный формат
        return [
            $docKey      => $baseData['document_uid'],
            'ПланФакт'   => $baseData['decomposition_type'],
            'Дата'       => $baseData['date'],
            'ИДЗаписи'   => $baseData['id'],
            'ТехПроцесс' => $baseData['tech_process'],
            'Продукт'    => [
                ['Наименование' => 'Нефть', 'Значение' => $baseData['oil'] ?? 0],
                ['Наименование' => 'СГК', 'Значение' => $baseData['sgk'] ?? 0],
                ['Наименование' => 'ПТ', 'Значение' => $baseData['pt'] ?? 0],
                ['Наименование' => 'БТ', 'Значение' => $baseData['bt'] ?? 0],
                ['Наименование' => 'Гелий', 'Значение' => $baseData['helium'] ?? 0],
            ],
            'Потери'         => $baseData['economic_loss'] ?? 0,
            'СсылкаНаЗапись' => $baseData['path'],
        ];
    }

    /**
     * Получение базовых данных для формирования ответа.
     *
     * @param array<string, mixed> $data
     * @param LossLog_Interface    $model
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function getBaseData(array $data, LossLog_Interface $model): array
    {
        switch ($model) {
            case $model instanceof LossLog_DecompositionPlan_Model:
                $decompositionType = 0;
                $decompositionModel = new LossLog_DecompositionPlan_Model();
                $decompositionItem = $decompositionModel->getLinkedItem($data, true);
                break;

            case $model instanceof LossLog_DecompositionFact_Model:
                $decompositionType = 1;
                $decompositionModel = new LossLog_DecompositionFact_Model();
                $decompositionItem = $decompositionModel->getLinkedItem($data, true);
                break;

            default:
                throw new Exception('Неизвестный тип декомпозиции!');
        }

        $mainRecordModel = new LossLog_GatewayObject_Model();
        $mainRecordItem = $mainRecordModel->getItem($decompositionItem['losslog_main_data_id']);

        $objectModel = new LossLog_Dictionaries_Objects_Model();
        $objectItem = $objectModel->getItem($mainRecordItem['object']);
        $objectLinkedItem = $objectModel->getLinkedItem($objectItem, true);

        /** @var CoreRequest $request */
        $request = Di::getInstance()->getDi()[CoreRequest::class];
        $path = $request->getSchemeAndHttpHost() . '/index.php?module=LossLog_GatewayObject&object=' .
            $mainRecordItem['object'] . '&date_from=' . $mainRecordItem['date'] . '&date_to=' . $mainRecordItem['date'];

        $actsTechnicalRepairItem = [];

        if (!empty($data['old_document_id'])) {
            // если установлен старый id документа, значит подгружаем его, для будущей отправки в ТОиР
            $actsTechnicalRepairModel = new LossLog_ActsTechnicalRepair_Model();
            $actsTechnicalRepairItem = $actsTechnicalRepairModel->getItem($data['old_document_id']);
        }

        return [
            'old_document_uid'   => $actsTechnicalRepairItem['doc_uid'] ?? '',
            'document_uid'       => $decompositionItem['document']['doc_uid'] ?? '',
            'is_nz'              => $decompositionItem['document']['is_nz'],
            'decomposition_type' => $decompositionType,
            'date'               => $mainRecordItem['date'] ?? '',
            'id'                 => $decompositionItem['id'] ?? '',
            'tech_process'       => $objectLinkedItem['tech_process']['tech_process_name'] ?? '',
            'oil'                => $decompositionItem['oil'] ?? 0,
            'sgk'                => $decompositionItem['sgk'] ?? 0,
            'pt'                 => $decompositionItem['pt'] ?? 0,
            'bt'                 => $decompositionItem['bt'] ?? 0,
            'helium'             => $decompositionItem['helium'] ?? 0,
            'economic_loss'      => $decompositionItem['economic_loss'] ?? 0,
            'path'               => $path,
        ];
    }
}
