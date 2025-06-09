<?php

use Ep\App\Core\Acl;
use Ep\App\Core\Request\CoreRequest;
use Ep\App\Core\Response\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LossLog_DecompositionPlan_Controller extends LossLog_Controller
{
    use LossLog_Decomposition_Trait;

    public string $permissionName = 'losslog_decomposition_plan';
    protected int $permissionType = Acl::READ;
    protected bool $checkPermission = true;
    public int $version = 3;

    public function __construct()
    {
        $this->setModel(new LossLog_DecompositionPlan_Model());
        parent::__construct();
    }

    /**
     * Получение доступных для копирования (декомпозиции в них) дат
     *
     * @param CoreRequest $request
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function planCopyForDateGetActiveDatesAction(CoreRequest $request): JsonResponse
    {
        $input = $request->toArray();

        if (empty($input['objectId'])) {
            throw new Exception('Не передан ID объекта', Response::HTTP_BAD_REQUEST);
        }

        $mainDataModel = new LossLog_GatewayObject_Model();
        $mainDataList = $mainDataModel->getList(
            [
                'object' => ['=', $input['objectId']],
                'plan'   => ['AND', [['IS NOT NULL'], ['>=', 0]]],
            ],
            '',
            0,
            0,
            'id, date'
        );

        return new JsonResponse($mainDataList);
    }

    /**
     * Метод копирует декомпозицию по алгоритму.
     *
     * @param CoreRequest $request
     * @param Acl         $acl
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function planCopyForDateAction(CoreRequest $request, Acl $acl): JsonResponse
    {
        if (!$acl->checkPermission($this->permissionName, Acl::WRITE)) {
            return new JsonResponse(
                ['errorMessage' => 'У вас нет прав на добавление'],
                Response::HTTP_FORBIDDEN
            );
        }

        $input = $request->toArray();
        $id = $input['id'] ?? null;

        if (!isset($id)) {
            return new JsonResponse(
                ['errorMessage' => 'ID декомпозиции источника не установлен'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!isset($input['ids'])) {
            return new JsonResponse(
                ['errorMessage' => 'Не выявлены целевые ID'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!isset($input['object'])) {
            return new JsonResponse(
                ['errorMessage' => 'Не передан object'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $useDeviation = (bool)($input['useDeviation'] ?? 0);

        $item = $this->model->getItem($input['id']);

        if (empty($item)) {
            return new JsonResponse(
                ['errorMessage' => 'Запись не найдена'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $usedKeys = [
            'losslog_main_data_id',
            'deviation',
            'parent_id',
            'immediate_cause',
            'comment',
            'root_cause',
            'side_cause',
            'responsible_object_id',
            'affected_object_id',
            'upload_docs',
            'document',
            'is_own_needs_accounting',
            'is_flaring_accounting',
        ];

        // Берём только нужные ключи
        $newDecomposition = array_intersect_key($item, array_flip($usedKeys));

        // удалим пустые ключи, для исключения ошибок в дальнейших проверках
        $newDecomposition = array_filter(
            $newDecomposition,
            static fn ($value) => !(null === $value || $value === '' || $value === [] || $value === 0)
        );

        // side_cause возвращаем в массив, т.к. нулевое значение является возможным
        if (!array_key_exists('side_cause', $newDecomposition) && array_key_exists('side_cause', $item)) {
            $newDecomposition['side_cause'] = $item['side_cause'];
        }

        $mainRecords = 0;
        $childRecords = 0;
        $sentToir = 0;
        $updatedRecords = 0;
        $errorMessage = null;

        // Получаем все декомпозиции для всех целевых ID одним запросом
        $allTargetDateDecompositions = $this->model->getSimpleList(
            filter: ['losslog_main_data_id' => $input['ids']],
            fields: 'deviation, losslog_main_data_id, is_own_needs_accounting, is_flaring_accounting, id'
        );

        // Группируем декомпозиции по losslog_main_data_id и суммируем отклонения
        $deviationsByMainDataId = [];
        $existingSpecialRecords = []; // Хранит существующие записи Факел/СН

        foreach ($allTargetDateDecompositions as $decomposition) {
            $mainDataId = $decomposition['losslog_main_data_id'];

            // Если это запись Факел/СН, сохраняем её
            if ($decomposition['is_own_needs_accounting'] || $decomposition['is_flaring_accounting']) {
                $existingSpecialRecords[$mainDataId][] = $decomposition;
                continue;
            }

            // Для обычных записей суммируем отклонения
            if (!isset($deviationsByMainDataId[$mainDataId])) {
                $deviationsByMainDataId[$mainDataId] = 0;
            }
            $deviationsByMainDataId[$mainDataId] += $decomposition['deviation'];
        }

        foreach ($input['ids'] as $id) {
            $newDecomposition['losslog_main_data_id'] = $id;

            if (!empty($newDecomposition['affected_object_id']) && is_string($newDecomposition['affected_object_id'])) {
                $newDecomposition['affected_object_id'] = explode(',', $newDecomposition['affected_object_id']);
            }
            $newDecomposition['object'] = $input['object'];

            if ($newDecomposition['is_own_needs_accounting'] || $newDecomposition['is_flaring_accounting']) {
                // Проверяем существующую запись Факел/СН
                if (isset($existingSpecialRecords[$id])) {
                    $existingRecords = $existingSpecialRecords[$id];
                    $existingRecord = null;

                    // Ищем запись соответствующего типа
                    foreach ($existingRecords as $record) {
                        if (
                            ($newDecomposition['is_own_needs_accounting'] && $record['is_own_needs_accounting'] == 1)
                            || ($newDecomposition['is_flaring_accounting'] && $record['is_flaring_accounting'] == 1)
                        ) {
                            $existingRecord = $record;
                            break;
                        }
                    }

                    if ($existingRecord) {
                        if ($existingRecord['deviation'] == 0) {
                            $updateData = $newDecomposition;
                            $this->model->update($updateData, $existingRecord['id']);
                            $response = new JsonResponse([
                                'statusText'     => 'Запись обновлена',
                                'updatedRecords' => 1, // Добавляем информацию об обновлении
                            ]);
                        } else {
                            // Создаем новую запись
                            $response = $this->createRecord($newDecomposition, true);
                        }
                    } else {
                        // Создаем новую запись
                        $response = $this->createRecord($newDecomposition, true);
                    }
                } else {
                    // Создаем новую запись
                    $response = $this->createRecord($newDecomposition, true);
                }
            } else {
                if ($useDeviation) {
                    // Используем deviation из исходной записи
                    $newDecomposition['deviation'] = $item['deviation'];
                } else {
                    // Используем предварительно рассчитанную сумму отклонений
                    $targetDateDeviationSumm = $deviationsByMainDataId[$id] ?? 0;

                    // Получаем главную запись, и берём нужные поля для расчётов
                    $gatewayObjectModel = new LossLog_GatewayObject_Model();
                    $gatewayObject = $gatewayObjectModel->getItem(
                        $newDecomposition['losslog_main_data_id'],
                        'object, date, plan'
                    );

                    // Ищем потенциал по объекту из главной записи
                    $objectPotentialModel = new LossLog_ObjectPotential_Model();
                    $objectPotential = $objectPotentialModel->getObjectPotential($gatewayObject['object']);

                    // Т.к. вверху возвращается массив из нескольких записей с разным периодом дат
                    // фильтруем по текущей дате, в которую вносим новую декомпозицию
                    $objectPotentialByTargetDate = $objectPotentialModel->filterObjectPotentialByDate(
                        $objectPotential,
                        $gatewayObject['date']
                    );

                    // Если данные отсутствуют, логируем и переходим к следующей итерации
                    if ($objectPotentialByTargetDate === null) {
                        $errorMessage = "Не удалось найти потенциал для объекта {$gatewayObject['object']} 
                            на дату {$gatewayObject['date']}.";

                        return new JsonResponse(['errorMessage' => $errorMessage], Response::HTTP_BAD_REQUEST);
                    }

                    // Убеждаемся, что все необходимые ключи присутствуют в массиве $objectPotentialByTargetDate
                    if (!isset($objectPotentialByTargetDate['performance'], $objectPotentialByTargetDate['mdp'])) {
                        $errorMessage = sprintf(
                            'Отсутствуют необходимые данные (performance или mdp) для объекта %s на дату %s.',
                            $gatewayObject['object'],
                            $gatewayObject['date']
                        );

                        return new JsonResponse(['errorMessage' => $errorMessage], Response::HTTP_BAD_REQUEST);
                    }

                    // Считаем потенциал минус план на целевую дату
                    $potential = max($objectPotentialByTargetDate['performance'], $objectPotentialByTargetDate['mdp']);
                    $deltaPlan = $potential - $gatewayObject['plan'];

                    // Указываем новое значение декомпозиции отклонения,
                    // вычитая существующие декомпозиции на целевой дате
                    $newDecomposition['deviation'] = $deltaPlan - $targetDateDeviationSumm;
                }

                $response = $this->createRecord($newDecomposition, true);
            }

            $content = $response->getContent();

            if (!is_string($content)) {
                $errorMessage = 'Не удалось получить корректный ответ от метода createRecord.';
                break;
            }

            $responseData = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $errorMessage = 'Ошибка декодирования JSON: ' . json_last_error_msg();
                break;
            }

            if (isset($responseData['errorMessage'])) {
                $errorMessage = $responseData['errorMessage'];
                break;
            }

            // Если в ответе есть информация о главных и дочерних записях, добавляем её в счетчики
            if (isset($responseData['mainRecords'])) {
                $mainRecords += $responseData['mainRecords'];
            }

            // дочерние записи
            if (isset($responseData['childRecords'])) {
                $childRecords += $responseData['childRecords'];
            }

            // обновлённые записи
            if (isset($responseData['updatedRecords'])) {
                $updatedRecords += $responseData['updatedRecords'];
            }

            if (!empty($newDecomposition['document'])) {
                $sentToir++;
            }
        }

        if ($errorMessage) {
            return new JsonResponse(['errorMessage' => $errorMessage], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'statusText' => ($mainRecords > 0 ? "Создано {$mainRecords} декомпозиции" : '') .
                ($updatedRecords > 0 ? " Обновлено {$updatedRecords} декомпозиции" : '') .
                ($childRecords > 0 ? ", сформировано {$childRecords} дочерних" : '') .
                ($sentToir > 0 ? "<br> Отправлено {$sentToir} в ТОиР" : ''),
        ]);
    }
}
