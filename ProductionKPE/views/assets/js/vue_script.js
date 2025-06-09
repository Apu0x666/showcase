const app = Vue.createApp({
    data() {
        return {
            moduleName: 'LossLog_ProductionKPE',

            //Actions
            actionGetData: 'getData',

            //Данные
            data: [],
            monthList: [],
            objectsLinkedList: [],

            filters: {
                date: [
                    dayjs().startOf('year').format('YYYY-MM'),
                    dayjs().endOf('month').format('YYYY-MM')
                ],
                orderBy: 'id',
                orderDirection: 'ASC'
            },
        };
    },
    computed: {
        groupsData() {
            let result = [];
            result.push({
                key: 'business',
            });
            result.push({
                key: 'workshop',
            });
            result.push({
                key: 'object',
            });
            return result;
        },
        getRecords() {
            let result = [];

            Object.entries(this.objectsLinkedList).forEach(([, item]) => {
                let exists = true;
                if (typeof item !== 'object' || item === null){
                    exists = false;
                }

                let skip = true;

                Object.entries(item.loss || {}).forEach(([key, value]) => {
                    item[key] = {
                        title: parseFloat(value).toLocaleString('ru-RU', {
                            maximumFractionDigits: 5
                        }),
                        value: parseFloat(value),
                    };
                    if (value !== 0) {
                        skip = false;
                    }
                });

                if (skip) return;

                item.business = {
                    title: 'ГБ',
                    value: 'gb',
                };

                item.workshop = exists === false
                    ? {
                        html: 'ОБЪЕКТ УДАЛЁН',
                    }
                    : {
                        title: item.workshop?.workshop,
                        value: item.workshop?.id
                    };

                item.object = exists === false
                    ? {
                        html: '<p></p>',
                    }
                    : {
                        title: item.object,
                        value: item.id,
                    };

                item.techProcess = exists === false
                    ? {
                        html: '<p></p>',
                    }
                    : {
                        title: item.techProcess?.techProcessName ?? item.techProcess?.techProcess,
                        value: item.techProcess?.id,
                    };

                item.keyU = {
                    title: item.U,
                    value: item.U,
                };

                item.keyAEKS = {
                    title: item.AEKS,
                    value: item.AEKS,
                };

                item.keyA = {
                    title: item.A,
                    value: item.A,
                };

                item.keyP = {
                    title: item.P,
                    value: item.P,
                };

                item.keyQ = {
                    title: item.Q,
                    value: item.Q,
                };

                item.keyTargetOEE = {
                    title: this.formatPercent(item.targetOEE),
                    value: item.targetOEE,
                };

                item.keyOEE = {
                    title: this.formatPercent(item.OEE),
                    value: item.OEE,
                };

                item.keyTargetOAE = {
                    title: this.formatPercent(item.targetOAE),
                    value: item.targetOAE,
                };

                item.keyOAE = {
                    title: this.formatPercent(item.OAE),
                    value: item.OAE,
                };

                item.keyTargetTEEP = {
                    title: this.formatPercent(item.targetTEEP),
                    value: item.targetTEEP,
                };

                item.keyTEEP = {
                    title: this.formatPercent(item.TEEP),
                    value: item.TEEP,
                };

                result.push({item});
            });
            return result;
        },

        getTableHeaders() {
            return [
                {
                    key: 'business',
                    title: 'Бизнес',
                    value: 'ГБ'
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
                ...this.monthList,
                {
                    key: 'keyU',
                    title: 'Коэффициент использования (U)',
                },
                {
                    key: 'keyAEKS',
                    title: 'Коэффициент эксплуатационной доступности (Aэкс)',
                },
                {
                    key: 'keyA',
                    title: 'Коэффициент доступности (A)',
                },
                {
                    key: 'keyP',
                    title: 'Коэффициент производительности (Р) ',
                },
                {
                    key: 'keyQ',
                    title: 'Коэффициент качества (Q)',
                },

                {
                    title: 'ОЕЕ=АэксPQ',
                    class: 'bg-red center',
                    childs: [
                        {
                            title: 'Цель',
                            class: 'center',
                            key: 'keyTargetOEE',
                        },
                        {
                            title: 'Факт',
                            class: 'center',
                            key: 'keyOEE',
                        }
                    ]
                },
                {
                    title: 'ОАЕ=APQ',
                    class: 'bg-orange center',
                    childs: [
                        {
                            title: 'Цель',
                            class: 'center',
                            key: 'keyTargetOAE',
                        },
                        {
                            title: 'Факт',
                            class: 'center',
                            key: 'keyOAE',
                        }
                    ]
                },
                {
                    title: 'ТЕЕР=UAP*Q',
                    class: 'bg-yellow center',
                    childs: [
                        {
                            title: 'Цель',
                            class: 'center',
                            key: 'keyTargetTEEP',
                        },
                        {
                            title: 'Факт',
                            class: 'center',
                            key: 'keyTEEP',
                        }
                    ]
                },
            ];
        },
    },
    methods: {
        formatPercent(value, defaultValue = '') {
            try {
                const numericValue = parseFloat(value);
                return !isNaN(numericValue) && numericValue >= 0
                    ? `${(numericValue * 100).toFixed(1)}%`
                    : defaultValue;
            } catch (e) {
                return defaultValue;
            }
        },
        // Метод загружает данные для табличной части
        async loadData() {
            let filters = { ...this.filters };
            filters.perPage = this.per_page;
            filters.page = this.page + 1;

            if(Array.isArray(filters.date) && filters.date.length === 2 && filters.date[0] && filters.date[1]) {
                Object.entries(filters.date).forEach(([key, value]) => {
                    filters.date[key] = dayjs(value).format('YYYY-MM');
                });
            } else {
                filters.date = [
                    dayjs().startOf('year').format('YYYY-MM'), // Начало текущего года
                    dayjs().endOf('month').format('YYYY-MM')  // Конец текущего месяца
                ];
            }

            this.loading = true;
            BaseTemplate.showProgress();
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionGetData;
            try {
                let response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify({
                        filters: filters,
                    }),
                });
                let data = await response.json();
                if (response.ok) {
                    if (data === []) {
                        Notify.showWarning('Данные не найдены');
                    } else {
                        this.data = data;
                        this.monthList = data.monthList;
                        this.objectsLinkedList = data.objectsLinkedList;
                    }
                } else {
                    Notify.showError('Загрузка данных. Ошибка HTTP: ' + response.status);
                }
            } catch (e) {
                Notify.showError('Ошибка: '+e.message);
            }
            BaseTemplate.hideProgress();
        },

        updateUrlParams() {
            // Создаём объект с текущими параметрами URL
            const url = new URL(window.location.href);
            const params = url.searchParams;

            // Проверяем, что переданы корректные данные
            if (!Array.isArray(this.filters.date) || this.filters.date.length < 2) {
                params.delete('date_from');
                params.delete('date_to');
                window.history.replaceState({}, '', `${url.pathname}?${params.toString()}`);
                return;
            }

            const [dateFrom, dateTo] = this.filters.date;

            // Проверяем, что даты указаны
            if (!dateFrom || !dateTo) {
                params.delete('date_from');
                params.delete('date_to');
                window.history.replaceState({}, '', `${url.pathname}?${params.toString()}`);
                return;
            }

            // Обновляем параметры начальной и конечной даты
            params.set('date_from', dayjs(dateFrom).format('YYYY-MM'));
            params.set('date_to', dayjs(dateTo).format('YYYY-MM'));

            // Обновляем адресную строку без перезагрузки страницы
            window.history.replaceState({}, '', `${url.pathname}?${params.toString()}`);
        },

        setDateFiltersFromUrl() {
            const url = new URL(window.location.href);
            const params = url.searchParams;

            const dateFrom = params.get('date_from'); // Например, "2025-01"
            const dateTo = params.get('date_to');     // Например, "2025-03"

            // Если оба параметра присутствуют, устанавливаем их в filters.date
            if (dateFrom && dateTo) {
                this.filters.date = [dateFrom, dateTo];
                return true;
            } else {
                // Если дат нет, удаляем параметры из URL и очищаем фильтры
                params.delete('date_from');
                params.delete('date_to');
                window.history.replaceState({}, '', `${url.pathname}?${params.toString()}`);
                // Возвращаем false, чтобы у родителя не запускался дополнительный loadData
                return false;
            }
        },

        getExcludedFilterKeys() {
            return [

            ];
        },

        getTableFilters() {
            // Получаем массив ключей фильтров из columnsDef
            let columnsDef = this.getTableHeaders;
            return columnsDef
                .filter(item => item.key && !this.getExcludedFilterKeys().includes(item.key))
                .map(item => item.key);
        },
    },
    watch: {
        'filters.date'(newValue, oldValue) {
            if (newValue !== oldValue) {
                this.updateUrlParams();
                this.loadData();
            }
        },
    },
    async mounted() {

        // Устанавливаем фильтры даты из URL, если они существуют
        const dateFiltersSet = this.setDateFiltersFromUrl();

        // Если параметры даты были установлены из URL, не вызываем loadData сразу
        if (!dateFiltersSet) {
            // Если фильтры не установлены, вызываем loadData с фильтром по умолчанию
            await this.loadData();
        }
    },
});
initComponents(app, ['LossLog/ProductionKPE']).then(() => app.mount('#app'));
