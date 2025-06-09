/* eslint-disable no-undef */
dayjs.extend(window.dayjs_plugin_customParseFormat);

const app = Vue.createApp({
    data() {
        return {
            moduleName: 'LossLog_GatewayObject',

            //Actions
            actionGetPermissions: 'getPermissions',
            actionGetData: 'getData',
            actionExcel: 'exportToExcel',
            actionDeleteData: 'delete',
            actionObjectPotential: 'getObjectPotential',
            actionObjectBurningRatePlanData: 'burningRatePlanData',
            actionDefaultData: 'getDefaultData',
            actionCheckObjectAccess: 'checkObjectAccess',

            filters: {
                orderBy: 'date',
                orderDirection: 'ASC',
                date: [dayjs().startOf('month').format('YYYY-MM-DD'), dayjs().endOf('month').format('YYYY-MM-DD')],
            },
            pagination: false,
            perPage: 50,
            page: 0,
            pagesCount: 1,

            //Данные
            data: [],
            list: [],
            linkedList: [],
            burningRatePlanData: [],
            objectPotentialData: [],
            objectId: null,
            initial: {
                techProcess: [],
                workshop: null,
                lu: null,
                object: null,
                unit: '',
                functionalPage: false,
            },
            datesWithoutPotential: [],

            prevData: [],

            //Права
            permissions: {
                edit: false,
                delete: false,
                dictionaries: false,
                admin: false,
            },

            decompositionPlanPermissions: {},
            decompositionFactPermissions: {},

            visibleModal: false,

            resizeObserver: null,

            groups: [
                'id',
                'actions',
                'date',
                'workshop',
                'techProcess',
                'performance',
                'mdp',
                'potential',
                'lu',
                'object',
                'deltaPlan',
                'plan',
                'deviation',
                'fact',
                'deltaFact',
            ],

            errorStatuses: {
                400: 'Недостаточно данных',
                403: 'У вас нет прав на это действие',
                500: 'Критическая ошибка сервера',
            },
        };
    },
    computed: {
        records() {
            let result = [];

            if (!this.linkedList || Object.keys(this.linkedList).length === 0 || !this.data?.linkedList) {
                //статус загрузки данных, они ещё сырые, поэтому показываем предыдущий набор
                //для исключения визуального дискомфорта с отображением
                return this.prevData;
            }

            Object.entries(this.linkedList).forEach(([key, item]) => {
                let baseItem = JSON.parse(JSON.stringify(this.data.linkedList[key]));
                const notLinkedItem = this.list[key] || {};
                let actionsArray = [];
                let formattedDate = dayjs(notLinkedItem.date);

                let objectPotential = this.objectPotentialData.find(it => {
                    // Проверяем совпадение objectId
                    const isMatchingObject = it.objectId === this.initial.object;

                    // Преобразуем границы диапазона и formattedDate в dayjs для сравнения
                    const dateStart = dayjs(it.dateStart, 'YYYY-MM-DD');
                    const dateEnd = dayjs(it.dateEnd, 'YYYY-MM-DD');

                    // Проверяем, входит ли targetDate в диапазон (включая границы)
                    const isDateInRange =
                        (formattedDate.isAfter(dateStart) || formattedDate.isSame(dateStart)) &&
                        (formattedDate.isBefore(dateEnd) || formattedDate.isSame(dateEnd));

                    return isMatchingObject && isDateInRange;
                });

                if (!objectPotential) {
                    let date = dayjs(notLinkedItem.date, 'YYYY-MM-DD').format('DD.MM.YYYY');
                    Notify.showWarning('Не найден потенциал установки для даты: '
                        + date
                        + '<br> Установлены нулевые значения');

                    this.datesWithoutPotential.push(notLinkedItem.date);

                    objectPotential = {
                        mdp: 0,
                        performance: 0,
                    };
                }

                if (this.permissions.edit) {
                    actionsArray.push({
                        name: 'Edit',
                        icon: 'edit',
                        title: BaseLangs.Edit
                    });
                }
                item.id = {
                    title: notLinkedItem.id,
                    value: notLinkedItem.id,
                };
                item.date = {
                    title: notLinkedItem.date,
                    value: notLinkedItem.date,
                };
                item.lu = {
                    title: baseItem.object.workshop?.licensedAreas.code,
                };
                item.workshop = {
                    title: baseItem.object.workshop?.workshop,
                };
                item.techProcess = {
                    title: baseItem.object.techProcess?.techProcessName ?? baseItem.object.techProcess?.techProcess,
                };
                item.object = {
                    title: baseItem.object.object,
                    value: baseItem.object.id
                };
                item.date = {
                    title: dayjs(notLinkedItem.date, 'YYYY-MM-DD').format('DD.MM.YYYY'),
                    value: notLinkedItem.date
                };

                item.mdp = objectPotential.mdp;
                item.performance = objectPotential.performance;

                item.potential = objectPotential.performance > objectPotential.mdp ?
                    objectPotential.performance : objectPotential.mdp;

                let deltaFact = parseFloat(notLinkedItem.plan) - parseFloat(notLinkedItem.fact);
                let deltaPlan = parseFloat(item.potential) - parseFloat(notLinkedItem.plan);

                item.plan = {
                    title: parseFloat(notLinkedItem.plan).toFixed(1),
                    value: notLinkedItem.plan
                };
                item.fact = {
                    title: parseFloat(notLinkedItem.fact).toFixed(1),
                    value: notLinkedItem.fact
                };

                let totalDeviationPlan = 0;
                Object.entries(item.decompositionPlanItems).forEach(([key, planItem]) => {
                    let planArray = [];
                    const planItemByKey = item.decompositionPlanItems[key];

                    if (!planItem || typeof planItem !== 'object') return;

                    if (this.permissions.decompositionPlan.edit) {
                        planArray.push({
                            name: 'EditPlan',
                            icon: 'edit',
                            id: planItem.id,
                            title: BaseLangs.Edit
                        });
                        if (this.permissions.decompositionPlan.delete) {
                            planArray.push({
                                name: 'PlanDelete',
                                icon: 'delete',
                                id: planItem.id,
                                title: BaseLangs.Delete
                            });
                        }
                        planArray.push({
                            name: 'DividePlan',
                            icon: 'tree',
                            id: planItem.id,
                            title: 'Разделить'
                        });
                        planArray.push({
                            name: 'MergePlan',
                            icon: 'docs',
                            id: planItem.id,
                            title: 'Объединить'
                        });
                        if (!planItem.isConsequencesAccounting) {
                            planArray.push({
                                name: 'PlanCopyForDate',
                                icon: 'date',
                                id: planItem.id,
                                title: 'Скопировать пояснения'
                            });
                        }
                    }

                    if (!this.isAccounting(planItemByKey)) {
                        totalDeviationPlan += parseFloat(planItemByKey.deviation);
                    }
                    planItemByKey.planActions = {
                        actions: planArray,
                        html: '<p class="idText">#' + planItem.id
                            + this.itemIcon(planItemByKey, true) + '</p>',
                    };

                    this.processDocument(planItem, 'decompositionPlanItems', item, key);

                    planItemByKey.immediateCause = {
                        title: planItem.immediateCause?.name,
                        value: planItem.immediateCause?.id,
                    };

                    planItemByKey.rootCause = {
                        title: planItem.rootCause?.name,
                        value: planItem.rootCause?.id,
                    };
                    planItemByKey.sideCause = {
                        title: sideCause[planItem.sideCause],
                        value: planItem.sideCause,
                    };

                    planItemByKey.sgk  = {
                        title: parseFloat(planItemByKey.sgk).toFixed(1)
                    };

                    planItemByKey.bt  = {
                        title: parseFloat(planItemByKey.bt).toFixed(1)
                    };

                    planItemByKey.pt  = {
                        title: parseFloat(planItemByKey.pt).toFixed(1)
                    };

                    planItemByKey.helium  = {
                        title: parseFloat(planItemByKey.helium).toFixed(1)
                    };

                    planItemByKey.oil  = {
                        title: parseFloat(planItemByKey.oil).toFixed(1)
                    };

                    planItemByKey.economicLoss =
                        this.formatCurrency(parseFloat(planItemByKey.economicLoss));

                    let idOut = planItemByKey.idOut;
                    planItemByKey.idOut = {
                        html: typeof idOut === 'string'
                            ? (() => {
                                const date = this.extractDateFromId(idOut);

                                if (date) {
                                    const dateFrom = date.split('.').reverse().join('-');
                                    const dateTo = dateFrom;
                                    const objectId = planItemByKey.responsibleObjectId?.id;

                                    if (objectId) {
                                        const link = `/index.php?module=LossLog_GatewayObject&object=${objectId}` +
                                            `&date_from=${dateFrom}&date_to=${dateTo}`;
                                        return `<a href="${link}">${idOut}</a>`;
                                    } else {
                                        return idOut;
                                    }
                                }
                                return idOut;
                            })()
                            : idOut,
                        value: idOut
                    };

                    let idConsequence = planItemByKey.idConsequence;
                    planItemByKey.idConsequence = {
                        html: typeof idConsequence === 'string'
                            ? idConsequence.split(';').map((id, index) => {
                                // Извлекаем дату из каждого элемента idConsequence
                                const date = this.extractDateFromId(id);

                                // Если дата найдена, формируем ссылку
                                if (date) {
                                    const dateFrom = date.split('.').reverse().join('-');
                                    const dateTo = dateFrom; // Дата "с" и "по" одинаковая

                                    // Извлекаем ID объекта из поля responsibleObjectId
                                    const objectId = planItemByKey.affectedObjectId[index]?.id;

                                    // Проверяем, если objectId существует
                                    if (objectId) {
                                        // Формируем ссылку
                                        const link = `/index.php?module=LossLog_GatewayObject&object=${objectId}` +
                                            `&date_from=${dateFrom}&date_to=${dateTo}`;
                                        return `<a href="${link}">${id}</a>`;
                                    } else {
                                        // Если objectId нет, возвращаем просто id
                                        return id;
                                    }
                                }
                                return id;
                            }).join('<br>') // Объединяем ссылки с <br> если их несколько
                            : idConsequence, // Если idConsequence не строка, просто возвращаем его как есть
                        value: idConsequence
                    };

                    const uploadDocs = planItemByKey.uploadDocs;
                    planItemByKey.uploadDocs = {
                        html: Array.isArray(uploadDocs) ?
                            uploadDocs.map(doc => `<a href="${doc.url}" target="_blank" download="${doc.filename}">
                            ${doc.name}</a>`).join('<br>') : ''
                    };

                    if (planItemByKey.sideCause.value === null) {
                        planItemByKey.deviation = {
                            html: '<p>' + parseFloat(planItemByKey.deviation).toFixed(1) + '</p>',
                            value: parseFloat(planItemByKey.deviation).toFixed(1),
                        };
                    } else {
                        planItemByKey.deviation = {
                            html: parseFloat(planItemByKey.deviation).toFixed(1),
                            value: parseFloat(planItemByKey.deviation).toFixed(1),
                        };
                    }
                });

                if (Math.abs(deltaPlan - totalDeviationPlan) < 1e-5) {
                    // числа равны с точностью до 5 знаков после запятой
                    item.deltaPlan = {
                        html: parseFloat(deltaPlan).toFixed(1),
                        value: parseFloat(deltaPlan),
                    };
                } else {
                    item.deltaPlan = {
                        html: '<p>'+parseFloat(deltaPlan).toFixed(1)+'</p>',
                        value: parseFloat(deltaPlan),
                    };
                }
                if (this.permissions.decompositionPlan.add && !this.initial.functionalPage) {
                    actionsArray.push({
                        name: 'addDecompositionPlan',
                        icon: 'add',
                        title: 'Добавить декомпозицию плана'
                    });
                }

                let totalDeviationFact = 0;
                Object.entries(item.decompositionFactItems).forEach(([key, factItem]) => {
                    let factArray = [];
                    const factItemByKey = item.decompositionFactItems[key];
                    //#ЗДЕСЬ
                    if (!factItem || typeof factItem !== 'object') return;

                    if (this.permissions.decompositionFact.edit) {
                        factArray.push({
                            name: 'EditFact',
                            icon: 'edit',
                            id: factItem.id,
                            title: BaseLangs.Edit
                        });
                        if (this.permissions.decompositionFact.delete) {
                            factArray.push({
                                name: 'FactDelete',
                                icon: 'delete',
                                id: factItem.id,
                                title: BaseLangs.Delete
                            });
                        }
                        factArray.push({
                            name: 'DivideFact',
                            icon: 'tree',
                            id: factItem.id,
                            title: 'Разделить'
                        });
                        factArray.push({
                            name: 'MergeFact',
                            icon: 'docs',
                            id: factItem.id,
                            title: 'Объединить'
                        });
                    }

                    // Дополнительная БЛ для отображения факта
                    const itemDateString = formattedDate.format('YYYY-MM-DD');
                    factItemByKey.deviation = this.factDeviationByDate(factItemByKey, itemDateString);

                    if (
                        !factItemByKey.isConsequencesAccounting
                        && !factItemByKey.isOwnNeedsAccounting
                        && !factItemByKey.isFlaringAccounting
                    ) {
                        totalDeviationFact += parseFloat(factItemByKey.deviation);
                    }

                    factItemByKey.factActions = {
                        actions: factArray,
                        html: '<p class="idText">#' + factItem.id
                            + this.itemIcon(factItemByKey) + '</p>',
                    };

                    this.processDocument(factItem, 'decompositionFactItems', item, key);

                    factItemByKey.immediateCause = {
                        title: factItem.immediateCause?.name,
                        value: factItem.immediateCause?.id,
                    };

                    factItemByKey.rootCause = {
                        title: factItem.rootCause?.name,
                        value: factItem.rootCause?.id,
                    };
                    factItemByKey.sideCause = {
                        title: sideCause[factItem.sideCause],
                        value: factItem.sideCause,
                    };

                    factItemByKey.sgk  = {
                        title: parseFloat(factItemByKey.sgk).toFixed(1)
                    };

                    factItemByKey.bt  = {
                        title: parseFloat(factItemByKey.bt).toFixed(1)
                    };

                    factItemByKey.pt  = {
                        title: parseFloat(factItemByKey.pt).toFixed(1)
                    };

                    factItemByKey.helium  = {
                        title: parseFloat(factItemByKey.helium).toFixed(1)
                    };

                    factItemByKey.oil  = {
                        title: parseFloat(factItemByKey.oil).toFixed(1)
                    };

                    factItemByKey.economicLoss =
                        this.formatCurrency(parseFloat(factItemByKey.economicLoss));

                    let idOut = factItemByKey.idOut;
                    factItemByKey.idOut = {
                        html: typeof idOut === 'string'
                            ? (() => {
                                const date = this.extractDateFromId(idOut);

                                if (date) {
                                    const dateFrom = date.split('.').reverse().join('-');
                                    const dateTo = dateFrom;
                                    const objectId = factItemByKey.responsibleObjectId?.id;

                                    if (objectId) {
                                        const link = `/index.php?module=LossLog_GatewayObject&object=${objectId}` +
                                            `&date_from=${dateFrom}&date_to=${dateTo}`;
                                        return `<a href="${link}">${idOut}</a>`;
                                    } else {
                                        return idOut;
                                    }
                                }
                                return idOut;
                            })()
                            : idOut,
                        value: idOut
                    };

                    let idConsequence = factItemByKey.idConsequence;
                    factItemByKey.idConsequence = {
                        html: typeof idConsequence === 'string'
                            ? idConsequence.split(';').map((id, index) => {
                                // Извлекаем дату из каждого элемента idConsequence
                                const date = this.extractDateFromId(id);

                                // Если дата найдена, формируем ссылку
                                if (date) {
                                    const dateFrom = date.split('.').reverse().join('-');
                                    const dateTo = dateFrom; // Дата "с" и "по" одинаковая

                                    // Извлекаем ID объекта из поля responsibleObjectId
                                    const objectId = factItemByKey.affectedObjectId[index]?.id;

                                    // Проверяем, если objectId существует
                                    if (objectId) {
                                        // Формируем ссылку
                                        const link = `/index.php?module=LossLog_GatewayObject&object=${objectId}` +
                                            `&date_from=${dateFrom}&date_to=${dateTo}`;
                                        return `<a href="${link}">${id}</a>`;
                                    } else {
                                        // Если objectId нет, возвращаем просто id
                                        return id;
                                    }
                                }
                                return id;
                            }).join('<br>') // Объединяем ссылки с <br> если их несколько
                            : idConsequence, // Если idConsequence не строка, просто возвращаем его как есть
                        value: idConsequence
                    };

                    const uploadDocs = factItemByKey.uploadDocs;
                    factItemByKey.uploadDocs = {
                        html: Array.isArray(uploadDocs) ?
                            uploadDocs.map(doc => `<a href="${doc.url}" target="_blank" download="${doc.filename}">
                            ${doc.name}</a>`).join('<br>') : ''
                    };

                    if (factItemByKey.sideCause.value === null) {
                        factItemByKey.deviation = {
                            html: '<p>' + parseFloat(factItemByKey.deviation).toFixed(1) + '</p>',
                            value: parseFloat(factItemByKey.deviation).toFixed(1),
                        };
                    } else {
                        factItemByKey.deviation = {
                            html: parseFloat(factItemByKey.deviation).toFixed(1),
                            value: parseFloat(factItemByKey.deviation).toFixed(1),
                        };
                    }
                });

                if (deltaFact <= totalDeviationFact + 1e-5) {
                    // условие выполняется, если deltaFact меньше или равен totalDeviationFact с учетом погрешности
                    item.deltaFact = {
                        html: parseFloat(deltaFact).toFixed(1),
                        value: parseFloat(deltaFact),
                    };
                } else {
                    item.deltaFact = {
                        html: '<p>'+parseFloat(deltaFact).toFixed(1)+'</p>',
                        value: parseFloat(deltaFact),
                    };
                }
                if (this.permissions.decompositionFact.add && !this.initial.functionalPage) {
                    actionsArray.push({
                        name: 'addDecompositionFact',
                        icon: 'add',
                        title: 'Добавить декомпозицию факта'
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
                    html: '<p class="idText">#'+notLinkedItem.id+'</p>',
                };

                let decompositionPlanItemsCount = item.decompositionPlanItems ?
                    item.decompositionPlanItems.length : 0;
                let decompositionFactItemsCount = item.decompositionFactItems ?
                    item.decompositionFactItems.length : 0;

                if (decompositionPlanItemsCount > 0 || decompositionFactItemsCount > 0) {
                    let newItem = this.newSetPrefixForChildItems(
                        item, item.decompositionPlanItems, item.decompositionFactItems
                    );
                    newItem.forEach(function (childItem) {
                        result.push({item: childItem});
                    });
                }

                if (decompositionPlanItemsCount === 0 && decompositionFactItemsCount === 0) {
                    result.push({item});
                }
            });

            BaseTemplate.hideProgress();

            //Сохраняем текущий набор данных, для будущего апдейта
            //чтобы не было визуального скачка, либо полного визуального обнуления
            this.prevData = result;
            return result;
        },

        tableHeaders() {
            return [
                {
                    title: 'Общая информация',
                    class: 'center',
                    childs: [
                        {
                            title: BaseLangs.Actions,
                            class: 'sticky-column actions',
                            key: 'actions',
                        },
                        {
                            key: 'date',
                            class: 'sticky-column date',
                            title: 'Дата',
                        },
                        {
                            key: 'workshop',
                            title: 'Подразделение',
                        },
                        {
                            key: 'lu',
                            title: 'ЛУ',
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
                            title: 'Проектная производительность',
                            childs: [
                                {
                                    title: this.initial.unit,
                                    key: 'performance',
                                    class: 'center',
                                }
                            ]
                        },
                        {
                            title: 'МДП',
                            childs: [
                                {
                                    title: this.initial.unit,
                                    key: 'mdp',
                                    class: 'center',
                                }
                            ]
                        },
                        {
                            title: 'Потенциал',
                            childs: [
                                {
                                    title: this.initial.unit,
                                    key: 'potential',
                                    class: 'center',
                                }
                            ]
                        },
                    ]
                },

                {
                    title: 'Плановые потери',
                    class: 'blue center',
                    childs: [
                        {
                            title: 'План',
                            class: 'blue',
                            childs: [
                                {
                                    title: this.initial.unit,
                                    class: 'blue center',
                                    key: 'plan',
                                }
                            ]
                        },
                        {
                            title: 'Потенциал минус План',
                            class: 'blue',
                            childs: [
                                {
                                    title: this.initial.unit,
                                    class: 'blue center',
                                    key: 'deltaPlan',
                                }
                            ]
                        },
                        {
                            title: BaseLangs.Actions,
                            key: 'decompositionPlanItems-planActions',
                            class: 'blue',
                        },
                        {
                            title: 'Декомпозиция отклонения',
                            class: 'blue',
                            childs: [
                                {
                                    title: this.initial.unit,
                                    class: 'blue center',
                                    key: 'decompositionPlanItems-deviation',
                                }
                            ],
                        },
                        {
                            title: 'Непосредственная причина',
                            class: 'blue',
                            key: 'decompositionPlanItems-immediateCause',
                        },
                        {
                            title: 'Комментарий',
                            class: 'blue',
                            key: 'decompositionPlanItems-comment',
                        },
                        {
                            title: 'Коренная причина',
                            class: 'blue',
                            key: 'decompositionPlanItems-rootCause',
                        },
                        /*
                        {
                            title: 'Сторона причины',
                            class: 'blue',
                            key: 'decompositionPlanItems-sideCause',
                        },
                        */
                        {
                            title: 'Загруженные документы',
                            class: 'blue',
                            key: 'decompositionPlanItems-uploadDocs',
                        },
                        {
                            title: 'Связанный документ',
                            class: 'blue',
                            key: 'decompositionPlanItems-document',
                        },
                        {
                            title: 'ID внутренней причины',
                            class: 'blue',
                            key: 'decompositionPlanItems-idIn',
                        },
                        {
                            title: 'ID внешней причины',
                            class: 'blue',
                            key: 'decompositionPlanItems-idOut',
                        },
                        {
                            title: 'ID последствия',
                            class: 'blue',
                            key: 'decompositionPlanItems-idConsequence',
                        },
                    ],
                },
                {
                    title: 'Недовыпуск продукции от потенциала',
                    class: 'blue center',
                    childs: [
                        {
                            title: 'Нефть',
                            class: 'blue',
                            childs: [
                                {
                                    title: 'т',
                                    class: 'blue center',
                                    key: 'decompositionPlanItems-oil',
                                }
                            ],
                        },
                        {
                            title: 'СГК',
                            class: 'blue',
                            childs: [
                                {
                                    title: 'т',
                                    class: 'blue center',
                                    key: 'decompositionPlanItems-sgk',
                                }
                            ],
                        },
                        {
                            title: 'ПТ',
                            class: 'blue',
                            childs: [
                                {
                                    title: 'т',
                                    class: 'blue center',
                                    key: 'decompositionPlanItems-pt',
                                }
                            ],
                        },
                        {
                            title: 'БТ',
                            class: 'blue',
                            childs: [
                                {
                                    title: 'т',
                                    class: 'blue center',
                                    key: 'decompositionPlanItems-bt',
                                }
                            ],
                        },
                        {
                            title: 'Гелий',
                            class: 'blue',
                            childs: [
                                {
                                    title: 'кг',
                                    class: 'blue center',
                                    key: 'decompositionPlanItems-helium',
                                }
                            ],
                        },
                        {
                            title: 'Экономические последствия млн.руб',
                            class: 'blue',
                            key: 'decompositionPlanItems-economicLoss',
                        },
                    ]
                },

                {
                    title: 'Анализ выполнения плана',
                    class: 'green center',
                    childs: [
                        {
                            title: 'Факт',
                            class: 'green',
                            childs: [
                                {
                                    title: this.initial.unit,
                                    class: 'green center',
                                    key: 'fact',
                                }
                            ]
                        },
                        {
                            title: 'План минус Факт',
                            class: 'green',
                            childs: [
                                {
                                    title: this.initial.unit,
                                    class: 'green center',
                                    key: 'deltaFact',
                                }
                            ]
                        },
                        {
                            title: BaseLangs.Actions,
                            key: 'decompositionFactItems-factActions',
                            class: 'green',
                        },
                        {
                            title: 'Декомпозиция отклонения',
                            class: 'green',
                            childs: [
                                {
                                    title: this.initial.unit,
                                    class: 'green center',
                                    key: 'decompositionFactItems-deviation',
                                }
                            ]
                        },
                        {
                            title: 'Непосредственная причина',
                            class: 'green',
                            key: 'decompositionFactItems-immediateCause',
                        },
                        {
                            title: 'Комментарий',
                            class: 'green',
                            key: 'decompositionFactItems-comment',
                        },
                        {
                            title: 'Коренная причина',
                            class: 'green',
                            key: 'decompositionFactItems-rootCause',
                        },
                        {
                            title: 'Загруженные документы',
                            class: 'green',
                            key: 'decompositionFactItems-uploadDocs',
                        },
                        {
                            title: 'Связанный документ',
                            class: 'green',
                            key: 'decompositionFactItems-document',
                        },
                        {
                            title: 'ID внутренней причины',
                            class: 'green',
                            key: 'decompositionFactItems-idIn',
                        },
                        {
                            title: 'ID внешней причины',
                            class: 'green',
                            key: 'decompositionFactItems-idOut',
                        },
                        {
                            title: 'ID последствия',
                            class: 'green',
                            key: 'decompositionFactItems-idConsequence',
                        },
                    ],
                },

                {
                    title: 'Недовыпуск продукции от плана',
                    class: 'green center',
                    childs: [
                        {
                            title: 'Нефть',
                            class: 'green',
                            childs: [
                                {
                                    title: 'т',
                                    class: 'green center',
                                    key: 'decompositionFactItems-oil',
                                }
                            ],
                        },
                        {
                            title: 'СГК',
                            class: 'green',
                            childs: [
                                {
                                    title: 'т',
                                    class: 'green center',
                                    key: 'decompositionFactItems-sgk',
                                }
                            ],
                        },
                        {
                            title: 'ПТ',
                            class: 'green',
                            childs: [
                                {
                                    title: 'т',
                                    class: 'green center',
                                    key: 'decompositionFactItems-pt',
                                }
                            ],
                        },
                        {
                            title: 'БТ',
                            class: 'green',
                            childs: [
                                {
                                    title: 'т',
                                    class: 'green center',
                                    key: 'decompositionFactItems-bt',
                                }
                            ],
                        },
                        {
                            title: 'Гелий',
                            class: 'green',
                            childs: [
                                {
                                    title: 'кг',
                                    class: 'green center',
                                    key: 'decompositionFactItems-helium',
                                }
                            ],
                        },
                        {
                            title: 'Экономические последствия млн.руб',
                            class: 'green',
                            key: 'decompositionFactItems-economicLoss',
                        },
                    ]
                },
            ];
        },
    },
    methods: {
        // Возвращает иконку для строк авто-декомпозиции
        itemIcon(item, isPlan = false) {
            let templateData = null;

            if (item?.isOwnNeedsAccounting) {
                templateData = {
                    title: isPlan ? 'Cобственные нужды' : 'Сверхнормативные собственные нужды',
                    icon: 'table-icon-refresh-state',
                };
            } else if (item?.isFlaringAccounting) {
                templateData = {
                    title: isPlan ? 'Плановое сжигание на факеле' : 'Избыточное сжигание на факеле',
                    icon: 'table-icon-upload',
                };
            } else if (item?.isConsequencesAccounting) {
                templateData = {
                    title: 'Последствие',
                    icon: 'table-icon-send-revision',
                };
            }

            return this.iconHtml(templateData);
        },
        factDeviationByDate(item, itemDateString) {
            let calculatedDeviation = parseFloat(item.deviation ?? 0);
            if (!this.isAccounting(item)) {
                return calculatedDeviation;
            }

            const burningRate = this.burningRateByDate(itemDateString);

            if (item?.isOwnNeedsAccounting) {
                calculatedDeviation += burningRate;
            } else if (item?.isFlaringAccounting) {
                calculatedDeviation = Math.max(calculatedDeviation - burningRate, 0);
            }

            return calculatedDeviation;
        },
        burningRateByDate(dateString) {
            return parseFloat(this.burningRatePlanData[dateString] ?? 0);
        },
        iconHtml(templateData) {
            if (!templateData) {
                return '';
            }

            return '<span title="' + templateData.title + '"><svg class="lossLog-table-icon">' +
                '<use xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#' + templateData.icon + '"></use>' +
                '</svg></span>';
        },
        isAccounting(item) {
            return item?.isOwnNeedsAccounting || item?.isFlaringAccounting || item?.isConsequencesAccounting;
        },
        // Функция для извлечения даты из строки формата 'DD.MM.YYYY'
        extractDateFromId(id) {
            const match = id.match(/(\d{2}\.\d{2}\.\d{4})/); // Ищем строку формата 01.11.2024
            return match ? match[0] : null;
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
            // Управление главной записью
            if (event.name === 'Edit') {
                this.$refs.itemEditModal.editItem(event.row.id.value);
            }
            if (event.name === 'Delete') {
                this.deleteItem(event.row.id.value);
            }

            //Управление декомпозицией плана
            if (event.name === 'addDecompositionPlan') {
                //модалка План
                this.$refs.itemDecompositionPlanModal.setEventRow(event.row, this.datesWithoutPotential);
                this.$refs.itemDecompositionPlanModal.newItem();
            }
            if (event.name === 'EditPlan') {
                this.$refs.itemDecompositionPlanModal.setEventRow(event.row, this.datesWithoutPotential);
                this.$refs.itemDecompositionPlanModal.editItem(event.actionSource.id);
            }
            if (event.name === 'PlanDelete') {
                this.$refs.itemDecompositionPlanModal.deleteItem(event.actionSource.id);
            }
            if (event.name === 'DividePlan') {
                this.$refs.itemDecompositionPlanMergeDivide.setEventRow(event.row, this.datesWithoutPotential);
                this.$refs.itemDecompositionPlanMergeDivide.divideForm(event.actionSource.id);
            }
            if (event.name === 'MergePlan') {
                this.$refs.itemDecompositionPlanMergeDivide.setEventRow(event.row, this.datesWithoutPotential);
                this.$refs.itemDecompositionPlanMergeDivide.mergeForm(event.actionSource.id);
            }
            if (event.name === 'PlanCopyForDate') {
                this.$refs.itemDecompositionPlanCopyForDate.setEventRow(event.row);
                this.$refs.itemDecompositionPlanCopyForDate.copyForDateForm();
            }
            //Управление декомпозицией факта
            if (event.name === 'addDecompositionFact') {
                //модалка Факт
                this.$refs.itemDecompositionFactModal.setEventRow(event.row, this.datesWithoutPotential);
                this.$refs.itemDecompositionFactModal.newItem();
            }
            if (event.name === 'EditFact') {
                this.$refs.itemDecompositionFactModal.setEventRow(event.row, this.datesWithoutPotential);
                this.$refs.itemDecompositionFactModal.editItem(event.actionSource.id);
            }
            if (event.name === 'FactDelete') {
                this.$refs.itemDecompositionFactModal.deleteItem(event.actionSource.id);
            }
            if (event.name === 'DivideFact') {
                this.$refs.itemDecompositionFactMergeDivide.setEventRow(event.row, this.datesWithoutPotential);
                this.$refs.itemDecompositionFactMergeDivide.divideForm(event.actionSource.id);
            }
            if (event.name === 'MergeFact') {
                this.$refs.itemDecompositionFactMergeDivide.setEventRow(event.row, this.datesWithoutPotential);
                this.$refs.itemDecompositionFactMergeDivide.mergeForm(event.actionSource.id);
            }

        },

        async deleteItem(id) {
            Notify.confirmWarning(`Вы действительно хотите удалить запись №: ${id}?`, async (state) => {
                if (!state) {
                    return;
                }
                BaseTemplate.showProgress();

                const url = `/index.php?module=${this.moduleName}&action=${this.actionDeleteData}`;
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        body: JSON.stringify({ id }),
                    });

                    if (!response.ok) {
                        throw new Error(`Ошибка HTTP: ${response.status}`);
                    }

                    const data = await response.json();

                    if (data.error) {
                        Notify.showError(data.error);
                    } else {
                        await this.loadData(); // Обновить данные после успешного удаления
                    }
                } catch (error) {
                    Notify.showError('Ошибка при удалении записи: ' + error.message);
                } finally {
                    BaseTemplate.hideProgress();
                }
            });
        },

        async exportToExcel() {
            BaseTemplate.showProgress();

            try {
                let filter = this.filters;
                let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionExcel;
                let response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify({
                        filters: filter,
                        initial: this.initial
                    }),
                });

                // Получаем заголовок Content-Disposition
                let contentDisposition = response.headers.get('Content-Disposition');

                // Извлекаем и декодируем имя файла
                let filename = '';
                if (contentDisposition && contentDisposition.includes('filename=')) {
                    // Извлечение имени файла
                    filename = contentDisposition.split('filename=')[1].replace(/['"]/g, '');
                    // Перекодирование из ISO-8859-1 в UTF-8
                    const decoder = new TextDecoder('utf-8');
                    filename = decoder.decode(new Uint8Array([...filename].map(c => c.charCodeAt(0))));
                }

                if (response.ok) {
                    // Получаем бинарные данные (blob) файла
                    let blob = await response.blob();
                    let downloadUrl = window.URL.createObjectURL(blob);

                    // Создаем ссылку для скачивания файла
                    let a = document.createElement('a');
                    a.href = downloadUrl;

                    a.download = filename ?? 'export.xlsx';
                    document.body.appendChild(a);
                    a.click();

                    // Удаляем ссылку после скачивания
                    a.remove();
                    window.URL.revokeObjectURL(downloadUrl);
                } else {
                    Notify.showError('Загрузка данных. Ошибка HTTP: ' + response.status + ' ' +
                        (typeof this.errorStatuses[response.status] !== 'undefined'
                            ? this.errorStatuses[response.status] : ''));
                }
            } catch (e) {
                Notify.showError('Ошибка: ' + e.message);
            } finally {
                BaseTemplate.hideProgress();
            }
        },

        processDocument(element, decompositionItemsKey, item, key) {
            if (!item || !item[decompositionItemsKey] || !item[decompositionItemsKey][key]) return;
            const target = item[decompositionItemsKey][key];

            // Проверка условия для обязательного документа
            if (element.immediateCause.documentRequired && !target.document?.id) {
                target.document = { html: '<p></p>' };
                return;
            }

            let documentValue = '';
            let documentId = '';

            // Обработка данных документа
            const doc = element.document;
            if (typeof doc === 'string') {
                documentValue = doc;
            } else if (doc?.docNum) {
                // Определяем тип документа
                let docType = 'АТР'; // По умолчанию
                if (doc.isArp) {
                    docType = 'АРП';
                } else if (doc.isNz) {
                    docType = 'НЗ';
                }

                documentValue = `${docType} №${doc.docNum}`;

                // Форматирование даты
                if (doc.atrDate !== '0000-00-00 00:00:00') {
                    const date = dayjs(doc.atrDate);
                    if (date.isValid()) {
                        documentValue += ` от ${date.format('DD.MM.YYYY')}`;
                    }
                }

                // Добавление ссылки
                if (doc.docLink?.trim()) {
                    documentValue = `<a href="${doc.docLink}">${documentValue}</a>`;
                }

                documentId = doc.id || '';
            }

            // Сохранение результата
            target.document = {
                html: documentValue,
                value: documentId
            };
        },

        // Метод загружает данные для табличной части
        async loadData() {
            try {
                BaseTemplate.showProgress(); // Включить состояние загрузки
                this.list = [];
                this.linkedList = [];

                await this.getObjectPotential(); // Получение данных потенциала
                await this.loadBurningRatePlan(); // Получение данных справочника Норматив сжигания (план)

                const url = `/index.php?module=${this.moduleName}&action=${this.actionGetData}`;
                const response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify({
                        filters: {
                            ...this.filters,
                            perPage: this.perPage,
                            page: this.page + 1,
                        },
                        initial: this.initial,
                    }),
                });

                if (!response.ok) {
                    throw new Error(
                        `Ошибка HTTP: ${response.status} ${
                            this.errorStatuses[response.status] || 'Неизвестная ошибка'
                        }`
                    );
                }

                const data = await response.json();

                if (data.length === 0) {
                    Notify.showWarning('Нет записей для отображения');
                } else {
                    this.data = data;
                    this.list = data.list;
                    this.linkedList = data.linkedList;
                    this.pagesCount = data.totalPages;
                }

                this.initResizeObserver();
            } catch (e) {
                Notify.showError(`Ошибка загрузки данных: ${e.message}`);
                this.list = [];
                this.linkedList = [];
            } finally {
                this.recalcHeadPosition();
                BaseTemplate.hideProgress();
            }
        },

        // Метод загружает данные для табличной части
        async getObjectPotential() {
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionObjectPotential;

            let response = await fetch(url, {
                method: 'POST',
                body: JSON.stringify({objectId: this.objectId}),
            });
            let data = await response.json();

            if (response.ok) {
                if (data.length === 0) {
                    throw new Error('Потенциал установки не задан');
                } else {
                    this.objectPotentialData = data;
                }
            } else {
                const errorMessage = 'Загрузка данных. Ошибка HTTP: ' + response.status + ' ' +
                    (this.errorStatuses[response.status] || 'Неизвестная ошибка');
                throw new Error(errorMessage);
            }
        },
        async loadBurningRatePlan() {
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionObjectBurningRatePlanData;

            let response = await fetch(url, {
                method: 'POST',
                body: JSON.stringify({
                    dateStart: this.filters?.date[0] ?? '',
                    dateEnd: this.filters?.date[1] ?? '',
                    objectId: this.objectId,
                }),
            });
            let data = await response.json();

            if (!response.ok) {
                const errorMessage = 'Загрузка данных. Ошибка HTTP: ' + response.status + ' ' +
                    (this.errorStatuses[response.status] || 'Неизвестная ошибка');
                throw new Error(errorMessage);
            }

            this.burningRatePlanData = data;
        },

        async getDefaultData() {
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionDefaultData;
            let response = await fetch(url, {
                method: 'POST', headers: {
                    'Content-Type': 'application/json'
                }, body: JSON.stringify({objectId: this.objectId})
            });
            let data = await response.json();
            if (response.ok) {
                if (data.errorMessage) {
                    throw new Error(data.errorMessage);
                } else {
                    this.initial = data;
                }
            } else {
                const errorMessage = 'Загрузка данных. Ошибка HTTP: ' + response.status + ' ' +
                    (this.errorStatuses[response.status] || 'Неизвестная ошибка');
                throw new Error(errorMessage);
            }
        },

        insertItem(item, childs, prefix) {
            for (let key in childs) {
                item[prefix + key] = childs[key];
            }
            return item;
        },

        newSetPrefixForChildItems(parent, plans, facts) {
            let result = [];

            for (let i = 0; i < Object.keys(plans).length; i++) {
                let childItem = JSON.parse(JSON.stringify(parent));
                if (i in plans) {
                    childItem = this.insertItem(childItem, plans[i], 'decompositionPlanItems-');
                }
                result.push(childItem);
            }

            for (let i = 0; i < Object.keys(facts).length; i++) {

                let childItem;
                if (result[i] === undefined) {
                    childItem = JSON.parse(JSON.stringify(parent));
                } else {
                    childItem = JSON.parse(JSON.stringify(result[i]));
                }

                if (i in facts) {
                    result[i] = this.insertItem(childItem, facts[i], 'decompositionFactItems-');
                }
            }

            return result;
        },

        getExcludedFilterKeys() {
            return [
                'actions',
                'id',
            ];
        },

        // Грузим права
        async loadPermissions() {
            let url = `/index.php?module=${this.moduleName}&action=${this.actionGetPermissions}`;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify({}),
                });

                if (!response.ok) {
                    Notify.showError('Ошибка HTTP: ' + response.status);
                    return; // Прерываем выполнение, если есть ошибка
                }

                const data = await response.json();

                // Проверка, если data пустой массив
                if (Array.isArray(data) && data.length === 0) {
                    Notify.showWarning('Права не найдены');
                } else {
                    this.permissions = data;
                }
            } catch (e) {
                Notify.showError('Ошибка: ' + e.message);
            }
        },

        getTableFilters() {
            // Получаем массив ключей фильтров из columnsDef
            let columnsDef = this.tableHeaders;
            return columnsDef
                .filter(item => item.key && !this.getExcludedFilterKeys().includes(item.key))
                .map(item => item.key);
        },

        async loadPage(n) {
            this.page = n;
            await this.loadData();
        },

        async init() {
            try {
                await Promise.all([this.loadPermissions(), this.getDefaultData()]);
            } catch (error) {
                console.error('Ошибка:', error);
                Notify.showError(error);
            }
        },

        async checkAccess(objectId) {
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionCheckObjectAccess;
            try {
                let response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify({objectId: objectId}),
                });
                let data = await response.json();
                if (response.ok) {
                    return true;
                } else {
                    Notify.showError(data.errorMessage);
                    return false;
                }
            } catch (e) {
                Notify.showError('Ошибка: ' + e.message);
                return false;
            }
        },

        updateUrlParams(newValue) {
            // Проверяем, что переданы корректные данные
            if (!Array.isArray(newValue) || newValue.length < 2) {
                return;
            }

            const [dateFrom, dateTo] = newValue;

            // Проверяем, что даты указаны
            if (!dateFrom || !dateTo) {
                return;
            }

            // Создаём объект с текущими параметрами URL
            const url = new URL(window.location.href);
            const params = url.searchParams;

            // Обновляем параметры начальной и конечной даты
            params.set('date_from', dateFrom);
            params.set('date_to', dateTo);

            // Обновляем адресную строку без перезагрузки страницы
            window.history.replaceState({}, '', `${url.pathname}?${params.toString()}`);
        },

        setDateFiltersFromUrl() {
            const url = new URL(window.location.href);
            const params = url.searchParams;

            const dateFrom = params.get('date_from');
            const dateTo = params.get('date_to');

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

        initResizeObserver() {
            const tabsElement = document.querySelector('.tabs');
            if (!tabsElement) {
                //вдруг нет табов
                return;
            }

            this.resizeObserver = new ResizeObserver((entries) => {
                for (let entry of entries) {
                    if (entry.target === tabsElement) {
                        this.recalcHeadPosition();
                    }
                }
            });

            this.resizeObserver.observe(tabsElement);
        },

        destroyResizeObserver() {
            if (this.resizeObserver) {
                this.resizeObserver.disconnect();
                this.resizeObserver = null;
            }
        },

        recalcHeadPosition() {
            const tabs = document.querySelector('.tabs');
            const stickyTableHead = document.querySelector('.tm-table-sticky-header thead');
            if (stickyTableHead && tabs) {
                const height = tabs.offsetHeight;
                // Устанавливаем её как отступ (например, margin-top) для другого элемента
                stickyTableHead.style.top = `${height}px`;
            }
        },
    },
    watch: {
        'filters.date'(newValue, oldValue) {
            if (newValue !== oldValue) {
                this.updateUrlParams(newValue);
                this.loadData();
            }
        },
    },
    async mounted() {
        try {
            this.objectId = BaseTemplate.URLparams.object ?? 0;

            if (await this.checkAccess(this.objectId)) {
                await this.init();

                // Устанавливаем фильтры даты из URL, если они существуют
                const dateFiltersSet = this.setDateFiltersFromUrl();

                // Если параметры даты были установлены из URL, не вызываем loadData сразу
                if (!dateFiltersSet) {
                    // Если фильтры не установлены, вызываем loadData с фильтром по умолчанию
                    await this.loadData();
                }
            }
        } catch (error) {
            console.error('Ошибка:', error);
        }
    },
    beforeDestroy() {
        this.destroyResizeObserver();
    },
    updated() {
        this.recalcHeadPosition();
    }
});
initComponents(app, ['LossLog/GatewayObject']).then(() => app.mount('#app'));
