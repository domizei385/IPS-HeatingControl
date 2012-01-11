<?
	include_once "IPSLogger.ips.php";
	
	if ($IPS_SENDER == "WebFront") {
		// IPSLogger_Dbg(__file__, $IPS_VARIABLE);
		$cValue = GetValueBoolean($IPS_VARIABLE);
		SetValue($IPS_VARIABLE, !$cValue);
	}
?>