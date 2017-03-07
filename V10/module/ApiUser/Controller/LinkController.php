<?php

namespace ApiUser\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use Zend\Mvc\Controller\AbstractActionController;

class LinkController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function indexAction()
    {
        return array();
    }
}
