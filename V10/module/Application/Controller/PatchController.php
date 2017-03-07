<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Controller;

use Deyi\BaseController;
use Deyi\Integral\Integral;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Http\Response;
use Deyi\JsonResponse;


class PatchController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    //设置补丁包目录
    public static function getPatchfile($client_type, $version_name, $patch_code)
    {
        if ($client_type == 'ios') {
            $filename = "patch-{$patch_code}.zip";
        } else {
            $filename = "patch-{$patch_code}.jar";
        }
        //相对目录
        $dir = "/patch/{$client_type}/{$version_name}";
        return array(
            'dir' => $_SERVER['DOCUMENT_ROOT'] . $dir, //绝对目录
            'filename' => $filename,
            'http_dir' => $dir
        );
    }


    public function indexAction()
    {

        /* 参数:
 device		ios	//平台
 appVersion	1.5	// 应用版本号
 patchVersion	1.0	// patch版本号，和应用版本号对应
        */

        if (!$this->pass()) {
            return $this->failRequest();
        }

        $device = $this->getParams('device');  // ios or android
        $appVersion = (string)$this->getParams('appVersion'); //3.0
        $patchVersion = (float)$this->getParams('patchVersion', '0'); //全量更细,总是返回最大的(如果有)

        $data = array(
            'needUpdate' => 0,    // 是否需要patch
            'appVersion' => 0,    // 应用版本号
            'patchVersion' => 0,    // patch版本号，和应用版本号对应
            'patchUrl' => ''
        );

        $db = $this->_getAdapter();
        $res = $db->query("select * from play_patch_update where version_name=? and client_type=? and patch_code>? ORDER BY patch_code DESC ", array($appVersion, $device, $patchVersion))->current();

        if ($res) {
            $patchfile = $this->getPatchfile($res->client_type, $res->version_name, $res->patch_code);
            $data = array(
                'needUpdate' => 1,    // 是否需要patch
                'appVersion' => $appVersion,    // 应用版本号
                'patchVersion' => $res->patch_code,    // patch版本号，和应用版本号对应
                'patchUrl' => $this->_getConfig()['url'] . $patchfile['http_dir'] . '/' . $patchfile['filename'],
                'hashCode' => $res->hash_code
            );
        }

        return $this->jsonResponse($data);


    }
}
