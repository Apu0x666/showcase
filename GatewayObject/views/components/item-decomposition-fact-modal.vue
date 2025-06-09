<template>
    <tm-modal v-if="visible" @close="exitModal()">
        <template v-slot:header>
            <div><slot name="header">{{ title }}</slot></div>
        </template>
        <template v-slot:body>
            <div style="width: 900px;">
                <slot name="body">
                    <div class="tm-grid tm-child-flex-bottom">
                        <div class="tm-width-1-1 child-flex-bottom">
                            <tm-input-number
                                v-model="factDeviationLeftValue"
                                disabled
                                title="Требуется пояснить отклонение плана от факта: "
                            ></tm-input-number>
                        </div>
                        
                        <div class="tm-width-1-3">
                            <tm-input-number
                                v-model="itemData.deviation"
                                title="Декомпозиция отклонения"
                                :disabled="isDisabledFieldByConsequencesAccounting"
                            ></tm-input-number>
                        </div>

                        <div class="tm-width-1-3">
                            <select-model-single
                                title="Непосредственная причина"
                                id="immediateCause"
                                model="LossLog_Dictionaries_ImmediateCause_Model"
                                v-model="itemData.immediateCause"
                                :disabled="isDisabledCauseField"
                            ></select-model-single>
                        </div>
                        
                        <div class="tm-width-1-3">
                            <select-single
                                title="Сторона причины"
                                id="sideCause"
                                v-model="itemData.sideCause"
                                :options="getSideCauseList()"
                                :disabled="isDisabledFieldByConsequencesAccounting"
                            ></select-single>
                        </div>
                        
                        <div class="tm-width-1-2">
                            <select-single
                                title="Подразделение внешней стороны"
                                id="responsibleObjectId"
                                :options="objectsList"
                                v-model="itemData.responsibleObjectId"
                                :disabled="disableResponsibleObjectId"
                            ></select-single>
                        </div>
                        
                        <div class="tm-width-1-2">
                            <select-multiple
                                title="Подразделения последствия"
                                id="affectedObjectId"
                                :options="objectsAffectedList"
                                v-model="itemData.affectedObjectId"
                                :disabled="isDisabledFieldByConsequencesAccounting ||
                                disableAffectedByResponsibleSelect"
                            ></select-multiple>
                        </div>
                        
                        <div class="tm-width-1-2">
                            <label for="document">Связанный документ</label>
                            <div class="tm-flex tm-margin-bottom-small">
                                <div class="tm-margin-right">
                                    <input type="radio" id="atr" v-model="doctype" value="atr" />
                                    <label for="atr">АТР</label>
                                </div>
                                <div class="tm-margin-right">
                                    <input type="radio" id="nz" v-model="doctype" value="nz" />
                                    <label for="nz">НЗ</label>
                                </div>
                                <div class="tm-margin-right">
                                    <input type="radio" id="arp" v-model="doctype" value="arp" />
                                    <label for="arp">АРП</label>
                                </div>
                            </div>
                            
                            <losslog-gatewayobject-text-autocomplete-field
                                title="Связанный документ"
                                :disabled="disableDocument"
                                v-model="docNum"
                                :customData="atrOptions || []"
                                @update:id="handleDocId"
                                @clear="clear"
                            ></losslog-gatewayobject-text-autocomplete-field>
                        </div>

                        <div class="tm-width-1-2">
                            <tm-fileupload-multiple
                                title="Документы"
                                :module="this.moduleName"
                                :mime="['application/vnd.ms-excel']"
                                :value="itemData.uploadDocs"
                                @input="setDocuments($event)"
                            ></tm-fileupload-multiple>
                        </div>
                        
                        <div class="tm-width-1-1">
                            <tm-input-textarea
                                title="Комментарий"
                                v-model="itemData.comment"
                                :maxlength="1000"
                            />
                        </div>
                        
                        <div class="tm-width-1-1">
                            <label>Коренная причина</label>
                            <!-- Показываем историческую причину если она выбрана -->
                            <div v-if="currentRootCause?.isHistorical">
                                <p>Архивная причина: <span style="color: red;">
                                    {{ currentRootCause.text }}
                                </span></p>
                            </div>
                            <select-single
                                id="rootCause"
                                v-model="itemData.rootCause"
                                :options="filteredRootCause"
                                :disabled="isDisabledRootCause"
                            ></select-single>
                        </div>
                        
                        <div class="tm-width-1-1" v-if="userName">
                            <p>Последнее изменение сделал: <b>{{ userName }}</b>  </p>
                            <p>Дата последних изменений: <b>{{ mtime }}</b> </p>
                        </div>
                    </div>
                </slot>
            </div>
        </template>
        <template v-slot:footer>
            <div class="tm-flex tm-flex-right">
                <slot name="footer">
                    
                    <button
                        class="tm-btn tm-margin-horz"
                        v-if="permissions.edit"
                        v-show="this.isNew"
                        @click="save">Добавить запись
                    </button>
                    
                    <button
                        class="tm-btn tm-margin-horz"
                        v-if="permissions.edit"
                        v-show="!this.isNew"
                        @click="save">Сохранить
                    </button>
                    
                    <button class="tm-btn" @click="exitModal()">Отмена</button>
                </slot>
            </div>
        </template>
    </tm-modal>
</template>

<script>
/* eslint-disable no-undef */
export default {
    emits: ['saved'],
    props: {
        permissions: {
            type: Object,
            default: () => {
                return {
                    canWrite: false,
                    delete: false,
                };
            },
        },
        initial: {
            type: Object,
            default: () => {},
        },
        burningRatePlanData: {
            type: Object,
            default: () => {},
        }
    },
    data() {
        return {
            moduleName: 'LossLog_DecompositionFact',
            itemData: {
                affectedObjectId: [],
                comment: '',
                deviation: null,
                document: '',
                idConsequence: '',
                idIn: '',
                idOut: '',
                immediateCause: null,
                losslogMainDataId: null,
                lu: null,
                object: null,
                responsibleObjectId: [],
                rootCause: null,
                sideCause: null,
                techProcess: null,
                uploadDocs: [],
                workshop: null,
            },
            userName: null,
            mtime: null,
            
            rootCauseFromToir: null,
            objectsList: null,
            objectsAffectedList: null,
            factDeviationLeft: null,
            factDeviationLeftValue: null,
            docNum: '',
            disableDocument: false,
            atrList: [],
            doctype: 'atr',
            
            disableResponsibleObjectId: false,
            disableAffectedByResponsibleSelect: false,
            eventRow: null,
            datesWithoutObjectPotential: [],

            visible: false,
            title: '',
            
            isNew: true,

            actionCreate: 'create',
            actionEdit: 'edit',
            actionLoadItem: 'getRecord',
            actionDeleteData: 'delete',
            actionGetObjects: 'getObjects',
            actionGetRelationsAndDeviationLeft: 'getRelationsAndDeviationLeft',
            actionCheckCoefficients: 'checkCoefficients',
            actionGetAtrList: 'getAtrList',
            actionGetRootCauseToir: 'getRootCauseToir',
            actionSetRootCauseFromAtr: 'getRootCauseFromAtr',

            action: '',
        };
    },
    computed: {
        atrOptions() {
            if (this.doctype === 'atr') {
                // Для 'atr' возвращаем элементы, где isArp === 0 И isNz === 0
                return this.atrList.filter(item => item.isArp === 0 && item.isNz === 0);
            }
            if (this.doctype === 'arp') {
                return this.atrList.filter(item => item.isArp === 1);
            }
            if (this.doctype === 'nz') {
                return this.atrList.filter(item => item.isNz === 1);
            }
            return this.atrList; // по умолчанию вернуть все элементы, если тип не совпал
        },
        isDisabledCauseField() {
            return !!(this.itemData?.isOwnNeedsAccounting || this.itemData?.isFlaringAccounting);
        },
        isDisabledFieldByConsequencesAccounting() {
            return !!this.itemData?.isConsequencesAccounting;
        },
        isDisabledRootCause() {
            return !!this.itemData?.document;
        },
        // Фильтруем причины, оставляя только неисторические + текущую выбранную (если она историческая)
        filteredRootCause() {
            const currentId = this.itemData.rootCause;
            const currentItem = this.rootCauseFromToir?.find(item => item.value === currentId);
            
            // Основной список - неисторические причины
            const mainList = this.rootCauseFromToir?.filter(item => !item.isHistorical) || [];
            
            // Если текущая причина историческая - добавляем ее в список
            if (currentItem?.isHistorical) {
                return [...mainList, currentItem];
            }
            
            return mainList;
        },
        
        // Находим текущую выбранную причину
        currentRootCause() {
            return this.rootCauseFromToir?.find(item => item.value === this.itemData.rootCause);
        }
    },
    methods: {
        clear() {
            this.docNum = '';
            this.itemData.document = '';
        },
        async handleDocId(id) {
            //связь на id документа из failure_new
            this.itemData.document = id;
            
            let self = this;
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionSetRootCauseFromAtr;
            
            try {
                let response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify(self.itemData),
                });
                let data = await response.json();
                if (response.ok && data > 0) {
                    this.itemData.rootCause = data;
                } else {
                    this.itemData.rootCause = '';
                    Notify.showWarning(data.message);
                }
            } catch (e) {
                Notify.showError(e.message);
            }
            
        },
        setDefaults() {
            Object.assign(this.itemData, this.initial);
        },
        setDocuments(event) {
            this.itemData.uploadDocs = event;
        },
        //Модалка на создание декомпозиции
        async newItem() {
            BaseTemplate.showProgress();
            try {
                await this.checkObjectPotential();
                // Проверяем коэффициенты
                await this.checkCoefficients();
                
                this.setDefaults();
                this.title = 'Добавление декомпозиции факта';
                this.action = this.actionCreate;
                this.buttonText = 'Добавить запись';
                
                this.itemData.losslogMainDataId = this.eventRow.id.value;
                await this.getRelationsAndDeviationLeft(this.eventRow);
                await this.getRootCauseToir();
                
                this.isNew = true;
                this.visible = true;
            } catch (e) {
                Notify.showError(e.message);
            } finally {
                BaseTemplate.hideProgress();
            }
        },
        async editItem(factId) {
            BaseTemplate.showProgress();
            try {
                await this.checkObjectPotential();
                // Проверяем коэффициенты
                await this.checkCoefficients();
                
                this.title = 'Редактирование записи';
                this.action = this.actionEdit;
                this.isNew = false;
                await this.loadItem(factId);
                await this.getRelationsAndDeviationLeft(this.eventRow);
                await this.getRootCauseToir();
                
                this.visible = true;
            } catch (e) {
                Notify.showError(e.message);
            } finally {
                BaseTemplate.hideProgress();
            }
        },
        setEventRow(eventRow, datesWithoutObjectPotential) {
            this.eventRow = eventRow;
            this.datesWithoutObjectPotential = datesWithoutObjectPotential;
        },
        // Проверить существует ли на выбранную дату технологические коэффициенты и экономика
        async checkCoefficients() {
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionCheckCoefficients;
            
            let response = await fetch(url, {
                method: 'POST',
                body: JSON.stringify({
                    object: this.eventRow.object.value,
                    date: this.eventRow.date.value
                }),
            });
            
            if (!response.ok) {
                Notify.showError(response.errorMessage || 'Отсутствуют коэффициенты на ' +
                    dayjs(this.eventRow.date.value, 'YYYY-MM-DD').format('DD.MM.YYYY'));
            }
        },
        
        //Проверка наличия потенциала установки
        //вызывается в родителе, который перехватывает исключение
        async checkObjectPotential() {
            if (this.datesWithoutObjectPotential.includes(this.eventRow.date.value)) {
                throw new Error('Не найден потенциал установки для даты: ' +
                    dayjs(this.eventRow.date.value, 'YYYY-MM-DD').format('DD.MM.YYYY'));
            }
        },
        
        //Посчитать остаток для декомпозиции
        async getRelationsAndDeviationLeft(eventRow) {
            let self = this;
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionGetRelationsAndDeviationLeft;
            
            try {
                let response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify({row:eventRow}),
                });
                let data = await response.json();
                if (response.ok) {
                    const factDeviationLeftValue = parseFloat(data.factDeviationLeft);

                    self.factDeviationLeftValue = self.calculateFactDeviationLeft(
                        factDeviationLeftValue,
                        eventRow
                    );
                    self.factDeviationLeft = factDeviationLeftValue;
                    self.objectsList = data.selectOptions;
                    self.objectsAffectedList = data.selectAffectedOptions;
                } else {
                    Notify.showError(data.errorMessage);
                }
            } catch (e) {
                Notify.showError(e.message);
            }
        },
        calculateFactDeviationLeft(initialDeviation, eventRow) {
            const isFlaringAccounting = eventRow['decompositionFactItems-isFlaringAccounting']?.title ?? 0;
            const isOwnNeedsAccounting = eventRow['decompositionFactItems-isOwnNeedsAccounting']?.title ?? 0;

            if (isOwnNeedsAccounting || isFlaringAccounting) {
                return 0;
            }

            return initialDeviation;
        },

        burningRateByDate(dateString) {
            return parseFloat(this.burningRatePlanData[dateString] ?? 0);
        },
        //Предварительное создание списка выбора стороны причины
        getSideCauseList() {
            let result = [];
            for (let number in sideCause) {
                result.push({
                    value: number,
                    text: sideCause[number],
                });
            }
            return result;
        },
        // Метод сохраняет элемент
        async save() {
            BaseTemplate.showProgress();
            const url = `/index.php?module=${this.moduleName}&action=${this.action}`;
            
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify(this.itemData),
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    Notify.showError(data.errorMessage || 'Произошла ошибка сохранения.');
                    BaseTemplate.hideProgress();
                    return;
                }
                
                Notify.showSuccess(data.statusText);
                
                if (data.TOiR3) {
                    const messages = [];
                    
                    // Обработка операции создания/обновления
                    if (data.TOiR3.messageCreate) {
                        const isSuccess = data.TOiR3.httpStatusCreate === 200;
                        messages.push({
                            text: data.TOiR3.messageCreate,
                            type: isSuccess ? 'info' : 'error'
                        });
                    }
                    
                    // Обработка операции удаления
                    if (data.TOiR3.messageDelete) {
                        const isSuccess = data.TOiR3.httpStatusDelete === 200;
                        messages.push({
                            text: data.TOiR3.messageDelete,
                            type: isSuccess ? 'info' : 'error'
                        });
                    }
                    
                    // Показ уведомлений
                    if (messages.length > 0) {
                        messages.forEach(msg => {
                            if (msg.type === 'info') {
                                Notify.showInfo(msg.text);
                            } else {
                                Notify.showError(msg.text);
                            }
                        });
                    }
                }
                
                if (data.divisionByZeroStatus) {
                    Notify.showWarning(data.divisionByZeroStatus);
                }
                
                this.$emit('saved');
                this.exitModal();
            } catch (e) {
                Notify.showError(`${e.message}`);
            }
        },
        
        //Получить доступные для выбора АТР
        async getAtrList() {
            let self = this;
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionGetAtrList;
            
            try {
                let response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify({}),
                });
                let data = await response.json();
                if (response.ok) {
                    self.atrList = data.selectOptions;
                } else {
                    Notify.showError(data.errorMessage);
                }
            } catch (e) {
                Notify.showError(e.message);
            }
        },
        
        async getRootCauseToir() {
            let self = this;
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionGetRootCauseToir;
            
            try {
                let response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify({}),
                });
                let data = await response.json();
                if (response.ok) {
                    self.rootCauseFromToir = data;
                } else {
                    Notify.showError(data.errorMessage);
                }
            } catch (e) {
                Notify.showError(e.message);
            }
        },
        
        //Загрузить данные записи
        async loadItem(item) {
            BaseTemplate.showProgress();
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionLoadItem;
            
            try {
                let response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify({
                        id: item,
                    })
                });
                let data = await response.json();
                if (response.ok) {
                    this.userName = data.userId;
                    this.mtime = data.mtime;
                    
                    this.itemData = data;
                    
                    this.itemData.affectedObjectId = data.affectedObjectId !== ''
                        ? data.affectedObjectId.includes(',')
                            ? data.affectedObjectId.split(',').map(Number)
                            : [Number(data.affectedObjectId)]
                        : [];
                    this.itemData.deviation = parseFloat(this.itemData.deviation);
                    
                    const foundItem = this.atrList.find(item => item.id === this.itemData.document);
                    if (foundItem) {
                        this.docNum = foundItem.docNum;
                        if (foundItem.isNz) {
                            this.doctype = 'nz';
                        } else if (foundItem.isArp) {
                            this.doctype = 'arp';
                        }
                    }
                    
                    this.setDefaults();
                } else {
                    throw new Error(data.errorMessage);
                }
            } catch (e) {
                throw new Error(e.message);
            }
            BaseTemplate.hideProgress();
        },
        deleteItem(id) {
            let self = this;
            Notify.confirmWarning('Вы действительно хотите удалить запись №: ' + id + ' ?', function (state) {
                if (!state) {
                    return false;
                }
                BaseTemplate.showProgress();
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
                        self.$emit('saved', this.itemData);
                        self.exitModal();
                    }
                    BaseTemplate.hideProgress();
                });
            });
        },
        // Закрытие модалки
        exitModal() {
            this.clearData();
            this.visible = false;
            this.action = '';
            this.title = '';
            this.eventRow = null;
            this.userName = null;
            this.mtime = null;
        },
        // Очистка модалки
        clearData() {
            this.itemData = {
                losslogMainDataId: null,
                deviation: null,
                immediateCause: null,
                responsibleObjectId: [],
                affectedObjectId: [],
                comment: '',
                rootCause: null,
                sideCause: null,
                document: '',
                uploadDocs: [],
                idIn: '',
                idOut: '',
                idConsequence: '',
                
                techProcess: null,
                lu: null,
                object: null,
                workshop: null,
            };
            this.docNum = '';
        },
    },
    watch: {
        'itemData.sideCause'(newVal) {
            let value = parseInt(newVal);
            this.disableResponsibleObjectId = value === 1;
            this.disableAffectedByResponsibleSelect = value === 0;
            this.disableDocument = value === 0;
            if (value === 1) {
                this.itemData.responsibleObjectId = [];
            } else if (value === 0) {
                this.itemData.document = '';
                this.itemData.affectedObjectId = [];
            }
        }
    },
    mounted() {
        this.getAtrList();
    },
};
</script>
