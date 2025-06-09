<template>
    <div class="tabs tm-sticky-left tm-sticky-top tm-second-level-tabs">
        <tm-nav-tabs :options="navTabs" />
    </div>
</template>

<script>
export default {
    // eslint-disable-next-line vue/multi-word-component-names
    name: 'losslog-gatewayobject-nav-tabs',
    data() {
        return {
            moduleName: 'LossLog_GatewayObject',
            actionLoadNavTabs: 'navTabs',
            navTabs: [],
        };
    },
    methods: {
        async loadTabs() {
            const urlParams = new URLSearchParams(window.location.search);
            const tabGroupId = urlParams.get('tab_group_id');

            if (!tabGroupId) {
                return;
            }

            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionLoadNavTabs;
            try {
                let response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify({
                        tabGroupId: tabGroupId
                    })
                });
                let data = await response.json();
                if (response.ok) {
                    this.navTabs = data.navTabs;
                    if (!this.navTabs || this.navTabs.length === 0) {
                        Notify.showWarning('Нет доступных вкладок для выбранной группы');
                    }

                    this.$nextTick(() => {
                        this.recalcTabsPosition();
                        window.addEventListener('resize', this.recalcTabsPosition);
                    });
                } else {
                    Notify.showError(data.errorMessage);
                    return false;
                }
            } catch (e) {
                Notify.showError('Ошибка: ' + e.message);
                return false;
            }
        },
        recalcTabsPosition() {
            const firstLevelTabs = document.querySelector('.tm-module-content > div.tabs:not(.tm-second-level-tabs)');
            const secondLevelTabs = document.querySelector('.tm-second-level-tabs');
            if (firstLevelTabs && secondLevelTabs && firstLevelTabs?.offsetHeight > 0) {
                const firstLevelHeight = firstLevelTabs.offsetHeight;
                secondLevelTabs.style.top = `${firstLevelHeight}px`;
            }
        }
    },
    mounted() {
        this.loadTabs();
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.recalcTabsPosition);
    },
};
</script>

<style>
.tm-module-content .tm-second-level-tabs {
    z-index: 97;
}

.tm-module-content .tm-table-sticky-header thead {
    z-index: 96;
}
</style>
