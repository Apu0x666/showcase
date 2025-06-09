<?php

use LossLog_Dictionaries_Controller as ControllerDictionaries;

/**
 * Контроллер Модели Установки.
 */
class LossLog_Dictionaries_Objects_Controller extends ControllerDictionaries
{
    public string $permissionName = 'losslog_dictionaries';

    public function __construct()
    {
        parent::__construct();
        $this->dictionary = new LossLog_Dictionaries_Objects_Model();
    }
}
