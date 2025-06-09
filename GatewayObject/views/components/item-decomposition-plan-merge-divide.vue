<template>
    <tm-modal v-if="visible" @close="exitModal()">
        <template v-slot:header>
            <div>
                <slot name="header">{{ title }}</slot>
            </div>
        </template>
        <template v-slot:body>
            <div style="width: 350px">
                <slot name="body">
                    <div class="tm-grid">
                        <div class="tm-width-1-1">
                            <tm-input-number
                                title="Введите значение, которое нужно оставить в текущей записи"
                                id="deviationLeft"
                                v-model="itemData.deviation"
                                v-if="isShow('divide')"
                            ></tm-input-number>
                            
                            <select-single
                                title="Объединить с записью"
                                id="mergeWith"
                                :options="decompositionsList"
                                v-model="itemData.sourceItem"
                                v-if="isShow('merge')"
                            ></select-single>
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
                        @click="save">{{ this.buttonText }}
                    </button>
                    
                    <button class="tm-btn" @click="exitModal()">Отмена</button>
                </slot>
            </div>
        </template>
    </tm-modal>
</template>

<script>
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
        }
    },
    data() {
        return {
            moduleName: 'LossLog_DecompositionPlan',
            
            itemData: {
                id: null,
                losslogMainDataId: null,
                deviation: null,
                idIn: '',
                idOut: '',
                idConsequence: '',
                mergeOrDivide: false,
                sourceItem: null,
                
                techProcess: null,
                lu: null,
                object: null,
                workshop: null,
            },
            
            mergeOrDivide: '',
            
            visible: false,
            title: '',
            buttonText: '',
            eventRow: null,
            
            decompositionsList: null,
            
            //сколько оставляем отклонения в текущей записи, остальное в разделение
            itemDeviation: null,
            deviationLeft: null,
            
            actionDivide: 'divide',
            actionEdit: 'edit',
            actionDeleteData: 'delete',
            actionLoadItem: 'getRecord',
            actionGetDecompositionOptions: 'getDecompositionsByMainId',
            actionCheckCoefficients: 'checkCoefficients',
        };
    },
    computed: {},
    watch: {},
    methods: {
        setDefaults() {
            Object.assign(this.itemData, this.initial);
        },
        isShow(type) {
            return this.itemData.mergeOrDivide === type;
        },
        setEventRow(eventRow) {
            this.eventRow = eventRow;
        },
        //Модалка на разделение декомпозиции
        async divideForm(planId) {
            BaseTemplate.showProgress();
            try {
                // Проверяем коэффициенты
                await this.checkCoefficients();
                
                this.title = 'Разделить декомпозицию плана';
                this.action = this.actionEdit;
                this.buttonText = 'Разделить';
                this.itemData.losslogMainDataId = this.eventRow.id.value;
                
                await this.loadItem(planId);
                
                this.itemData.mergeOrDivide = 'divide';
                this.visible = true;
            } catch (e) {
                Notify.showError(e.message);
            } finally {
                BaseTemplate.hideProgress();
            }
        },
        //Модалка на объединение декомпозиции
        async mergeForm(planId) {
            BaseTemplate.showProgress();
            try {
                // Проверяем коэффициенты
                await this.checkCoefficients();
                
                this.title = 'Объединить декомпозицию плана';
                this.action = this.actionEdit;
                this.buttonText = 'Объединить';
                this.itemData.losslogMainDataId = this.eventRow.id.value;
                
                await this.loadItem(planId);
                await this.getDecompositionOptions();
                
                this.itemData.mergeOrDivide = 'merge';
                this.visible = true;
            } catch (e) {
                Notify.showError(e.message);
            } finally {
                BaseTemplate.hideProgress();
            }
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
                throw new Error(response.errorMessage || 'Отсутствуют коэффициенты на ' +
                    dayjs(this.eventRow.date.value, 'YYYY-MM-DD').format('DD.MM.YYYY'));
            }
        },
        async getDecompositionOptions() {
            BaseTemplate.showProgress();
            
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionGetDecompositionOptions;
            
            try {
                let response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify(this.itemData),
                });
                let data = await response.json();
                if (response.ok) {
                    this.decompositionsList = data;
                    Notify.showSuccess(data.statusText);
                } else {
                    Notify.showError(data.errorMessage);
                    BaseTemplate.hideProgress();
                }
            } catch (e) {
                Notify.showError(e.message);
                BaseTemplate.hideProgress();
            }
        },
        validateDeviation() {
            if (this.itemData.mergeOrDivide === 'merge') return false;
            if (parseFloat(this.itemData.deviation) >= parseFloat(this.itemDeviation)) {
                throw new Error('Введённое значение не может быть больше или равно начального значения декомпозиции');
            }
            this.deviationLeft = parseFloat(this.itemDeviation) - parseFloat(this.itemData.deviation);
        },
        async save() {
            BaseTemplate.showProgress();
            let self = this;
            
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.action;
            
            try {
                this.validateDeviation();
                
                let response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify(this.itemData),
                });
                
                let data = await response.json();
                
                if (response.ok) {
                    Notify.showSuccess(data.statusText);
                    
                    if (data.TOiR3?.success === true) {
                        Notify.showInfo(data.TOiR3?.message);
                    } else {
                        Notify.showError(data.TOiR3?.message);
                    }
                    
                    if (this.itemData.mergeOrDivide === 'divide') {
                        await this.divide();
                    }
                    if (this.itemData.mergeOrDivide === 'merge') {
                        self.$emit('saved', this.itemData);
                        self.exitModal();
                    }
                } else {
                    Notify.showError(data.errorMessage);
                    BaseTemplate.hideProgress();
                }
            } catch (e) {
                Notify.showError(e.message);
                BaseTemplate.hideProgress();
            }
        },
        // Создание дополнительной записи
        async divide() {
            
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionDivide;
            
            try {
                this.itemData.deviation = this.deviationLeft;
                let response = await fetch(url, {
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
                
                this.$emit('saved', this.itemData);
                this.exitModal();
            } catch (e) {
                Notify.showError(e.message);
            }
        },
        deleteItem(id) {
            let self = this;
            let url = '/index.php?module=' + self.moduleName + '&action=' + self.actionDeleteData;
            fetch(url, {
                method: 'POST',
                body: JSON.stringify({
                    id: id,
                }),
            }).then(response => response.json()).then(data => {
                if (data.error) {
                    Notify.showError(data.error);
                } else {
                    self.$emit('saved', this.itemData);
                    self.exitModal();
                    BaseTemplate.hideProgress();
                }
            });
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
                    this.itemData = data;
                    this.itemDeviation = data.deviation;
                    this.itemData.deviation = parseFloat(data.deviation);
                    this.setDefaults();
                } else {
                    Notify.showError(data.errorMessage);
                }
            } catch (e) {
                Notify.showError(e.message);
            }
            BaseTemplate.hideProgress();
        },
        exitModal() {
            this.clearData();
            this.visible = false;
            this.action = '';
            this.title = '';
        },
        clearData() {
            this.itemsData = {
                id: null,
                losslogMainDataId: null,
                deviation: null,
                idIn: '',
                idOut: '',
                idConsequence: '',
                mergeOrDivide: '',
            };
        },
    },
    mounted() {
    },
};
</script>
