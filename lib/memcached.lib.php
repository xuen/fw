<?php
//需要将memcached 对外接口和redis 对外接口完全统一
//just a wrapper for front (read the conf);
//注defensive 缓存指向 redis 备份
class API_Memcached_Factory {
    private static $_instances = array();
    private static $_Pool = array();

    public static function newInstance($prefix) {
        $frontMcConf = Config_Server::getInstance()->getServerConfig('front.dev.mc');
        $mcObj       = self::Factory($frontMcConf['ips'], $prefix);
        $mcObj->setTypePrefix($prefix);
        self::$_instances[$prefix] = $mcObj;
        return $mcObj;
    }
    public static function getInstance($prefix){
        if (!isset(self::$_instances[$prefix])) {
            self::$_instances[$prefix] = self::newInstance($prefix);
        }
        return self::$_instances[$prefix];
    }

    /**
     *  $ips = '127.0.0.1:11211,127.0.0.2:11211'
     *  $ips = ['127.0.0.1:11211', '127.0.0.2:11211']
     */
    public static function Factory ($ips) {
        if(empty($ips)) {
            throw new Exception('API_Memcached::Factory ips empty');
        }
        else if(is_array($ips)) {
            $key = implode(',', $ips);
        }
        else if(is_string($ips)) {
            $key = trim($ips, ',');
            $ips = explode(',', $key);
        }
        else {
            throw new Exception('API_Memcached::Factory ips format error');
        }
        if(isset(self::$_Pool[$key])) return self::$_Pool[$key];
        self::$_Pool[$key] = new API_Memcached($ips);
        return self::$_Pool[$key];
    }
}

/** Memcached */
class API_Memcached extends CacheBase{
    public function __construct ($ips, $pkey = "sysmc") {
        $servers = [];
        foreach($ips as $ip) {
            $ip = explode(':', $ip);
            if(!isset($ip[1])) $ip[1] = 11211;
            $servers[] = [$ip[0], $ip[1]];
        }
        $this->_instance = new Memcached($pkey);
        $this->_instance->setOption(Memcached::OPT_POLL_TIMEOUT, 600000);
        if (!count($this->_instance->getServerList())) {
            $result = $this->_instance->addServers($servers);
            // remove_failed_servers 等价于 auto_eject_hosts
            // libmemcached 会对remove_failed_servers 做bool 转换，而原先 auto_eject_hosts 参数则没有
            $this->_instance->setOption(Memcached::OPT_REMOVE_FAILED_SERVERS, true);
            $this->_instance->setOption(Memcached::OPT_AUTO_EJECT_HOSTS, true);
            // 链接超时
            $this->_instance->setOption(Memcached::OPT_CONNECT_TIMEOUT, 1000);
            // 通过此接口存入，都会使用igbinary_serialize 压缩，是故不需要默认再次压缩
            // SERIALIZER_PHP | SERIALIZER_IGBINARY | SERIALIZER_JSON
            $this->_instance->setOption(Memcached::OPT_COMPRESSION, false);
            // $this->_instance->setOption(Memcached::OPT_SERIALIZER, Memcached::SERIALIZER_PHP);
            // HASH_DEFAULT | DISTRIBUTION_CONSISTENT | HASH_MD5 | HASH_CRC
            $this->_instance->setOption(Memcached::OPT_HASH, Memcached::HASH_DEFAULT);
            $this->_instance->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);
            //Memcached::OPT_LIBKETAMA_COMPATIBLE
            $this->_instance->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
            //API_Logger::Write("memcachedreopen", "add server:". $pkey, "memcached");

            // retry_timeout 设置为30s, 当连接失败时, 会设置next_retry 影响到next_distribution_rebuild, 直接影响到memcached_st.ketama. 分布
            $this->_instance->setOption(Memcached::OPT_RETRY_TIMEOUT, 40);
            // 36 即 2.2.0 当中的OPT_DEAD_TIMEOUT, 2.1.0 无此参数 直接传入libmemcached
            // 即libmemcached 中MEMCACHED_BEHAVIOR_DEAD_TIMEOUT, struct memcached_behavior_t 中第37个(lib 1.6/1.8 的顺序)
            // 当链接失败数超过default失败数(5)时，会设置server 状态为dead, 此时假如设置(默认auto_eject情况下) dead_timeout 则会设置next_retry
            // 再过此时间点之后，会再次尝试链接 server, 注: 此时仅会给予一次链接机会，假如再次失败，则再次将server 置为dead, next_retry 将重置
            // 当不 auto_eject 时，则设置retry_timeout 将再过期后重试链接，当auto_eject 时，需要设置dead_timeout 才会重新去链接
            $this->_instance->setOption(36, 30);
        }
    }

    private $_instance = NULL;

    public function __destruct() {
        if ($this->_instance) {
            $this->_instance->quit();
        }
    }

    public function get ($key) {
        if (empty($key)) return false;
        //defensive 缓存指向 redis 备份
        if (strpos($key, "defensive0307_") !== false) {
            $cache = API_Redis_Factory::getInstance(API_Redis::PREFIX_BLOCK);
            return $cache->get($key);
        }
        API_Benchmark::Counter('mc_get');
        if($this->canUseCache() && !$this->current()->isRefresh()){
            $ret = $this->_instance->get($this->genNewKey($key));
            return igbinary_unserialize($ret);
        } else {
            return false;
        }
    }

    //public function getMulti($keys) {
    //    API_Benchmark::Counter('mc_getmulti');
    //    if($this->canUseCache() && !$this->current()->isRefresh()){
    //        return $this->_instance->getMulti($keys);
    //    } else {
    //        return false;
    //    }
    //}

    public function set($key, $val, $expiresTime = 86400) {
        if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'spider')) return false;
        // 应对百度蜘蛛 并不缓存
        if (empty($key)) return false;
        //defensive 缓存指向 redis 备份
        if (strpos($key, "defensive0307_") !== false) {
            $cache = API_Redis_Factory::getInstance(API_Redis::PREFIX_BLOCK);
            return $cache->set($key, $val, $expiresTime);
        }
        $val = igbinary_serialize($val);
        if (strlen($val) > 1000000) {
            //这里保留原key信息，即使含有空格/控制字符等非支持字符也保留，为查询更方便
            API_Logger::Write("memcachedseterror", "msgTooLong:". $this->getPrefix(). $key, "memcached");
            return false;
        }
        if (($expiresTime <= 0) || ($expiresTime >  21600)) $expiresTime = 21600;
        if (!($this->canUseCache())){
            return false;
        }
        API_Benchmark::Counter('mc_set');
        $setReturn = $this->_instance->set($this->genNewKey($key),$val,$expiresTime);
        if ($setReturn == false) {
            $errMessage = $this->_instance->getResultMessage();
            $errCode    = $this->_instance->getResultCode();
            //这里保留原key信息，即使含有空格/控制字符等非支持字符也保留，为查询更方便
            API_Logger::Write("memcachedseterror", "errMessage:". $errMessage. '|errCode:'. $errCode. '|errKey:'. $this->getPrefix(). $key, "memcached");
        }
        return $setReturn;
    }
    /*
     *  暂时不提供setMulti功能
     *  return $this->_instance->setMulti($key,$val);
     */

    public function delete ($key) {
        API_Benchmark::Counter('mc_del');
        return $this->_instance->delete($this->genNewKey($key));
    }
    //public function deleteMulti($keys) {
    //    API_Benchmark::Counter('mc_delMulti');
    //    return $this->_instance->deleteMulti($keys);
    //}

    public function exists ($key) {
        // not recommended
        $this->_instance->get($this->genNewKey($key));
        if ( $this->_instance->getResultCode() == Memcached::RES_NOTSTORED ) {
            return FALSE ;
        }
        else {
            return TRUE ;
        }
    }
    public function getResult () {
        return $this->_instance->getResultMessage();
    }


    public function setEach ($key, $val, $expire = 86400) {
        return FALSE;
    }

    public function getEach ($key) {
        return FALSE;
    }
}
