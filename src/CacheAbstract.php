<?php

namespace TaggableRedisCache;


/**
 * Created by PhpStorm.
 * User: a.dulev
 * Date: 28.09.2015
 * Time: 19:05
 */
abstract class CacheAbstract
{
    /**
     * @var \TaggableRedisCache\CacheConfig
     */
    protected $config = null;

    /**
     * @var \TaggableRedisCache\Adapter\RedisAdapter
     */
    protected $adapter = null;
    /**
     * @var array
     */
    protected $cacheSettings = array();

    /**
     * @var int
     */
    protected $cacheTime = 3600;

    /**
     * @var string
     */
    protected $prefix = '';

    /**
     * @var string
     */
    protected $storageNameSpace = 'sns';
    /**
     * @var string
     */
    protected $key = 'default';

    /**
     * @var bool
     */
    private $isEnabled = true;

    /**
     * CacheAbstract constructor.
     */
    public function __construct()
    {
        $this->config =  \TaggableRedisCache\CacheConfig::getInstance();
        $this->adapter = $this->_getAdapter();
    }

    /**
     * @param array $config
     */
    protected function _setCacheSettings($config)
    {
        $this->cacheSettings = $config;
        $this->cacheTime = $this->cacheSettings['cacheTime'];
        $this->prefix    = $this->cacheSettings['prefix'];
        $this->isEnabled = $this->_getEnabledByConfig();

        $this->storageNameSpace = $this->config->getStorageNameSpace() ;

        $this->key  = $this->prefix . '_'. $this->key;
    }

    protected  function _getEnabledByConfig(){
        if(!$this->config->getIsEnabledGlobal()){
            return false;
        }

        if(!$this->cacheSettings['isEnabled']){
            return false;
        }

        return true;
    }

    /**
     * use values less then 15
     *
     * Switches to a given database
     * @param $dbIndex
     * @return  bool    TRUE in case of success, FALSE in case of failure.
     */
    protected function _setDbIndex($dbIndex){
        return $this->adapter->selectDb($dbIndex);
    }

    /**
     * @return bool
     */
    public function isEnabled(){
       return $this->isEnabled;
    }

    /**
     * @return bool
     */
    public function disableCache(){
        return $this->isEnabled = false;
    }

    /**
     * @return bool
     */
    public function enableCache(){
        return $this->isEnabled = true;
    }


    protected function _initCache(){
        $this->adapter->getAdapter()->setOption(\Redis::OPT_PREFIX, 'appData:' . $this->storageNameSpace);
    }



    /**
     * @return Adapter\RedisAdapter
     */
    protected function _getAdapter(){
        static $adapter = null;
        if(isset($adapter)){
            return $adapter;
        }
        $adapter = new \TaggableRedisCache\Adapter\RedisAdapter();
        return $adapter;
    }

    /**
     *
     * returns data in array like key=>value
     * @param $tag
     * @return array
     */
    protected  function _getKeysByTag($tag){
        return $this->_getAdapter()->getKeysByTag($tag);
    }


    /**
     * @param string $id
     * @param mixed  $data
     * @param int $specificLifetime
     * @param array $tags
     *
     * @return bool
     */
    protected  function _setData($id, $data, $specificLifetime = 0 , $tags = array() ){
        if(!$this->isEnabled()){
            return  false;
        }
        return $this->adapter->set($id, $data, $specificLifetime, $tags);
    }

    /**
     *
     * returns data without keys
     * @param string $tag
     * @param bool $keysInResult
     *
     * @return array
     */
    protected function _getByTag($tag, $keysInResult = false){
        if(!$this->isEnabled()){
            return array();
        }

        $keys = $this->_getKeysByTag($tag);
        return $this->_getAdapter()->getMultiple($keys, $keysInResult);
    }

    /**
     *
     * returns data without keys
     * @param string $tag
     *
     * @return array
     */
    protected function _getOneByTag($tag){
        if(!$this->isEnabled()){
            return array();
        }

        return $this->_getAdapter()->getOneByTag($tag);

    }

    /**
     *
     * @param array  $tagsArray
     * @param bool $keysInResult - returns data without keys
     *
     * @return array
     */
    protected function _getByMultipleTags($tagsArray, $keysInResult = false){
        if(!$this->isEnabled()){
            return array();
        }
        return $this->_getAdapter()->getMultipleByTagsList($tagsArray);

    }

    /**
     *
     * returns data without keys
     * @param string $key
     * @return array
     */
    protected function _getByKey($key){
        if(!$this->isEnabled()){
            return array();
        }

        return $this->_getAdapter()->get($key);
    }

    /**
     *
     * returns data without keys
     *
     * @param array $keysArray
     * @return array
     */
    protected function _getByMultipleKeys($keysArray){
        if(!$this->isEnabled()){
            return array();
        }
        return $this->_getAdapter()->getMultiple($keysArray);
    }


    /**
     * @param string $key
     * @return bool
     */
    protected function _delByKey($key){
        if(!$this->isEnabled()){
            return false;
        }
        return $this->_getAdapter()->remove($key);
    }

    /**
     * @param array $keysList
     * @return bool
     */
    protected function _delByKeysList($keysList){
        foreach($keysList as $k)  {
            $this->_delByKey($k);
        }
        return true;
    }

    /**
     * * Remove a cache tag record, remove all data associated with keys from tag
     *
     * @param string $tag
     * @return bool
     */
    protected function _delByTag($tag){
        if(!$this->isEnabled()){
            return false;
        }

        return $this->_getAdapter()->removeTag($tag);

    }

    /**
     *  Remove a cache tags list records, remove all data associated with keys from tags
     *
     * @param array $tagsList
     * @return bool
     */
    protected function _delByTagsList($tagsList){
        if(!$this->isEnabled()){
            return false;
        }
        return $this->_getAdapter()->removeTag($tagsList);
    }

    /**
     * @deprecated
     *
     * returns data without keys by default
     * @param array $keys
     * @param bool $keysInResult
     * @return array
     */
    protected function _getDataByKeysList($keys, $keysInResult = false){
        if(!$this->isEnabled()){
            return array();
        }

        $keys = is_array($keys) ? $keys : array();

        $r = array();
        foreach($keys as $k){
            if ($keysInResult){
                $r[$k] = $this->_getByKey($k);
            }else{
                $r[]  = $this->_getByKey($k);
            }
        }
        return $r;
    }

    /**
     * @return timestamp
     */
    public  function getMicrotime(){
        round(microtime(true) * 1000);
    }


    /**
     * clear all cache
     */
    protected function _flushDb(){
        $this->_getAdapter()->flushDb();
    }
}