<?php
namespace TaggableRedisCache\Adapter;
/**
 * User: a.dulev
 * Date: 30.09.2015
 */




class RedisAdapter
{
    /**
     * @var \Redis
     */
    private $cacheAdapter = null;

    /**
     * @param string $address
     * @param string $port
     * @param int $dbIndex
     * @param string appPrefix
     */
    public function init($address="127.0.0.1", $port="6379", $dbIndex=1, $appPrefix="appPrefix"){

        $redis = new \Redis();
        $redis->connect($address, $port);
        $redis->select($dbIndex);//separated DB for data storage
        $redis->setOption(\Redis::OPT_PREFIX, $appPrefix);
        $redis->setOption(\Redis::OPT_SERIALIZER, 1);
        $redis->setOption(\Redis::SERIALIZER_PHP, 1);

        $this->cacheAdapter = $redis;
    }

    /**
     * @return \Redis
     */
    public function getAdapter(){
        if(!$this->cacheAdapter){
            $this->init();
        }
        return $this->cacheAdapter;
    }

    /**
     * @param int $dbIndex
     * @return bool
     */
    public function selectDb($dbIndex){
        return $this->getAdapter()->select($dbIndex);
    }

    /**
     * @param int $dbIndex - default is 0
     * @return bool
     */
    public function dbSize($dbIndex = 0 ){
        $this->selectDb($dbIndex);
        return $this->getAdapter()->dbSize();
    }



    /**
     * Sets an expiration date (a timeout) on an item. pexpire requires a TTL in milliseconds.
     *
     * @param int $key
     * @param $ttl
     */
    public function setTimeout($key, $ttl){
        return $this->getAdapter()->setTimeout($key,$ttl);
    }


    /**
     * Prefixes tag ID
     *
     * @param string $id tag key
     * @return string prefixed tag
     */
    protected function _keyFromTag($id)
    {
        return  'tag__' . $id;
    }

    /**
     * Prefixes item tag ID
     *
     * @param string $id item tag key
     * @return string prefixed item tag
     */
    protected function _keyFromItemTags($id)
    {
        return 'item_tags__' . $id;
    }

    /**
     * Prefixes key ID
     *
     * @param string $id cache key
     * @return string prefixed id
     */
    protected function _keyFromId($id)
    {
        return 'item__' . $id;
    }


    /**
     * @param int $specificLifetime
     * @return int
     */
    public function getLifetime($specificLifetime=0){
        return $specificLifetime;
    }

    /**
     * @param string $id
     * @param mixed $data
     * @param int $specificLifetime | null - means forever, 0 - means newer
     * @param array $tags array('tag1','tag2')
     * @return bool
     */
    public function set($id, $data, $specificLifetime = 0 , $tags = array() ){

        if (!$tags || !count($tags)) {
            $tags = array('');
        }

        if (is_string($tags)) {
            $tags = array($tags);
        }

        $lifetime = $this->getLifetime($specificLifetime);
        if (!count($tags)){
            $this->remove($this->_keyFromItemTags($id));
            if ($lifetime === null) {
                $return = $this->set($this->_keyFromId($id), $data);
            } else {
                $return = $this->getAdapter()->setex($this->_keyFromId($id), $lifetime, $data);
            }
            $this->getAdapter()->sAdd($this->_keyFromItemTags($id), '');
            if ($lifetime !== null) {
                $this->setTimeout($this->_keyFromItemTags($id), $lifetime);
            }else {
                $this->getAdapter()->persist($this->_keyFromItemTags($id));
            }
            return $return;
        }

        $tagsTTL = array();
        foreach ($tags as $tag) {
            if ($tag) {
                if (!$this->getAdapter()->exists($this->_keyFromTag($tag)))
                    $tagsTTL[$tag] = false;
                else
                    $tagsTTL[$tag] = $this->getAdapter()->ttl($this->_keyFromTag($tag));
            }
        }

        $redis = $this->getAdapter()->multi();
        $return = array();
        if (!$redis)
            $return[] = $this->getAdapter()->delete($this->_keyFromItemTags($id));
        else
            $redis = $redis->delete($this->_keyFromItemTags($id));
        if ($lifetime === null) {
            if (!$redis) {
                $return[] = $this->getAdapter()->set($this->_keyFromId($id), $data);
            }else {
                $redis = $redis->set($this->_keyFromId($id), $data);
            }
        } else {
            if (!$redis) {
                $return[] = $this->getAdapter()->setex($this->_keyFromId($id), $lifetime, $data);
            }else {
                $redis = $redis->setex($this->_keyFromId($id), $lifetime, $data);
            }
        }
        $itemTags = array($this->_keyFromItemTags($id));
        foreach ($tags as $tag) {
            $itemTags[] = $tag;
            if ($tag) {
                if (!$redis)
                    $return[] = $this->getAdapter()->sAdd($this->_keyFromTag($tag), $id);
                else
                    $redis = $redis->sAdd($this->_keyFromTag($tag), $id);
            }
        }
        if (count($itemTags) > 1) {
            if (!$redis)
                $return[] = call_user_func_array(array($this->getAdapter(), 'sAdd'), $itemTags);
            else
                $redis = call_user_func_array(array($redis, 'sAdd'), $itemTags);
        }
        if ($lifetime !== null) {
            if (!$redis)
                $return[] = $this->getAdapter()->setTimeout($this->_keyFromItemTags($id), $lifetime);
            else
                $redis = $redis->setTimeout($this->_keyFromItemTags($id), $lifetime);
        } else {
            if (!$redis) {
                $return[] = $this->getAdapter()->persist($this->_keyFromItemTags($id));
            }else {
                $redis = $redis->persist($this->_keyFromItemTags($id));
            }
        }
        if ($redis){
            $return[] = $redis->exec();
        }

        if (!count($return)){
            return false;
        }

        foreach ($tags as $tag) {
            if ($tag) {
                $ttl = $tagsTTL[$tag];
                if ($lifetime === null && $ttl !== false && $ttl != -1) {
                    $this->getAdapter()->persist($this->_keyFromTag($tag));
                } else if ($lifetime !== null && ($ttl === false || ($ttl < $lifetime && $ttl != -1))) {
                    $this->getAdapter()->setTimeout($this->_keyFromTag($tag), $lifetime);
                }
            }
        }
        foreach ($return as $value) {
            if ($value === false){
                return false;
            }
        }
        return true;
    }


    /**
     * @param  string  $key
     * @return  mixed
     */
    public function get($key){
        return $this->getAdapter()->get($this->_keyFromId($key));
    }

    /**
     * Sets a value and returns the previous entry at that key
     * do not hav expiration time
     *
     * @param string  $key
     * @param mixed  $value
     * @return string   A string, the previous value located at this key.
     */
    public function getSet($key, $value){
        return $this->getAdapter()->getSet($this->_keyFromId($key), $value);
    }

    /**
     * custom fix for assoc array
     *
     * @param  array $keys
     * @param  bool $keysInResult - result is associative array this parm is ignored
     * @return  array
     */
    public function getMultiple($keys, $keysInResult = false){

        if (!$keys || !count($keys)) {
            $keys = array();
        }
        if (is_string($keys)) {
            $keys = array($keys);
        }

        $keysArray = array();

        foreach ($keys as $numericIndex){
            $keysArray[] = $this->_keyFromId($numericIndex);
        }

        /**
         * result array always contains the count of items that equals to requested keys count
         */
        $resFromCache =  $this->getAdapter()->getMultiple($keysArray); // returns numeric array from 0 till n

        if(!is_array($resFromCache)){
            return array();
        }

        $res = array();
        foreach ($resFromCache as $numericIndex=>$item){
            if(empty($item)){// do not return empty results
                continue;
            }
            $res[$this->_getKeyByIndex($keys, $numericIndex, $keysInResult)] = $item;
        }
        return $res;
    }


    private function _getKeyByIndex($keysArray, $numericArrayIndex, $keysInResult){
        if (!$keysInResult) {
            return $numericArrayIndex;
        }

        return isset($keysArray[$numericArrayIndex]) ? $keysArray[$numericArrayIndex] : $numericArrayIndex;
    }


    /**
     * @param  string  $key
     * @return  bool
     */
    public function has($key){
        return $this->getAdapter()->exists($this->_keyFromId($key));
    }

    /**
     * @ToDo rename to delete
     *
     * Remove a cache record
     *
     * @param  string $id cache id
     * @param  bool  $hardReset false - clear only tags
     * @return boolean true if no problem
     */
    public function remove($id, $hardReset = true)
    {
        if (!$this->getAdapter()) {
            return false;
        }

        if (!$id) {
            return false;
        }
        if (is_string($id)) {
            $id = array($id);
        }
        if (!count($id)) {
            return false;
        }
        $deleteIds = array();
        foreach ($id as $i) {
            $deleteIds[] = $this->_keyFromItemTags($i);
            if ($hardReset){
                $deleteIds[] = $this->_keyFromId($i);
            }
        }

        $this->getAdapter()->delete($deleteIds);
        return true;
    }


    /**
     *  Returns the time to live left for a given key in seconds (ttl)
     *
     * @param string $key
     * @return int
     */
    public function getTtl($key){
        return $this->getAdapter()->ttl($this->_keyFromId($key));
    }


    /**
     * not work
     *
     * removes key from tag
     * does not removes cache by key
     *
     * @param string $key
     * @param string $tag
     * @return int
     */
    protected  function removeKeyFromTag($key, $tag){
        return $this->getAdapter()->sRemove( $this->_keyFromId($key), $this->_keyFromTag($tag));
    }

    /**
     * Remove a cache tag record, remove all data associated with keys from tag
     *
     * @param  string|array  $tag cache tag
     * @return boolean true if no problem
     */
    public function removeTag($tag){

        if (!$tag){
            return false;
        }
        if (is_string($tag)) {
            $tag = array($tag);
        }
        if (!count($tag)) {
            return false;
        }
        $deleteTags = array();

        foreach ($tag as $t) {
            $deleteTags[] = $this->_keyFromTag($t);
            $deleteKeys = $this->getKeysByTag($t);
            $this->remove($deleteKeys);
        }
        if ($deleteTags && count($deleteTags)) {
            $this->getAdapter()->delete($deleteTags);
        }
        return true;
    }

    /**
     * @deprecated
     *
     * @param $tag
     * @return mixed
     */
    public function deleteTagOld($tag){
        $this->getAdapter()->watch($tag);
        $keys = $this->getAdapter()->sMembers($tag);

        return $this->getAdapter()->multi()
            ->delete($tag)
            ->delete($keys)
            ->exec();
    }

    /**
     * An array of elements, the contents of the tag.
     * @param string $tag
     * @return array
     */
    public function getKeysByTag($tag){
        return $this->getAdapter()->sMembers($this->_keyFromTag($tag));
    }

    /**
     * An array of elements, the contents of the tag.
     * @param string $tag
     *
     * @return mixed || false if not found
     */
    public function getOneByTag($tag){
        $keys = $this->getAdapter()->sMembers($this->_keyFromTag($tag));
        if(isset($keys[0])){
            return $this->get($keys[0]);
        }
        return false;
    }

    /**
     * An array of elements, the contents of the tag.
     * @param string $tag
     * @param bool $keysInResult - keys in result array
     *
     * @return mixed || false if not found
     */
    public function getMultipleByTag($tag, $keysInResult = false){
        $keys = $this->getAdapter()->sMembers($this->_keyFromTag($tag));
        return $this->getMultiple($keys, $keysInResult );
    }

    /**
     *
     * An array of elements, the contents of the tag.
     * @param string $tagsArray
     * @param bool $keysInResult - keys in result array
     *
     * @return mixed || false if not found
     */
    public function getMultipleByTagsList($tagsArray, $keysInResult = false){
        if(!is_array($tagsArray)){
            $tagsArray = array($tagsArray);
        }

        $keysByTag = array();
        foreach ($tagsArray as $tag){
            $keysByTag = array_merge($keysByTag, $this->getAdapter()->sMembers($this->_keyFromTag($tag)));
        }
        return $this->getMultiple($keysByTag, $keysInResult);
    }


    /**
     * doesn't work correct
     * An array of elements, the contents of the tag.
     *
     * @param string $key
     * @return array
     */
    protected  function getTagsByKey($key){
        return $this->getAdapter()->sMembers($this->_keyFromId($key));
    }

    /**
     * @deprecated
     *
     * does not work
     * Returns the all keys
     *
     * @return array
     */
    protected  function getAllKeys(){
        return $this->getAdapter()->keys('*');
    }

    /**
     * Returns the keys that match a certain pattern.
     *
     * Example
        $allKeys = $redis->keys('*');   // all keys will match this.
        $keyWithUserPrefix = $redis->keys('user*');
     *
     * @return array
     */
    public function getKeys($pattern){
        return $this->getAdapter()->keys($pattern);
    }

    /**
     * @return bool SCRIPT FLUSH should always return TRUE
     */
    public function flushDb(){
        return $this->getAdapter()->flushDB();
    }

    /**
     *  BE CAREFULLY - Removes all keys from all databases
     * @return bool
     */
    public function flushRedisStorage(){
        return $this->getAdapter()->flushAll();
    }

    /**
     * Get information and statistics about the server
     *
     * @return string
     */
    public function info(){
        return $this->getAdapter()->info();
    }
}