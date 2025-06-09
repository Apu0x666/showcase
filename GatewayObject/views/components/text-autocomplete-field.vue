<template>
    <tm-input-box
        :title="title"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :error="error"
        :not-clear="notClear"
        @clear="clear"
    >
        <div
            :class="['autocompletion-container', { 'disabled': disabled }]"
        >
            <div
                class="autocompletion-rendered"
                @click="setFocus(true)"
            >{{ currentValue }}</div>
            
            <div v-show="isFocus" class="autocompletion-list-wrapper">
                <input
                    v-model="currentValue"
                    ref="inputSearch"
                    type="text"
                    class="tm-input"
                    autocomplete="off"
                    :maxlength="limit || undefined"
                    :disabled="disabled"
                    @blur="setFocus(false)"
                >
                <div
                    v-if="currentValue && !disabled && !readonly"
                    class="autocompletion-clear-button"
                    @click="clearAndFocus"
                >✕</div>
                <transition name="mx-zoom-in-down">
                    <div v-if="isShowFoundList" class="autocompletion-list">
                        <div v-if="isLoad" class="autocompletion-loader"></div>
                        <ul class="autocompletion-options-list">
                            <li
                                v-for="elem in viewedList"
                                class="autocompletion-option-item"
                                :key="elem.id"
                                v-html="elem.text"
                                @click="setValue(elem)"
                            />
                            <li v-if="isShowNotFound">– Не найдено –</li>
                        </ul>
                    </div>
                </transition>
            </div>
        </div>
    </tm-input-box>
</template>

<script>
export default {
    emits: ['update:modelValue', 'update:id', 'blur'],
    name: 'tm-input-text-autocompletion',
    props: {
        required: {
            type: Boolean,
            default: false,
        },
        disabled: {
            type: Boolean,
            default: false,
        },
        readonly: {
            type: Boolean,
            default: false,
        },
        modelValue: {
            type: [String, null],
            default: '',
        },
        title: {
            type: String,
            default: '',
        },
        error: {
            type: String,
            default: '',
        },
        model: {
            type: String,
            default: '',
        },
        field: {
            type: String,
            default: '',
        },
        minCharsToQuery: {
            type: Number,
            default: 3,
        },
        limit: {
            type: Number,
            default: 50,
        },
        customData: {
            type: Array,
            default: () => [],
        },
    },
    
    computed: {
        notClear() {
            return !this.modelValue || this.isFocus || this.disabled || this.readonly;
        },
        
        currentValue: {
            get() {
                return this.modelValue;
            },
            set(val) {
                if (val !== this.modelValue) {
                    this.$emit('update:modelValue', val);
                }
            },
        },
        
        viewedList() {
            if (this.customData?.length) {
                return this.customData
                    .filter(item => item.text.toLowerCase().includes(this.currentValue.toLowerCase()))
                    .map(item => ({
                        text: item.text.replace(
                            new RegExp(this.currentValue, 'gi'),
                            matched => `<mark>${matched}</mark>`  // Подсвечиваем совпадение
                        ),
                        value: item.id,
                        docNum: item.docNum
                    }));
            }
            
            // Если кастомных данных нет, используем другие данные (например, из API)
            if (!this.autoCompletionList?.length) {
                return null;
            }
            return this.autoCompletionList.map(text => ({
                text: text.replace(
                    new RegExp(this.currentValue, 'gi'),
                    matched => `<mark>${matched}</mark>`
                ),
                value: text
            }));
        },
        
        isShowNotFound() {
            return !this.viewedList?.length && !this.isLoad && this.currentValue.length >= this.minCharsToQuery;
        },
        
        isShowFoundList() {
            return this.isFocus && this.currentValue.length >= this.minCharsToQuery;
        },
    },
    
    data() {
        return {
            autoCompletionList: [],
            isFocus: false,
            isLoad: false,
        };
    },
    
    methods: {
        clear() {
            this.currentValue = '';
            this.setValue({
                docNum: '',
                value: '',
            });
        },
        
        clearAndFocus() {
            this.currentValue = '';
            this.setValue({
                docNum: '',
                value: '',
            });
            setTimeout(() => {
                this.setFocus(true);
            }, 50);
        },
        
        setValue(item) {
            // В поле ввода выводим только docNum
            this.currentValue = item.docNum;
            
            // Передаем только id в родительский компонент
            this.$emit('update:modelValue', item.docNum);  // Отправляем только docNum в родительский компонент
            this.$emit('update:id', item.value);  // Отправляем только ID в родительский компонент
            
            // Очищаем список после выбора
            this.autoCompletionList = [];
            this.isLoad = false;
        },
        
        async getFindTextList() {
            if (this.customData?.length) {
                return; // Кастомные данные уже загружены, не запрашиваем с сервера
            }
            
            this.isLoad = true;
            const text = this.currentValue;
            if (text.length < this.minCharsToQuery) {
                this.autoCompletionList = [];
                this.isLoad = false;
                return;
            }
            const url = '/index.php?module=Api&action=getFindTextList';
            const response = await fetch(url, {
                method: 'POST',
                body: JSON.stringify({
                    modelName: this.model,
                    fieldName: this.field,
                    text: text,
                }),
            });
            const data = await response.json();
            if (response.ok) {
                this.autoCompletionList = data.list;
            } else {
                Notify.showError('Загрузка данных.' + data.message);
            }
            this.isLoad = false;
        },
        
        setFocus(val) {
            if (val == true && !this.disabled && !this.readonly) {
                this.isFocus = true;
                setTimeout(() => {
                    this.$refs.inputSearch.focus();
                }, 200);
            } else {
                setTimeout(() => {
                    this.isFocus = false;
                }, 200);
            }
        },
    },
    
    mounted() {
        this.getFindTextList();
    },
    
    watch: {
        currentValue() {
            clearTimeout(this.debounceTimeoutId);
            this.isLoad = true;
            this.debounceTimeoutId = setTimeout(() => {
                this.getFindTextList();
            }, 500);
        }
    }
};
</script>

<style scoped>
.autocompletion-container {
    display: block;
    position: relative;
}

.disabled > .autocompletion-rendered {
    background-color: #eee;
    cursor: default;
}

.autocompletion-rendered {
    display: block;
    box-sizing: border-box;
    padding: 0 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    height: 40px;
    line-height: 40px;
    background-color: #fffefe;
    cursor: pointer;
    border: 1px solid #151b2140;
    border-radius: 4px;
}

.autocompletion-list-wrapper {
    top: 0;
    position: absolute;
    width: 100%;
    background-color: #fffefe;
    border: 1px solid #151b21;
    border-radius: 4px;
    box-sizing: border-box;
    z-index: 1;
}

.autocompletion-list-wrapper > input{
    border: none;
    padding-right: 32px;
}

.autocompletion-clear-button{
    position: absolute;
    right: 8px;
    top: 0;
    padding: 4px;
    height: 32px;
    line-height: 32px;
    cursor: pointer;
}

.autocompletion-list{
    border-top: 1px solid #000f0c0d;
}

.autocompletion-list ul{
    display: block;
    position: relative;
    width: 100%;
    margin: 4px 0;
    max-height: 300px;
    overflow: hidden auto;
    background-color: #fffefe;
    z-index: 10;
}

.autocompletion-list li {
    cursor: pointer;
    list-style-type: none;
    padding: 8px 12px;
    user-select: none;
}

.autocompletion-list li.autocompletion-option-item:hover {
    background-color: #0000000d;
    color: #151b21;
}

.autocompletion-loader {
    width: 100%;
    height: 1px;
    position: relative;
    overflow: hidden;
}

.autocompletion-loader::before {
    content: '';
    display: block;
    position: absolute;
    left: -50%;
    width: 20%;
    height: 100%;
    background-color: #151b2140;
    animation: autocompletion-loading 2s linear infinite;
}

@keyframes autocompletion-loading {
    0% {
        left: -50%;
    }
    100% {
        left: 100%;
    }
}
</style>
