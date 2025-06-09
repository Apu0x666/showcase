<?php

use Ep\App\Core\Response\JsonResponse;

/**
 * Контроллер Модели Цеха.
 */
class LossLog_ObjectPotential_Controller extends LossLog_Controller
{
    public string $permissionName = 'losslog_object_potential';

    public function __construct()
    {
        parent::__construct();
        $this->setModel(new LossLog_ObjectPotential_Model());
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
}
