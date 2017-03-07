<?php

/**
 * �ٶȵ�ͼ������
 */
namespace Deyi;
use Zend\Json\Server\Exception\ErrorException;

class BaiduLocation
{
    private static $_instance;

    const REQ_GET = 1;
    const REQ_POST = 2;

    /**
     * ����ģʽ
     * @return map
     */
    public static function instance()
    {
        if (!self::$_instance instanceof self)
        {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    /**
     * ִ��CURL����
     * @author: xialei<xialeistudio@gmail.com>
     * @param $url
     * @param array $params
     * @param bool $encode
     * @param int $method
     * @return mixed
     */
    private function async($url, $params = array(), $encode = true, $method = self::REQ_GET)
    {
        $ch = curl_init();
        if ($method == self::REQ_GET)
        {
            $url = $url . '?' . http_build_query($params);
            $url = $encode ? $url : urldecode($url);
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        else
        {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }
        curl_setopt($ch, CURLOPT_REFERER, '�ٶȵ�ͼreferer');
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (iPhone; CPU iPhone OS 7_0 like Mac OS X; en-us) AppleWebKit/537.51.1 (KHTML, like Gecko) Version/7.0 Mobile/11A465 Safari/9537.53');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $resp = curl_exec($ch);
        curl_close($ch);
        return $resp;
    }

    /**
     * ip��λ
     * @param string $ip
     * @return array
     * @throws Exception
     */
    public function locationByIP($ip)
    {
        //����Ƿ�Ϸ�IP
        if (!filter_var($ip, FILTER_VALIDATE_IP))
        {
            throw new ErrorException('ip��ַ���Ϸ�');
        }
        $params = array(
            'ak' => '9cBeAvrF76W2cM9t1q0Q32Ii',
            'ip' => $ip,
            'coor' => 'bd09ll'//�ٶȵ�ͼGPS����
        );
        $api = 'http://api.map.baidu.com/location/ip';
        $resp = $this->async($api, $params);
        $data = json_decode($resp, true);
        //�д���
        if ($data['status'] != 0)
        {
            throw new ErrorException($data['message']);
        }

        //���ص�ַ��Ϣ
        return array(
            'address' => $data['content']['address'],
            'province' => $data['content']['address_detail']['province'],
            'city' => $data['content']['address_detail']['city'],
            'district' => $data['content']['address_detail']['district'],
            'street' => $data['content']['address_detail']['street'],
            'street_number' => $data['content']['address_detail']['street_number'],
            'city_code' => $data['content']['address_detail']['city_code'],
            'lng' => $data['content']['point']['x'],
            'lat' => $data['content']['point']['y']
        );
    }


    /**
     * GPS��λ
     * @param $lng
     * @param $lat
     * @return array
     * @throws Exception
     */
    public function locationByGPS($lng, $lat)
    {
        $params = array(
            'coordtype' => 'wgs84ll',
            'location' => $lat . ',' . $lng,
            'ak' => '9cBeAvrF76W2cM9t1q0Q32Ii',
            'output' => 'json',
            'pois' => 0
        );
        $resp = $this->async('http://api.map.baidu.com/geocoder/v2/', $params, false);
        $data = json_decode($resp, true);
        if ($data['status'] != 0)
        {
            throw new ErrorException($data['message']);
        }
        return array(
            'address' => $data['result']['formatted_address'],
            'province' => $data['result']['addressComponent']['province'],
            'city' => $data['result']['addressComponent']['city'],
            'street' => $data['result']['addressComponent']['street'],
            'street_number' => $data['result']['addressComponent']['street_number'],
            'city_code'=>$data['result']['cityCode'],
            'lng'=>$data['result']['location']['lng'],
            'lat'=>$data['result']['location']['lat']
        );
    }
}