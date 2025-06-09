<?php

class LossLog_Tab_Helper
{
    /**
     * @param string $module
     * @param int    $queryTabGroupId
     * @param int    $queryTabId
     *
     * @return array<int, mixed>
     */
    public function tabGroups(string $module, int $queryTabGroupId, int $queryTabId): array
    {
        $tabs = [];

        $tabGroupsModel = new LossLog_Dictionaries_TabGroups_Model();
        $tabGroups = $tabGroupsModel->getList(
            fields: 'id, name'
        );

        if (empty($tabGroups)) {
            return $tabs;
        }

        // Таб по-умолчанию для группы
        $setupGatewayModel = new LossLog_SetupGateway_Model();
        $setupGatewayDefaultParams = $setupGatewayModel->getDefaultConfigParamsByGroup();

        foreach ($tabGroups as $tabGroup) {
            $tabGroupId = (int)$tabGroup['id'];

            if (empty($setupGatewayDefaultParams[$tabGroupId])) {
                continue;
            }

            $tabId = ($queryTabId && $queryTabGroupId === $tabGroupId)
                ? $queryTabId
                : $setupGatewayDefaultParams[$tabGroupId]['id'] ?? 0;

            $tabs[] = [
                'module' => 'LossLog_GatewayObject',
                'title'  => htmlspecialchars_decode($tabGroup['name']),
                'action' => 'index',
                'active' => $module === 'LossLog_GatewayObject' && $queryTabGroupId === $tabGroupId,
                'query'  => '&tab_group_id=' . $tabGroup['id'] . '&object=' . $tabId,
            ];
        }

        return $tabs;
    }

    /**
     * @param string $module
     * @param int    $queryTabGroupId
     *
     * @return array<int, mixed>
     */
    public function navTabsByGroup(string $module, int $queryTabGroupId): array
    {
        $tabs = [];

        if (!$queryTabGroupId) {
            return $tabs;
        }

        $setupGatewayModel = new LossLog_SetupGateway_Model();
        $navTabs = $setupGatewayModel->getConfigParamsByTabGroup($queryTabGroupId);

        foreach ($navTabs as $navTab) {
            $tabs[] = [
                'name'  => htmlspecialchars_decode($navTab['title']),
                'title' => htmlspecialchars_decode($navTab['title']),
                'href'  => [
                    'module'       => $module,
                    'tab_group_id' => $queryTabGroupId,
                    'object'       => $navTab['id'],
                ],
            ];
        }

        return $tabs;
    }
}
