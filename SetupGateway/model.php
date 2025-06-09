<?php

use Ep\App\Service\AuthenticationService;
use LossLog\LossLog_Interface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class LossLog_SetupGateway_Model.
 */
class LossLog_SetupGateway_Model extends Model_Extended implements LossLog_Interface
{
    protected string $table = 'losslog_gateway';

    public array $fields = [
        'id'           => ['type' => 'primarykey'],
        'title'        => ['type' => 'string'],
        'tab_group_id' => [
            'type' => 'int',
            'link' => 'LossLog_Dictionaries_TabGroups_Model',
        ],
        'object_id' => [
            'type'    => 'int',
            'link'    => 'LossLog_Dictionaries_Objects_Model',
            'comment' => 'Установка',
        ],
        'users' => [
            'type'     => 'string',
            'link'     => 'User_WithPosition',
            'multiple' => 1,
            'comment'  => 'Пользователи, имеющие доступ к модулю',
        ],
        'enable'               => ['type' => 'bool'],
        'functional_page'      => ['type' => 'bool'],
        'own_needs_accounting' => ['type' => 'bool'],
        'flaring_accounting'   => ['type' => 'bool'],
        'bt_formula'           => ['type' => 'string'],
        'pt_formula'           => ['type' => 'string'],
        'sgk_formula'          => ['type' => 'string'],
        'oil_formula'          => ['type' => 'string'],
        'helium_formula'       => ['type' => 'string'],

        'ctime'      => ['type' => 'int'],
        'mtime'      => ['type' => 'int'],
        'created_id' => ['type' => 'int'],
        'user_id'    => ['type' => 'int', 'link' => 'User'],
    ];

    public const ERROR_EMPTY_TITLE = 1;
    public const ERROR_EMPTY_OBJECT_ID = 2;
    public const ERROR_EMPTY_TABGROUP_ID = 3;
    public const SUCCESS_VALID = 0;
    public const ERROR_NOT_FOUND = 404;

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
     * Метод возвращает мап ошибок модели.
     *
     * @return array<int, string>
     */
    public static function getErrors(): array
    {
        return [
            self::ERROR_EMPTY_TITLE       => 'Значение поля Название страницы не установлено',
            self::ERROR_EMPTY_OBJECT_ID   => 'Значение поля Объект не установлено',
            self::ERROR_EMPTY_TABGROUP_ID => 'Значение поля Группа вкладки не установлено',
            self::ERROR_NOT_FOUND         => 'Ошибка не найдена',
        ];
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
            'object_id'    => self::ERROR_EMPTY_OBJECT_ID,
            'tab_group_id' => self::ERROR_EMPTY_TABGROUP_ID,
            'title'        => self::ERROR_EMPTY_TITLE,
        ];

        $required = LossLog_Data_Helper::checkRequiredFields($requiredFields, $params);

        if ($required) {
            return $required;
        }

        return self::SUCCESS_VALID;
    }

    /**
     * @param array<string, mixed> $filter
     * @param string               $ordering
     * @param array<string, mixed> $request
     *
     * @return array<string, array<string, mixed>> возвращает массив данных
     */
    public function getData(array $filter, string $ordering, $request = []): array
    {
        $list = $this->getIndexedSimpleList($filter, 'id', $ordering);
        $linkedList = $this->getLinkedList($list, true);

        foreach ($linkedList as &$item) {
            if (!is_array($item['object_id'])) {
                continue;
            }
            $item['object_id'] = $item['object_id']['workshop']['workshop'] . ' (' . trim(
                $item['object_id']['object'] . ' - ' .
                    (!empty($item['object_id']['tech_process']['tech_process_name']) ?
                        $item['object_id']['tech_process']['tech_process_name'] :
                        $item['object_id']['tech_process']['tech_process']),
                ' -'
            ) . ')';
        }

        $linkedList = LossLog_Data_Helper::convertArrayKeysToCamelCase($linkedList);

        return compact(
            'list',
            'linkedList',
        );
    }

    /**
     * @param int    $objectId
     * @param string $fields
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getFieldsByObjectId(int $objectId, string $fields = '*'): array
    {
        if (empty($objectId)) {
            throw new Exception('Не передан номер объекта для поиска', Response::HTTP_BAD_REQUEST);
        }

        return $this->getItemByFilter(['object_id' => $objectId], '', $fields);
    }

    /**
     * Проверяет, имеет ли пользователь доступ к элементу.
     *
     * @param array<string, mixed> $item   Элемент списка
     * @param null|int             $userId ID пользователя
     *
     * @return bool
     */
    private function hasUserAccess(array $item, ?int $userId): bool
    {
        if ($userId === null || empty($item['users'])) {
            return false;
        }

        $users = array_map('intval', explode(',', $item['users']));

        return in_array($userId, $users, true);
    }

    /**
     * @return array<int, mixed>
     *
     * @throws Exception
     */
    public function getConfigParams(): array
    {
        $userId = $this->getCurrentUserId();

        $list = $this->getIndexedSimpleList(
            filter: ['enable' => 1],
            fields: 'id, object_id, tab_group_id, title, users'
        );
        $tabsData = [];

        foreach ($list as $item) {
            if (!$this->hasUserAccess($item, $userId)) {
                continue;
            }

            $tabsData[] = [
                'tab_group_id' => $item['tab_group_id'],
                'title'        => $item['title'],
                'id'           => $item['object_id'],
            ];
        }

        return $tabsData;
    }

    /**
     * @return array<int, mixed>
     *
     * @throws Exception
     */
    public function getDefaultConfigParamsByGroup(): array
    {
        $userId = $this->getCurrentUserId();

        $list = $this->getIndexedSimpleList(
            filter: [
                'enable' => 1,
            ],
            fields: 'id, object_id, tab_group_id, title, users',
            keyField: 'tab_group_id',
            grouping: true
        );

        $tabsGroupedDefaultData = [];

        foreach ($list as $tabGroupId => $items) {
            if ((int)$tabGroupId > 0) {
                foreach ($items as $item) {
                    if ($this->hasUserAccess($item, $userId)) {
                        $tabsGroupedDefaultData[$tabGroupId] = [
                            'tab_group_id' => $item['tab_group_id'],
                            'title'        => $item['title'],
                            'id'           => $item['object_id'],
                        ];
                        break;
                    }
                }
            }
        }

        return $tabsGroupedDefaultData;
    }

    /**
     * @return array<int, mixed>
     *
     * @throws Exception
     */
    public function getConfigParamsByTabGroup(int $tabGroup): array
    {
        $userId = $this->getCurrentUserId();

        $list = $this->getIndexedSimpleList(
            filter: ['enable' => 1, 'tab_group_id' => $tabGroup],
            fields: 'id, object_id, tab_group_id, title, users',
        );
        $navTabsData = [];

        foreach ($list as $item) {
            if (!$this->hasUserAccess($item, $userId)) {
                continue;
            }

            $navTabsData[] = [
                'tab_group_id' => $item['tab_group_id'],
                'title'        => $item['title'],
                'id'           => $item['object_id'],
            ];
        }

        return $navTabsData;
    }

    /**
     * Возвращает ID текущего пользователя или null, если пользователь не авторизован.
     *
     * @return null|int
     */
    private function getCurrentUserId(): ?int
    {
        /** @var AuthenticationService $auth */
        $auth = Di::getInstance()->getDi()[AuthenticationService::class];

        return $auth->isAuthorizedUser() ? $auth->getCurrentUserId() : null;
    }
}
