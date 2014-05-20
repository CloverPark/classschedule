<?php
	//GLOBALS
	//how many quarters to look ahead for
	$look_at = 4;
	//open Soap client for web service
	$client = new SoapClient("https://tredstone.cptc.edu/CourseData/CPTC_Courses.asmx?wsdl");

	//no default set
	date_default_timezone_set('America/Los_Angeles');
	
	//error reporting for dev stage
	ini_set('display_errors', 'On');
	error_reporting(E_ALL);
	
	updateCourses($_GET['date']);
	
	/**
	 * Gets json feed from web service, truncates database, then inserts new data into database
	 */
	function getQuarters($date) {
		global $look_at;
		if(isset($_GET['date'])) {
			$y = substr($date, 0, 2);
			$m = substr($date, 2, 2);
			$d = substr($date, 4, 2);
			
			$yrqs = array();
			$names = array();
			$found = 0;
			//find the next quarters as specified by $look_at
			while(count($yrqs) < $look_at) {
				$yrq = getYRQ($y . $m . $d);
				//if it's the first YRQ found or a new one from the last found, add it to the list
				if(strlen($yrq) > 3 && (count($yrqs) == 0 || $yrqs[count($yrqs) - 1] != $yrq)) {
					$yrqs[] = $yrq;
					$names[] = getQtr($yrq);
				}
				//update date to check the next week
				$d += 7;
				if($d > 28) {
					$m++;
					if($m > 12) {
						$m = 0;
						$y++;
					}
				}
				//add zeros to numbers if single digits
				if($d < 10) {
					$d = '0' . $d;
				}
				if($m < 10) {
					$m = '0' . $m;
				}
			}
			print_r($names);
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
		
		return $qtr;
	}

?>