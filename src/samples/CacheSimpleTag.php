<?php
/**
 * User: dredger
 * Date: 10.05.2017
 * Time: 17:46
 */

namespace TaggableRedisCache\Samples;

use TaggableRedisCache\CacheAbstract;

class CacheSimpleTag extends CacheAbstract
{
    /**
     * @var string
     */
    protected $key = 'cache-key-simple-tag';

    /**
     * Cache CacheSimpleTag  constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_setCacheSettings(array(
                'isEnabled' => false,
                'prefix' => 'simpleTagCache',
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
        return $this->_getByKey($this->_getKeySimple($key));
    }

    /**
     * @param array  $tags
     * @return array
     */
    public function getDataByTags($tags)
    {
        $r = array();
        foreach($tags as $tag ){
            $r[$tag] = $this->_getByTag($tag);
        }

        return $r;
    }

    /**
     * @param string $key
     * @param mixed  $data
     * @param array  $tags
     * @return bool
     */
    public function setData($key, $data, $tags)
    {
        return $this->_setData($this->_getKeySimple($key), $data, $this->cacheTime, $tags);
    }


    /**
     * @param array  $tags
     *
     * @return bool
     */
    public function removeDataByTags($tags)
    {
        return  $this->_delByTagsList($tags);

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