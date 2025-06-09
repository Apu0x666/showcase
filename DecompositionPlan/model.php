<?php

use LossLog\LossLog_Interface;

class LossLog_DecompositionPlan_Model extends Model_Extended implements LossLog_Interface
{
    use LossLog_Decomposition_Trait;

    protected string $table = 'losslog_decomposition_plan';

    public array $fields = [
        'id' => ['type' => 'primarykey'],
        // связь для таблицы losslog_main_data
        'losslog_main_data_id' => ['type' => 'int'],
        // Планирование
        'deviation' => ['type' => 'decimal'], // Декомпозиция отклонения
        'parent_id' => [
            // id объекта от которого была создана декомпозиция (если не указан - создана локально на
            // странице установки)
            'type' => 'int',
        ],
        'immediate_cause' => [
            // Непосредственная причина
            'type' => 'int',
            'link' => 'LossLog_Dictionaries_ImmediateCause_Model',
        ],
        // Создано автоматически, на основе флага own_needs_accounting
        'is_own_needs_accounting' => ['type' => 'bool'],
        // Создано автоматически, на основе флага flaring_accounting
        'is_flaring_accounting' => ['type' => 'bool'],
        // Создано автоматически, исходя из последствий
        'is_consequences_accounting' => ['type' => 'bool'],
        'comment'                    => ['type' => 'string'], // комментарий
        'root_cause'                 => [
            // коренная причина
            'type' => 'int',
            'link' => 'LossLog_Dictionaries_RootCause_Model',
        ],
        'side_cause'            => ['type' => 'int'], // сторона причины
        'responsible_object_id' => [
            'type'    => 'string',
            'link'    => 'LossLog_Dictionaries_Objects_Model',
            'comment' => 'ID виновной стороны',
        ],
        'affected_object_id' => [
            'type'     => 'string',
            'multiple' => 1,
            'link'     => 'LossLog_Dictionaries_Objects_Model',
            'comment'  => 'ID пострадавшей стороны',
        ],
        'upload_docs' => [
            'type'    => 'json',
            'comment' => 'Загруженные документы',
        ],
        'document'       => ['type' => 'int', 'link' => 'LossLog_ActsTechnicalRepair_Model'], // связанный документ
        'id_in'          => ['type' => 'string'], // ID внутренней причины
        'id_out'         => ['type' => 'string'], // ID внешней / связанной причины
        'id_consequence' => ['type' => 'string'], // ID последствия
        // ----------------------------------
        // Недовыпуск продукции от потенциала
        'bt'            => ['type' => 'decimal'], // БТ
        'pt'            => ['type' => 'decimal'], // ПТ
        'sgk'           => ['type' => 'decimal'], // СГК
        'oil'           => ['type' => 'decimal'], // нефть
        'helium'        => ['type' => 'decimal'], // гелий
        'economic_loss' => ['type' => 'decimal'], // Экономические последствия млн.руб
        // ----------------------------------
        'ctime'      => ['type' => 'int'],
        'mtime'      => ['type' => 'int'],
        'created_id' => ['type' => 'int'],
        'user_id'    => ['type' => 'int', 'link' => 'User'],
    ];

    public const int ERROR_EMPTY_DEVIATION = 1;
    public const int ERROR_EMPTY_IMMEDIATE_CAUSE = 2;
    public const int ERROR_EMPTY_SIDE_CAUSE = 3;
    public const int ERROR_EMPTY_RESPONSIBLE_OBJECT_ID = 4;
    public const int SUCCESS_VALID = 0;
    public const int ERROR_NOT_FOUND = 404;

    public function getData(array $filter, string $ordering, $request = []): array
    {
        return [];
    }

    /**
     * Метод возвращает мап ошибок модели.
     *
     * @return array<int, string>
     */
    public static function getErrors(): array
    {
        return [
            self::ERROR_EMPTY_DEVIATION             => 'Значение поля Декомпозиция отклонения не установлено',
            self::ERROR_EMPTY_IMMEDIATE_CAUSE       => 'Значение поля Непосредственная причина не установлено',
            self::ERROR_EMPTY_SIDE_CAUSE            => 'Значение поля Сторона причины не установлено',
            self::ERROR_EMPTY_RESPONSIBLE_OBJECT_ID => 'Значение поля Подразделение внешней стороны не установлено',
            self::ERROR_NOT_FOUND                   => 'Ошибка не найдена',
        ];
    }

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
            'deviation'       => self::ERROR_EMPTY_DEVIATION,
            'immediate_cause' => self::ERROR_EMPTY_IMMEDIATE_CAUSE,
            'side_cause'      => self::ERROR_EMPTY_SIDE_CAUSE,
        ] + ($params['side_cause'] == 0 ?
                ['responsible_object_id' => self::ERROR_EMPTY_RESPONSIBLE_OBJECT_ID] :
                []);

        $required = LossLog_Data_Helper::checkRequiredFields($requiredFields, $params);

        if ($required) {
            return $required;
        }

        // Проверка, что immediate_cause > 0
        if ($params['immediate_cause'] <= 0) {
            return self::ERROR_EMPTY_IMMEDIATE_CAUSE;
        }

        return self::SUCCESS_VALID;
    }

    /**
     * @param int    $parentId
     * @param string $idOut
     *
     * @return bool
     */
    public function deleteDecompositionByParentIdAndIdOut(int $parentId, string $idOut): bool
    {
        return $this->getCurrentDb()->query(
            'DELETE FROM ' . $this->getTableName() . ' WHERE parent_id = :parentId AND id_in = :idOut',
            [':parentId' => $parentId, ':idOut' => $idOut]
        );
    }
}
