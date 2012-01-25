<?
	include_once "IPSLogger.ips.php";
	include_once "HeatingControl\HeatingControl_RoomState.ips.php";
	include_once "HeatingControl\Homematic_ClimateControl.ips.php";
	include_once "HeatingControl\Homematic_Constants.ips.php";
	include_once "HeatingControl\HeatingConfiguration.ips.php";
	include_once "HeatingControl\ValueUtils.ips.php";
	include_once "HeatingControl\WeatherUtils.ips.php";
	include_once "IPSInstaller.ips.php";
	
	/* TODO:
	- add selection (similar to "Temperature.HM") for TIME in UI ( to be able to extend the override)
	- 
	- implement AUTO_AWAY logic (based on TV, Receiver, Computer usage, bewegungsmelder)?
	- implement work time recording (with state: anwesend, abwesend for automated climate control)
	*/
	/*
	04.12.2011: - add UI switch: ignore WINDOW_OPEN_SWITCH
	19.12.2011: - stop valve stall by increasing temperature for a short time and then decreasing it again
	20.01.2012: - show temperature override TIME VALIDITY in WebFront
	*/
	define("OVERRIDE_DURATION", 60); // in minutes
	define("VALVE_STALL_LIMIT", 4); // in hours. Time frame in, which a valve has to change its value.
	define("VALVE_STALL_UPPER_VALUE", 7); // integer (range: 1 - 100)
	
	if(!IPS_CategoryExists(c_ID_state) || !IPS_CategoryExists(c_ID_rooms) || !IPS_VariableExists(c_ID_presence)) {
		die("Heating control does not seem to be properly installed.");
	}
	
	function setVariableLogging($varId, $enabled = true) {
		AC_SetLoggingStatus(48801 /*[Archive Handler]*/, $varId, $enabled);
		IPS_ApplyChanges($varId);
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
	
	function isStallRecoveryRunning($ids, $roomCategory) {
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
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Returns false, if isAway and roomWindowOpen are both set to "false".
	 */
	function getAwayWindowOpenTemperature($isAway, $roomWindowOpen, $config) {
		$windowOpenTemp = null;
		$awayTemp = null;
		$newTargetTemp = 20;
		
		// check for "window open" temperature
		if($roomWindowOpen) {
			$windowOpenTemp = $config[t_WINDOW_OPEN];
		}
		
		// check for AWAY temperature
		if(isset($config[t_AWAY]) && $isAway) {
			$awayTemp = $config[t_AWAY];
		}
		
		// check for lowest temperature of windowOpen and away and return it
		if($windowOpenTemp != null || $awayTemp != null) {
			if($windowOpenTemp != null && $windowOpenTemp < $newTargetTemp) {
				$newTargetTemp = $windowOpenTemp;
			}
			if($awayTemp != null && $awayTemp < $newTargetTemp) {
				$newTargetTemp = $awayTemp;
			}
			return $newTargetTemp;
		}
		
		return false;
	}
	
	function evaluateTargetTemperature($ids, $config, $roomCategory) {
		global $day_order;
		
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
		$newTargetTemp = $bestConf[tp_TARGET];
		// IPSLogger_Dbg(__file__, "Daytime-based target temperature: ".$newTargetTemp);
		
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
				$newTargetTemp += $currentIncrease;
				//IPSLogger_Dbg(__file__, "Humidity-based temperature increase: ".$currentIncrease);
			}
		}
		return $newTargetTemp;
	}
	
	/**
	 * Check whether updates are temporarily locked (Preserved)
	 */
	function shouldPreserveValue($currentTimeStamp, $roomName, $climateControl, $roomState, $currentAutoTemp) {
		// clear preserve value if it has expired
		$preserveUntil = $roomState->preserveUntil->value;
		$hasValidPreserveValue = false;
		if($preserveUntil != 0 && $preserveUntil <= $currentTimeStamp) {
			$roomState->resetPreserveUntil();
			IPSLogger_Dbg(__file__, "Preserving value expired in ".$roomName);
		} else if($preserveUntil != 0) {
			$hasValidPreserveValue = true;
		}
		unset($preserveUntil);
		
		// get time stamp of last HM based change
		$currentTargetTempDetails = IPS_GetVariable($climateControl->getTargetTemperatureReadId());
		$currentTargetTempUpdated = round($currentTargetTempDetails["VariableChanged"]);
		
		// set initial value
		if($roomState->lastHMUpdate->value == 0) {
			$lastHMUpdate = $currentTargetTempUpdated;
			$roomState->lastHMUpdate->value = lastHMUpdate;
		}
		
		$lastUpdateId = $roomState->lastUpdate->variableId;
		$IPS_variableDetails = IPS_GetVariable($lastUpdateId);
		$IPS_variableUpdated = round($IPS_variableDetails["VariableChanged"]);
		
		$shouldPreserve = false;
		
		// print("HM :".$currentTargetTempUpdated."\n");
		// print("IPS:".$IPS_variableUpdated."\n\n");
		
		// outside update occurred -> preserve value for 1 hour
		if($currentTargetTempUpdated > $IPS_variableUpdated) {
			$preserveUntil = $roomState->preserveUntil->value;
			if($roomState->lastHMUpdate->value < $currentTargetTempUpdated) {
				IPSLogger_Dbg(__file__, "Setting new preserve value in ".$roomName);
				
				// print("new preserve: ".$preserveUntil."\n");
				$roomState->lastHMUpdate->value = $currentTargetTempUpdated;
				
				$preserveUntil = $currentTargetTempUpdated + 60 * OVERRIDE_DURATION;
				$roomState->preserveUntil->value = $preserveUntil;
				$shouldPreserve = true;
			} else if($preserveUntil != 0 && $preserveUntil > $currentTargetTempUpdated) {
				// preserve value is set and valid;
				$shouldPreserve = true;
			}
		}
		
		if($hasValidPreserveValue) {
			$shouldPreserve = $hasValidPreserveValue;
		}
		
		$currentTargetTemp = $climateControl->getTargetTemperature();
		$lastTargetTemperature = $roomState->lastTargetTemperature->value;
		if($shouldPreserve && $currentAutoTemp == $currentTargetTemp) {
			IPSLogger_Trc(__file__, "Should preserve but temperatures matching. Disabling preserve value.");
			$roomState->resetPreserveUntil();
			$shouldPreserve = false;
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
						
						if($roomTemperature !== false && $roomHumidity !== false && $roomAbsHumidity !== false) break;
					}
					$absHumidity = round(calculateAbsoluteHumidity($roomTemperature, $roomHumidity), 2);
					$absHumidityId = IPS_GetVariableIDByName("ABS_HUMIDITY", $targetRoomCategory);
					if($absHumidityId === false) {
						$absHumidityId = CreateVariable("ABS_HUMIDITY",  2, $targetRoomCategory, 0, 'Abs.Humidity', '', $absHumidity, '');
					} else {
						SetValue($absHumidityId, $absHumidity);
					}
					
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
				$ccc->setTargetTemperatureReadId($objectIds[o_TEMP_CONTROL][v_GET]);
				$ccc->setLocation($objectName);
				
				// search for category with name "objectName"
				$roomCategory = CreateCategory($objectName, c_ID_state, 10);
				$roomState = new RoomState($roomCategory);
				
				if($objectName != "KITCHEN") {
					continue;
				}
				
				if(isStallRecoveryRunning($objectIds, $roomCategory)) {
					$ccc->setTargetTemperature(30);
					continue;
				}
				
				// create aggregate to encapsulate all important heating controls, which can be used by webfront etc.
				$heatingControlModuleId = IPS_GetInstanceIDByName(lang_HEATING_CONTROL, $roomCategory);
				if($heatingControlModuleId === false) {
					$heatingControlModuleId = CreateDummyInstance(lang_HEATING_CONTROL, $roomCategory, 100);
				}
				// CreateLink(lang_TARGET_TEMPERATURE, $objectIds[o_TEMP_CONTROL][v_GET], $heatingControlModuleId, 50);
				
				// check if there is an open window that we dont want to ignore
				$roomWindowOpen = $ignoreWindowOpen = false;
				if(isset($objectIds[o_WINDOW])) {
					$window = $objectIds[o_WINDOW];
					if(isset($window[v_STATE])) {
						// create links for aggregate
						for($i = 0, $length = count($window[v_STATE]); $i < $length; $i++) {
							$text = lang_WINDOW.($length > 1 ? " $i" : "");
							$sourceVariableId = $window[v_STATE][$i];
							
							if(@IPS_GetVariableIDByName($text, $heatingControlModuleId) == false) {
								$targetVariableId = CreateVariable($text, 0 /* Boolean */, $heatingControlModuleId, 1, 'Windows-Color', '', null, 'Window');
								setVariableLogging($targetVariableId);
								
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
								setVariableLogging($varId);
							}
							IPS_SetHidden($varId, false);
							$roomWindowOpen = true;
							$ignoreWindowOpen = GetValue($varId);
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
				
				$newAutoTemperature = evaluateTargetTemperature($objectIds, $config[$objectName], $roomCategory);
				
				$considerOpenWindow = $roomWindowOpen && !$ignoreWindowOpen;
				$shouldPreserve = false;
				if(!$considerOpenWindow) {
					$shouldPreserve = shouldPreserveValue($currentTimeStamp, $objectName, $ccc, $roomState, $newAutoTemperature);
					//echo "should preserve: ".($shouldPreserve  ?"true":"false")."\n";
					$preserveUntilVarId = @IPS_GetObjectIDByIdent(ident_PRESERVE_VALUE_SET, $heatingControlModuleId);
					if($shouldPreserve) {
						$timestamp = $roomState->preserveUntil->value;
						// TODO: maybe display as remaining minutes
						$formattedTime = date("H:i", $timestamp);
						$varTitle = sprintf(lang_PRESERVE_VALUE_SET, $newAutoTemperature);
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
				}
				
				$currentTargetTemp = $ccc->getTargetTemperature();
				$newTargetTemp = getAwayWindowOpenTemperature(!$isPresent, $considerOpenWindow, $config[$objectName]);
				
				$windowStateChanged = $roomState->previousWindowState->value != $considerOpenWindow;
				$windowJustOpened = $windowStateChanged && $considerOpenWindow;
				//echo "ConsiderWindow: ".($considerOpenWindow  ?"1":"0")." - WJustOpened: ".($windowJustOpened ?"1":"0")."\n";
				//echo "Kit: WStateChanged: ".($windowStateChanged ?"1":"0")." - roomWindowOpen: ".($roomWindowOpen ?"1":"0")."\n";
				
				if($windowStateChanged) {
					if($roomWindowOpen && !$ignoreWindowOpen) {
						// window was just opened - backup to "lastTargetTemp"
						$roomState->lastTargetTemperature->value = $currentTargetTemp;
						echo "window opened - stored: ".$roomState->lastTargetTemperature->value."°C\n";
					} else if($roomWindowOpen && $ignoreWindowOpen) {
						// restore persisted temperature
						$newTargetTemp = $roomState->lastTargetTemperature->value;
						echo "window ignored - restoring: ".$roomState->lastTargetTemperature->value."°C\n";
					} else {
						// window was just closed -> restore from "lastTargetTemp"
						if($roomState->preserveUntil->value > time()) {
							$newTargetTemp = $roomState->lastTargetTemperature->value;
							echo "window closed - stored: ".$roomState->lastTargetTemperature->value."°C\n";
						} else {
							$roomState->lastTargetTemperature->value = 0;
						}
					}
				}
				
				if($newTargetTemp == false) {
					if($shouldPreserve) {
						$newTargetTemp = $currentTargetTemp;
						//echo "PRESERVE TEMP: $newTargetTemp"."\n";
					} else {
						// no temp overrides set, use evaluated temperature
						$newTargetTemp = $newAutoTemperature;
						//echo "NORMAL TEMP: $newTargetTemp"."\n";
					}
				} else {
					//echo "AWAY WINDOW TEMP: $newTargetTemp"."\n";
				}
				
				
				if($newTargetTemp != $currentTargetTemp) {
					//echo "NewTargetTemp: $newTargetTemp - CurrentTargetTemp: ".$currentTargetTemp."\n";
					//echo "isPreserve: ".$roomState->isPreserveSet()." - TempDiff: ".($currentTargetTemp != $roomState->lastTargetTemperature->value)."\n";
					
					$ccc->setTargetTemperature($newTargetTemp);
					$roomState->lastUpdate->value = time();
				}
				if($windowStateChanged) {
					$roomState->previousWindowState->value = $considerOpenWindow;
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