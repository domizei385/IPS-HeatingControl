<?

$path = dirname(__FILE__) . '/../';
include_once($path . 'IPSLogger.ips.php');
include_once "HeatingControl_RoomStateVariable.ips.php";

class RoomState {
	
	private $previousWindowState;
	// timestamp, when this variable was last changed by ourselves
	private $lastUpdate;
	// timestamp, when the setting was last changed by homematic e.g. manually changing the setting on the control unit
	private $lastHMUpdate;
	// timestamp until this script will ignore changing the temperature setting
	private $preserveUntil;
	
	private $lastTargetTemperature;
	//private $lastAutoTemperature;
	//private $overrideUntil;
	
	public function __construct($parentCategoryId) {
		if(IPS_CategoryExists($parentCategoryId)) {
			$this->previousWindowState = new RoomStateVariable("PREV_WINDOW_STATE", $parentCategoryId, 0);
			$this->lastUpdate = new RoomStateVariable("LAST_UPDATE", $parentCategoryId);
			$this->lastHMUpdate = new RoomStateVariable("LAST_HM_UPDATE", $parentCategoryId);
			$this->preserveUntil = new RoomStateVariable("PRESERVE_UNTIL", $parentCategoryId);
			$this->lastTargetTemperature = new RoomStateVariable("LAST_TARGET_TEMPERATURE", $parentCategoryId, 2);
			//$this->lastAutoTemperature = new RoomStateVariable("LAST_AUTO_TEMPERATURE", $parentCategoryId, 2);
			//$this->overrideUntil = new RoomStateVariable("OVERRIDE_UNTIL", $parentCategoryId);
		} else {
			IPSLogger_Err(__file__, "ID $parentCategoryId is not a category");
		}
	}
	
	public function __get($name) {
		if(isset($this->$name)) {
			return $this->$name;
		} else {
			throw new Exception("Variable or Handler for $name does not exist.",$name);
		}
	}
	
	public function __set($name,$value) {
		echo "Setting variables directly is not supported";
		//$this->$name = $value;
	}
	
	public function resetPreserveUntil() {
		$this->preserveUntil->value = 0;
	}
	
	public function isPreserveSet() {
		return $this->preserveUntil->value > 0;
	}
}

?>