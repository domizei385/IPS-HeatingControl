<?

$path = dirname(__FILE__) . '/../';
include_once($path . 'IPSLogger.ips.php');

class RoomStateVariable {
	private $variableName;
	private $variableId;
	
	public function __construct($variableIdentifier, $parentCategory = null, $variableType = 1) {
		$categoryMissing = $parentCategory == null || !IPS_CategoryExists($parentCategory);
		$variableIsNumeric = is_numeric($variableIdentifier);
		$variableMissing = $variableIsNumeric && !IPS_VariableExists($variableIdentifier);
		
		if(is_null($variableType) || !is_numeric($variableType) || $variableType > 5 || $variableType < 0) {
			IPSLogger_Err(__file__, "Invalid variable type $variableType. Variable type needs to be a value from 0 - 5. ");
			return;
		}
		
		if(($categoryMissing && $variableIsNumeric && $variableMissing) || (!$variableIsNumeric && strlen($variableIdentifier) == 0)) {
			IPSLogger_Err(__file__, "When variableIdentifier is not a valid variable ID, you need to supply a variableName and a valid parentCategory id");
			return;
		}
		
		if($variableIsNumeric) {
			$this->variableId = $variableIdentifier;
			$this->variableName = IPS_GetName($variableIdentifier);
		} else {
			$this->variableName = $variableIdentifier;
			$this->variableId = CreateVariable($variableIdentifier, $variableType, $parentCategory, 0, '', '', null, '');
		}
	}
	
	private function getValue() {
		return GetValue($this->variableId);
	}
	
	public function __get($name) {
		if(isset($this->$name)) {
			return $this->$name;
		} else {
			if($name == "value") {
				return $this->getValue();
			} else {
				throw new Exception("Variable or Handler for $name does not exist.",$name);
			}
		}
	}
	
	private function setValue($value) {
		return SetValue($this->variableId, $value);
	}
	
	public function __set($name,$value) {
		if($name == "value") {
			$this->setValue($value);
		} else {
			throw new Exception("Variable or Handler for $name does not exist.",$name);
		}
	}
	
	public function getVariableMetadata() {
		return IPS_GetVariable($this->variableId);
	}
	
	public function getVariableId() {
		return $this->variableId;
	}
	
	public function getVariableName() {
		return $this->variableName;
	}
}

?>