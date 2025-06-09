<?php

use Ep\App\Core\Acl;
use Ep\App\Core\Request\CoreRequest;
use Ep\App\Core\Response\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LossLog_Economy_Controller extends LossLog_Controller
{
    public string $permissionName = 'losslog_economy';
    public int $version = 3;
    protected int $permissionType = Acl::READ;
    protected bool $checkPermission = true;

    public function __construct()
    {
        parent::__construct();
        $this->setModel(new LossLog_Economy_Model());
    }

    /**
     * Получение доступных коэффициентов для вывода.
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function getEconomyProductsListAction(): JsonResponse
    {
        $productsModel = new LossLog_Dictionaries_Products_Model();
        $productsList = $productsModel->getList(['is_enabled' => 1]);
        $productsList = LossLog_Data_Helper::convertArrayKeysToCamelCase($productsList);

        return new JsonResponse($productsList);
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
        // Проверка прав доступа
        if (!$acl->checkPermission($this->permissionName, Acl::DELETE)) {
            return new JsonResponse(
                ['errorMessage' => 'У вас нет прав на удаление'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Проверка наличия ID и получение элемента
        $item = $this->getItemFromRequest($request) ?:
            new JsonResponse(
                ['errorMessage' => 'Запись не найдена, либо не передан ID'],
                Response::HTTP_BAD_REQUEST
            );

        if ($item instanceof JsonResponse) {
            return $item;
        }

        // Удаление основной записи
        $result = $this->deleteRecord($item['id']);

        // Получаем контент и проверяем, является ли он строкой
        $content = (string)$result->getContent();
        $data = json_decode($content, true);

        if (!empty($data['statusText'])) {
            $this->getCurrentDb()->query(
                'DELETE FROM losslog_economy_values WHERE economy_row_id = :economyRowId',
                [':economyRowId' => $item['id']]
            );
        }

        return $result;
    }

    /**
     * Возвращает текущий объект базы данных.
     *
     * @return PDOdb
     */
    private function getCurrentDb(): PDOdb
    {
        return $this->model->getPdo();
    }
}
