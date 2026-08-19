<?php

class Cache
{
    public $cacheDirectory = TEMPFOLDER . 'cache/';

    public function __construct()
    {
        $this->createDirectory($this->cacheDirectory);
        $this->createDefaultFiles($this->cacheDirectory);
    }

    private function createDirectory($directory)
    {
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    private function createDefaultFiles($directory)
    {
        if (!file_exists($directory . DIRECTORY_SEPARATOR . '.htaccess')) {
            $file = fopen($directory . DIRECTORY_SEPARATOR . '.htaccess', 'a+');
            fwrite($file, 'deny from all');
            fclose($file);
        }

        if (!file_exists($directory . DIRECTORY_SEPARATOR . 'index.html')) {
            $file = fopen($directory . DIRECTORY_SEPARATOR . 'index.html', 'a+');
            fwrite($file, '<h1>403 Forbidden</h1>');
            fclose($file);
        }
    }

    public function getCachePath($cacheName)
    {
        $cacheName = str_replace(' ', '-', trim(strtolower($cacheName)));

        $cacheName = preg_replace('/[^A-Za-z0-9\-]/', '', $cacheName);

        $cacheName = preg_replace('/-+/', '-', $cacheName);

        return $this->cacheDirectory . DIRECTORY_SEPARATOR . hash('sha1', $cacheName) . '.kick.cache';
    }

    public function delete($cacheName)
    {
        unlink($this->getCachePath($cacheName));
    }

    public function write($cacheName, $content)
    {
        $cacheFile = $this->getCachePath($cacheName);

        file_put_contents($cacheFile, serialize($content));

        return $content;
    }

    public function read($cacheName, $maxAge = 0, $deleteExpired = true)
    {
        $cacheFile = $this->getCachePath($cacheName);

        if ($this->check($cacheName, $maxAge, $deleteExpired)) {
            return unserialize(file_get_contents($cacheFile));
        }

        return null;
    }

    public function check($cacheName, $maxAge = 0, $deleteExpired = true)
    {
        $cacheFile = $this->getCachePath($cacheName);

        if (file_exists($cacheFile)) {
            if ($maxAge == 0 || (time() - filemtime($cacheFile)) <= $maxAge) {
                return true;
            }

            if ($deleteExpired) {
                $this->delete($cacheName);
            }
        }

        return false;
    }

    public function clear($maxAge = 0)
    {
        foreach (array_diff(scandir($this->cacheDirectory), array('.', '..', '.htaccess', 'index.html')) as $file) {
            $cacheFile = $this->cacheDirectory . DIRECTORY_SEPARATOR . $file;

            if (is_file($cacheFile) && ($maxAge == 0 || (time() - filemtime($cacheFile)) >= $maxAge)) {
                unlink($cacheFile);
            }
        }
    }
}
