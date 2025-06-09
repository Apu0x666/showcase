<?php

use Ep\App\Core\Acl;
use Ep\App\Core\Request\CoreRequest;
use Ep\App\Core\Response\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LossLog_ProductionKPE_Controller extends ControllerBase
{
    public string $permissionName = 'losslog_production_kpe';
    public int $version = 3;
    protected int $permissionType = Acl::READ;
    protected bool $checkPermission = true;

    protected LossLog_ProductionKPE_Model $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new LossLog_ProductionKPE_Model();
    }

    /**
     * Стартовая страница.
     *
     * @param Acl $acl *
     *
     * @return Response
     *
     * @throws Exception
     */
    public function indexAction(Acl $acl): Response
    {
        if (!$acl->checkPermission($this->permissionName, Acl::READ)) {
            throw new Exception('У вас нет прав на просмотр модуля', Response::HTTP_FORBIDDEN);
        }

        return $this->render('index.tpl');
    }

    /**
     * @throws Exception
     */
    public function getDataAction(CoreRequest $request): JsonResponse
    {
        $request = $request->body->all();

        $result = $this->model->getData($request['filters']);
        $result['filter'] = $request['filters'];

        return new JsonResponse($result, Response::HTTP_OK);
    }
}
