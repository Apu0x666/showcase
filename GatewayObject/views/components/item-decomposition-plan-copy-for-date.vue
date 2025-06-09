<template>
    <tm-modal v-if="visible" @close="exitModal()">
        <template v-slot:header>
            <div>
                <slot name="header">{{ title }}</slot>
            </div>
        </template>
        <template v-slot:body>
                <slot name="body">
                    <div class="tm-grid" style="width: 450px">
                        <div class="tm-width-1-1">
                            <tm-datepicker
                                title="Даты для копирования"
                                v-model="date"
                                type="date"
                                :range=true
                                format="DD.MM.YYYY"
                                :value-type="'YYYY-MM-DD'"
                                :disabled-date="isDisabledDate"
                            />
                        </div>
                        
                        <div class="tm-width-1-1" v-if="!shouldHideCheckbox">
                            <tm-input-checkbox
                                title="Учитывать значение декомпозиции при копировании"
                                id="useDeviation"
                                v-model="useDeviation"
                                inputClass="tm-switcher"
                                :checked="useDeviation"
                            />
                        </div>
                    </div>
                </slot>
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
            date: null,
            eventRow: null,
            visible: false,
            activeDates: [],
            fullData: null,
            title: '',
            buttonText: 'Скопировать пояснения',
            action: 'planCopyForDate',
            actionGetActiveDates: 'planCopyForDateGetActiveDates',
            useDeviation: true,
        };
    },
    computed: {
        shouldHideCheckbox() {
            const currentId = this.eventRow?.['decompositionPlanItems-planActions']?.actions?.[0]?.id;
            if (!currentId || !this.eventRow?.decompositionPlanItems) return false;
            
            const currentItem = this.eventRow.decompositionPlanItems.find(
                item => item.id === currentId
            );
            
            return currentItem?.isOwnNeedsAccounting === 1
                || currentItem?.isFlaringAccounting === 1;
        }
    },
    methods: {
        isDisabledDate(renderedDate) {
            if (!(renderedDate instanceof Date)) {
                return true; // Блокируем все невалидные даты
            }
            
            // Преобразуем renderedDate в строку формата 'YYYY-MM-DD'
            const formattedDate = dayjs(renderedDate).format('YYYY-MM-DD');
            
            // Возвращаем true для дат, которые отсутствуют в this.activeDates
            return !this.activeDates.includes(formattedDate);
        },
        setEventRow(eventRow) {
            this.eventRow = eventRow;
        },
        //Модалка на объединение декомпозиции
        async copyForDateForm() {
            this.title = 'Скопировать пояснения';
            this.buttonText = 'Скопировать пояснения';
            await this.getActiveDates();
            
            this.visible = true;
        },
        async save() {
            Notify.confirm('Вы уверены в правильности введенных дат?',  async(state) => {
                if (!state) {
                    return;
                }
                
                BaseTemplate.showProgress();
                let url = '/index.php?module=' + this.moduleName + '&action=' + this.action;
                
                try {
                    // Сначала сортируем this.date, чтобы гарантировать правильный порядок
                    // (т.к. существует возможность выбора от большего к меньшему)
                    const [startDate, endDate] = this.date.sort((a, b) => new Date(a) - new Date(b));
                    
                    // Фильтруем элементы, которые находятся в указанном диапазоне
                    let filteredIds = this.fullData
                        .filter(item => {
                            const itemDate = new Date(item.date);
                            return itemDate >= new Date(startDate) && itemDate <= new Date(endDate);
                        })
                        .map(item => item.id);
                    
                    let response = await fetch(url, {
                        method: 'POST',
                        body: JSON.stringify({
                            ids: filteredIds,
                            object: this.eventRow['object'].value,
                            id: this.eventRow['decompositionPlanItems-planActions'].actions[0].id,
                            useDeviation: (this.shouldHideCheckbox) ? false : this.useDeviation,
                        }),
                    });
                    
                    let data = await response.json();
                    
                    if (response.ok) {
                        Notify.showSuccess(data.statusText);
                        
                        if (data.TOiR3?.success === true) {
                            Notify.showInfo(data.TOiR3?.message);
                        } else {
                            Notify.showError(data.TOiR3?.message);
                        }
                        
                        this.$emit('saved', this.itemsData);
                        this.exitModal();
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
            });
        },
        
        async getActiveDates() {
            BaseTemplate.showProgress();
            let url = '/index.php?module=' + this.moduleName + '&action=' + this.actionGetActiveDates;
            
            try {
                let response = await fetch(url, {
                    method: 'POST',
                    body: JSON.stringify({objectId: this.initial.object}),
                });
                
                let data = await response.json();
                
                if (response.ok) {
                    this.fullData = data;
                    // Записываем только даты в this.activeDates
                    this.activeDates = data.map(item => item.date);
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
        },
        clearData() {
            this.date = null;
        },
    },
    mounted() {
    },
};
</script>
