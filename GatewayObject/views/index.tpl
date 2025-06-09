<?php

/** @var ViewBase $this */
$this->addVueLoader();
$this->addJs('/modules/LossLog/GatewayObject/views/assets/js/dictionaries.js', false);
$this->addJs('/modules/LossLog/GatewayObject/views/assets/js/vue_script.js', false);

$this->addCss('/modules/LossLog/GatewayObject/views/assets/css/style.css', false);
?>

<div id="app">

    <losslog-gatewayobject-nav-tabs></losslog-gatewayobject-nav-tabs>

    <div class="tm-module-buttons">
        <button v-if="(permissions?.add || permissions?.write || permissions?.admin) && (!initial?.functionalPage)"
                class="tm-btn-rounded" @click="this.$refs.itemEditModal.newItem();">
            Добавить
        </button>
        <button v-if="(permissions?.add || permissions?.write || permissions?.admin) && (!initial?.functionalPage)"
                class="tm-btn-rounded" @click="this.$refs.itemMonthPlan.generate();">
            План на месяц
        </button>
        <button
                v-if="permissions?.export || permissions?.admin"
                class="tm-btn-rounded tm-btn-bordered"
                @click="this.exportToExcel();">
            Экспорт в Excel
        </button>

        <div class="date-filter tm-btn bordered">
            <tm-datepicker title="Период: "
                v-model="filters.date"
                lang="ru"
                type="date"
                range
                value-type="YYYY-MM-DD"
                format="DD.MM.YYYY">
            </tm-datepicker>
        </div>

        <download-instructions module="LossLog_GatewayObject" />
    </div>

    <tm-table-main
        ref="tableExcel"
        class="lossLog-table"
        :hdata="tableHeaders"
        :records="records"
        :groups="groups"
        :pagination="pagination"
        :count-rows="pagesCount"
        @actionclick="handlerActionClick($event)"
    ></tm-table-main>

    <losslog-gatewayobject-item-edit-modal
        ref="itemEditModal"
        :permissions="permissions"
        :initial="initial"
        @saved="loadData()"
    ></losslog-gatewayobject-item-edit-modal>

    <losslog-gatewayobject-item-month-plan
        ref="itemMonthPlan"
        :permissions="permissions"
        :initial="initial"
        @saved="loadData()"
    ></losslog-gatewayobject-item-month-plan>

    <losslog-gatewayobject-item-decomposition-plan-modal
        ref="itemDecompositionPlanModal"
        :permissions="permissions"
        :initial="initial"
        @saved="loadData()"
    ></losslog-gatewayobject-item-decomposition-plan-modal>

    <losslog-gatewayobject-item-decomposition-fact-modal
        ref="itemDecompositionFactModal"
        :permissions="permissions"
        :initial="initial"
        :burning-rate-plan-data="burningRatePlanData"
        @saved="loadData()"
    ></losslog-gatewayobject-item-decomposition-fact-modal>

    <losslog-gatewayobject-item-decomposition-plan-merge-divide
        ref="itemDecompositionPlanMergeDivide"
        :permissions="permissions"
        :initial="initial"
        @saved="loadData()"
    ></losslog-gatewayobject-item-decomposition-plan-merge-divide>

    <losslog-gatewayobject-item-decomposition-fact-merge-divide
        ref="itemDecompositionFactMergeDivide"
        :permissions="permissions"
        :initial="initial"
        @saved="loadData()"
    ></losslog-gatewayobject-item-decomposition-fact-merge-divide>

    <losslog-gatewayobject-item-decomposition-plan-copy-for-date
        ref="itemDecompositionPlanCopyForDate"
        :permissions="permissions"
        :initial="initial"
        @saved="loadData()"
    ></losslog-gatewayobject-item-decomposition-plan-copy-for-date>
</div>
