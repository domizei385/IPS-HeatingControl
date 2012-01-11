<?

include_once "IPSLogger.ips.php";
include_once "Homematic_AbstractDevice.ips.php";
include_once "ITemperatureControl.ips.php";

class HomematicClimateControl extends AbstractHomematicDevice implements ITemperatureControl {
    private $targetTempReadId;
    
    public function setTargetTemperatureReadId($id) {
        $this->targetTempReadId = $id;
    }

    public function getTargetTemperatureReadId() {
        return $this->targetTempReadId;
    }
    
    public function setTargetTemperature($newTemp) {
        $targetTemp = $this->getTargetTemperature();
        if($targetTemp == $newTemp) {
            return;
        }
        
        $result = HM_WriteValueFloat($this->getId(), hm_SETPOINT, $newTemp);
        IPSLogger_Com(__file__, $this->getLocation().": Setting temperature to ".$newTemp);
        return $result;
    }
    
    public function getTargetTemperature() {
        return GetValueFloat($this->targetTempReadId);
    }
}

?>