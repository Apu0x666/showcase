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
                        
                        <div class="tm-width-1-1">
                            <select-model-multiple
                                title="Ответственный за учет потерь эксплуатационной дирекции/департамента"
                                id="user"
                                model="User_WithPosition"
                                v-model="itemData.users"
                            ></select-model-multiple>
                        </div>
                        
                        <div class="tm-width-1-2">
                            <tm-input-text
                                title="Проектная производительность"
                                id="performance"
                                v-model="itemData.performance"
                            ></tm-input-text>
                        </div>
                        
                        <div class="tm-width-1-2">
                            <tm-input-text
                                title="МДП"
                                id="mdp"
                                v-model="itemData.mdp"
                            ></tm-input-text>
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
    },
    data() {
        return {
            moduleName: 'LossLog_ObjectPotential',
            itemData: {
                id: null,
                workshopId: null,
                objectId: null,
                techProcessId: null,
                performance: null,
                mdp: null,
                users: [],
                dateStart: null,
                dateEnd: null,
                date: null,
                user: null,
            },

            visible: false,
            title: '',

            isNew: true,

            actionCreate: 'create',
            actionEdit: 'edit',
            actionLoadItem: 'getRecord',
            actionGetObjects: 'getObjects',

            objectsList: {},

            action: '',
        };
    },
    computed: {
        objectOptions() {
            return this.objectsList;
        },
    },
    methods: {
        //Модалка на создание новой записи
        newItem() {
            this.title = 'Новая запись';
            this.action = this.actionCreate;
            this.buttonText = 'Добавить запись';
            this.isNew = true;
            this.visible = true;
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
                    this.itemData.users = data.users.split(',').map(Number);
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
                workshopId: null,
                objectId: null,
                techProcessId: null,
                performance: null,
                mdp: null,
                users: [],
                dateStart: null,
                dateEnd: null,
                date: null,
                user: null,
            };
        },
    },
    mounted() {
        this.getObjectsList();
    },
};
</script>
