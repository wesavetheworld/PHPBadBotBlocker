<?php

namespace BadBotBlocker;

/**
 * Count the requests from the current IP and saves this info int the memcache.
 * If the requests hits the limie the user will be redirect to the captcha page
 * where a Googgle ReCaptcha is showed and after the uses says that he is a real
 * user the access is enables and the user is redirecter to the last page
 * visited.
 *
 * class BadBotBlocker
 *
 * @package  BadBotBlocker
 */
class Blocker
{
    public $error = false;
    private $memcacheHost = false;
    private $memcachePort = false;
    public $requestsLimit = 50;
    public $requestsPeriod = 1200; // In seconds where 60 * 20 minutes
    public $blockByAgent = false;
    public $debugContent = '';
    public $debug = false;
    public $showCaptcha = false;
    public $cacheKeyPrefix = 'BBB||';

    public function __construct($debug = false)
    {
        if ($this->checkConfig() === false) {
            $this->error = true;
            return false;
        }
        $this->memcacheHost = MEMCACHE_HOST;
        $this->memcachePort = MEMCACHE_PORT;
        $this->memcacheConnect();
    }

    /**
     * Check config variables
     *
     * @return boolean
     */
    private function checkConfig()
    {
        if (!constant('MEMCACHE_HOST') || !constant('MEMCACHE_PORT')) {
            $this->debugContent .= 'Missing config constants.' . PHP_EOL;
            return false;
        }
        return true;
    }

    /**
     * Check and count the access from current IP
     *
     * @return string
     */
    public function checkAccess()
    {
        if ($this->error == true) {
            return 'error';
        }
        $requestsCount = (int) $this->getData($this->cacheKeyPrefix . $_SERVER['REMOTE_ADDR']);
        if ($requestsCount >= $this->requestsLimit) {
            $this->showCaptcha = true;
            return 'captcha';
        }
        $requestsCount++;
        $this->setData($this->cacheKeyPrefix . $_SERVER['REMOTE_ADDR'], $requestsCount, $this->requestsPeriod);
        return 'ok|' . $requestsCount;
    }

    /**
     * Enables the access to current IP
     *
     * @return string
     */
    public function enableAccess()
    {
        if ($this->error == true) {
            return 'error';
        }
        $this->deleteData($this->cacheKeyPrefix . $_SERVER['REMOTE_ADDR']);
        return 'ok';
    }

    /**
     * Connect to Memcance and se the Mencache object
     *
     * @return boolean
     */
    private function memcacheConnect()
    {
        if (!class_exists('Memcache') || !function_exists('memcache_connect')) {
            $this->debugContent .= 'Memcache Library not loaded.' . PHP_EOL;
            $this->cacheObj = null;
            $this->memcacheEnabled = false;
            $this->error = true;
            return false;
        }
        $this->cacheObj = new \Memcache();
        $this->memcacheEnabled = true;
        if (!$this->cacheObj->pconnect($this->memcacheHost, $this->memcachePort)) {
            $this->cacheObj = null;
            $this->memcacheEnabled = false;
            $this->error = true;
            $this->debugContent .= 'Can\'t connect to memcache server.' . PHP_EOL;
        }
    }

    /**
     * Return data from Memcache
     *
     * @param string $sKey
     * @return type
     */
    private function getData($sKey)
    {
        $vData = $this->cacheObj->get(md5($sKey));
        if (strlen($vData) > 0) {
            $vData = unserialize($vData);
        }
        return false === $vData ? null : $vData;
    }

    /**
     * Set data in memcache
     *
     * @param string $sKey
     * @param string $vData
     * @param int $cache_time cache time in seconds
     * @return bool
     */
    private function setData($sKey, $vData, $cache_time = 600)
    {
        return $this->cacheObj->set(md5($sKey), serialize($vData), MEMCACHE_COMPRESSED, $cache_time);
    }

    /**
     * Delete data from a memcache key
     *
     * @param type $sKey
     * @return bool
     */
    private function deleteData($sKey)
    {
        return $this->cacheObj->delete(md5($sKey));
    }
}
