<?php

namespace Ep\App\Modules\LossLog\Datareon;

use Ep\App\Trait\Datareon\ToirSerializer;

class LossLogProcessor
{
    use ToirSerializer;

    public function __construct(string $entity, string $format)
    {
        $this->entity = $entity;
        $this->format = $format;
    }
}
