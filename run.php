#!/usr/bin/php
<?php

	require_once(dirname(__FILE__) . '/config.php');
	require_once(dirname(__FILE__) . '/ssdp.php');
	require_once(dirname(__FILE__) . '/phpuri.php');
	require_once(dirname(__FILE__) . '/cliparams.php');

	addCLIParam('s', 'search', 'Just search for devices, don\'t do anything.');
	addCLIParam('d', 'debug', 'Don\'t save data or attempt to post to collector, just dump to CLI instead.');
	addCLIParam('', 'ip', 'Discovery IP to probe rather than config value', true);

	$daemon['cli'] = parseCLIParams($_SERVER['argv']);
	if (isset($daemon['cli']['help'])) {
		echo 'Usage: ', $_SERVER['argv'][0], ' [options]', "\n\n";
		echo 'Options:', "\n\n";
		echo showCLIParams(), "\n";
		die(0);
	}

	if (isset($daemon['cli']['ip'])) { $discoveryIPs = $daemon['cli']['ip']['values']; }
	$time = time();

	$ssdp = new SSDP($discoveryIPs);
	$devices = array();

	$insightService = 'urn:Belkin:service:insight:1';

	foreach ($ssdp->search($insightService, $ssdpTimeout, $allowUnicastDiscovery) as $device) {
		$loc = file_get_contents($device['location']);
		$xml = simplexml_load_string($loc);

		$dev = array();
		$dev['name'] = (String)$xml->device->friendlyName;
		$dev['serial'] = (String)$xml->device->serialNumber;
		$dev['ip'] = $device['__IP'];
		$dev['port'] = $device['__PORT'];

		$dev['data'] = array();
		$dev['services'] = array();

		echo sprintf('Found: %s / %s [%s:%s -> %s]' . "\n", $dev['name'], $dev['serial'], $dev['ip'], $dev['port'], $device['location']);

		if (isset($daemon['cli']['search'])) { continue; }
		if (!isset($xml->device->serviceList->service)) { continue; }
		foreach ($xml->device->serviceList->service as $service) {
			if (!isset($service->serviceType) || !isset($service->controlURL)) { continue; }
			$url = phpUri::parse($device['location'])->join($service->controlURL);
			$dev['services'][(string)$service->serviceType] = $url;

			if ($service->serviceType == $insightService) {
				$soap = new SoapClient(null, array('location' => $url, 'uri' => $insightService));

				$calls = array();
				$calls['insightParams'] = 'GetInsightParams';
				$calls['instantPower'] = 'GetPower';
				$calls['todayKWH'] = 'GetTodayKWH';
				$calls['powerThreshold'] = 'GetPowerThreshold';
				$calls['insightInfo'] = 'GetInsightInfo';
				$calls['onFor'] = 'GetONFor';
				$calls['inSBYSince'] = 'GetInSBYSince';
				$calls['todayONTime'] = 'GetTodayONTime';
				$calls['todaySBYTime'] = 'GetTodaySBYTime';

				foreach ($calls as $k => $f) {
					try {
						$dev['data'][$k] = $soap->__soapCall($f, array());
					} catch (Exception $e) { }
				}

				// Newwer firmware doesn't seem to like the answering to
				// all of the above functions all of the time.
				//
				// However, it does seem to always answer insightParams.
				//
				// So now we parse insightParams...
				//
				// Based on http://ouimeaux.readthedocs.io/en/latest/_modules/ouimeaux/device/insight.html
				// also http://home.stockmopar.com/wemo-insight-hacking/
				// and https://github.com/openhab/openhab/blob/master/bundles/binding/org.openhab.binding.wemo/src/main/java/org/openhab/binding/wemo/internal/WemoBinding.java
				if (isset($dev['data']['insightParams'])) {
					$bits = explode('|', $dev['data']['insightParams']);
					$dev['data']['insightParams_state'] = $bits[0];
					$dev['data']['insightParams_lastChange'] = $bits[1];
					$dev['data']['insightParams_onFor'] = $bits[2];
					$dev['data']['insightParams_onToday'] = $bits[3];
					$dev['data']['insightParams_onTotal'] = $bits[4];
					$dev['data']['insightParams_timeperiod'] = $bits[5];
					$dev['data']['insightParams_averagePower'] = $bits[6];
					$dev['data']['insightParams_currentMW'] = $bits[7];
					$dev['data']['insightParams_todayMW'] = $bits[8];
					$dev['data']['insightParams_totalMW'] = $bits[9];
					$dev['data']['insightParams_threshold'] = $bits[10];
				}

				// And then where we didn't get anything from the real
				// function calls, and there is an appropriate entry in
				// insightParams, we'll simulate that instead... Stupid.
				$map = array();
				$map['instantPower'] = 'insightParams_currentMW';
				$map['powerThreshold'] = 'insightParams_threshold';
				$map['onFor'] = 'insightParams_onFor';
				$map['todayONTime'] = 'insightParams_onToday';

				foreach ($map as $k => $v) {
					if (!isset($dev['data'][$k]) && isset($dev['data'][$v])) {
						$dev['data'][$k] = $dev['data'][$v];
					}
				}
			}
		}

		$devices[] = $dev;
	}

	if (isset($daemon['cli']['search'])) { die(0); }
	if (isset($daemon['cli']['debug'])) {
		print_r($devices);
		die(0);
	}

	echo "\n";

	require_once(dirname(__FILE__) . '/always-on.php');
