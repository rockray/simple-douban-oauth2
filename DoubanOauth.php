<?php
/**
 * @file DoubanOauth.php
 * @brief 一个简单的豆瓣PHP Oauth2类
 * @author JonChou <ilorn.mc@gmail.com>
 * @version 0.4
 * @date 2012-12-03
 */

if (!function_exists('curl_init')) {
    throw new Exception('Simple douban oauth2 needs the CURL PHP extension.');
}
if (!function_exists('json_decode')) {
    throw new Exception('Simple douban oauth2 needs the JSON PHP extension.');
}

class DoubanOauth {
    
    /**
     * @var 声明豆瓣OAUTH需要的最基本API链接
     */
    protected $authorizeUri = 'https://www.douban.com/service/auth2/auth';
    protected $accessUri = 'https://www.douban.com/service/auth2/token';
    protected $apiUri = 'https://api.douban.com';
            
    /**
     * @var 声明豆瓣OAUTH需要的APIKEY以及callback链接
     */
    protected $clientId, $secret, $redirectUri, $scope, $responseType;
    
    /**
     * @var 用于储存已获取的令牌
     */
    protected $authorizeCode, $tokens, $accessToken, $refreshToken;

    /**
     * @var 默认请求头信息 
     */
    protected $defaultHeader = array(
                'Content_type: application/x-www-form-urlencoded'
                );
    
    /**
     * @var 需授权的请求头
     */
    protected $authorizeHeader;
    
    /**
     * @var curl默认设置  
     */
    protected $CURL_OPTS = array(
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_USERAGENT      => 'simple-douban-oauth2-0.4',
                );

    /**
     * @brief 初始化豆瓣OAUTH，设置相关参数
     *
     * @param string $client_id
     * @param string $secret
     * @param string $redirect_uri
     * @param string $scope
     * @param string $responseType
     *
     * @return void
     */
    public function __construct($clientId, $secret, $redirectUri, $scope ='douban_basic_common', $responseType = 'code')
    {
        $this->clientId = $clientId;
        $this->secret = $secret;
        $this->redirectUri = $redirectUri;
        $this->scope = $scope;
        $this->responseType = $responseType;

        // API基类路径
        $basePath = dirname(__FILE__).'/api/Base.php';

        // 载入API基类
        try {
            $this->fileLoader($basePath);
        } catch(Exception $e) {
            echo 'Baseloader error:'.$e->getMessage();
        }
    }

    /**
     * @brief 跳转到豆瓣用户授权页面，获取AuthorizeCode
     *
     * @return redirect
     */
    public function getAuthorizeCode()
    {
        // 获取AuthorizeCode请求链接
        $authorizeUrl = $this->getAuthorizeUrl();
        header('Location:'.$authorizeUrl);
    }
    
    /**
     * @brief 设置AuthorizeCode
     *
     * @param string $authorizeCode
     *
     * @return void
     */
    public function setAuthorizeCode($authorizeCode)
    {
        $this->authorizeCode = $authorizeCode;
    }

    /**
     * @brief 通过AuthorizeCode获取accessToken
     *
     * @return string
     */
    public function getAccessToken()
    {
        // 获取accessToken请求链接
        $accessUrl = $this->getAccessUrl();
        $header = $this->defaultHeader;
        // 使用curl模拟请求，获取token信息
        $result = $this->curl($accessUrl, 'POST', $header);
        $this->tokens = json_decode($result);
        // 设置refreshToken,需要时可启用
        //$this->refreshToken = $this->tokens->refresh_token;

        // 设置Access_token
        return $this->accessToken = $this->tokens->access_token;
    }

    /**
     * @brief 生成豆瓣用户授权页面完整地址
     *
     * @return string
     */
    protected function getAuthorizeUrl()
    {
        $params = array(
                    'client_id' => $this->clientId,
                    'redirect_uri' => $this->redirectUri,
                    'response_type' => $this->responseType,
                    'scope' => $this->scope
                    );

        return $this->authorizeUri.'?'.http_build_query($params);
    }

    /**
     * @brief 生成豆瓣access_token完整获取链接
     *
     * @return string
     */
    protected function getAccessUrl()
    {

        $params = array(
                    'client_id' => $this->clientId,
                    'client_secret' => $this->secret,
                    'redirect_uri' => $this->redirectUri,
                    'grant_type' => 'authorization_code',
                    'code' => $this->authorizeCode,
                    );

        return $this->accessUri.'?'.http_build_query($params);
    }

    /**
     * @brief 请求豆瓣API,返回包含相关数据的对象
     *
     * @param object $API
     * @param array $data
     * @param boolean 为true时会在header中发送accessToken
     *
     * @return object
     */
    public function makeRequest($api, $data = null, $authorization = false)
    {
        // API的完整URL
        $url = $this->apiUri.$api->uri;
        $header = $authorization ? $this->getAuthorizeHeader() : $this->defaultHeader;
        $type = $api->type;

        // 发送请求
        return $this->curl($url, $type, $header, $data);
    }
    
    /**
     * @brief 豆瓣API实例注册函数
     *
     * @param string $api
     *
     * @return object
     */
    public function apiRegister($api)
    {
        // 需要注册的API路径
        $apiPath = dirname(__FILE__).'/api/'.ucfirst(strtolower($api)).'.php';

        try {
            $this->fileLoader($apiPath);
        } catch(Exception $e) {
            echo 'Apiloader error:'.$e->getMessage();
        }

        return new $api($this->clientId);
    }

    /**
     * @brief 获取Authorization header
     *
     * @return array
     */
    protected function getAuthorizeHeader()
    {
        return $this->authorizeHeader = array('Authorization: Bearer '.$this->accessToken);
    }

    /**
     * @brief 使用CURL模拟请求，并返回取得的数据
     *
     * @param string $url
     * @param string $type
     * @param array $header
     * @param array $data
     *
     * @return object
     */
    protected function curl($url, $type, $header, $data = null)
    {
        $opts = $this->CURL_OPTS;
        $opts[CURLOPT_URL] = $url;
        $opts[CURLOPT_CUSTOMREQUEST] = $type;
        $header[] = 'Expect:'; 
        $opts[CURLOPT_HTTPHEADER] = $header;
        if ($type == 'POST' || $type =='PUT') {
            $opts[CURLOPT_POSTFIELDS] = $data;
        }

        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            die('CURL error: '.curl_error($sh));
        }

        curl_close($ch);  
        return $result;
    }
    
    /**
     * @brief 文件加载类
     *
     * @param string $path
     *
     * @return void
     */
    protected function fileLoader($path)
    {
        // 文件路径错误时抛出异常
        if ( ! file_exists($path)) {
            throw new Exception('The file you wanted to load does not exists.');
        }

        require $path;
    }
}
