<?php

use Ep\App\Core\Acl;
use Ep\App\Core\Request\CoreRequest;
use Ep\App\Core\Response\JsonResponse;
use Ep\App\Core\Response\XlsxResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class LossLog_Controller.
 */
class LossLog_GatewayObject_Controller extends LossLog_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->setModel(new LossLog_GatewayObject_Model());
        $this->dictionariesArray = [];
    }

    public function getDefaultDataAction(CoreRequest $request): JsonResponse
    {
        $gatewayModel = new LossLog_GatewayObject_Model();

        return new JsonResponse($gatewayModel->getDefaultData($request->toArray()));
    }

    public function getObjectPotentialAction(CoreRequest $request): JsonResponse
    {
        $request = $request->toArray();
        $objectPotentialModel = new LossLog_ObjectPotential_Model();
        $result = $objectPotentialModel->getObjectPotential($request['objectId']);

        return new JsonResponse($result);
    }

    /**
     * Получить настройки сжигания по плану на установке, за указанный диапазон.
     *
     * @param CoreRequest $request
     *
     * @return JsonResponse
     */
    public function burningRatePlanDataAction(CoreRequest $request): JsonResponse
    {
        $request = $request->toArray();

        if (
            empty($request['objectId'])
            || empty($request['dateStart'])
            || empty($request['dateEnd'])
        ) {
            return new JsonResponse([]);
        }

        $result = LossLog_GatewayObject_Data_Helper::getBurningRatePlanData(
            (int)$request['objectId'],
            (string)$request['dateStart'],
            (string)$request['dateEnd']
        );

        return new JsonResponse($result);
    }

    /**
     * Создание записей плана на месяц.
     *
     * Метод принимает массив данных, содержащий информацию о значениях на каждый день месяца,
     * и создает записи для каждого заполненного значения.
     *
     * @param CoreRequest $request запрос, содержащий данные для создания записей
     * @param Acl         $acl     объект управления доступом для проверки прав пользователя
     *
     * @return JsonResponse ответ с результатами создания записей для каждого дня месяца
     *
     * @throws Exception
     */
    public function createMonthPlanAction(CoreRequest $request, Acl $acl): JsonResponse
    {
        if (!$acl->checkPermission($this->permissionName, Acl::WRITE)) {
            return new JsonResponse(
                ['errorMessage' => 'У вас нет прав на добавление'],
                Response::HTTP_FORBIDDEN
            );
        }

        $input = LossLog_Data_Helper::convertArrayKeysToSnakeCase($request->toArray());

        $date = $input['date']; // Формат MM.YYYY
        [$month, $year] = explode('.', $date);
        $daysInMonth = $input['days_in_month'];
        $dailyValues = $input['daily_values'];

        $failedDates = [];
        $confirmDates = [];

        for ($i = 0; $i < count($dailyValues); $i++) {
            if (!empty($dailyValues[$i])) {
                $day = $daysInMonth[$i];
                $fullDate = sprintf('%04d-%02d-%02d', $year, $month, $day);

                $data = [
                    'date'         => $fullDate,
                    'plan'         => $dailyValues[$i],
                    'lu'           => $input['lu'],
                    'object'       => $input['object'],
                    'tech_process' => $input['tech_process'],
                    'workshop'     => $input['workshop'],
                ];

                // Валидация
                $resultValidate = $this->model->validateData($data);

                if (array_key_exists($resultValidate, $this->model->getErrors())) {
                    $failedDates[] = $fullDate;
                    continue;
                }

                try {
                    $resultSave = $this->model->addData($data);

                    if (!$resultSave) {
                        $failedDates[] = $fullDate;
                    }
                    $confirmDates[] = $fullDate;
                } catch (Exception) {
                    $failedDates[] = $fullDate;
                }
            }
        }

        $failedDatesString = implode(', ', $failedDates);
        $confirmDatesString = implode(', ', $confirmDates);

        $response = [];

        if (!empty($failedDates)) {
            $response['errorMessage'] = "Записи для дат {$failedDatesString} не могут быть добавлены";
        }

        if (!empty($confirmDatesString)) {
            $response['statusText'] = "Добавлены записи для дат {$confirmDatesString}";
        }

        return new JsonResponse($response, empty($failedDates) ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }

    /**
     * Удаление основной записи.
     *
     * @param CoreRequest $request
     * @param Acl         $acl
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function deleteAction(CoreRequest $request, Acl $acl): JsonResponse
    {
        if (!$acl->checkPermission($this->permissionName, Acl::DELETE)) {
            return new JsonResponse(
                ['errorMessage' => 'У вас нет прав на удаление'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Проверка наличия ID и получение элемента
        $item = $this->getItemFromRequest($request);

        if (empty($item)) {
            return new JsonResponse(
                ['errorMessage' => 'Запись не найдена, либо не передан ID'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Уникальная логика для удаления основной записи
        // Удаляем дочерние декомпозиции
        try {
            $decompositionPlan = new LossLog_DecompositionPlan_Model();
            $decompositionPlan->deleteByFilter(['losslog_main_data_id' => $item]);
            $decompositionFact = new LossLog_DecompositionFact_Model();
            $decompositionFact->deleteByFilter(['losslog_main_data_id' => $item]);
        } catch (Exception $e) {
            return new JsonResponse(
                ['errorMessage' => 'Произошла ошибка при удалении связанных записей'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        try {
            // Вызов общей логики удаления
            return $this->deleteRecord($item['id']);
        } catch (Exception $e) {
            return new JsonResponse(
                ['errorMessage' => 'Произошла ошибка при удалении основной записи'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @throws Exception
     */
    public function checkObjectAccessAction(CoreRequest $request): JsonResponse
    {
        $objectId = $request->toArray()['objectId'];

        if (isset($objectId)) {
            $objectModel = new LossLog_Dictionaries_Objects_Model();
            $item = $objectModel->getItem($objectId);

            if (empty($item)) {
                return new JsonResponse(
                    ['errorMessage' => 'Неправильно задан объект'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $setupGateWay = new LossLog_SetupGateway_Model();
            $availableTabs = $setupGateWay->getConfigParams();

            if (count($availableTabs) > 0) {
                $exists = array_filter(
                    $availableTabs,
                    static fn ($tab) => isset($tab['id']) && $tab['id'] === (int)$objectId
                );

                if (!empty($exists)) {
                    return new JsonResponse(['statusText' => ''], Response::HTTP_OK);
                }

                return new JsonResponse(
                    ['errorMessage' => 'Нет доступа к объекту'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            return new JsonResponse(
                ['errorMessage' => 'Нет доступа к объекту'],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(
            ['errorMessage' => 'Нет доступа к объекту'],
            Response::HTTP_BAD_REQUEST
        );
    }

    /**
     * @throws Exception
     */
    public function exportToExcelAction(CoreRequest $request, Acl $acl): XlsxResponse
    {
        if (!$acl->checkPermission($this->permissionName, Acl::EXPORT_DATA)) {
            throw new Exception(tr('PERMISSION_ERROR'), Response::HTTP_FORBIDDEN);
        }

        [$filter, $ordering] = $this->getFilters($request);
        $request = $request->toArray();
        $data = $this->model->getData($filter, $ordering, $request);

        // Ищем потенциал по объекту из главной записи
        $objectPotentialModel = new LossLog_ObjectPotential_Model();

        foreach ($data['linkedList'] as $i => $item) {
            $objectPotential = $objectPotentialModel->getObjectPotential($item['object']['id']);
            // Т.к. вверху возвращается массив из нескольких записей с разным периодом дат
            // фильтруем по текущей дате, в которую вносим новую декомпозицию
            $objectPotentialByTargetDate = $objectPotentialModel->filterObjectPotentialByDate(
                $objectPotential,
                $item['date']
            );

            $data['linkedList'][$i]['performance'] = $objectPotentialByTargetDate['performance'] ?? 0;
            $data['linkedList'][$i]['mdp'] = $objectPotentialByTargetDate['mdp'] ?? 0;
        }

        $gateWayModel = new LossLog_SetupGateway_Model();
        $objectTitle = $gateWayModel->getItemByFilter(['object_id' => $request['initial']['object']], '', 'title');

        if (empty($objectTitle)) {
            throw new Exception('Запрошенный объект не существует', Response::HTTP_BAD_REQUEST);
        }

        $filename = 'УПП - ' . $objectTitle['title'] .
            ' [' . $request['filters']['date'][0] . '-' . $request['filters']['date'][1] . ']';

        $addData = [
            'title'    => (string)preg_replace('/[\\\\\/:*?"<>|]|[\x00-\x1F\x7F]/u', '', $objectTitle['title']),
            'filename' => (string)preg_replace('/[\\\\\/:*?"<>|]|[\x00-\x1F\x7F]/u', '', $filename),
            'unit'     => (string)preg_replace('/[\\\\\/:*?"<>|]|[\x00-\x1F\x7F]/u', '', $request['initial']['unit']),
        ];

        $excelHelper = new LossLog_GatewayObject_Excel_Helper();

        return $excelHelper->makeExcel($data, $addData);
    }

    /**
     * Загружает внутренние табы, исходя из выбранной группы.
     *
     * @return JsonResponse
     */
    public function navTabsAction(CoreRequest $request): JsonResponse
    {
        $queryTabGroupId = $request->body->getInt('tabGroupId');

        $tabHelper = new LossLog_Tab_Helper();
        $tabs = $tabHelper->navTabsByGroup('LossLog_GatewayObject', $queryTabGroupId);

        return new JsonResponse(['navTabs' => $tabs]);
    }
}
