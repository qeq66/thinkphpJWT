<?php
namespace app\index\controller;

use think\Controller;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Parser;
use think\cache\driver\Redis;

class AdminControl extends Controller
{

	/**
     * 登录用户资料
     */
    protected $loginUserInfo;

    /**
     * 私钥
     */
    protected $key = 'HelloWorld';

    /**
     * 初始化
     *
     * @param
     * @return
     */
    public function _initialize()
    {

        // 测试中 先全部进行 true
        // return true;

        // 验证token
        $this -> loginUserInfo = $this -> checkToken();

        if (!$this -> loginUserInfo) {
            // 验证方法是否可以访问
            $this -> checkAction();
        }
    }

    /**
     * 检查token
     *
     * @param
     * @return array 用户资料
     */
    protected final function checkToken()
    {
    	$tokenInfo = request() -> header('Authorization');
    	if (!$tokenInfo) {
    		return false;
    	}

    	// 加密算法
        $signer = new Sha256();

    	// 获取token并转为对象
        $token = (new Parser()) -> parse((string) $tokenInfo);

        // 获取token内用户信息
        $userInfo = (Array) $token -> getClaim('data');

        // 验证token是否合法
        if(!$token -> verify($signer, $this -> key)){
            echo json_encode(['code' => '10002', 'msg' => 'token is not legal']);die;
        }

        // 判断token是否过期
        $tokenEXP = $token->getClaim('exp');
        if ($tokenEXP <= time()) {
        	echo json_encode(['code' => '10003', 'msg' => 'token has expired']);die;
        }

        // 判断token类型
        if ($userInfo['scope'] == 'refresh_token') {

        	// 获取jwt信息
        	$jti = $token -> getHeader('jti');

        	// 判断是否存在此jti对应的token
        	$redis = new Redis();
        	if (!$redis -> get($jti)) {
        		echo json_encode(['code' => '10005', 'msg' => 'token is not found']);die;
        	} else {
        		$redis -> rm($jti);
        	}

        	// 获取access_token
	   		$access_token = $this -> getToken($userInfo);

	   		// 获取refresh_token
	   		$refresh_token = $this -> getToken($userInfo, true);

	   		// 返回数据
	   		echo json_encode([
				'data' => [
					'access_token' => $access_token,
					'refresh_token' => $refresh_token
				],
				'message' => 'success',
				'code' => response() -> getCode()
	   		]);die;
        } elseif ($userInfo['scope'] == 'access_token') {
        	return $userInfo;
        } else {
        	echo json_encode(['code' => '10004', 'msg' => 'token type error']);die;
        }

    }

    /**
     * 获取token
     *
     * @param bool false获取access_token,true获取refresh_token
     * @return string token
     */
    public function getToken($user, $flag = false)
    {
    	if (!$flag) {
    		$user['scope'] = 'access_token';
    		$time = time() + 86400;//过期时间1天
    	} else {
    		$user['scope'] = 'refresh_token';
    		$time = time() + (86400 * 30);//过期时间30天
    	}
    	// 加密算法
        $signer = new Sha256();
    	$aud = $user['ID'];
    	$jti = md5(uniqid(md5(microtime(true).$user['ID']),true));
		$token = (new Builder()) -> setIssuer(request() -> domain()) // iss 该JWT的签发者
		                        -> setAudience($aud) // aud 接收jwt的一方
		                        -> setId($jti, true) // jti JWT ID为web token提供唯一标识
		                        -> setIssuedAt(time()) // iat 在什么时候签发的token
		                        -> setExpiration($time) // exp token什么时候过期
		                        -> set('data', (Object) $user) // Configures a new claim, called "uid"
		                        -> sign($signer, $this -> key) // key 私钥
		                        -> getToken(); // Retrieves the generated token
		if ($flag) {
			$redis = new Redis();
			$remaining_time = 86400 * 30;
        	$redis -> set($jti, (String) $token, $remaining_time);
    	}
		return (String) $token;
    }

    /**
     * 指定方法无需验证即可方法
     *
     * @param
     * @return bool
     */
    protected final function checkAction()
    {
        $action = request()->action();
        //以下几项不需要验证
        $tmp = array('getuserinfoandgettoken', 'refreshtoken');
        if (in_array($action,$tmp)){
            return true;
        }
        echo json_encode(['code' => '10001', 'msg' => 'please sign in']);die;
    }

   	/**
     * 获取登录用户信息
     *
     * @param
     * @return array 用户信息
     */
   	public function getUserInfo()
   	{
   		return $this -> loginUserInfo;
   	}

}