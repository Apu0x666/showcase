<?php

class LossLog_Dictionaries_TabGroups_Model extends Model_Extended
{
    protected string $table = 'losslog_tab_groups';

    public array $fields = [
        'id'         => ['type' => 'primarykey'],
        'name'       => ['type' => 'string'],
        'ctime'      => ['type' => 'int'],
        'mtime'      => ['type' => 'int'],
        'created_id' => ['type' => 'int'],
        'user_id'    => ['type' => 'int'],
    ];

    public array $editableFields = ['id', 'name'];
}
