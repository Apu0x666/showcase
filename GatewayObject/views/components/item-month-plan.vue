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
                        <div class="tm-width-1-2">
                            <tm-datepicker
                                title="Выбор месяца"
                                v-model="date"
                                type="month"
                                lang="ru"
                                format="MM.YYYY"
                            />
                        </div>
                        
                        <div class="tm-width-1-2">
                            <select-model-single
                                title="Технологический процесс"
                                id="techProcess"
                                model="LossLog_Dictionaries_TechProcess_Model"
                                v-model="itemsData.techProcess"
                                :filter="{id: techProcess}"
                                keyText="tech_process"
                            ></select-model-single>
                        </div>
                        
                        <div class="tm-grid" v-if="itemsData.daysInMonth.length">
                            <div v-for="day in itemsData.daysInMonth" :key="day" class="tm-width-1-7">
                                <tm-input-text
                                    :title="day.toString()"
                                    :id="'id' + day"
                                    v-model="itemsData.dailyValues[day - 1]"
                                ></tm-input-text>
                            </div>
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
                        @click="copyFromFirst">Заполнить с 1-ого числа
                    </button>
                    
                    <button
                        class="tm-btn tm-margin-horz"
                        v-if="permissions.edit"
                        @click="save">Создать
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
            
            itemsData: {
                date: '',
                daysInMonth: [],
                dailyValues: [],
                workshop: null,
                lu: null,
                techProcess: null,
                object: null,
            },
            
            techProcess: null,
            
            visible: false,
            title: '',
            
            date: new Date(), // Хранит выбранный месяц и год
            
            actionCreate: 'createMonthPlan',
            actionDefaultData: 'getDefaultData',
            
            action: '',
        };
    },
    watch: {
        'date': 'generateDaysInMonth', // Следим за изменением даты
    },
    methods: {
        setDefaults() {
            this.techProcess = this.initial.techProcess;
            Object.assign(this.itemsData, this.initial);
        },
        generate() {
            this.setDefaults();
            this.title = 'Заполнение плана на месяц';
            this.action = this.actionCreate;
            this.generateDaysInMonth();
            this.visible = true;
        },
        copyFromFirst() {
            const firstValue = this.itemsData.dailyValues[0];
            if (firstValue !== undefined) {
                this.itemsData.dailyValues = this.itemsData.dailyValues.map(() => firstValue);
            }
        },
        generateDaysInMonth() {
            if (!this.date) return;
            
            const date = new Date(this.date);
            const month = date.getMonth() + 1;
            const year = date.getFullYear();
            const lastDayOfMonth = new Date(year, month, 0).getDate();
            this.itemsData.date = month + '.' + year;
            
            this.itemsData.daysInMonth = Array.from({length: lastDayOfMonth}, (_, i) => i + 1);
            this.itemsData.dailyValues = Array.from({length: lastDayOfMonth}, () => '');
        },
        async save() {
            BaseTemplate.showProgress();
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.action;
            
            try {
                let response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify(this.itemsData),
                });
                
                let data = await response.json();
                
                if (response.ok) {
                    Notify.showSuccess(data.statusText);
                    
                    this.$emit('saved', this.itemsData);
                    this.exitModal();
                    BaseTemplate.hideProgress();
                } else {
                    Notify.showError(data.errorMessage);
                    if (data.statusText !== undefined) {
                        Notify.showSuccess(data.statusText);
                    }
                    BaseTemplate.hideProgress();
                }
            } catch (e) {
                Notify.showError(e.message);
            }
        },
        exitModal() {
            this.clearData();
            this.visible = false;
            this.action = '';
            this.title = '';
        },
        clearData() {
            this.itemsData = {
                date: null,
                daysInMonth: [],
                dailyValues: [],
                techProcess: null,
            };
        },
    },
    mounted() {
    },
};
</script>
