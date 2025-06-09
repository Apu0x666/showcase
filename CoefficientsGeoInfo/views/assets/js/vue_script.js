const app = Vue.createApp({
    data() {
        return {
            moduleName: 'LossLog_CoefficientsGeoInfo',

            //Actions
            actionGetPermissions: 'getPermissions',
            actionCoefficientsList: 'getCoefficientsList',
            actionGetData: 'getData',

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
            data: {
                list: {},
                linkedList: {},
            },

            //Права
            permissions: {
                edit: false,
                delete: false,
                dictionaries: false,
                admin: false,
            },

            visibleModal: false,

            //список технологических коэффициентов для вывода/заполнения
            coefficients: {},
        };
    },
    computed: {
        groupsData() {
            let result = [];
            result.push({
                key: 'object',
            });
            result.push({
                key: 'techProcess',
            });
            return result;
        },
        getRecords() {
            const formatDate = timestamp => new Date(timestamp * 1000).toISOString().slice(0, 19).replace('T', ' ');
            let result = [];

            Object.entries(this.data.linkedList).forEach(([key, item]) => {
                let notLinkedItem = this.data.list[key];
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
                item.id = {
                    title: notLinkedItem.id,
                    value: notLinkedItem.id,
                };

                item.object = exists === false
                    ? {
                        html: 'ОБЪЕКТ УДАЛЁН',
                    }
                    : {
                        title: item.objectId.object,
                        value: item.objectId.id
                    };

                item.techProcess = exists === false
                    ? {
                        html: '<p></p>',
                    }
                    : {
                        title: item.objectId.techProcess?.techProcessName ?? item.objectId.techProcess?.techProcess,
                        value: item.objectId.techProcess?.id,
                    };

                Object.entries(notLinkedItem.coefficients || {}).forEach(([key, value]) => {
                    // Заменяем запятую на точку перед преобразованием
                    const normalizedValue = String(value).replace(',', '.');

                    // Преобразуем в число
                    item[`coefficient${key}`] = {
                        title: parseFloat(normalizedValue) !== 0 ? parseFloat(normalizedValue) : '',
                    };
                });

                item.actions = {
                    actions: actionsArray,
                };

                item.responsible = {
                    title: item.responsible.name,
                };
                item.user = {
                    title: item.userId.name,
                };
                item.mtime = {
                    title: formatDate(item.mtime),
                };

                result.push({item});
            });

            return result;
        },

        getTableHeaders() {
            let coefficientHeaders = Array.isArray(this.coefficients)
                ? this.coefficients.map(el => {
                    return {
                        key: 'coefficient'+el.id,
                        title: el.coefficientName + ' <br> <i>'+ el.coefficientAbbreviate +'</i>',
                    };
                })
                : [];

            return [
                {
                    title: BaseLangs.Actions,
                    key: 'actions',
                },
                {
                    key: 'id',
                    title: '№ П/П',
                    isPin: true,
                    isSort: true,
                },
                {
                    key: 'object',
                    title: 'Установка',
                },
                {
                    key: 'techProcess',
                    title: 'Технологический процесс',
                },
                ...coefficientHeaders,
                {
                    key: 'responsible',
                    title: 'Ответственный за предоставление геологической информации',
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
            ];
        },
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
                this.$refs.geoItemEditModal.editItem(event.row.id.value);
            }
            if (event.name === 'Delete') {
                this.$refs.geoItemEditModal.deleteItem(event.row.id.value);
            }
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

        async getCoefficientsList() {
            BaseTemplate.showProgress();
            let body = {};
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionCoefficientsList;
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
                    Notify.showWarning('Коэффициенты не добавлены');
                } else {
                    this.coefficients = data;
                }
            } catch (e) {
                Notify.showError('Ошибка: '+e.message);
            }
            BaseTemplate.hideProgress();
        },

        // Грузим права
        async loadPermissions() {
            BaseTemplate.showProgress();
            let body = {};
            let url = `/index.php?module=${this.moduleName}&action=${this.actionGetPermissions}`;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify(body),
                });

                if (!response.ok) {
                    Notify.showError('Ошибка HTTP: ' + response.status);
                    return;
                }

                const data = await response.json();

                if (Array.isArray(data) && data.length === 0) {
                    Notify.showWarning('Права не найдены');
                } else {
                    this.permissions = data;
                }
            } catch (e) {
                Notify.showError('Ошибка: ' + e.message);
            } finally {
                BaseTemplate.hideProgress();
            }
        },

        getTableFilters() {
            return [
                'object',
                'techProcess',
            ];
        },

        async loadPage(n) {
            this.page = n;
            await this.loadData();
        },
    },

    async mounted() {
        await Promise.all([this.loadPermissions(), this.getCoefficientsList()]);
        this.loadData();
    },

});
initComponents(app, ['LossLog/CoefficientsGeoInfo']).then(() => app.mount('#app'));
