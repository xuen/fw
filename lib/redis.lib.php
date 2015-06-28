<?php

class API_Redis_Factory
{
    private static $_instances=array();

    const DEV_MAIN = 'dev.main.redis';
    const DEV_BACK = 'dev.back.redis';

    /**
     * array('master'=>redis,'slave'=>redis, 'masterback'=> redis, 'slaveback'=>redis)
     * @return array
     * 最初始不链接
     * 注 defensive 缓存指向 redis 备份
     */
    public static function getDefaultRedisClient($role = 'slave')
    {}

    public static function fetchRedisClient($api_redis, $role = "slave"){
        $clients    =   self::getDefaultRedisClient($role);
        if (empty($clients[$role]))     return false;
        if ($role == 'slave')           $api_redis->setSlaveClient($clients['slave']);
        elseif ($role == 'master')      $api_redis->setMasterClient($clients['master']);
        elseif ($role == 'slaveback')   $api_redis->setSlaveBackClient($clients['slaveback']);
        elseif ($role == 'masterback')  $api_redis->setMasterBackClient($clients['masterback']);
        return true;
    }

    public static function newInstance($prefix) {
        $cache=new API_Redis();
        $cache->setTypePrefix($prefix);
        //$clients=self::getDefaultRedisClient();
        //$cache->setSlaveClient($clients['slave']);
        return $cache;
    }

    /**
     * @param $prefix
     * @return API_Redis
     */
    public static function getInstance($prefix){
        if (!isset(self::$_instances[$prefix])) {
            self::$_instances[$prefix] = self::newInstance($prefix);
        }
        return self::$_instances[$prefix];
    }

    /*
     * preview 情况下全部指向一个 并全部初始化
     */
    public static function getPreviewInstance()
    {}
}

class API_Redis extends CacheBase
{
    /**
     * @var Redis_Client
     * client 为false 时，即链接失败，不再作链接
     */
    protected  $masterClient;
    protected  $slaveClient;
    protected  $masterBackClient;
    protected  $slaveBackClient;

    public function __destruct()
    {
        //XXX 此处假定除factory不会有其他地方new API_Redis
        if($this->masterClient){
            $this->masterClient->close();
        }
        if($this->slaveClient){
            $this->slaveClient->close();
        }
        if ($this->masterBackClient) {
            $this->masterBackClient->close();
        }
        if ($this->slaveBackClient) {
            $this->slaveBackClient->close();
        }
    }

    public function getMasterClient($key = '')
    {
        if (strpos($key, "defensive0307_") !== false) {
            return $this->getMasterBackClient();
        }
        if ($this->masterClient)    return $this->masterClient;
        API_Redis_Factory::fetchRedisClient($this, "master");
        if ($this->masterClient)    return $this->masterClient;
        else return $this->getMasterBackClient();
    }

    public function setMasterClient($client)
    {
        $this->masterClient=$client;
    }

    public function getMasterBackClient()
    {
        if ($this->masterBackClient)    return $this->masterBackClient;
        API_Redis_Factory::fetchRedisClient($this, "masterback");
        if ($this->masterBackClient)    return $this->masterBackClient;
        else return false;
    }

    public function setMasterBackClient($client)
    {
        $this->masterBackClient=$client;
    }

    public function getSlaveClient($key = '')
    {
        if (strpos($key, "defensive0307_") !== false) {
            return $this->getSlaveBackClient();
        }
        if ($this->slaveClient)    return $this->slaveClient;
        API_Redis_Factory::fetchRedisClient($this, "slave");
        if ($this->slaveClient)    return $this->slaveClient;
        else return $this->getSlaveBackClient();
    }

    public function setSlaveClient($client)
    {
        $this->slaveClient=$client;
    }

    public function getSlaveBackClient()
    {
        if ($this->slaveBackClient)    return $this->slaveBackClient;
        API_Redis_Factory::fetchRedisClient($this, "slaveback");
        if ($this->slaveBackClient)    return $this->slaveBackClient;
        else return false;
    }

    public function setSlaveBackClient($client)
    {
        $this->slaveBackClient=$client;
    }

    public function get($key)
    {
        if (empty($key)) return false;
        if($this->canUseCache() && !$this->current()->isRefresh()){
            $redisSlaveClient   = $this->getSlaveClient($key);
            $ret = ($redisSlaveClient == false)?  false: $redisSlaveClient->get($this->genNewKey($key));
            if ($ret === false) {
                $redisMasterClient  = $this->getMasterClient($key);
                $ret = ($redisMasterClient == false)?  false: $redisMasterClient->get($this->genNewKey($key));
            }
            return igbinary_unserialize($ret);
        }else{
            return false;
        }
    }

    public function set($key,$val,$expiresTime=86400)
    {
        if (empty($key)) return false;
        $val = igbinary_serialize($val);
        if (($expiresTime <= 0) || ($expiresTime >  21600)) $expiresTime = 21600;
        if($this->canUseCache()){
            $redisMasterClient  = $this->getMasterClient($key);
            $ret = ($redisMasterClient == false)? false: $redisMasterClient->set($this->genNewKey($key), $val, $expiresTime);
            return $ret;
        }else{
            return false;
        }
    }

    public function delete($key){
        if (empty($key)) return false;
        $redisMasterClient  = $this->getMasterClient($key);
        $ret = ($redisMasterClient == false)? false: $redisMasterClient->delete($this->genNewKey($key));
        return $ret;
    }
}

