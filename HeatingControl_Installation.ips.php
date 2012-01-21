<?
	include_once "IPSLogger.ips.php";
	include_once "IPSInstaller.ips.php";

	echo "--- Installing HeatingControl ----------------------------------------------------------\n";
	
	$RoomsPath = "ROOMS";
	$ProgramPath = "Program.TemperatureControl";
	
	echo "--- Creating Categories ----------------------------------------------------------\n";
	$CategoryIdRoot		= CreateCategoryPath($ProgramPath);
	$CategoryIdControl	= CreateCategory('Control', $CategoryIdRoot, 10);
	$CategoryIdState	= CreateCategory('State', $CategoryIdRoot, 20);
	$CategoryIdRooms	= CreateCategoryPath($RoomsPath);
	
	echo "--- Adding Scripts ------------------------------------------------------------------------ \n";
	IPS_SetParent($IPS_SELF, $CategoryIdControl);
	$ScriptIdPresence = CreateScript('PresenceSwitcher',  'HeatingControl_PresenceSwitch.ips.php', $CategoryIdControl, 11);
	$ScriptIdIgnoreWindow = CreateScript('IgnoreWindow',  'HeatingControl_IgnoreWindow.ips.php', $CategoryIdControl, 12);
	SetVariableConstant("s_ID_IgnoreWindow", $ScriptIdIgnoreWindow, "HeatingControl\Homematic_Constants.ips.php");
	
	echo "--- Adding presence control ----------------------------------------------------------\n";
	$ControlIdPresence  = CreateVariable("Presence",  0 /*Boolean*/, $CategoryIdRooms, 10, 'Presence_Editable', $ScriptIdPresence, null, 'Motion');
	
	echo "--- Register Variable Constants --------------------------------------------------------\n";
	SetVariableConstant("c_ID_state", $CategoryIdState, "HeatingControl\Homematic_Constants.ips.php");
	SetVariableConstant("c_ID_rooms", $CategoryIdRooms, "HeatingControl\Homematic_Constants.ips.php");
	SetVariableConstant("c_ID_presence", $ControlIdPresence, "HeatingControl\Homematic_Constants.ips.php");
	
	echo "--- Installation successfully finished !!! ----------------------------------------------\n";

?>