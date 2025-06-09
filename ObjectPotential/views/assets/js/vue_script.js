const app = Vue.createApp({
    data() {
        return {
            moduleName: 'LossLog_ObjectPotential',

            //Actions
            actionGetPermissions: 'getPermissions',
            actionGetData: 'getData',
            actionDeleteData: 'delete',

            filterSettings: [
                {
                    key: 'date',
                    component: 'tm-datepicker',
                    options: {
                        title: 'Дата',
                        type: 'day',
                        format: 'DD.MM.YYYY',
                        valueType: 'YYYY-MM-DD',
                        range: true,
                    },
                    defaultValue: [
                        dayjs().format('YYYY-MM-01'),
                        dayjs().endOf('month').format('YYYY-MM-DD'),
                    ],
                },
            ],
            filters: {
                date: null,
                perPage: 50,
                page: 0,
                orderBy: 'id',
                orderDirection: 'ASC',
            },
            loading: true,
            pagination: false,
            perPage: 50,
            page: 0,
            pagesCount: 1,

            //Данные
            data: [],
            list: [],
            linkedList: [],

            //Права
            permissions: {
                edit: false,
                delete: false,
                dictionaries: false,
                admin: false,
            },

            visibleModal: false,

            columnsDef: [
                {
                    title: BaseLangs.Actions,
                    key: 'actions',
                },
                {
                    key: 'workshop',
                    title: 'Подразделение',
                },
                {
                    key: 'object',
                    title: 'Установка',
                },
                {
                    key: 'techProcess',
                    title: 'Технологический процесс',
                },
                {
                    key: 'performance',
                    title: 'Проектная производительность',
                },
                {
                    key: 'mdp',
                    title: 'МДП',
                },
                {
                    key: 'users',
                    title: 'Ответственный за учет потерь эксплуатационной дирекции/департамента',
                },
                {
                    key: 'dateStart',
                    title: 'Дата начала действия',
                },
                {
                    key: 'dateEnd',
                    title: 'Дата окончания действия',
                },
                {
                    key: 'user',
                    title: 'Последнее изменение сделал',
                },
                {
                    key: 'mtime',
                    title: 'Дата последних изменений',
                },
            ],
        };
    },
    computed: {
        groupsData() {
            let result = [];
            result.push({
                key: 'workshop',
            });
            result.push({
                key: 'object',
            });
            return result;
        },
        getRecords() {
            const formatDate = timestamp => new Date(timestamp * 1000).toISOString().slice(0, 19).replace('T', ' ');
            let result = [];

            Object.entries(this.linkedList).forEach(([key, item]) => {
                let notLinkedItem = this.list[key];
                let actionsArray = [];

                let exists = true;
                if (typeof item.objectId !== 'object' || item.objectId === null){
                    exists = false;
                }

                if (this.permissions.edit) {
                    actionsArray.push({
                        name: 'Edit',
                        icon: 'edit',
                        title: BaseLangs.Edit
                    });
                }
                if (this.permissions.delete) {
                    actionsArray.push({
                        name: 'Delete',
                        icon: 'delete',
                        title: BaseLangs.Delete
                    });
                }

                item.actions = {
                    actions: actionsArray,
                };
                item.id = {
                    title: notLinkedItem.id,
                    value: notLinkedItem.id,
                };
                item.workshop = exists === false
                    ? {
                        html: 'ОБЪЕКТ УДАЛЁН',
                    }
                    : {
                        title: item.objectId.workshop?.workshop,
                        value: item.objectId.workshop?.id
                    };

                item.object = {
                    title: item.objectId.object,
                    value: item.objectId.id,
                };

                item.object = exists === false
                    ? {
                        html: '<p></p>',
                    }
                    : {
                        title: item.objectId.object,
                        value: item.objectId.id,
                    };

                item.users = {
                    title: this.formatUsers(item.users),
                };
                item.user = {
                    title: item.userId.name,
                };
                item.techProcess = {
                    title: item.objectId.techProcess?.techProcessName ?? item.objectId.techProcess?.techProcess,
                    value: item.objectId.techProcess?.id,
                };

                item.techProcess = exists === false
                    ? {
                        html: '<p></p>',
                    }
                    : {
                        title: item.objectId.techProcess?.techProcessName ?? item.objectId.techProcess?.techProcess,
                        value: item.objectId.techProcess?.id,
                    };

                item.mtime = {
                    title: formatDate(item.mtime),
                };
                result.push({item});
            });
            return result;
        }
    },
    methods: {
        async loadFilters(filters) {
            this.filters = filters;

            try {
                await this.loadData();
            } catch (error) {
                Notify.showError('Ошибка при загрузке данных с фильтрами: ' + error.message);
            }
        },
        handlerActionClick(event) {
            if (event.name === 'Edit') {
                this.$refs.itemEditModal.editItem(event.row.id.value);
            }
            if (event.name === 'Delete') {
                this.deleteItem(event.row.id.value);
            }
        },
        formatUsers(users) {
            let result = '';
            users.forEach(function (user) {
                if (result.length > 0) {
                    result += ', ';
                }
                result += user.name;
            });
            return result;
        },

        deleteItem(id) {
            let self = this;
            Notify.confirmWarning('Вы действительно хотите удалить запись №: ' + id+ ' ?', function (state) {
                if (!state) {
                    return false;
                }
                let url = '/index.php?module=' + self.moduleName + '&action=' + self.actionDeleteData;
                fetch(url, {
                    method: 'POST',
                    body: JSON.stringify({
                        id: id,
                    }),
                }).then(response => response.json()).then(data => {
                    if (data.error) {
                        Notify.showError(data.error);
                    }
                    else {
                        self.loadData();
                    }
                });
            });
        },

        // Метод загружает данные для табличной части
        async loadData() {
            this.filters.perPage = this.per_page;
            this.filters.page = this.page + 1;
            let filter = this.filters;
            this.loading = true;
            BaseTemplate.showProgress();
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionGetData;
            try {
                let response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify({
                        filters: filter,
                    }),
                });
                let data = await response.json();
                if (response.ok) {
                    if (data === []) {
                        Notify.showWarning('Данные не найдены');
                    } else {
                        this.data = data;
                        this.list = data.list;
                        this.linkedList = data.linkedList;
                        this.pagesCount = data.totalPages;
                    }
                } else {
                    Notify.showError('Загрузка данных. Ошибка HTTP: ' + response.status);
                }
            } catch (e) {
                Notify.showError('Ошибка: '+e.message);
            }
            BaseTemplate.hideProgress();
        },

        insertItem (item, childs, prefix) {
            for (let key in childs) {
                item[prefix + key] = childs[key];
            }
            return item;
        },

        getExcludedFilterKeys() {
            return [
                'actions',
                'dateEnd',
                'dateStart',
                'id',
                'mdp',
                'mtime',
                'performance',
                'user',
                'users',
            ];
        },

        //Грузим права
        async loadPermissions() {
            BaseTemplate.showProgress();
            let body = {};
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionGetPermissions;
            try {
                let response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify(body)
                });

                if (!response.ok) {
                    Notify.showError('Ошибка HTTP: ' + response.status);
                }

                let data = await response.json();

                if (data === []) {
                    Notify.showWarning('Права не найдены');
                } else {
                    this.permissions = data;
                }
            } catch (e) {
                Notify.showError('Ошибка: '+e.message);
            }
            BaseTemplate.hideProgress();
        },

        getTableFilters() {
            // Получаем массив ключей фильтров из columnsDef
            return this.columnsDef
                .filter(item => item.key && !this.getExcludedFilterKeys().includes(item.key))
                .map(item => item.key);
        },

        async loadPage(n) {
            this.page = n;
            await this.loadData();
        },
    },

    mounted() {
        let self = this;
        this.loadPermissions().then(() => {
            self.loadData();
        });

    },
});
initComponents(app, ['LossLog/ObjectPotential']).then(() => app.mount('#app'));
