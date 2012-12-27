<?php

/**
 * Memcached storage engine for CakePHP
 *
 * PHP versions 4 and 5
 *
 * @author Ezra Pool <ezra@tsdme.nl>
 * @copyright BSD
 * @version 0.2.1
 */

/**
 * Memcached storage engine for cache.
 */
class MemcachedEngine extends CacheEngine {

	/**
	 * Memcache wrapper.
	 *
	 * @var Memcache
	 * @access private
	 */
	var $__Memcached = null;

	/**
	 * Settings
	 *
	 *  - servers = array of memcache servers, default => 127.0.0.1, 11211. If an
	 *    array MemcacheEngine will use them as a pool.
	 *  - compress = boolean, default => false
	 *
	 * @var array
	 * @access public
	 */
	var $settings = array();

	/**
	 * Initialize the Cache Engine
	 *
	 * Called automatically by the cache frontend
	 * To reinitialize the settings call Cache::engine('EngineName', [optional] settings = array());
	 *
	 * @param array $setting array of setting for the engine
	 * @return boolean True if the engine has been successfully initialized, false if not
	 * @access public
	 * @since 0.1
	 * @author Ezra Pool <ezra@tsdme.nl>
	 */
	function init($settings = array()) {
		if (!class_exists('Memcached')) {
			return false;
		}
		parent::init(array_merge(array(
					'engine' => 'Memcached',
					'prefix' => Inflector::slug(APP_DIR) . '_',
					'servers' => array(array(
							'host' => '127.0.0.1',
							'port' => 11211,
							'weight' => 0,
					)),
					'compress' => true,
					'persistent' => false,
					'options' => array()
						), $settings)
		);

		if (!isset($this->__Memcached)) {
			$return = false;
			if ($this->settings['persistent']) {
				$this->__Memcached = new Memcached($this->settings['persistent']);
			} else {
				$this->__Memcached = new Memcached();
			}

			if (!empty($this->settings['compress'])) {
				$this->__Memcached->setOption(Memcached::OPT_COMPRESSION, $this->settings['compress']);
			}

			if (count($this->settings['options'])) {
				foreach ($this->settings['options'] as $key => $value) {
					$this->__Memcached->setOption($key, $value);
				}
			}

			//find out if the servers have already been added, extension does not check for dupes, so we have to!
			$servers = $this->__Memcached->getServerList();
			$newserverlist = array();
			foreach ($this->settings['servers'] as $settingsserver) {
				$found = false;
				foreach ($servers as $server) {
					if ($server['host'] == $settingsserver[0] && $server['port'] == $settingsserver[1]) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					$newserverlist[] = $settingsserver;
				}
			}

			if (empty($newserverlist) || $this->__Memcached->addServers($this->_parseServerList($newserverlist))) {
				$return = true;
			}
			return $return;
		}
		return true;
	}

	/**
	 * Parses the server address into the host/port.  Handles both IPv6 and IPv4
	 * addresses
	 *
	 * @param string $server The server address string.
	 * @return array Array containing host, port
	 * @since 0.1
	 * @author Ezra Pool <ezra@tsdme.nl>
	 */
	function _parseServerList($serverlist) {
		foreach ($serverlist as &$server) {
			if (!isset($server['host'])) {
				$server['host'] = 'localhost';
			}
			if (!isset($server['port'])) {
				$server['port'] = 11211;
			}
			if (!isset($server['weight'])) {
				$server['weight'] = 0;
			}
		}
		return $serverlist;
	}

	/**
	 * Write data for key into cache.  When using memcached as your cache engine
	 * remember that the Memcached extension treats cache expiry times greater
	 * than 30 days as a timestamp not an offset.
	 *
	 * @param string $key Identifier for the data
	 * @param mixed $value Data to be cached
	 * @param integer $duration How long to cache the data, in seconds
	 * @return boolean True if the data was succesfully cached, false on failure
	 * @see http://php.net/manual/en/memcached.set.php
	 * @access public
	 * @since 0.1
	 * @author Ezra Pool <ezra@tsdme.nl>
	 */
	function write($key, $value, $duration) {
		if ($duration > 30 * DAY && $duration < time()) {
			$duration = 0;
		}
		return $this->__Memcached->set($key, $value, $duration);
	}

	/**
	 * Read a key from the cache
	 *
	 * @param string $key Identifier for the data
	 * @return mixed The cached data, or false if the data doesn't exist, has expired, or if there was an error fetching it
	 * @access public
	 * @since 0.1
	 * @author Ezra Pool <ezra@tsdme.nl>
	 */
	function read($key) {
		return $this->__Memcached->get($key);
	}

	/**
	 * Read one or multiple keys from the cache ansync.
	 *
	 * @param array $keys Array of identifiers for the data
	 * @param callback $callback Callback that gets called when the data is fetched.
	 * @param bool $withCas If true CAS tokens will be present in the return. OPTIONAL
	 * @return bool Returns TRUE on success or FALSE on failure.
	 * @since 0.1
	 * @author Ezra Pool <ezra@tsdme.nl>
	 */
	function readAsync(&$keys, &$callback, $withCas = false) {
		return $this->__Memcached->getDelayed($keys, $withCas, $callback);
	}

	/**
	 * Read multiple keys from the cache
	 *
	 * @param array $keys Array of identifiers for the data
	 * @return mixed Returns the array of found items or FALSE on failure.
	 * @since 0.1
	 * @author Ezra Pool <ezra@tsdme.nl>
	 */
	function readMulti(&$keys) {
		return $this->__Memcached->getMulti($keys, null, Memcached::GET_PRESERVE_ORDER);
	}

	/**
	 * Increments the value of an integer cached key
	 *
	 * @param string $key Identifier for the data
	 * @param integer $offset How much to increment
	 * @param integer $duration How long to cache the data, in seconds
	 * @return New incremented value, false otherwise
	 * @access public
	 * @since 0.1
	 * @author Ezra Pool <ezra@tsdme.nl>
	 */
	function increment($key, $offset = 1) {
		return $this->__Memcached->increment($key, $offset);
	}

	/**
	 * Decrements the value of an integer cached key
	 *
	 * @param string $key Identifier for the data
	 * @param integer $offset How much to substract
	 * @param integer $duration How long to cache the data, in seconds
	 * @return New decremented value, false otherwise
	 * @access public
	 * @since 0.1
	 * @author Ezra Pool <ezra@tsdme.nl>
	 */
	function decrement($key, $offset = 1) {
		return $this->__Memcached->decrement($key, $offset);
	}

	/**
	 * Delete a key from the cache
	 *
	 * @param string $key Identifier for the data
	 * @return boolean True if the value was succesfully deleted, false if it didn't exist or couldn't be removed
	 * @access public
	 * @since 0.1
	 * @author Ezra Pool <ezra@tsdme.nl>
	 */
	function delete($key, $time = 0) {
		return $this->__Memcached->delete($key, $time);
	}

	/**
	 * Delete all keys from the cache
	 *
	 * @return boolean True if the cache was succesfully cleared, false otherwise
	 * @access public
	 * @since 0.1
	 * @author Ezra Pool <ezra@tsdme.nl>
	 */
	function clear($delay) {
		return $this->__Memcached->flush($delay);
	}

}

?>