<?
	include_once "IPSLogger.ips.php";
	include_once "IPSInstaller.ips.php";
	
	define('SMPT_MailId', 56262);
	
	$value = $IPS_VALUE;
	if($value > 10.0) {
		// TODO: check if the last warning was at least 2 hours ago
		SMTP_SendMail(SMPT_MailId, "K�hlschrank �ber 10�C", "Der K�hlschrank hat ".round($value, 1)."�C!");
	}
	
?>