<?php

class LossLogImportXmlDatareon_Helper
{
    /**
     * Сохраняет данные ATR, полученные в виде XML.
     *
     * @param string $datareonData XML-данные
     *
     * @return bool возвращает true при успешном выполнении, иначе false
     */
    private function storeATRData(string $datareonData): bool
    {
        $columnMapping = [
            'Ссылка'                      => 'doc_uid',
            'Номер'                       => 'doc_num',
            'ДатаВремяОтказа'             => 'doc_date',
            'Дата'                        => 'atr_date',
            'ОписаниеОтказа'              => 'description',
            'СкрытиеСПП'                  => 'delete_this',
            'ФактическиОтказавшаяЕО'      => 'object_guid',
            'НавигационнаяСсылка'         => 'doc_link',
            'Подразделение'               => 'workshop_guid',
            'КореннаяПричинаНаименование' => 'root_cause',
            'КореннаяПричина'             => 'root_cause_guid',
            'СтатусЭДО'                   => 'edo_status',
            'АРП'                         => 'is_arp',
            'СтатусРасследованияОтказа'   => 'deny_status',
        ];

        $data = $this->storeXmlData($datareonData, $columnMapping, 'АТР');

        if ($data === false) {
            return false;
        }

        $lossLogATRModel = new LossLog_ActsTechnicalRepair_Model();

        if (isset($data['delete_this']) && $data['delete_this'] === 'true') {
            $lossLogATR = $lossLogATRModel->getItemByFilter(['doc_uid' => $data['doc_uid']]);

            if (!empty($lossLogATR['id'])) {
                try {
                    $lossLogATRModel->delete((int)$lossLogATR['id']);
                } catch (Exception $e) {
                    elkLogException(
                        'Ошибка обновления записи ATR: Не удалось удалить запись для doc_uid ' . $data['doc_uid'] .
                        '. ' . $e->getMessage()
                    );

                    return false;
                }
            }
        } else {
            try {
                $lossLogATRModel->store($data);
            } catch (Exception $e) {
                elkLogException(
                    'Ошибка обновления записи ATR: Не удалось сохранить запись для doc_uid ' . $data['doc_uid'] . '. ' .
                    $e->getMessage()
                );

                return false;
            }
        }

        return true;
    }

    /**
     * Принимает XML данные и передает их для сохранения ATR сообщений.
     *
     * @param string $xmlData XML-данные
     *
     * @return bool возвращает true при успешном выполнении, иначе false
     */
    public function storeATRMessages(string $xmlData): bool
    {
        try {
            return $this->storeATRData($xmlData);
        } catch (Exception $e) {
            // Ловим любые непредвиденные исключения и логируем их.
            elkLogException($e->getMessage());

            return false;
        }
    }

    /**
     * @param string $datareonData
     * @param string $context
     *
     * @return array<string, mixed>
     */
    private function parseXml(string $datareonData, string $context = ''): array
    {
        // Пытаемся разобрать входящую XML-строку.
        $xml = simplexml_load_string($datareonData);

        if (!$xml) {
            // Если XML не удалось разобрать, логируем ошибку и возвращаем false.
            elkLogException('Ошибка сохранения записи [' . $context . ']: Не удалось разобрать XML');

            return [];
        }

        // Преобразуем XML в JSON, а затем в ассоциативный массив для удобной обработки.
        $json = json_encode($xml);

        if ($json === false) {
            elkLogException('Ошибка преобразования XML в JSON [' . $context . ']');

            return [];
        }

        return json_decode($json, true);
    }

    /**
     * Сохраняет данные ATR, полученные в виде XML.
     *
     * @param string $datareonData XML-данные
     *
     * @return bool возвращает true при успешном выполнении, иначе false
     */
    private function storeRootCauseData(string $datareonData): bool
    {
        $columnName = [
            'Ссылка'          => 'guid',
            'Код'             => 'code',
            'Наименование'    => 'name',
            'ПометкаУдаления' => 'mark_deleted_toir', // отмечено удалённым в ТОиР
        ];

        $array = $this->parseXml($datareonData, 'Коренная причина');

        $data = [];

        // Перебираем элементы полученного массива и формируем массив данных для сохранения.
        foreach ($array as $key => $value) {
            if (isset($columnName[$key])) {
                $internalKey = $columnName[$key];

                // Если значение является пустым массивом, сохраняем пустую строку,
                // иначе приводим значение к строке и обрезаем лишние пробелы.
                if ($internalKey === 'mark_deleted_toir') {
                    // обработка логического поля
                    $value = is_array($value) ? '' : trim((string)$value);
                    $data[$internalKey] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } else {
                    // Стандартная обработка для остальных полей
                    $data[$internalKey] = (is_array($value) && count($value) === 0)
                        ? ''
                        : trim((string)$value);
                }
            }
        }

        $rootCauseModel = new LossLog_Dictionaries_RootCause_Model();

        try {
            $filter = ['code' => $data['code'], 'name' => $data['name']];
            $rootCauseModel->storeItem(filter: $filter, data: $data);
        } catch (Exception $e) {
            // Если сохранение не удалось, логируем ошибку.
            elkLogException(
                'Ошибка сохранения Коренной причины. Ошибка: ' . $e->getMessage()
            );

            return false;
        }

        // Если все операции выполнены успешно, возвращаем true.
        return true;
    }

    /**
     * Принимает XML данные и передает их для сохранения коренных причин.
     *
     * @param string $xmlData XML-данные
     *
     * @return bool возвращает true при успешном выполнении, иначе false
     */
    public function storeRootCauseMessages(string $xmlData): bool
    {
        try {
            return $this->storeRootCauseData($xmlData);
        } catch (Exception $e) {
            // Ловим любые непредвиденные исключения и логируем их.
            elkLogException($e->getMessage());

            return false;
        }
    }

    /**
     * Принимает XML данные и передает их для сохранения Наряд-Заказа.
     *
     * @param string $xmlData XML-данные
     *
     * @return bool возвращает true при успешном выполнении, иначе false
     */
    public function storeNZMessages(string $xmlData): bool
    {
        try {
            return $this->storeNZData($xmlData);
        } catch (Exception $e) {
            // Ловим любые непредвиденные исключения и логируем их.
            elkLogException($e->getMessage());
            echo $e->getMessage();

            return false;
        }
    }

    /**
     * Сохраняет данные из Наряд-Заказа.
     *
     * @param string $xmlData XML-данные
     *
     * @return bool возвращает true при успешном выполнении, иначе false
     */
    private function storeNZData(string $xmlData): bool
    {
        $columnMapping = [
            'Ссылка'                 => 'doc_uid',
            'Номер'                  => 'doc_num',
            'Дата'                   => 'doc_date',
            'ПроизводственныйОбъект' => 'object_guid',
            'Описание'               => 'description',
            'СтатусДокумента'        => 'deny_status',
            'НавигационнаяСсылка'    => 'doc_link',
        ];

        $additionalProcessing = static function ($data) {
            $failureObjectModel = new FailureObjects_Model();
            $object = $failureObjectModel->getItemByFilter(
                ['object_guid' => $data['object_guid']],
                '',
                'id'
            );
            $data['object_id'] = $object['id'] ?? 0;
            $data['is_nz'] = true;

            return $data;
        };

        $data = $this->storeXmlData($xmlData, $columnMapping, 'Наряд-Заказ', $additionalProcessing);

        if ($data === false) {
            return false;
        }

        $atrModel = new LossLog_ActsTechnicalRepair_Model();
        $item = $atrModel->getItemByFilter(['doc_uid' => $data['doc_uid']]);

        try {
            if (!empty($item['id'])) {
                $atrModel->update($data, $item['id']);
            } else {
                $atrModel->addData($data);
            }
        } catch (Exception $e) {
            elkLogException(
                'Ошибка обновления записи Наряд-Заказа: Не удалось сохранить запись для doc_uid ' .
                $data['doc_uid'] . '. ' . $e->getMessage()
            );

            return false;
        }

        return true;
    }

    /**
     * Общий метод для сохранения данных из XML.
     *
     * @param string               $xmlData              XML-данные
     * @param array<string, mixed> $columnMapping        Маппинг полей XML на внутренние ключи
     * @param string               $context              Контекст для логирования
     * @param null|callable        $additionalProcessing Дополнительная обработка данных
     *
     * @return array<string, mixed>|false возвращает массив данных или false в случае ошибки
     */
    private function storeXmlData(
        string $xmlData,
        array $columnMapping,
        string $context,
        ?callable $additionalProcessing = null
    ): array | false {
        $array = $this->parseXml($xmlData, $context);

        if (empty($array)) {
            return false;
        }

        $data = [];

        // Перебираем элементы полученного массива и формируем массив данных для сохранения
        foreach ($array as $key => $value) {
            if (isset($columnMapping[$key])) {
                $internalKey = $columnMapping[$key];

                if (is_array($value) && count($value) === 0) {
                    $data[$internalKey] = '';
                } else {
                    $value = trim((string)$value);

                    // Обработка логических полей
                    if (in_array($internalKey, ['is_arp', 'mark_deleted_toir'])) {
                        $data[$internalKey] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $data[$internalKey] = $value;
                    }
                }
            }
        }

        // Обработка дат
        $dateFields = ['doc_date', 'atr_date'];

        foreach ($dateFields as $field) {
            if (!empty($data[$field])) {
                try {
                    $dateTime = new DateTimeImmutable((string)$data[$field]);
                    $data[$field] = $dateTime->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    elkLogException("Ошибка обновления записи {$context}: " . $e->getMessage());

                    return false;
                }
            } else {
                $data[$field] = '';
            }
        }

        // Если обязательное поле "doc_num" отсутствует, прерываем обработку
        if (empty($data['doc_num'])) {
            return false;
        }

        // Дополнительная обработка данных, если передана
        if ($additionalProcessing !== null) {
            $data = $additionalProcessing($data);
        }

        return $data;
    }
}
