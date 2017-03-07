<?php
namespace Deyi;

use library\Service\System\Logger;
use Zend\Http\Response;
use Zend\View\Model\JsonModel;

trait JsonResponse
{
    static public $init_verification_token=true;  //token验证
    public $pagecache = 0;
    public $status_code = array(
        200 => '请求成功',
        400 => '请求参数错误',
        401 => '用户登录验证信息已失效',
        403 => '数据解密失败',
        404 => '请求接口不存在',
        500 => '服务器内部错误'
    );

    public function jsonResponse($v, $ttl = 0, $status = Response::STATUS_CODE_200)
    {
        $this->getResponse()->setStatusCode($status);
        $headers = $this->getResponse()->getHeaders();
        $headers->addHeaderLine("max-age", $ttl);

        $message = $this->PregNull($v);
        if (isset($message['status']) and $message['status'] == 0) { //记录操作失败的日志
            Logger::writeLog("Response:{$_SERVER['REQUEST_URI']}|返回:{$message['message']}\n" . json_encode($message, JSON_UNESCAPED_UNICODE).'|useragent:'.$this->getUserAgent());
        }
        return new JsonModel(array('response_params' => $message));
    }

    public function jsonResponseCache($v)
    {
//        header('Content-Type:application/json;charset=UTF-8');
        return $this->jsonResponse(json_decode($v, true));
    }


    public function jsonResponseError($message, $status = Response::STATUS_CODE_400, $ERROR_CODE = 0)
    {
        $this->getResponse()->setStatusCode($status);
        Logger::writeLog("ResponseError:{$_SERVER['REQUEST_URI']}|返回:{$message}\n".'|useragent:'.$this->getUserAgent());
        return new JsonModel(array('error_code' => $ERROR_CODE, 'error_msg' => $message));
    }


    public function failRequest()
    {
        if(JsonResponse::$init_verification_token===false){
            return $this->jsonResponseError('token失效,请重新登录',Response::STATUS_CODE_401,1001);
        }else{
            return $this->jsonResponseError('接口验证失败',Response::STATUS_CODE_403);
        }
    }


    //admin模块使用
    public function jsonResponsePage($v)
    {
        $message = $this->PregNull($v);
        return new JsonModel($message);
    }


    /**
     * 转换数组里面的null为空字符串
     * @param $v
     * @return array
     *
     */
    public function PregNull(&$v)
    {
        if (is_array($v)) {
            foreach ($v as $k => $a) {
                if (gettype($a) == 'array') {
                    $v[$k] = $this->PregNull($v[$k]);
                } else if ($a === null) {
                    $v[$k] = '';
                }
            }
            return $v;
        }
        return $v;
    }


    public function getUserAgent(){
        $http_user_agent=isset($_SERVER['HTTP_USER_AGENT'])?$_SERVER['HTTP_USER_AGENT']:'';
        $ios_user_agent=isset($_SERVER['HTTP_USERAGENT'])?$_SERVER['HTTP_USERAGENT']:'';
        if ($ios_user_agent) {
            $http_user_agent= $ios_user_agent;
        }
        if(!$http_user_agent){
            return "UserAgent 不存在";
        }else{
            return $http_user_agent;
        }
    }
}