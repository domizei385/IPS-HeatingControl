<?
	function getSDD($temperature, $constant_1, $constant_a, $constant_b) {
			return $constant_1 * pow(10, ($constant_a * $temperature)/($constant_b + $temperature));
		}
	
	/*
	* Returns the absolute humidity in g/m³
	* see: http://www.wettermail.de/wetter/feuchte.html
	*/
	function calculateAbsoluteHumidity($temperature, $relHumidity) {
		$constant_1 = 6.1078;
		$universalGasConstant = 8314.3;
		$molecularWeightOfSteam = 18.016;
		$constant_a_positiveTemp = 7.5;
		$constant_a_dewPoint = 7.6;
		$constant_b_positiveTemp = 237.3;
		$constant_b_dewPoint = 240.7;
		
		$sdd_T = getSDD($temperature, $constant_1, $constant_a_positiveTemp, $constant_b_positiveTemp); // Saettigungsdampfdruck
		$dd_r_T = ($relHumidity / 100) * $sdd_T; // Dampfdruck
		// $td_r_T = log($constant_1 / $dd_r_T, 10); // Taupunkt
		// $sdd_TD = getSDD($td_r_T, $constant_1, $constant_a_dewPoint, $constant_b_dewPoint); // Saettigungsdampfdruck am Taupunkt
		// $r_T_TD = 100 * $sdd_TD / $sdd_T; // rel. Luftfeuchtigkeit am Taupunkt
		$af_r_TK = pow(10, 5) * ($molecularWeightOfSteam / $universalGasConstant) * ($dd_r_T / ($temperature + 273.15));
		return $af_r_TK;
	}
?>