<?
	/*
	   Return 0 if the $thisTime is equal $cmpTimeStart or between $timeStart and $timeEnd. -1 if the $thisTime lower than $timeStart and 1 if the time bigger that $timeEnd.
	*/
	function compareTime($thisTime, $cmpTime) {
	   return compareTimeWithRange($thisTime, $cmpTime, null);
	}
	
	function compareTimeWithRange($thisTime, $cmpTimeStart, $cmpTimeEnd) {
		if(!is_array($thisTime)) $thisTime = explode(":", $thisTime);
		if(!is_array($cmpTimeStart)) $cmpTimeStart = explode(":", $cmpTimeStart);
		if(!is_array($cmpTimeEnd)) $cmpTimeEnd = explode(":", $cmpTimeEnd);
		
		// print("CC:".$thisTime[0].":".$thisTime[1]."\n");
		// print("cS:".$cmpTimeStart[0].":".$cmpTimeStart[1]."\n");
		
		if(!isset($cmpTimeEnd)) {
			$cmpTimeEnd = null;
		} else {
		 //print("cE:".$cmpTimeEnd[0].":".$cmpTimeEnd[1]."\n");
		}
		
		$isAfterStart = ($thisTime[0] > $cmpTimeStart[0]) ||
							($thisTime[0] == $cmpTimeStart[0] && $thisTime[1] >= $cmpTimeStart[1]);
		$isBeforeEnd = $cmpTimeEnd != null &&
							(
								($thisTime[0] < $cmpTimeEnd[0]) ||
								($thisTime[0] == $cmpTimeEnd[0] && $thisTime[1] < $cmpTimeEnd[1])
							);
		$isBeforeEndOrMatch = $isBeforeEnd || ($thisTime[0] == $cmpTimeStart[0] && $thisTime[1] == $cmpTimeStart[1]);
		if($isAfterStart) {
		   if($isBeforeEndOrMatch) {
		      return 0;
		   }
		   else {
		      return 1;
		   }
		} else {
		   return -1;
		}
	}
	
	function getBestConfigurationForTime($dayConfig, $currentTime) {
		$bestConf = null;
		
		$i = -1;
		foreach($dayConfig as $timeperiod) {
		   $i++;
			$iConf = $timeperiod[tp_START];

			// print(print_r($timeperiod, true)."\n");
			$cmpVal = compareTime($currentTime, $iConf);
			// print($cmpVal."\n");
			
			if($cmpVal == 0) {
				$bestConf = $timeperiod;
				break;
			} else if($cmpVal == -1) {
				continue;
			}
			
			if($bestConf == null) {
			   // print("Best Conf: $i");
				$bestConf = $timeperiod;
				continue;
			}

			// check if best configuration is still "best"
			$confVal = compareTime($iConf, $bestConf[tp_START]);
			if($confVal == 0) {
				// best configuration and current configuration match -> ignore
				continue;
			} else if($confVal == 1) {
				// current configuration is "larger" than (= closer) than best configuration (= closer to current time)
				// print("Best Conf: $i");
				$bestConf = $timeperiod;
			}
		}
		return $bestConf;
	}
?>