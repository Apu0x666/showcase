<template>
    <tm-modal v-if="visible" @close="exitModal()">
        <template v-slot:header>
            <div><slot name="header">{{ title }}</slot></div>
        </template>
        <template v-slot:body>
            <div style="width: 650px">
                <slot name="body">
                    <div class="tm-grid tm-child-flex-bottom">
                        <div class="tm-width-1-2">
                            <tm-datepicker title="Дата"
                                v-model="itemData.date"
                                lang="ru"
                                type="date"
                                value-type="format"
                                format="DD.MM.YYYY">
                            </tm-datepicker>
                        </div>
                        
                        <div class="tm-width-1-2">
                            <select-model-single
                                title="Технологический процесс"
                                id="techProcess"
                                model="LossLog_Dictionaries_TechProcess_Model"
                                v-model="itemData.techProcess"
                                :filter="{id: techProcess}"
                                keyText="tech_process"
                            ></select-model-single>
                        </div>

                        <div class="tm-width-1-2">
                            <tm-input-text
                                title="План"
                                id="plan"
                                v-model="itemData.plan"
                            ></tm-input-text>
                        </div>

                        <div class="tm-width-1-2">
                            <tm-input-text
                                title="Факт"
                                id="fact"
                                v-model="itemData.fact"
                            ></tm-input-text>
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
        initial: {
            type: Object,
            default: () => {},
        }
    },
    data() {
        return {
            moduleName: 'LossLog_GatewayObject',
            itemData: {
                id: null,
                date: null,
                workshop: null,
                lu: null,
                techProcess: null,
                object: null,
                plan: null,
                fact: null,
            },
            
            defaultData: {},

            visible: false,
            title: '',
            
            isNew: true,

            actionCreate: 'create',
            actionEdit: 'edit',
            actionLoadItem: 'getRecord',

            techProcess: null,

            action: '',
        };
    },
    computed: {
    
    },
    methods: {
        setDefaults() {
            this.techProcess = this.initial.techProcess;
            Object.assign(this.itemData, this.initial);
        },
        //Модалка на создание новой записи
        async newItem() {
            this.setDefaults();
            this.title = 'Новая запись';
            this.action = this.actionCreate;
            this.buttonText = 'Добавить запись';
            this.isNew = true;
            this.visible = true;
        },
        //Модалка на редактирование записи
        //используется та же что и на создание новой, но с подгрузом и заменой кнопки
        async editItem(itemId) {
            this.title = 'Редактирование записи';
            this.action = this.actionEdit;
            this.isNew = false;
            await this.loadItem(itemId);
            this.setDefaults();
            
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
        // Сохранение редактируемого/создаваемого элемента
        async save() {
            BaseTemplate.showProgress();
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.action;
            
            try {
                let response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify(this.itemData),
                });
                
                let data = await response.json();
                
                if (response.ok) {
                    Notify.showSuccess(data.statusText);

                    this.$emit('saved', this.itemData);
                    this.exitModal();
                    BaseTemplate.hideProgress();
                } else {
                    Notify.showError(data.errorMessage);
                    BaseTemplate.hideProgress();
                }
            } catch (e) {
                Notify.showError(e.message);
            }
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
                date: null,
                workshop: null,
                lu: null,
                techProcess: null,
                object: null,
                plan: null,
            };
        },
    },
    mounted() {},
};
</script>
