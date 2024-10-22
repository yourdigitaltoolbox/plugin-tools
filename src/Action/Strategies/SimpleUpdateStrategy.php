<?php

namespace YDTBWP\Action\Strategies;

use YDTBWP\Action\UpdateStrategyInterface;
use YDTBWP\Utils\Requests;

class SimpleUpdateStrategy implements UpdateStrategyInterface
{
    public function update($item)
    {
        $body = new \stdClass();
        $body->ref = "main";
        $body->inputs = new \stdClass();
        $body->inputs->json = \json_encode($item);
        Requests::updateRequest(json_encode($body));
    }
}
