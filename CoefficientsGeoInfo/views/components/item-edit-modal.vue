<template>
    <tm-modal v-if="visible" @close="exitModal()">
        <template v-slot:header>
            <div>
                <slot name="header">{{ title }}</slot>
            </div>
        </template>
        <template v-slot:body>
            <div style="width: 650px">
                <slot name="body">
                    <div class="tm-grid">
                        <div class="tm-width-1-1">
                            <select-single
                                title="Установка"
                                id="object"
                                :options="objectOptions"
                                v-model="itemData.objectId"
                            ></select-single>
                        </div>
                        
                        <div class="tm-grid tm-child-flex-bottom"
                             v-if="itemData.coefficients && Object.keys(itemData.coefficients).length">
                            <div v-for="([key, coefficient]) in Object.entries(itemData.coefficients)"
                                 :key="key" class="tm-width-1-4">
                                <tm-input-text
                                    :title="coefficient.title"
                                    :id="'id' + key"
                                    v-model="itemData.coefficients[key].value"
                                ></tm-input-text>
                            </div>
                        </div>
                        
                        <div class="tm-width-1-1">
                            <select-model-single
                                title="Ответственный за учет потерь эксплуатационной дирекции/департамента"
                                id="userId"
                                model="User_WithPosition"
                                v-model="itemData.responsible"
                            ></select-model-single>
                        </div>
                        
                        <div class="tm-width-1-2">
                            <tm-datepicker title="Дата от"
                                v-model="itemData.dateStart"
                                lang="ru"
                                type="date"
                                value-type="format"
                                format="DD.MM.YYYY">
                            </tm-datepicker>
                        </div>
                        
                        <div class="tm-width-1-2">
                            <tm-datepicker title="Дата до"
                                v-model="itemData.dateEnd"
                                lang="ru"
                                type="date"
                                value-type="format"
                                format="DD.MM.YYYY">
                            </tm-datepicker>
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
        coefficients: {
            type: Object,
            default: () => {
                return {};
            },
        },
    },
    data() {
        return {
            moduleName: 'LossLog_CoefficientsGeoInfo',
            itemData: {
                id: null,
                objectId: null,
                techProcessId: null,
                coefficients: {},
                responsible: null,
                dateStart: '',
                dateEnd: '',
                user: '',
            },

            visible: false,
            title: '',

            isNew: true,

            actionCreate: 'create',
            actionEdit: 'edit',
            actionLoadItem: 'getRecord',
            actionGetObjects: 'getObjects',
            actionDeleteData: 'delete',
            
            techProcess: [],
            objectsList: {},

            action: '',
        };
    },
    computed: {
        objectOptions() {
            return this.objectsList;
        },
        processedCoefficients() {
            return Array.isArray(this.coefficients)
                ? this.coefficients.reduce((acc, coeff) => {
                    acc[coeff.id] = {
                        title: coeff.coefficientName,
                        value: '0.00000', // Значение по умолчанию
                    };
                    return acc;
                }, {})
                : {}; // Если не массив, возвращаем пустой объект
        },
    },
    methods: {
        //Модалка на создание новой записи
        newItem(techId) {
            this.title = 'Новая запись';
            this.action = this.actionCreate;
            this.buttonText = 'Добавить запись';
            this.isNew = true;
            this.visible = true;
            this.itemData.losslogCoefficientsTechInfoId = techId;
        },
        //Модалка на редактирование записи
        //используется та же что и на создание новой, но с подгрузом и заменой кнопки
        editItem(itemId) {
            this.title = 'Редактирование записи';
            this.action = this.actionEdit;
            this.isNew = false;
            this.loadItem(itemId);

            this.visible = true;
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
                } else {
                    Notify.showError(data.errorMessage);
                }
            } catch (e) {
                Notify.showError(e.message);
            }
            BaseTemplate.hideProgress();
        },
        async getObjectsList() {
            let self = this;
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionGetObjects;
            
            try {
                let response = await fetch(url, {
                    method: 'POST',
                });
                let data = await response.json();
                if (response.ok) {
                    self.objectsList = data;
                } else {
                    Notify.showError(data.errorMessage);
                }
            } catch (e) {
                Notify.showError(e.message);
            }
        },
        // Сохранение редактируемого/создаваемого элемента
        async save() {
            BaseTemplate.showProgress();
            const url = `/index.php?module=${this.moduleName}&action=${this.action}`;
            
            Object.entries(this.itemData.coefficients).forEach(([, item]) => {
                if (typeof item.value === 'string') {
                    item.value = item.value.replace(',', '.');
                }
            });
            
            this.itemData.module = this.moduleName;
            
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify(this.itemData),
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    Notify.showError(data.errorMessage || 'Произошла ошибка сохранения.');
                    return;
                }

                Notify.showSuccess(data.statusText);
                this.$emit('saved', this.itemData);
                this.exitModal();
            } catch (e) {
                Notify.showError(e.message);
            } finally {
                BaseTemplate.hideProgress();
            }
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
                        Notify.showSuccess(data.statusText);
                        self.$emit('saved', this.itemData);
                    }
                });
            });
        },
        // Закрыть модалку
        exitModal() {
            this.clearData();
            this.visible = false;
            this.action = '';
            this.title = '';
        },
        // Очистка модалки
        clearData() {
            this.itemData = {
                id: null,
                objectId: null,
                techProcessId: null,
                coefficients: {},
                responsible: null,
                dateStart: '',
                dateEnd: '',
                user: '',
            };
        },
    },
    mounted() {
        this.getObjectsList();
        this.itemData.coefficients = this.processedCoefficients;
    },
    updated() {
        this.itemData.coefficients = this.processedCoefficients;
    },
};
</script>
