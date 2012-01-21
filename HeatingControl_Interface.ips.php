<?
	include_once "IPSLogger.ips.php";
	include_once "HeatingControl\Homematic_ClimateControl.ips.php";
	include_once "HeatingControl\Homematic_Constants.ips.php";
	include_once "HeatingControl\HeatingConfiguration.ips.php";
	include_once "HeatingControl\ValueUtils.ips.php";
	include_once "HeatingControl\WeatherUtils.ips.php";
	include_once "IPSInstaller.ips.php";
	
	/* TODO:
	- show temperature override TIME VALIDITY in WebFront
	- add selection (similar to "Temperature.HM") for TIME in UI ( to be able to extend the override)
	- 
	- implement AUTO_AWAY logic (based on TV, Receiver, Computer usage, bewegungsmelder)?
	- implement work time recording (with state: anwesend, abwesend for automated climate control)
	*/
	/*
	04.12.2011: - add UI switch: ignore WINDOW_OPEN_SWITCH
	19.12.2011: - stop valve stall by increasing temperature for a short time and then decreasing it again
	*/
	define("OVERRIDE_DURATION", 60); // in minutes
	define("VALVE_STALL_LIMIT", 4); // in hours. Time frame in, which a valve has to change its value.
	define("VALVE_STALL_UPPER_VALUE", 7); // integer (range: 1 - 100)
	
	if(!IPS_CategoryExists(c_ID_state) || !IPS_CategoryExists(c_ID_rooms) || !IPS_VariableExists(c_ID_presence)) {
		die("Heating control does not seem to be properly installed.");
	}
	
	function hasOpenWindow($windowIds) {
		$isWindowOpen = false;
		if(count($windowIds > 0)) {
			// we have a window in the room that takes precendence
			// get state of window(s)
			foreach($windowIds as $id) {
				$isWindowOpen = GetValueBoolean($id);
				if($isWindowOpen) break;
			}
		}
		return $isWindowOpen;
	}
	
	function evaluateTargetTemperature($ids, $config, $isAway, $shouldPreserveValue, $roomCategory, $roomWindowOpen) {
		global $day_order;
		
		$categoryObject = IPS_GetObject($roomCategory);
		$roomName = $categoryObject["ObjectName"];
		
		// check for stalled valve
		// TODO: check if there is an old valve value
		if(isset($ids[o_VALVES]) && is_array($ids[o_VALVES])) {
			$stallRecoveryRunning = false;
			
			$valveStallRecoveryVariableId = @IPS_GetVariableIDByName("VALVE_STALL_RECOVERY", $roomCategory);
			$recoveryInProcessSince = 0;
			if($valveStallRecoveryVariableId != false) {
				// we have a stall recovery in process
				$recoveryInProcessSince = GetValue($valveStallRecoveryVariableId);
			}
			// go through valve values and check when they were last changed (ignore a 0% setting as well as everything above VALVE_STALL_UPPER_VALUE)
			$lastChanged = 0;
			$stalledValveValue = 0;
			foreach($ids[o_VALVES] as $valveId) {
				$variableObject = IPS_GetVariable($valveId);
				$valveValue = $variableObject["VariableValue"]["ValueInteger"];
				if($valveValue > 0 && $valveValue < VALVE_STALL_UPPER_VALUE) {
					$lastChanged = max((int) $variableObject["VariableChanged"], (int) $lastChanged);
					$stalledValveValue = $valveValue;
				}
			}
			$clearStallState = false;
			// todo: check current and target temperature
			
			if($lastChanged > 0) {
				if($recoveryInProcessSince > 0 && $lastChanged > $recoveryInProcessSince) {
					IPSLogger_Dbg(__file__, " Stopping recovery mode in ".$roomName.". (Reason: Valve value changed)");
					$clearStallState = true;
				}
				
				$timeStamp = time();
				$hoursSinceLastChange = ($timeStamp - $lastChanged) / 3600;
				if($hoursSinceLastChange > VALVE_STALL_LIMIT) {
					//IPSLogger_Dbg(__file__, "Valve state not change in x hours: ".$hoursSinceLastChange);
					
					// set a variable indicating that we are trying to solve a STALL
					if($valveStallRecoveryVariableId == false) {
						$valveStallRecoveryVariableId = @CreateVariable("VALVE_STALL_RECOVERY", 2, $roomCategory, 0, '', '', $timeStamp, '');
						IPSLogger_Dbg(__file__, "Starting valve stall recovery in ".$roomName);
					} else if($clearStallState) {
						SetValue(valveStallRecoveryVariableId, $timeStamp);
						IPSLogger_Dbg(__file__, "Restarting valve stall recovery in ".$roomName);
					}
					
					$clearStallState = false;
					
					if($recoveryInProcessSince == 0) {
						IPSLogger_Dbg(__file__, "Setting stall recovery flag in ".$roomName.". (Reason: Valve value of '" . $stalledValveValue . "%' did not change for ".round($hoursSinceLastChange, 2)." hours.)");
					}
					$stallRecoveryRunning = true;
				}
			} else {
				// valve value is outside the relevant value range -> everything should be okay
				$clearStallState = $recoveryInProcessSince > 0;
			}
			
			if($clearStallState) {
				IPSLogger_Dbg(__file__, "Clearing valve stall recovery");
				// reset recovery since it is still in progress
				IPS_DeleteVariable($valveStallRecoveryVariableId);
			}
			
			if($stallRecoveryRunning) {
				return 30;
			}
		}
		
		$targetTemp = 20;
		$windowOpenTemp = null;
		$awayTemp = null;
		
		// check for "window open" temperature
		if($roomWindowOpen) {
			// check external override condition
			$ignoreWindowState = isset($window[v_OVERRIDE]) && GetValue($window[v_OVERRIDE]);
			if($ignoreWindowState) {
				// found an open window but choose to ignore it
			} else {
				$windowOpenTemp = $config[t_WINDOW_OPEN];
			}
		}
		
		// check for AWAY temperature
		if(isset($config[t_AWAY]) && $isAway) {
			$awayTemp = $config[t_AWAY];
		}
		
		// check for lowest temperature of windowOpen and away and return it
		if($windowOpenTemp != null || $awayTemp != null) {
			if($windowOpenTemp != null && $windowOpenTemp < $targetTemp) {
				$targetTemp = $windowOpenTemp;
			}
			if($awayTemp != null && $awayTemp < $targetTemp) {
				$targetTemp = $awayTemp;
			}
			return $targetTemp;
		}
		
		// preserve value is overridden by stall recovery, an open window and AWAY only
		if($shouldPreserveValue) {
			return false;
		}
		
		$daysConfig = $config[t_DAYS];
		$currentDayNumber = date("N") - 1;
		$currentDayName = $day_order[$currentDayNumber];
		while($currentDayNumber > 0 && !isset($daysConfig[$currentDayName])) {
			$currentDayNumber--;
			$currentDayName = $day_order[$currentDayNumber];
		}

		if(!isset($daysConfig[$currentDayName]) || count($daysConfig[$currentDayName]) == 0) {
			throw new Exception("No configuration found for ".$currentDayName);
		}
		
		$dayConfig = $daysConfig[$currentDayName];
		$currentTime = array(0 => date("G"), 1 => date("i"));
		
		$bestConf = getBestConfigurationForTime($dayConfig, $currentTime);
		if($bestConf == null) {
			IPSLogger_Wrn(__file__, "No matching config found. Using first one\n");
			$bestConf = $daysConfig[0];
		}
		$targetTemp = $bestConf[tp_TARGET];
		// IPSLogger_Dbg(__file__, "Daytime-based target temperature: ".$targetTemp);
		
		// check if we need to take the humidity into account (requires HUMIDITY reading)
		if(isset($config[t_HUMIDITY])) {
			$humConf = $config[t_HUMIDITY];
			// check time
			$cmpVal = compareTimeWithRange($currentTime, $humConf[t_HUMIDITY_INC_START_HOUR], $humConf[t_HUMIDITY_INC_END_HOUR]);
			//print("Should check humidity: ".$cmpVal."\n");
			if($cmpVal == 0) {
				// time is in range -> get current humidity
				$cHumidity = GetValue($ids[o_CLIMATE][v_GET_HUMIDITY]);
				//print("Current Humidity: ".$cHumidity."\n");

				$currentIncrease = 0;
				foreach($humConf[t_HUMIDITY_VALUES] as $humValues) {
				   if($cHumidity >= $humValues[x_HUMIDITIY_MIN]) {
						//print("Humidity Increase: ".$humValues[x_HUMIDITY_TEMP_INC]."\n");
						$currentIncrease = $humValues[x_HUMIDITY_TEMP_INC];
					}
				}
				$targetTemp += $currentIncrease;
				//IPSLogger_Dbg(__file__, "Humidity-based temperature increase: ".$currentIncrease);
			}
		}
		return $targetTemp;
	}
	
	function shouldPreserveValue($currentTimeStamp, $roomName, $roomCategory, $roomWindowOpen, $HM_targetTempVarId) {
		// check whether updates are temporarily locked (Preserved)
		
		// get or create "LAST_UPDATE" variable (timestamp, when this scripts changed the setting last)
		$lastUpdateId = CreateVariable("LAST_UPDATE",  1, $roomCategory, 0, '', '', null, '');
		// get or create "LAST_HM_UPDATE" variable (timestamp, when the setting was last changed by homematic e.g. manually changing the setting on the control unit)
		$lastHMUpdateId = CreateVariable("LAST_HM_UPDATE",  1, $roomCategory, 0, '', '', null, '');
		$lastHMUpdate = GetValue($lastHMUpdateId);
		// get or create "PRESERVE_UNTIL" variable (timestamp until this script will ignore changing the temperature setting)
		$preserveUntilId = CreateVariable("PRESERVE_UNTIL",  1, $roomCategory, 0, '', '', null, '');
		$preserveUntil = GetValue($preserveUntilId);
		if($preserveUntil != 0 && $preserveUntil <= $currentTimeStamp) {
			$preserveUntil = 0;
			IPSLogger_Dbg(__file__, "Preserving value expired in ".$roomName);
			SetValue($preserveUntilId, $preserveUntil);
		}
		
		// get time stamp of last HM based change
		$HM_variableDetails = IPS_GetVariable($HM_targetTempVarId);
		$HM_variableUpdated = round($HM_variableDetails["VariableChanged"]);
		// set initial value
		if($lastHMUpdate == 0) {
			$lastHMUpdate = $HM_variableUpdated;
			SetValue($lastHMUpdateId, $lastHMUpdate);
		}
		$IPS_variableDetails = IPS_GetVariable($lastUpdateId);
		$IPS_variableUpdated = round($IPS_variableDetails["VariableChanged"]);
		$shouldPreserve = false;
		
		// print("HM :".$HM_variableUpdated."\n");
		// print("IPS:".$IPS_variableUpdated."\n\n");
		
		// outside update occurred -> preserve value for 1 hour
		if($HM_variableUpdated > $IPS_variableUpdated) {
			if($lastHMUpdate < $HM_variableUpdated) {
				IPSLogger_Dbg(__file__, "Setting new preserve value in ".$roomName);
				
				// print("new preserve: ".$preserveUntil."\n");
				SetValue($lastHMUpdateId, $HM_variableUpdated);
				
				$preserveUntil = $HM_variableUpdated + 60 * OVERRIDE_DURATION;
				SetValue($preserveUntilId, $preserveUntil);
				$shouldPreserve = true;
			} else if($preserveUntil != 0 && $preserveUntil > $HM_variableUpdated) {
				// preserve value is set and valid;
				$shouldPreserve = true;
			}
		}
		return $shouldPreserve;
	}
	
	function calculateValues() {
		// go through all ROOMS and do calculations for each
		
		$rooms_object = IPS_GetObject(c_ID_rooms);
		foreach($rooms_object["ChildrenIDs"] as $childID) {
			// check if it is a category
			if(IPS_CategoryExists($childID)) {
				$thisRoomCategory = IPS_GetObject($childID);
				
				// look for "ct_CLIMATE" -> calculate abs. Humiditiy
				$climateInstanceId = @IPS_GetInstanceIDByName(ct_CLIMATE, $childID);
				if($climateInstanceId != false) {
					//var_dump(IPS_GetInstance($climateInstanceId));
					$targetRoomCategory = IPS_GetCategoryIDByName($thisRoomCategory["ObjectName"], c_ID_state);
					if($targetRoomCategory == false) {
						$targetRoomCategory = CreateCategory($thisRoomCategory["ObjectName"], c_ID_state, 10);
					}
					
					$climateInstanceObject = IPS_GetObject($climateInstanceId);
					$roomTemperature = $roomHumidity = $roomAbsHumidity = false;
					
					foreach($climateInstanceObject["ChildrenIDs"] as $varID) {
						// skip everything that is no variable
						if(!IPS_VariableExists($varID)) {
							continue;
						}
						$varObject = IPS_GetVariable($varID);
						$varProfile = $varObject["VariableProfile"];
						
						if($varProfile == "~Temperature") {
							$roomTemperature = $varObject["VariableValue"]["ValueFloat"];
						} else if($varProfile == "~Humidity") {
							$roomHumidity = $varObject["VariableValue"]["ValueInteger"];
						} else if($varProfile == "Abs.Humidity") {
							$roomAbsHumidity = $varObject["VariableValue"]["ValueInteger"];
						}
						
						if($roomTemperature !== false && $roomHumidity !== false) break;
					}
					$absHumidity = round(calculateAbsoluteHumidity($roomTemperature, $roomHumidity), 2);
					$absHumidityId = CreateVariable("ABS_HUMIDITY",  2, $targetRoomCategory, 0, 'Abs.Humidity', '', $absHumidity, '');
					
					// create link to absolute humidity variable in source "Klima" instance
					if($roomAbsHumidity == false) {
						CreateLink(lang_ABS_HUMIDITY,  $absHumidityId,  $climateInstanceId, 100);
					}
				}
			}
		}
	}
	
	// calculate absolute humidity
	calculateValues();
	
	$isPresent = GetValueBoolean(c_ID_presence);
	$lastAwayStatusId = CreateVariable("LAST_PRESENCE",  0, c_ID_state, 0, '', '', null, '');
	$awayStatusChanged = GetValue($lastAwayStatusId) != $isPresent;
	if($awayStatusChanged) {
		IPSLogger_Inf(__file__, "Away status changed to ".($isPresent ? "PRESENT" : "AWAY"));
		SetValue($lastAwayStatusId, $isPresent);
	}
	
	$objects = getIds();
	$config = getConfig();
	$currentTimeStamp = time();
	
	foreach($objects as $objectName => $objectIds) {
		if($objectIds[o_TYPE] == tp_CONTROL) {
			// object with controllable temperature
			$instanceInfo = IPS_GetInstance($objectIds[o_TEMP_CONTROL][v_SET]);
			$deviceModuleName = $instanceInfo["ModuleInfo"]["ModuleName"];
			
			if($deviceModuleName == "HomeMatic Device") {
				$ccc = new HomematicClimateControl($objectIds[o_TEMP_CONTROL][v_SET]);
				$ccc->setLocation($objectName);
				
				$HM_targetTempVarId = $objectIds[o_TEMP_CONTROL][v_GET];
				$ccc->setTargetTemperatureReadId($HM_targetTempVarId);
				
				// search for category with name "objectName"
				$roomCategory = CreateCategory($objectName, c_ID_state, 10);
				
				// create aggregate which can be used by webfront etc.
				$heatingControlModuleId = CreateDummyInstance(lang_HEATING_CONTROL, $roomCategory, 100);
				
				//CreateLink(lang_TARGET_TEMPERATURE, $objectIds[o_TEMP_CONTROL][v_GET], $heatingControlModuleId, 50);
				
				$lastTargetTemperatureId = CreateVariable("LAST_TARGET_TEMPERATURE",  2, $roomCategory, 0, '', '', null, '');
				$lastTargetTemperature = GetValue($lastTargetTemperatureId);
				
				// check if there is an open window that we dont want to ignore
				$roomWindowOpen = false;
				if(isset($objectIds[o_WINDOW])) {
					$window = $objectIds[o_WINDOW];
					if(isset($window[v_STATE])) {
						// create links for aggregate
						for($i = 0, $length = count($window[v_STATE]); $i < $length; $i++) {
							$text = lang_WINDOW.($length > 1 ? " $i" : "");
							$sourceVariableId = $window[v_STATE][$i];
							
							if(@IPS_GetVariableIDByName($text, $heatingControlModuleId) == false) {
								$targetVariableId = CreateVariable($text, 0 /* Boolean */, $heatingControlModuleId, 1, 'Windows-Color', '', null, 'Window');
								AC_SetLoggingStatus(48801 /*[Archive Handler]*/, $targetVariableId, true);
								IPS_ApplyChanges($targetVariableId);
								
								// create change event on source
								$script = "SetValueBoolean($targetVariableId, GetValueBoolean($sourceVariableId));";
								CreateEventWithScript("Ereignis: Bei Variablenänderung.", $sourceVariableId, $targetVariableId, $script, $TriggerType=1 /*ByChange*/);
							}
						}
						
						if(hasOpenWindow($window[v_STATE])) {
							// create "override window state" variable
							$varId = @IPS_GetVariableIDByName(lang_OVERRIDE_OPEN_WINDOW, $heatingControlModuleId);
							if($varId == false) {
								$varId = CreateVariable(lang_OVERRIDE_OPEN_WINDOW, 0 /* Boolean */, $heatingControlModuleId, 1, 'WindowIgnore', s_ID_IgnoreWindow, false, 'Window');
								AC_SetLoggingStatus(48801 /*[Archive Handler]*/, $varId, true);
								IPS_ApplyChanges($varId);
							}
							IPS_SetHidden($varId, false);
							$roomWindowOpen = true && !GetValue($varId);
						} else {
							$varId = @IPS_GetVariableIDByName(lang_OVERRIDE_OPEN_WINDOW, $heatingControlModuleId);
							if($varId != false) {
								IPS_SetHidden($varId, true);
								if(GetValue($varId) != false) {
									SetValue($varId, false);
								}
							}
						}
					}
				}
				
				$shouldPreserve = shouldPreserveValue($currentTimeStamp, $objectName, $roomCategory, $roomWindowOpen, $HM_targetTempVarId);
				// IPSLogger_Inf(__file__, "LAST:".GetValue($lastTargetTemperatureId));
				// IPSLogger_Inf(__file__, "TARGET:".$ccc->getTargetTemperature());
				$preserveUntilId = CreateVariable("PRESERVE_UNTIL",  1, $roomCategory, 0, '', '', null, '');
				$oldTargetTemp = $ccc->getTargetTemperature();
				if($shouldPreserve && $lastTargetTemperature == $oldTargetTemp) {
					IPSLogger_Trc(__file__, "Should preserve but temperatures matching. Disabling preserve value.");
					SetValue($preserveUntilId, 0);
					$shouldPreserve = false;
				}
				
				$preserveUntilVarId = @IPS_GetObjectIDByIdent(ident_PRESERVE_VALUE_SET, $heatingControlModuleId);
				if($shouldPreserve) {
					$timestamp = GetValue($preserveUntilId);
					// TODO: maybe display minutes
					$formattedTime = date("H:i", $timestamp);
					$varTitle = sprintf(lang_PRESERVE_VALUE_SET, $lastTargetTemperature);
					if($preserveUntilVarId === false) {
						// TODO: offer time selection to extend validity of value
						$preserveUntilVarId = CreateVariable($varTitle, 3 /* String */, $heatingControlModuleId, 10, '', '', $formattedTime, '');
						IPS_SetIdent($preserveUntilVarId, ident_PRESERVE_VALUE_SET);
					} else {
						IPS_SetName($preserveUntilVarId, $varTitle);
						SetValue($preserveUntilVarId, $formattedTime);
					}
				} else {
					if($preserveUntilVarId !== false) {
						IPS_DeleteVariable($preserveUntilVarId);
						$preserveUntilVarId = false;
					}
				}
				
				//IPSLogger_Inf(__file__, $objectName);
				//if($objectName == "LIVING" || $objectName == "SLEEP")
					$targetTemp = evaluateTargetTemperature($objectIds, $config[$objectName], !$isPresent, $shouldPreserve, $roomCategory, $roomWindowOpen);
				//else
				//   continue;
				
				if($targetTemp !== false && $targetTemp != $oldTargetTemp) {
					$lastUpdateId = CreateVariable("LAST_UPDATE",  1, $roomCategory, 0, '', '', null, '');
					$ccc->setTargetTemperature($targetTemp);
					SetValue($lastUpdateId, time());
					SetValue($lastTargetTemperatureId, $targetTemp);
				}
			} else {
				IPSLogger_Err(__file__, "Device not supported: ".$deviceModuleName);
			}
		} else if($objectIds[o_TYPE] == tp_WATCH) {
			// object with watchable temperature
			// TODO: implement FRIDGE watch
		}
	}

?>