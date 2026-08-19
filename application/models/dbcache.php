<?php

class dbcache
{
    protected $call;

    protected $maxAge = 0;

    protected $cacheName = null;

    public function __construct($front)
    {
        $this->call = $front;
    }

    public function setAge($seconds)
    {
        $this->maxAge = (int) $seconds;

        return $this;
    }

    public function setName($name)
    {
        $this->cacheName = (string) $name;

        return $this;
    }

    public function setQuery($query, $decode_field = [] )
    {
        if (!empty($this->cacheName)) {
            $cacheItem = $this->call->cache->read($this->cacheName, $this->maxAge);
            if (!empty($cacheItem)) return $cacheItem;
            // die("masuk");
            $execute = $this->call->db->query($query);

            if (!empty($execute->_numOfRows)) {
                $cacheItem = [];
                foreach ($execute as $value) {
                    foreach ($decode_field as $ki => $vi) {
                      $value[$vi] = json_decode($value[$vi],TRUE);
                    }
                    $cacheItem[] = $value;
                }

                return $this->call->cache->write($this->cacheName, $cacheItem);
            }
        }

        return;
    }
}
