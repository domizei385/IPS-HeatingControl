<?

	// controls
	define("r_BATH",		"BATH");
	define("r_KITCHEN",		"KITCHEN");
	define("r_LIVING",		"LIVING");
	define("r_SLEEP",		"SLEEP");
	
	// watches
	define("w_FRIDGE",		"FRIDGE");
	
	// objects
	define("o_TYPE",			"TYPE");
	define("o_WINDOW",  		"WINDOW");
	define("o_TEMP_CONTROL",	"TEMP_CONTROL");
	define("o_CLIMATE",			"CLIMATE");
	define("o_VALVES",			"VALVES");
	
	// types
	define("tp_CONTROL",		"TYPE_CONTROL");
	define("tp_WATCH",			"TYPE_WATCH");
	
	// vars
	define("t_REFERENCE",  		"REFERENCE");
	define("t_OFFSET",  		"TEMP_OFFSET");
	define("t_WINDOW_OPEN",		"WINDOW_OPEN_TEMP");
	define("t_DAYS",				"TEMP_BY_DAYS");
	define("t_AWAY",				"TEMP_WHEN_AWAY");
	define("tp_START",			"TEMP_START_PERIOD");
	define("tp_TARGET",			"TEMP_TARGET");
	define("v_GET",				"GETTER_ID");
	define("v_SET",				"SETTER_ID");
	define("v_GET_TEMPERATURE",	"TEMPERATURE_ID");
	define("v_GET_HUMIDITY",	"HUMIDITY_ID");
	define("v_STATE",				"STATE");
	define("v_OVERRIDE",			"OVERRIDE");
	
	// humdity settings
	define("x_HUMIDITIY_MIN",	"HUMIDITY_MIN");
	define("x_HUMIDITY_TEMP_INC",	"HUMIDITY_TEMP_INC");
	define("t_HUMIDITY", "HUMIDITY_EVALUATION");
	define("t_HUMIDITY_INC_START_HOUR", "HUMIDITY_START_HOUR");
	define("t_HUMIDITY_INC_END_HOUR", "HUMIDITY_END_HOUR");
	define("t_HUMIDITY_VALUES", "HUMIDITY_VALUES");
	
	// days
	define("d_MONDAY",			"MONDAY");
	define("d_TUESDAY",			"TUESDAY");
	define("d_WEDNESDAY",		"WEDNESDAY");
	define("d_THURSDAY",		"THURSDAY");
	define("d_FRIDAY",			"FRIDAY");
	define("d_SATURDAY",		"SATURDAY");
	define("d_SUNDAY",			"SUNDAY");

	$day_order = array(d_MONDAY, d_TUESDAY, d_WEDNESDAY, d_THURSDAY, d_FRIDAY, d_SATURDAY, d_SUNDAY);

	define("hm_SETPOINT",		"SETPOINT");
	define("hm_STATE",			"STATE");
	
	// category names
	define("ct_CLIMATE",		"Klima");
	
	define("lang_HEATING_CONTROL",		"Heizungssteuerung");
	define("lang_ABS_HUMIDITY",			"abs. Feuchtigkeit");
	define("lang_WINDOW",				"Fenster");
	define("lang_PRESERVE_VALUE_SET",	"Automatik: %.1f°C. Manuell überschrieben bis ");
	define("lang_TARGET_TEMPERATURE",	"Zieltemperatur");
	define("lang_OVERRIDE_OPEN_WINDOW",	"Offenes Fenster ignorieren");
	
	define("ident_PRESERVE_VALUE_SET",			"PRESERVE_VALUE_UNTIL");
	
	// semi variables
	define("c_ID_presence",		13075);
	define("c_ID_state",		41419);
	define("c_ID_rooms",		14951);
	define("s_ID_IgnoreWindow",	13326);
	
?>