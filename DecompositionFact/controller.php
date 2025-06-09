<?php

use Ep\App\Core\Acl;

class LossLog_DecompositionFact_Controller extends LossLog_Controller
{
    use LossLog_Decomposition_Trait;

    public string $permissionName = 'losslog_decomposition_fact';
    protected int $permissionType = Acl::READ;
    protected bool $checkPermission = true;

    public int $version = 3;

    public function __construct()
    {
        $this->setModel(new LossLog_DecompositionFact_Model());
        parent::__construct();
    }
}
