<?php

/** @var ViewBase $this */
$this->addVueLoader();
$this->addJs('/modules/LossLog/ProductionKPE/views/assets/js/vue_script.js', false);

$this->addCss('/modules/LossLog/ProductionKPE/views/assets/css/style.css', false);
?>

<div id="app">

    <div class="tm-module-buttons">
        <div class="date-filter tm-btn bordered">
            <tm-datepicker title="Период: "
                           v-model="filters.date"
                           lang="ru"
                           type="month"
                           :range=true
                           format="YYYY-MM"
                           :value-type="'YYYY-MM'">
            </tm-datepicker>
        </div>
    </div>

    <tm-table-main
        ref="tableExcel"
        class="lossLog-table"
        :hdata="getTableHeaders"
        :records="getRecords"
        :filters="getTableFilters"
        :groups="groupsData"
        @actionclick="handlerActionClick($event)"
    ></tm-table-main>


</div>
