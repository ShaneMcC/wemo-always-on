<?php

	/** IPs to send SSDP Discovery to. */
	$discoveryIPs = array('239.255.255.250');

	/** Try setting this to false if unicast discovery doesn't work. */
	$allowUnicastDiscovery = true;

	/** Timeout for discovery packets */
	$ssdpTimeout = 2;

	/**
	 * Array of devices to ensure always on, will compare to name and serial.
	 *
	 * Each match should be a regex string to pass to preg_match.
	 */
	$alwaysOnDevices = ['#.*#'];

	if (file_exists(dirname(__FILE__) . '/config.user.php')) {
		require_once(dirname(__FILE__) . '/config.user.php');
	}

	if (!function_exists('deviceOnNotify')) {
		/**
		 * Function to notify someone that the device was turned on.
		 *
		 * @param $device Device array
		 * @param $response Response from power-on command.
		 */
		function deviceOnNotify($device, $response) { }
	}
