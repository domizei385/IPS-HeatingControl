<?
	function getIds() {
		// TODO: validate if ids exist
	   return array(
			r_BATH => array(
				o_WINDOW				=> array(
					v_STATE				=> array(29529),
				),
				o_TEMP_CONTROL			=> array(
					v_SET				=> 19384,
					v_GET				=> 14956,
				),
				o_CLIMATE				=> array(
					v_GET_TEMPERATURE	=> 45852,
					v_GET_HUMIDITY		=> 27611,
				),
				o_VALVES				=> array(
					41751
				),
			),
			// -----
			r_SLEEP => array(
				o_WINDOW				=> array(
					v_STATE				=> array(52728),
					v_OVERRIDE			=> 44349,
				),
				o_TEMP_CONTROL			=> array(
					v_SET				=> 49372,
					v_GET				=> 18241,
				),
				o_CLIMATE				=> array(
					v_GET_TEMPERATURE	=> 29933,
					v_GET_HUMIDITY		=> 42707,
				),
				o_VALVES				=> array(
					14111
				),
			),
			// -----
			r_KITCHEN => array(
				o_WINDOW				=> array(
					v_STATE				=> array(42269),
				),
				o_TEMP_CONTROL			=> array(
					v_SET				=> 19257,
					v_GET				=> 52264,
				),
				o_CLIMATE				=> array(
					v_GET_TEMPERATURE	=> 42173,
					v_GET_HUMIDITY		=> 52164,
				),
				o_VALVES				=> array(
					47189
				),
			),
			// -----
			r_LIVING => array(
				o_WINDOW				=> array(
					v_STATE				=> array(36247),
				),
				o_TEMP_CONTROL			=> array(
					v_SET				=> 56712,
					v_GET				=> 49652,
				),
				o_CLIMATE				=> array(
					v_GET_TEMPERATURE	=> 58331,
					v_GET_HUMIDITY		=> 47659,
				),
				o_VALVES				=> array(
					47713, 46637
				),
			),
		);
	}
	
	function getConfig() {
		// TODO: validation
		// t_REFERENCE => use temp definition of another day
		// undefined days use configuration of previous day to keep the configuration lightweight
		return array(
			r_BATH => array(
				t_WINDOW_OPEN	=> 12.0,
				t_AWAY			=> 18,
				t_DAYS			=> array(
					d_MONDAY		=> array(
						0	=> array(
							tp_START		=> "00:00",
							tp_TARGET		=> 21.0
						),
						1	=> array(
							tp_START		=> "05:00",
							tp_TARGET		=> 22.0
						),
						2	=> array(
							tp_START		=> "07:00",
							tp_TARGET		=> 21.0
						),
						3	=> array(
							tp_START		=> "19:00",
							tp_TARGET		=> 22.0
						),
						4	=> array(
							tp_START		=> "23:00",
							tp_TARGET		=> 21.0
						),
					),
				),
				t_HUMIDITY => array(
					t_HUMIDITY_INC_START_HOUR	=> "00:00",
					t_HUMIDITY_INC_END_HOUR		=> "23:59",
					t_HUMIDITY_VALUES => array(
						0	=> array(
							x_HUMIDITIY_MIN		=> 70,
							x_HUMIDITY_TEMP_INC	=> 1.5
						),
						1	=> array(
							x_HUMIDITIY_MIN		=> 65,
							x_HUMIDITY_TEMP_INC	=> 1
						),
						2	=> array(
							x_HUMIDITIY_MIN		=> 60,
							x_HUMIDITY_TEMP_INC	=> 0.5
						),
					),
				),
			),
			// -----------
			r_SLEEP => array(
				t_WINDOW_OPEN	=> 12.0,
				t_AWAY			=> 16,
				t_DAYS			=> array(
					d_MONDAY		=> array(
						0	=> array(
							tp_START	=> "00:00",
							tp_TARGET	=> 18.0
						),
						1	=> array(
							tp_START	=> "04:45",
							tp_TARGET	=> 20.5
						),
						2	=> array(
							tp_START	=> "06:30",
							tp_TARGET	=> 19.0
						),
						3	=> array(
							tp_START	=> "20:30",
							tp_TARGET	=> 21.5
						),
						4	=> array(
							tp_START	=> "21:30",
							tp_TARGET	=> 18.0
						),
					),
					d_SATURDAY		=> array(
						0	=> array(
							tp_START	=> "00:00",
							tp_TARGET	=> 18.0
						),
						1	=> array(
							tp_START	=> "05:00",
							tp_TARGET	=> 20.5
						),
						2	=> array(
							tp_START	=> "08:45",
							tp_TARGET	=> 18.0
						),
						3	=> array(
							tp_START	=> "22:00",
							tp_TARGET	=> 21.5
						),
						4	=> array(
							tp_START	=> "23:30",
							tp_TARGET	=> 18.0
						),
					),
					d_SUNDAY		=> array(
						0	=> array(
							tp_START	=> "00:00",
							tp_TARGET	=> 18.0
						),
						1	=> array(
							tp_START	=> "07:00",
							tp_TARGET	=> 21.0
						),
						2	=> array(
							tp_START	=> "09:30",
							tp_TARGET	=> 19,
						),
						3	=> array(
							tp_START	=> "20:30",
							tp_TARGET	=> 21.5
						),
						4	=> array(
							tp_START	=> "21:30",
							tp_TARGET	=> 18.0
						),
					),
				),
			),
			// -----------
			r_KITCHEN => array(
				t_WINDOW_OPEN	=> 12.0,
				t_AWAY			=> 16,
				t_DAYS			=> array(
					d_MONDAY		=> array(
						0	=> array(
							tp_START	=> "00:00",
							tp_TARGET	=> 18
						),
						1	=> array(
							tp_START	=> "05:45",
							tp_TARGET	=> 21.0
						),
						2	=> array(
							tp_START	=> "09:00",
							tp_TARGET	=> 19.0
						),
						3	=> array(
							tp_START	=> "14:00",
							tp_TARGET	=> 21.0
						),
						4	=> array(
							tp_START	=> "20:30",
							tp_TARGET	=> 18.0
						),
					),
					d_SATURDAY		=> array(
						0	=> array(
							tp_START	=> "00:00",
							tp_TARGET	=> 18.0
						),
						1	=> array(
							tp_START	=> "06:00",
							tp_TARGET	=> 21.0
						),
						3	=> array(
							tp_START	=> "21:00",
							tp_TARGET	=> 18.0
						),
					),
					d_SUNDAY		=> array(
						0	=> array(
							tp_START	=> "00:00",
							tp_TARGET	=> 18.0
						),
						1	=> array(
							tp_START	=> "08:45",
							tp_TARGET	=> 21.0
						),
						3	=> array(
							tp_START	=> "20:00",
							tp_TARGET	=> 18.0
						),
					),
				),
			),
			// -----------
			r_LIVING => array(
				t_WINDOW_OPEN	=> 12.0,
				t_AWAY			=> 17,
				t_DAYS			=> array(
					d_MONDAY		=> array(
						0	=> array(
							tp_START	=> "00:00",
							tp_TARGET	=> 18
						),
						1	=> array(
							tp_START	=> "05:00",
							tp_TARGET	=> 22.0
						),
						2	=> array(
							tp_START	=> "21:30",
							tp_TARGET	=> 18.0
						),
					),
					d_SATURDAY		=> array(
						0	=> array(
							tp_START	=> "00:00",
							tp_TARGET	=> 18.0
						),
						1	=> array(
							tp_START	=> "05:00",
							tp_TARGET	=> 22.0
						),
						3	=> array(
							tp_START	=> "23:00",
							tp_TARGET	=> 18.0
						),
					),
					d_SUNDAY		=> array(
						0	=> array(
							tp_START	=> "00:00",
							tp_TARGET	=> 18.0
						),
						1	=> array(
							tp_START	=> "08:30",
							tp_TARGET	=> 22.0
						),
						3	=> array(
							tp_START	=> "21:30",
							tp_TARGET	=> 18.0
						),
					),
				),
			),
		);
	}
?>