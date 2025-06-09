<?php

/** @var ViewBase $this */
$this->addVueLoader();
$this->addJs('/modules/LossLog/Economy/views/assets/js/vue_script.js', false);

$this->addCss('/modules/LossLog/Economy/views/assets/css/style.css', false);
?>

<div id="app">

    <div class="tm-sticky-left">
        <div class="tm-module-buttons">
            <button v-if="permissions.edit || permissions.admin"
                class="tm-btn-rounded" @click="this.$refs.itemEditModal.newItem();">
                Добавить
            </button>
        </div>
        <div class="tm-module-filter">
            <filter-blocks
                @created="loadFilters"
                :fields="filterSettings"
                :enable-clear-button="true"
                :enable-remember-state="false"
                :notfilter="true"
            ></filter-blocks>
        </div>
    </div>

    <tm-table-main
        ref="tableExcel"
        class="lossLog-table"
        :hdata="getTableHeaders"
        :records="getRecords"
        :pagination="pagination"
        :filters="getTableFilters()"
        :groups="groupsData"
        :count-rows="pagesCount"
        @actionclick="handlerActionClick($event)"
    ></tm-table-main>

    <losslog-economy-item-edit-modal
        ref="itemEditModal"
        :permissions="permissions"
        @saved="loadData()"
        :products="products"
    ></losslog-economy-item-edit-modal>
</div>
