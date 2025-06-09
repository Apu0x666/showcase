<?php

use Ep\App\Core\Response\JsonResponse;

/**
 * Контроллер Модели Цеха.
 */
class LossLog_SetupGateway_Controller extends LossLog_Controller
{
    public string $permissionName = 'losslog_gateway';

    public function __construct()
    {
        parent::__construct();
        $this->setModel(new LossLog_SetupGateway_Model());
    }

    /**
     * Получение доступных объектов для селекта.
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function getObjectsAction(): JsonResponse
    {
        $result = LossLog_Data_Helper::getObjects();

        return new JsonResponse($result);
    }

    public function getHintAction(): JsonResponse
    {
        $objectRelationModel = new LossLog_Dictionaries_ObjectsRelations_Model();
        $hint = $objectRelationModel->dictionaryDescription;

        return new JsonResponse($hint);
    }
}
