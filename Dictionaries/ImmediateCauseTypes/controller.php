<?php

use LossLog_Dictionaries_Controller as ControllerDictionaries;

/**
 * Контроллер Модели Цеха.
 */
class LossLog_Dictionaries_ImmediateCauseTypes_Controller extends ControllerDictionaries
{
    public string $permissionName = 'losslog_dictionaries_coefficient_type';

    public function __construct()
    {
        parent::__construct();
        $this->dictionary = new LossLog_Dictionaries_ImmediateCauseTypes_Model();
    }
}
