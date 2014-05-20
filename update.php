<?php
	/************************
	 * update.php
	 * This file is used to update the MySQL database with the most current information from the SMS database via JSON through a web service.
	 * This file should be ran on 30 minute intervals (as the SMS database only updates every 30 minutes).
	 * Each update will check for course data for the next x amount of quarters (set below in the variable $look_at).
	 * Each quarter checked will delete all current quarter data, then insert the current data. This way, deleted courses will be removed
	 *	and every case will be handled, where certain cases could be missed via update instead.
	 * In addition to deleted course removed, each update will update course enrollment information.
	 * NOTE: Enrollment information may be up to an hour delayed, so in certain cases, a class may show enrollment room on here, but be full when
	 *	registration is attempted.
	 *
	 *************************/
	 
	//error reporting for dev stage
	ini_set('display_errors', 'On');
	error_reporting(E_ALL);
	
	//start timer
	$time_start = microtime(true);
	
	//default isn't set
	date_default_timezone_set('America/Los_Angeles');
	
	//get config file
	require('config.php');
	
	//GLOBALS
	$client = new SoapClient("https://tredstone.cptc.edu/CourseData/CPTC_Courses.asmx?wsdl");
	$dbh = null;
	//how many quarters to look ahead for
	$look_at = 4;
	//when finding new quarters, this checks for YRQ every x ammount of days. If a quarter isn't found, try a smaller number
	$daysSkipped = 15;
	
	//attempt to connect to DB
	try {
		$dbh = new PDO('mysql:host=localhost;dbname=cptcclasses;charset=utf8', 'classes', 'CKnpF62v5qA3L9aZ');
	} catch(PDOException $e) {
		echo "Access denied: " . $e->getMessage();
	}
	
	getQuarters('140115');
	
	//close database and web service connections
	$dbh = null;
	$client = null;
	
	//print time taken
	$time_end = microtime(true);
	$time = $time_end - $time_start;
	echo  "<br>Update complete in: " . round($time,2) . " s";
	
	//     *************************
	//     ******* FUNCTIONS *******
	//     *************************
	
	/**
	 * Gets json feed from web service, truncates database, then inserts new data into database
	 */
	function getQuarters($date) {
		global $look_at, $daysSkipped;
		if(isset($date)) {
			$y = substr($date, 0, 2);
			$m = substr($date, 2, 2);
			$d = substr($date, 4, 2);
			
			$yrqs = array();
			$names = array();
			//find the next quarters as specified by $look_at
			while(count($yrqs) < $look_at) {
				$yrq = getYRQ($y . $m . $d);
				//echo $y . $m . $d . ' got: ' . $yrq . '<br>';
				//if it's the first YRQ found or a new one from the last found, add it to the list
				if(strlen($yrq) > 3 && (count($yrqs) == 0 || $yrqs[count($yrqs) - 1] != $yrq)) {
					$yrqs[] = $yrq;
					$names[] = getQtr($yrq);
				}
				//update date to check the next week
				$d += $daysSkipped;
				if($d > 28) {
					$m++;
					$d = 0;
					if($m > 12) {
						$m = 0;
						$y++;
					}
				}
				//add zeros to numbers if single digits
				if($d < 10) {
					$d = '0' . $d;
				}
				if(strlen($d) > 2) {
					$d = substr($d, -2);
				}
				if($m < 10) {
					$m = '0' . $m;
				}
				if(strlen($m) > 2) {
					$m = substr($m, -2);
				}
			}
			processYrqs($yrqs, $names);
		}
	}
	
	/**
	 * Given a date in YYMMDD form, returns the YRQ for the following quarter
	 * Returns YRQ for next quarter
	 */
	function getYRQ($date) {
		global $client;
		$yrq = $client -> __soapCall("GetYRQ", array(array("now" => $date))) -> GetYRQResult;
		return $yrq;
	}
	
	/**
	 * Converts a YRQ (ie 'B452'), to a quarter name (ie 'Spring 2014')
	 * Returns quarter name
	 */
	function getQtr($yrq) {
		global $client;
		$qtr = $client -> __soapCall("GetQtrNameFromYRQ", array(array("yrq" => $yrq))) -> GetQtrNameFromYRQResult;
		//format quarter titles
		$qtr = str_replace('SPRNG', 'Spring', $qtr);
		$qtr = str_replace('SUMMR', 'Summer', $qtr);
		$qtr = str_replace('FALL', 'Fall', $qtr);
		$qtr = str_replace('WINTR', 'Winter', $qtr);
		//return quarter name
		return trim($qtr);
	}
	
	/**
	 * Given two arrays with quarters (YRQ and quarter name), processes each yrq to check for needed updates
	 */
	function processYrqs($yrqs, $names) {
		global $dbh;
		//get list of courses needed to force admin_unit (as set by xml)
		$force = getForced();
		for($i = 0; $i < count($yrqs); $i++) {
			$active = updateCourses($yrqs[$i], $force);
			$time = getTimeStamp($names[$i]);
			$stmt = $dbh -> prepare("INSERT quarters (yrq, name, time_stamp, active) VALUES (:yrq, :name, :time_stamp, :active)
										ON DUPLICATE KEY UPDATE active = :active");
			$stmt->bindParam(':yrq', $yrqs[$i]);
			$stmt->bindParam(':name', $names[$i]);
			$stmt->bindParam(':time_stamp', $time);
			$stmt->bindParam(':active', $active);
			$stmt->execute();
		}
		
	}
	
	/*
	 * Given a quarter name (Spring 2014) format, generates a timeStamp
	 * Returns timestamp of quarter
	 */
	function getTimeStamp($quarter) {
		//get year value and space by 4 (since there are 4 quarters
		$time = intval(substr($quarter, -4)) * 4;
		//add one for each quarter into the year it is (ie, summer is 3rd, so add 2)
		switch(substr($quarter, 0, strpos($quarter, ' '))) {
			case 'Fall':
				$time++;
			case 'Summer':
				$time++;
			case 'Spring':
				$time++;
			default:
				return $time;
		}
	}
	
	/**
	 * Gets json feed from web service, truncates database, then inserts new data into database
	 * Returns 1 if courses were found for the YRQ, or 0 if not
	 */
	function updateCourses($yrq, $force) {
		global $dbh;
		global $client;
		//in case script needs to break
		$go = true;
		//error reporting for dev stage
		ini_set('display_errors', 'On');
		error_reporting(E_ALL);

		//build call to webservice to get json feed
		$params = array(
			"yrq" => $yrq,
		);
		$response = $client->__soapCall("GetClassDataYRQ", array($params));

		//create json string
		$json = json_decode($response->GetClassDataYRQResult);

		//empty old database
		$stmt = $dbh->prepare('DELETE FROM courses WHERE yrq = \'' . $yrq . '\'');
		$stmt->execute();
		
		//insert each item into the database
		foreach($json as $item) {
			insertViaJSON($item, $force);
		}
		
		if(count($json) > 5) {
			return 1;
		} else {
			return 0;
		}
	}
	
	/**
	 * Given an item of json, inserts data into database
	 * $item = row of json
	 * $dbh = opened connection to database
	 */
	function insertViaJSON($item, $force) {
		global $dbh, $force_item;
		$sql = "INSERT INTO courses (admin_unit, class_cap, class_id, course_id, course_title, cr, day_cd, end_date, end_time, enr, instr_name, org_indx, prg_indx, room_loc, sect, sect_stat, strt_date, strt_time, yrq, start_24, end_24, mode)
			VALUES (:admin_unit, :class_cap, :class_id, :course_id, :course_title, :cr, :day_cd, :end_date, :end_time, :enr, :instr_name, :org_indx, :prg_indx, :room_loc, :sect, :sect_stat, :strt_date, :strt_time, :yrq, :start_24, :end_24, :mode)";
		
		//format all the data
		formatItems($item);
		
		//if forced admin_unit set, change to that admin_unit
		if(isset($force[trim($item->COURSE_ID)]) && $force[trim($item->COURSE_ID)] != -2) {
			$item->ADMIN_UNIT = $force[trim($item->COURSE_ID)];
			//echo "Forced '" . trim($item->COURSE_ID) . "' to admin_unit: " . $force[trim($item->COURSE_ID)] . "<br>";
		}
		
		//force admin_unit based on item number (from config.php)
		if(isset($force_item[$item->CLASS_ID])) {
			$item->ADMIN_UNIT = $force_item[$item->CLASS_ID];
		}
		
		//insert if admin_unit isn't -1. -1 means unwanted course.
		if($item->ADMIN_UNIT != -1) {
			$stmt = $dbh->prepare($sql);
			bindParams($stmt, $item);
			$stmt->execute();
		}
	}
	
	/**
	 * Get and return a list of courses that have a forced admin_unit set via XML.
	 * Forced courses are pulled from course_description table and are rows where force_admin != -2
	 */
	function getForced() {
		global $dbh;
		$forced = array();
		$sql = "SELECT cnumber, force_admin FROM course_description WHERE force_admin != -2";
		$stmt = $dbh->prepare($sql);
		$stmt->execute();
		while ($row = $stmt->fetchObject()) {
			$forced[$row->cnumber] = $row->force_admin;
		}
		return $forced;
	}
	
	/**
	 * Formats SMS data into usable style for CPTC.
	 */
	function formatItems($item) {
		//get timestamp before time is formatted.
		$item->END_24 = timeTo24($item->END_TIME);
		$item->START_24 = timeTo24($item->STRT_TIME);
		//done after time used.
		mergeAdminUnit($item);
		$item->CLASS_ID = formatClassID($item->CLASS_ID);
		$item->COURSE_ID = formatCourseID($item->COURSE_ID);
		$item->COURSE_TITLE = formatCourseTitle($item->COURSE_TITLE);
		$item->CR = formatCredits($item->CR);
		$item->END_TIME = formatTime($item->END_TIME);
		$item->INSTR_NAME = formatName($item->INSTR_NAME);
		$item->MODE = findMode($item->ROOM_LOC);
		$item->ROOM_LOC = formatLocation($item->ROOM_LOC);
		$item->SECT_STAT = trim($item->SECT_STAT);
		$item->STRT_TIME = formatTime($item->STRT_TIME);
		//remove unwanted courses
		filterCourses($item);
	}
	
	/**
	 * Will bind all the parameters to their respective values.
	 * Call this after data has been formatted.
	 */
	 function bindParams($stmt, $item) {
		$stmt->bindParam(':admin_unit', $item->ADMIN_UNIT);
		$stmt->bindParam(':class_cap', $item->CLASS_CAP);
		$stmt->bindParam(':class_id', $item->CLASS_ID);
		$stmt->bindParam(':course_id', $item->COURSE_ID);
		$stmt->bindParam(':course_title', $item->COURSE_TITLE);
		$stmt->bindParam(':cr', $item->CR);
		$stmt->bindParam(':day_cd', $item->DAY_CD);
		$stmt->bindParam(':end_date', $item->END_DATE);
		$stmt->bindParam(':end_time', $item->END_TIME);
		$stmt->bindParam(':enr', $item->ENR);
		$stmt->bindParam(':instr_name', $item->INSTR_NAME);
		$stmt->bindParam(':org_indx', $item->ORG_INDX);
		$stmt->bindParam(':prg_indx', $item->PRG_INDX);
		$stmt->bindParam(':room_loc', $item->ROOM_LOC);
		$stmt->bindParam(':sect', $item->SECT);
		$stmt->bindParam(':sect_stat', $item->SECT_STAT);
		$stmt->bindParam(':strt_date', $item->STRT_DATE);
		$stmt->bindParam(':strt_time', $item->STRT_TIME);
		$stmt->bindParam(':yrq', $item->YRQ);
		$stmt->bindParam(':start_24', $item->START_24);
		$stmt->bindParam(':end_24', $item->END_24);
		$stmt->bindParam(':mode', $item->MODE);
	 }
	
	/**
	 * Takes a string in form of 0720P and returns 24 hour representation (ie 0720P becomes 1920)
	 */
	function timeTo24($str) {
		$str = trim($str);
		//if string not in correct format, return -1
		//this is the case for online classes or ARR times
		if(strlen($str) < 5) {
			return -1;
		}
		//get int out of string
		$nums = intval(substr($str, 0, 4));
		//check if AM or PM, since PM needs to add 1200 to time (unless 12PM)
		if(strpos($str, "A") !== false) {
			//if AM
			if(($nums / 100) == 12) {
				$nums += 1200;
			}
		} else {
			//if PM
			$nums += 1200;
			if(($nums / 100) >= 24) {
				$nums -= 1200;
			}
		}
		//return final value
		return $nums;
	}
	
	/**
	 * Returns the class mode number based on the room location.
	 * 1 = Lakewood
	 * 2 = South Hill
	 * 3 = Online
	 * 4 = Arranged
	 * 5 = Off Campus
	 */
	function findMode($str) {
		$mode;
		if(strpos($str, "LINE") !== false) {
			//class is online: (3)
			$mode = 3;
		} else if(strpos($str, "SHC") !== false) {
			//class is at South Hill (2)
			$mode = 2;
		} else if(strpos($str, "ARR") !== false) {
			//class is listed as Arranged Campus(4)
			$mode = 4;
		} else if(strpos($str, "OFFCAMP") !== false) {
			//class is listed as Off Campus(5)
			$mode = 5;
		} else {
			//class is at Lakewood (by default) (1)
			$mode = 1;
		}
		return $mode;
	}
	
	/**
	 * Merges select programs into a single admin ID.
	 * Merges Daycare Coordinators (13) with Early Care & Education (41)
	 * Merges Computer Application (20) with General Education (5)
	 * Merges RN Option (25) with Nursing (80)
	 * Merges Cosmotology-Purdy (52) with Cosmotology (53)
	 * Merges Medical Esthetics (72) with Esthetic Sciences (62)
	 * Merges Dental Assistant (77) with Dental (4)
	 * Merges COLL 101 (2) with General Ed (5), removes other courses in 2
	 */
	function mergeAdminUnit($item) {
		switch($item->ADMIN_UNIT) {
			case 13:
				$item->ADMIN_UNIT = 41;
				break;
			case 20:
				$item->ADMIN_UNIT = 5;
				break;
			case 25:
				$item->ADMIN_UNIT = 80;
				break;
			case 52:
				$item->ADMIN_UNIT = 53;
				break;
			case 72:
				$item->ADMIN_UNIT = 62;
				break;
			case 77:
				$item->ADMIN_UNIT = 4;
				break;
			case 2:
				//admin_unit 2 contains coll 101 (which goes with gened) and junk courses
				//remove junk courses by setting admin_unit to -1
				if(strpos(formatCourseID($item->COURSE_ID), "COLL 101") !== false) {
					$item->ADMIN_UNIT = 5;
				} else {
					$item->ADMIN_UNIT = -1;
				}
				break;
		}
	}
	
	/**
	 * Removes following unwanted courses from the list...
	 * Removes all ADHS courses.
	 * Removes MDP 212, MDP 210, MDP 231 and MDP 239, since program is being phased out.
	 * Removes anything with admin_unit of 0 (misc classes)
	 */
	function filterCourses($item) {
		if(strpos($item->COURSE_ID, "ADHS") !== false){
			$item->ADMIN_UNIT = -1;
		} else if(strpos($item->COURSE_ID, "MDP") !== false) {
			//if MDP found, check for 212, 210, 231 or 239, and remove
			if(strpos($item->COURSE_ID, "212") !== false) {
				$item->ADMIN_UNIT = -1;
			} else if(strpos($item->COURSE_ID, "210") !== false) {
				$item->ADMIN_UNIT = -1;
			} else if(strpos($item->COURSE_ID, "231") !== false) {
				$item->ADMIN_UNIT = -1;
			} else if(strpos($item->COURSE_ID, "239") !== false) {
				$item->ADMIN_UNIT = -1;
			}
		} else if($item->ADMIN_UNIT == 0) {
			$item->ADMIN_UNIT = -1;
		}
	}
	
	/**
	 * Converts removes extra spaces, leaving, at most, one space. Trims extra spaces from front and back.
	 */
	function formatCourseID($str) {
		while(strpos($str, '  ') !== false) {
			$str = str_replace('  ', ' ', $str);
		}
		return $str;
	}

	/**
	 * Formats time to school standard
	 * School standard is: "5 p.m." or "7:30 a.m."
	 * Stored in database as "0115P" or 
	 * Returns String of date in correct format
	 */
	function formatTime($str) {
		//if ARR or blank, change to "Arranged"
		if(strpos($str, "R") || (!strpos($str, "P") && !strpos($str, "A"))) {
			return "Arranged";
		}
		//get A or P
		$end = $str{4};
		//convert A or P to " a.m." or " p.m."
		if($end == 'P') {
			$end = " p.m.";
		} else if($end == 'A') {
			$end = " a.m.";
		} else {
			$end = "";
		}
		//get hour and minute from original time
		$hr = substr($str, 0, 2);
		$min = substr($str, 2, 2);
		//remove leading zero from hour if needed
		if($hr{0} == '0') {
			$hr = $hr{1};
		}
		//clear minute if double zero, or not, add semicolon to front
		if($min == "00") {
			$min = "";
		} else {
			$min = ":" . $min;
		}
		
		return $hr . $min . $end;
	}

	/**
	 * Converts "ON LINE" to "Online"
	 * Converts "OFFCAMP" to "Off Campus"
	 * Converts "ARR" to "Arranged"
	 * Adds building and room where needed (ie changes "02 234" to "Building 02 Room 234")
	 * Returns formatted string
	 */
	function formatLocation($str) {
		//if at south hill campus
		if(strpos($str, "SHC") !== false) {
			if(strlen(trim($str)) == 3) {
				//if no room info, simply return "South Hill Campus"
				return "South Hill Campus";
			} else {
				//if it contains room info...
				//return "South Hill Campus Room #"
				return "South Hill Campus Room " . substr($str, 3);
			}
		}
		//change "ON LINE" to "Online"
		if(strpos($str, "ON LINE") !== false) {
			return "Online";
		}
		//change "OFFCAMPUS" to "Off Campus"
		if(strpos($str, "OFFCAMP") !== false) {
			return "Off Campus";
		}
		//change "ARR" to "Arranged"
		if(strpos($str, "ARR") !== false) {
			return "Arranged";
		}
		//change "'TBD '" to "TBD"
		if(strpos($str, "TBD") !== false) {
			return "TBD";
		}
		//add the words "Building" and "Room" where needed
		if($str{2} == ' ') {
			$full = "Bldg. " . substr($str, 0, 2);
			//if no room into, leave rm. part off
			if(strlen(trim($str)) > 2) {
				$full .= ", Rm. " . substr($str, 3);
			}
			return $full;
		}
		//default quotes around unhandled cases
		return "'" . $str . "'";
	}

	/**
	 * Formats names.
	 * Checks if space missing after comma, if so, adds space
	 * Caps last and first name
	 * Returns formatted string
	 */
	function formatName($str) {
		global $name_swap;
		$str = trim($str);

		if(isset($name_swap[$str])) {
			return $name_swap[$str];
		} else if(strpos($str, "STAFF") !== false) {
			return "Staff";
		} else if(strpos($str, ",") !== false) {
			$last = substr($str, 0, strpos($str, ","));
			$first = trim(substr($str, strpos($str, ",") + 1));
		} else if(strpos($str, " ") !== false) {
			$last = substr($str, 0, strpos($str, " "));
			$first = trim(substr($str, strpos($str, " ") + 1));
			//if course is Educa To Go...
			if(strpos($first, "TO GO") !== false) {
				return "Arranged";
			}
		} else {
			echo "Conflict for: '" . $str . "' Use:<br>"; 
			echo '"' . $str . '" => "New, Name",<hr>';
			return "Arranged";
		}
		$str = $last . ", " . $first{0} . ".";
		return ucwords(strtolower($str));
	}
	
	/**
	 * Extracts line number from class ID
	 * Class_id stored as: "5474B344" where first 4 is line number and last 4 is quarter YRQ (key for quarter name)
	 */
	function formatClassID($str) {
		if($str && (strlen($str) > 3)) {
			return substr($str, 0, 4);
		} else {
			return "Err";
		}
	}
	
	/**
	 * Credits stored multiplied by 10 (ie a 5 credit class is stored as 50 cr)
	 */
	function formatCredits($int) {
		return $int / 10.0;
	}
	
	/**
	 * Changes from all caps to just caps on first letter of each word
	 * Fixes roman numeral caps (Vii to VII)
	 * Caps first letter after "/" (One/two to One/Two)
	 * Caps first letter after "," if not a space
	 * Returns formated string
	 */
	function formatCourseTitle($str) {
		//make first letter of words upper-case and rest lower-case
		$str = ucwords(strtolower($str));
		//make trailing roman numerals upper-case
		$lower = array("Viii", "Vii", "Vi", "Ix", "Xiii", "Xii", "Xi", "Iv", "Iii", "Ii");
		$upper = array("VIII", "VII", "VI", "IX", "XIII", "XII", "XI", "IV", "III", "II");
		for($i = 0; $i < count($lower); $i++) {
			if(strpos($str, $lower[$i])) {
				$str = substr($str, 0, strpos($str, $lower[$i])) . $upper[$i];
			}
		}

		//make words after a backslash upper-case
		for ($i = 0; $i < strlen($str); $i++)  { 
			$cr = $str{$i};
			if($cr == "/") {
				$str{$i + 1} = strtoupper($str{$i + 1});
			}
		}  
		
		//make words after a comma upper-case
		for ($i = 0; $i < strlen($str); $i++)  { 
			if($str{$i} == ",") {
				if($str{$i + 1} != " ") {
					$str{$i + 1} = strtoupper($str{$i + 1});
				}
			}
		}
		
		//make words after a period (with no space) upper-case
		for ($i = 0; $i < strlen($str); $i++)  { 
			if($str{$i} == ".") {
				if($str{$i + 1} != " ") {
					$str{$i + 1} = strtoupper($str{$i + 1});
				}
			}
		}
		
		//make words after a - upper-case
		for ($i = 0; $i < strlen($str); $i++)  { 
			if($str{$i} == "-") {
				if($str{$i + 1} != " ") {
					$str{$i + 1} = strtoupper($str{$i + 1});
				}
			}
		}
		
		//make words after a : upper-case
		for ($i = 0; $i < strlen($str); $i++)  { 
			if($str{$i} == ":") {
				if($str{$i + 1} != " ") {
					$str{$i + 1} = strtoupper($str{$i + 1});
				}
			}
		}
		
		return $str;
	}
?>