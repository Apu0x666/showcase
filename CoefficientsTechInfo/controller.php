<?php

use Ep\App\Core\Acl;

class LossLog_CoefficientsTechInfo_Controller extends LossLog_Controller
{
    use LossLog_Coefficients_Controller_Trait;

    public string $permissionName = 'losslog_coefficients_tech_info';
    public int $version = 3;
    protected int $permissionType = Acl::READ;
    protected bool $checkPermission = true;

    public function __construct()
    {
        parent::__construct();
        $this->setModel(new LossLog_CoefficientsTechInfo_Model());
    }
}
