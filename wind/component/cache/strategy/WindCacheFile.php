<?php

Wind::import('WIND:component.cache.AbstractWindCache');
Wind::import('WIND:component.utility.WindFile');

/**
 * 文件缓存类
 * the last known user to change this file in the repository  <$LastChangedBy$>
 * @author Su Qian <weihu@alibaba-inc.com>
 * @version $Id$ 
 * @package 
 */
class WindCacheFile extends AbstractWindCache {

	/**
	 * 缓存目录
	 * @var string 
	 */
	protected $cacheDir;

	/**
	 * 缓存后缀
	 * @var string 
	 */
	protected $cacheFileSuffix = '';

	/**
	 * 缓存多级目录。最好不要超3层目录
	 * @var int 
	 */
	protected $cacheDirectoryLevel = '';

	const CACHEDIR = 'cache-dir';

	const SUFFIX = 'cache-suffix';

	const LEVEL = 'cache-level';

	/* (non-PHPdoc)
	 * @see AbstractWindCache::set()
	 */
	public function set($key, $value, $expire = null, IWindCacheDependency $denpendency = null) {
		$expire = null === $expire ? $this->getExpire() : $expire;
		return $this->writeData($this->getRealCacheKey($key), $this->storeData($value, $expire, $denpendency), $expire);
	}

	/* (non-PHPdoc)
	 * @see AbstractWindCache::get()
	 */
	public function get($key) {
		return $this->getDataFromMeta($key, $this->readData($this->getRealCacheKey($key)));
	}

	/* (non-PHPdoc)
	 * @see AbstractWindCache::delete()
	 */
	public function delete($key) {
		$cacheFile = $this->getRealCacheKey($key);
		if (is_file($cacheFile)) {
			return WindFile::delFile($cacheFile);
		}
		return false;
	}

	/* (non-PHPdoc)
	 * @see AbstractWindCache::clear()
	 */
	public function clear($isExpired = false) {
		return WindFile::clearDir($this->getCacheDir(), $isExpired);
	}
	/**
	 * 获取缓存文件名。
	 * @param string $key
	 * @return string
	 */
	protected function getRealCacheKey($key, $getDir = false) {
		$filename = $this->buildSecurityKey($key) . '.' . ltrim($this->getCacheFileSuffix(), '.');
		$_tmp = $this->getCacheDir();
		if (0 < $this->getCacheDirectoryLevel()) {
			for ($i = $this->getCacheDirectoryLevel(); $i > 0; --$i) {
				if (false === isset($key[$i])) continue;
				$_tmp .= $key[$i] . DIRECTORY_SEPARATOR;
			}
			if (!is_dir($_tmp)) mkdir($_tmp, 0777, true);
		}
		if (!is_dir($_tmp)) mkdir($_tmp, 0777, true);
		return $getDir ? $_tmp : $_tmp . $filename;
	}

	/**
	 * 写入文件缓存
	 * @param string $file 缓存文件名
	 * @param string $data 缓存数据
	 * @param int $mtime 缓存文件的修改时间，即缓存的过期时间
	 * @return boolean
	 */
	protected function writeData($file, $data, $mtime = 0) {
		if (WindFile::write($file, $data) == strlen($data)) {
			$mtime += $mtime ? time() : 0;
			chmod($file, 0777);
			return touch($file, $mtime);
		}
		return false;
	}

	/**
	 * 从文件中读取缓存内容
	 * @param string $file 缓存文件名
	 * @return null|string
	 */
	protected function readData($file) {
		if (false === is_file($file)) return null;
		$mtime = filemtime($file);
		if (0 === $mtime || ($mtime && $mtime > time()))
			return unserialize(WindFile::read($file));
		elseif (0 < $mtime)
			WindFile::delFile($file);
		return null;
	}

	/* (non-PHPdoc)
	 * @see AbstractWindCache::setConfig()
	 */
	public function setConfig($config) {
		parent::setConfig($config);
		$this->setCacheDir($this->getConfig(self::CACHEDIR, WIND_CONFIG_VALUE));
	}

	/**
	 * 设置缓存目录
	 * @param string $dir
	 */
	private function setCacheDir($dir) {
		$this->cacheDir = Wind::getRealPath($dir,true) . DIRECTORY_SEPARATOR;
	}

	/**
	 * @return the $cacheDir
	 */
	public function getCacheDir() {
		if (!is_dir($this->cacheDir)) mkdir($this->cacheDir, 0777, true);
		return $this->cacheDir;
	}

	/**
	 * @return the $cacheFileSuffix
	 */
	protected function getCacheFileSuffix() {
		if ('' === $this->cacheFileSuffix) {
			$this->cacheFileSuffix = $this->getConfig(self::SUFFIX, WIND_CONFIG_VALUE, '', 'bin');
		}
		return $this->cacheFileSuffix;
	}

	/**
	 * 返回cache目录级别，默认为0，不分级，最大分级为5
	 * @return the $cacheDirectoryLevel
	 */
	protected function getCacheDirectoryLevel() {
		if ('' === $this->cacheDirectoryLevel) {
			$this->cacheDirectoryLevel = $this->getConfig(self::LEVEL, WIND_CONFIG_VALUE, '', 0);
		}
		return $this->cacheDirectoryLevel > 5 ? 5 : $this->cacheDirectoryLevel;
	}

	/**
	 * @param string $cacheFileSuffix
	 */
	public function setCacheFileSuffix($cacheFileSuffix) {
		$this->cacheFileSuffix = $cacheFileSuffix;
	}

	/**
	 * @param int $cacheDirectoryLevel
	 */
	public function setCacheDirectoryLevel($cacheDirectoryLevel) {
		$this->cacheDirectoryLevel = $cacheDirectoryLevel;
	}

	/**
	 * 垃圾回收，清理过期缓存
	 */
	public function __destruct() {
		$this->clear(true);
	}

}