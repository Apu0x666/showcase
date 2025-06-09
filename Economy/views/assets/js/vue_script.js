const app = Vue.createApp({
    data() {
        return {
            moduleName: 'LossLog_Economy',

            //Actions
            actionGetPermissions: 'getPermissions',
            actionEconomyProductsList: 'getEconomyProductsList',
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
            products: {},
        };
    },
    computed: {
        groupsData() {
            let result = [];
            result.push({
                key: 'licensedAreaId',
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
                if (typeof item.licensedAreaId !== 'object' || item.licensedAreaId === null){
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

                item.actions = {
                    actions: actionsArray,
                };

                item.licensedAreaId = exists === false
                    ? {
                        html: '<p>ЛУ УДАЛЁН</p>',
                    }
                    : {
                        title: item.licensedAreaId.name,
                        value: item.licensedAreaId.id,
                    };

                Object.entries(notLinkedItem.products || {}).forEach(([key, value]) => {
                    item[`product${key}`] = {
                        title: parseFloat(String(value)) !== 0 ? this.formatCurrency(parseFloat(String(value))) : '',
                    };
                });

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
            let productsHeaders = Array.isArray(this.products)
                ? this.products.map(el => {
                    return {
                        title: el.productName,
                        childs: [
                            {
                                title: el.currencyUnit,
                                key: 'product'+el.id,
                            }
                        ],
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
                    key: 'licensedAreaId',
                    title: 'Наименование Компании/участка',
                },
                {
                    title: 'Готовый продукт',
                    childs: [
                        ...productsHeaders,
                    ],
                },
                {
                    key: 'responsible',
                    title: 'Ответственный за предоставление технологической информации',
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
        formatCurrency(amount) {
            return Number(amount).toLocaleString('ru-RU', {
                style: 'currency',
                currency: 'RUB',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },
        handlerActionClick(event) {
            if (event.name === 'Edit') {
                this.$refs.itemEditModal.editItem(event.row.id.value);
            }
            if (event.name === 'Delete') {
                this.$refs.itemEditModal.deleteItem(event.row.id.value);
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

        getExcludedFilterKeys() {
            return [
                'actions',
                'dateEnd',
                'dateStart',
                'id',
                'mtime',
                'responsible',
                'user',
            ];
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

        async getEconomyProductsList() {
            BaseTemplate.showProgress();
            let body = {};
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionEconomyProductsList;
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
                    Notify.showWarning('Продукты не добавлены');
                } else {
                    this.products = data;
                }
            } catch (e) {
                Notify.showError('Ошибка: '+e.message); // Выводим текст ошибки
            }
            BaseTemplate.hideProgress();
        },

        getTableFilters() {
            // Получаем массив ключей фильтров из columnsDef
            let columnsDef = this.getTableHeaders;
            return columnsDef
                .filter(item => item.key && !this.getExcludedFilterKeys().includes(item.key))
                .map(item => item.key);
        },

        async loadPage(n) {
            this.page = n;
            await this.loadData();
        },
    },
    async mounted() {
        await Promise.all([this.loadPermissions(), this.getEconomyProductsList()]);
        this.loadData();
    },
});
initComponents(app, ['LossLog/Economy']).then(() => app.mount('#app'));
