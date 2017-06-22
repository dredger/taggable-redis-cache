<?php
/**
 * User: dredger
 * Date: 10.05.2017
 * Time: 17:46
 */

namespace TaggableRedisCache\Samples;

use TaggableRedisCache\CacheAbstract;

class CacheSimple extends CacheAbstract
{
    /**
     * @var string
     */
    protected $key = 'cache-key-simple';

    /**
     * Cache CacheSimpleTag  constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_setCacheSettings(array(
                'isEnabled' => false,
                'prefix' => 'simpleCache',
                'cacheTime' => 86400, //seconds 24h
            )
        );
        $this->_initCache();
        $this->enableCache(); // ignore global cache settings
    }

    /**
     * @param string $key
     * @return array
     */
    public function getData($key)
    {
        return $this->_getByKey($this->_getKeySimple());
    }

    /**
     * @param string $key
     * @param mixed  $data
     * @return bool
     */
    public function setData($key, $data)
    {
        return $this->_setData($this->_getKeySimple($key), $data, $this->cacheTime);
    }


    /**
     * @param string $key
     *
     * @return bool
     */
    public function removeData($key)
    {
        return $this->_delByKey($this->_getKeySimple($key));
    }

    /**
     * @param array $keysList
     *
     * @return bool
     */
    public function removeList($keysList)
    {

        $keysNs = array();

        foreach ($keysList as $key){
            $keysNs[] = $this->_getKeySimple($key);
        }
        return $this->_delByKeysList($keysNs);
    }


    /**
     * @return string
     */
    private function _getKeySimple($key)
    {
        return $this->key . $key;
    }
}