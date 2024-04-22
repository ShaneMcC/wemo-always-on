<?php

	$eventService = 'urn:Belkin:service:basicevent:1';

	// Include our config if running stand-alone.
	if (!function_exists('deviceOnNotify')) {
		require_once(dirname(__FILE__) . '/config.php');
	}

	foreach ($devices as $dev) {
		if (isset($dev['data']['insightParams_state']) && $dev['data']['insightParams_state'] == 0) {
			echo 'Device is off: ', $dev['name'], "\n";

			$matched = false;
			foreach ($alwaysOnDevices as $aOD) {
				if (preg_match($aOD, $dev['name']) || preg_match($aOD, $dev['serial'])) {
					$matched = true;
					break;
				}
			}

			if (!$matched) {
				echo "\t", 'Ignoring device.', "\n";
				continue;
			}

			if (isset($dev['services'][$eventService])) {
				echo "\t", 'Device can be powered on.', "\n";

				$soap = new SoapClient(null, array('location' => $dev['services'][$eventService], 'uri' => $eventService));

				$result = $soap->SetBinaryState(new SoapParam(1, 'BinaryState'));
				if ($result == "Error") {
					echo "\t", 'Device failed to turn on.', "\n";
				} else {
					echo "\t", 'Device is now on: ', (is_array($result) ? $result['BinaryState'] : $result), "\n";
				}
				deviceOnNotify($dev, $result);
			}
		}
	}
