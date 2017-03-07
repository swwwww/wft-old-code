<?php

namespace Web\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class RedirectController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function indexAction() {
        //header('Location: wanfantian://com.deyi.wanfantian?type=user&id=10013');

    }

}
