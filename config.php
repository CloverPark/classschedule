<?php
	/**
	 * Swap names here in for format of:
	 *		"OLD-NAME" => "New-Name",
	 *   
	 *	Note: Make sure there's a comma at the end of the line
	 *	Note: OLD-NAME must be all caps (must match what is in the SMS)
	 */
	 $name_swap = array(
		//Add names bellow:
		"ROSE-PENNISI" => "Rose-Pennisi, T.",
		"LOVELESS-MOR" => "Loveless-Morris, J.",
		"HOLLAND-OHER" => "Holland-O'Hern, C.",
		"CALLAHAN-MCC" => "Callahan-McCain, T.",
		"CHASE-DEITRI" => "Chase-Deitrich, D.",
		"KORSCHINOWSK" => "Korschinowski, C.",
		"LINGENFELTER" => "Lingenfelter, R.",
		"FELCH" => "Felch, L.",
		"LEWANDOWSKI" => "Carson-Lewandowski, D.",
		"SWORD" => "Sword, Y.",
		"WESTBERRY" => "Westberry, C.",
		"BAHRT" => "Bahrt, D.",
		"LAZARUS" => "Lazarus, B.",
		"CONDON" => "Condon, J.",
		"COOPER" => "Cooper, L.",
		"HERNANDEZ" => "Hernandez, K.",
		"MUSSON" => "Musson",
		"ANDERSON" => "Anderson",
		"LARGENT" => "Largent",
		
		//Do not modify bellow:
		"EOF" => "EOF"
	 );
	 
	 /**
	 * Force item numbers to different admin_units:
	 *		"item #" => "admin_unit",
	 *
	 *	For example:
	 *	
	 *		"0523" => "4",
	 *   
	 *	Note: Make sure there's a comma at the end of the line
	 */
	 $force_item  = array(
		//Add classes below:
		/* 904: IBEST NAC; 903: IBEST Chemical Dependency; 902: IBEST CAD */
		"6801" => "-1",
		"68AA" => "-1",
		"6892" => "-1",
		"5701" => "-1",
		"5702" => "-1",
		"631R" => "902",
		"631C" => "902",
		"631T" => "902",
		"631M" => "902",
		"632N" => "902",
		"632A" => "902",
		"632P" => "902",
		"632D" => "902",
		"632Y" => "902",
		"632B" => "902",
	
		
		
		"241H" => "903",
		"241V" => "903",
		"241B" => "903",
		"241G" => "903",
		"242L" => "903",
		"242C" => "903",
		"242R" => "903",		
		"242D" => "903",
		"242P" => "903",
		
		
		
		"NS1F" => "904",
		"NS1C" => "904",
		"NS1D" => "904",
		
		"NS2N" => "904",
		"NS2T" => "904",
		
		"24A1" => "24",
		"24C1" => "24",
		"24B1" => "24",
		"09F1" => "-1", // cancelled
		"09G1" => "-1", // cancelled
		"09H1" => "-1", // cancelled	
		"5W06" => "-1", // cancelled
		"0522" => "-1", // cancelled		
		"5W33" => "-1", // cancelled	
		"0507" => "-1", // cancelled	
		"05W8" => "-1", // cancelled	
		"5W26" => "-1", // cancelled	
		"3031" => "-1", // cancelled
		"54G1" => "-1", // cancelled		
		"2011" => "-1", // cancelled	
		"0552" => "-1", // cancelled
		"0553" => "-1", // cancelled
		"0554" => "-1", // cancelled	
		"0555" => "-1", // cancelled
		"0549" => "-1", // cancelled
		"51AA" => "-1", // cancelled
		"51AB" => "-1", // cancelled
		"51AC" => "-1", // cancelled
		"51AD" => "-1", // cancelled
		"51AF" => "-1", // cancelled
		"51AG" => "-1", // cancelled
		"51AH" => "-1", // cancelled
		"51AJ" => "-1", // cancelled
		
		//Do not modify bellow:
		"EOF" => "EOF"
	 );
?>