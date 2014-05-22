<?php
	/************************
	 * view.php
	 * This file is used to search through and display course information for the selected quarter.
	 * Update course information through update.php.
	 * Specify course titles, descriptions and whether the course is I-BEST with descriptions.php.
	 *
	 *
	 *************************/

	//error reporting for dev stage
	ini_set('display_errors', 'On');
	error_reporting(E_ALL);
	
	//default not set on server
	date_default_timezone_set('America/Los_Angeles');
	
	//default quarter (will be updated once quarter select implemented
	$default_quarter = "B451";
	
	//create WHERE string for SQL
	$where = buildWhere();
	
	//start HTML
	echo '<!DOCTYPE html>
	<html>
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
		<title>Class Schedule | Clover Park Technical College</title>
		<link rel="shortcut icon" href="http://www.cptc.edu/sites/default/files/favicon_1.ico" type="image/vnd.microsoft.icon">
		<link href="http://www.cptc.edu/sites/all/themes/dill/style.css" media="screen, projection" rel="stylesheet" type="text/css" />
		<link href="http://www.cptc.edu/sites/all/themes/dill/schedule-style-3.css" media="screen, projection"rel="stylesheet" type="text/css" />
		<link href="http://www.cptc.edu/sites/all/themes/dill/schedule-print-8.css" media="print"rel="stylesheet" type="text/css" />';
	
	//print JS method to reset form
	printResetJS();
	echo '</head><body>';
	//include site header
	$header = $_SERVER['DOCUMENT_ROOT'];
	$header .= "/sites/all/themes/dill/header.php";
	include_once($header);

	//try to connect to database
	try {
		$dbh = new PDO('mysql:host=localhost;dbname=cptcclasses;charset=utf8', 'classes', 'CKnpF62v5qA3L9aZ');
	} catch(PDOException $e) {
		echo "Access to database denied.";
		die();
	}

	echo '<div class="container content-page">
		<div class="landing-top">
		<h1 class="page-header">Class Schedule</h1>
		<div class="row-fluid">
<p>Use the navigation menu below to see the classes offered for the selected quarter. The default is summer quarter 2014. To see classes for fall quarter, be sure to select "Fall 2014" under "Quarter." Be careful to note which campus a class is at. If you do not want classes from a certain campus, unselect that campus under "location."</p>
<p>The number of seats available is refreshed every thirty minutes.</p>
</div>
		</div>
		<div class="row-fluid">
		<div class="span3 schedule-sort landing-left-column">';
	//print the form
	printForm($dbh);
	echo '<h3>Other Offerings</h3>
		<p><a href="/adult-basic-ed">Adult Basic Education Classes</a></p>
		<p><a href="/continuing-ed">Continuing Ed Classes</a></p>
		</div>
		<div class="span9 landing-list">';
	

	//try to run the sql
	try {
		$stmt = $dbh->prepare("SELECT * FROM view_courses " . $where . " ORDER BY course_id");
		$stmt->execute();
		//echo "Rows: " . $stmt->rowCount() . "<hr>";
	} catch(PDOException $e) {
		echo "Error with SQL.";
	}
	
	//if no results are found, print message. Else, print results.
	$last_row = null;
	if($stmt->rowCount() == 0) {
		echo "<h4>No results found.</h4>";
	} else {
		//prints all courses (to show formatting)
		while ($row = $stmt->fetchObject()) {
			$last_row = printCourse($row, $last_row, $dbh);
		}
		//close the last row
		printBottom($last_row);
	}
		echo '</div>';
	echo '</div>';
	echo '</div>';
	//include site footer
	$footer = $_SERVER['DOCUMENT_ROOT'];
	$footer .= "/sites/all/themes/dill/footer.php";
	include_once($footer);
	$analytics = $_SERVER['DOCUMENT_ROOT'];
	$analytics.= "/sites/all/themes/dill/analytics.php";
	include_once($analytics);
	echo '</body></html>';
	
	//     *************************
	//     ******* FUNCTIONS *******
	//     *************************
	
	/**
	 * Builds a WHERE string to use for SQL based on POST variables.
	 */
	function buildWhere() {
	global $default_quarter;
		$where = "";
		//check if we got here via submit... (only have to check this once because if one POST is set, they all will be, even if values are "")
		if(isset($_POST['program'])) {
			//by quarter
			if($_POST['quarter'] != -1) {
				$where .= "WHERE yrq LIKE '" . $_POST['quarter'] . "' ";
			}
			//search by program
			if($_POST['program'] != -1) {
				if($where != "") {
					$where .= "AND ";
				} else {
					$where = "WHERE ";
				}
				//if searching for I-BEST (999), display that. if not, display requested program id
				if($_POST['program'] == "999") {
					$where .= " ibest = 1 ";
				} else if($_POST['program'] == "998") {
					$where .= " ibest2 = 1 ";
				} else if($_POST['program'] == "997") {
					$where .= " ibest3 = 1 ";
				} else {
					$where .= " admin_unit = " . $_POST['program'] . " ";
				}
			}
			//search by instructor
			if($_POST['instructor'] != "") {
				if($where != "") {
					$where .= "AND ";
				} else {
					$where = "WHERE ";
				}
				$where .= "instr_name LIKE '%" . $_POST['instructor'] . "%' ";
			}
			//search by keyword (in description)
			if($_POST['keyword'] != "") {
				if($where != "") {
					$where .= "AND ";
				} else {
					$where = "WHERE ";
				}
				$where .= "MATCH(cdescription, ctitle, course_id, course_title) AGAINST ('" . $_POST['keyword'] . "' IN BOOLEAN MODE) ";
			}
			//starting after a time
			if($_POST['after'] != -1) {
				if($where != "") {
					$where .= "AND ";
				} else {
					$where = "WHERE ";
				}
				$where .= "start_24 >= " . $_POST['after'] . " AND start_24 != -1 ";
			}
			//ending before a time
			if($_POST['before'] != -1) {
				if($where != "") {
					$where .= "AND ";
				} else {
					$where = "WHERE ";
				}
				$where .= "end_24 <= " . $_POST['before'] . " AND end_24 != -1 ";
			}
			//ending before a time
			if($_POST['credits'] != -1) {
				if($where != "") {
					$where .= "AND ";
				} else {
					$where = "WHERE ";
				}
				$where .= "cr = " . $_POST['credits'] . " ";
			}
			//location
			if(isset($_POST['mode']) && count($_POST['mode']) < 5) {
				if($where != "") {
					$where .= "AND ";
				} else {
					$where = "WHERE ";
				}
				$where .= '(';
				for($i = 0; $i < count($_POST['mode']); $i++) {
					if($i > 0) {
						//if not the first checked, add an "or" to the statement
						$where .= ' OR ';
					}
					$where .= 'mode = ' . $_POST['mode'][$i] . ' ';
				}
				$where .= ') ';
			}
			//not full
			if(isset($_POST['notFull'])) {
				if($where != "") {
					$where .= "AND ";
				} else {
					$where = "WHERE ";
				}
				$where .= 'enr != class_cap ';
			}
		} else {
			$where = "WHERE yrq LIKE '" . $default_quarter . "'";
		}
		//return where string
		return $where;
	}
	
	/**
	 * Prints a single class of information given a $row from database
	 */
	function printCourse($row, $last_row, $dbh) {
		if($last_row == null) {
			printTop($row);
		} else if($last_row->course_id != $row->course_id) {
			printBottom($last_row);
			printTop($row);
		}
		echo '
			<tr>
				<td>' . $row->class_id . '</td>
				<td>' . $row->enr . '/' . $row->class_cap . '</td>
				<td>' . formatDate($row->strt_date) . '</td>
				<td>' . $row->strt_time . '</td>
				<td>' . $row->end_time . '</td>
				<td>' . $row->days . '</td>
				<td>' . $row->instr_name .'</td>
				<td>';
				//add <span> for south hill
				if(strpos($row->room_loc, "South") !== false) {
					echo '<span class="schedule-south-class">' . $row->room_loc . '</span>';
				} else {
					echo $row->room_loc;
				}
				echo '</td>
			</tr>	
			';
			return $row;
	}
	
	/**
	 * Prints top area of a grouped course area (everything leading up to the <tr> for the specific course section)
	 * Will get called before each unique course
	 * Will not get called between two similar courses (ie, if there are two ENG 101, will not get called between the two courses)
	 */
	function printTop($row) {
		echo '<div class="schedule-class-info">	
			<h2 class="schedule-class-title">';
			//Use title provided by XML if able, if not, use from database
			if($row->ctitle != null && $row->ctitle != "") {
				echo $row->ctitle;
			} else {
				echo $row->course_title;
			}
			echo '</h2>
			<h3 class="schedule-class-subtitle">' . $row->course_id . '</h3>
			<table class="table table-striped table-condensed schedule-table">
				<thead>
					<tr>
						<th>Item #</th>
						<th>Enrollment</th>
						<th>Start Date</th>
						<th>Start Time</th>
						<th>End Time</th>
						<th>Days</th>
						<th>Instructor</th>
						<th>Location</th>
					</tr>
				</thead>
				<tbody>';
	}
	
	/**
	 * Prints bottom area of a grouped course area (everything after the </tr> for the specific course section, closing the <div> from printTop())
	 * Will get called after each unique course
	 * Will not get called between two similar courses (ie, if there are two ENG 101, will not get called between the two courses)
	 */
	function printBottom($row) {
		echo '				
				</tbody>
				</table>
					<div class="desc">
						<p><strong>Credits</strong>: ' . $row->cr . '</p><p>';
					if($row->cdescription) {
						echo $row->cdescription;
					} else {
						echo 'No description available.';
					}
					echo '
					</p>
					</div>
		</div>';
	}

	/**
	 * Formats a date to fit school standards
	 * In form "Feb. 7, 2014"
	 * Month abbreviations are: "Jan.", "Feb.", "March", "April", "May", "June", "July", "Aug.", "Sept.", "Oct.", "Nov.", "Dec."
	 * Returns formatted string
	 */
	function formatDate($str) {
		//if date info not available, return "Arranged"
		if($str == "0000-00-00") {
			return "Arranged";
		}
		//converts MySQL date format to school format (minus the month, which will be replaced next)
		$str = date("M j, Y", strtotime($str));
		//PHP month abbreviations (to search for)
		$monthsF = array("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec");
		//School abbreviations to replace with
		$monthsR = array("Jan.", "Feb.", "March", "April", "May", "June", "July", "Aug.", "Sept.", "Oct.", "Nov.", "Dec.");
		//find which month to replace, then replace it
		for($i = 0; $i < count($monthsF); $i++) {
			$str = str_replace($monthsF[$i], $monthsR[$i], $str);
		}
		return $str;
	}

	/**
	 * Prints search form
	 * Sets default values if sent from last search
	 */
	 function printForm($dbh) {
		global $default_quarter;
		echo '<form action="view.php" method="post">';
		//quarter
		echo '<h4>Quarter</h4> <select name="quarter">';
		echo '<option value="B451">Summer 2014</option>';
		echo '<option value="B452"';
		if(isset($_POST['quarter']) && $_POST['quarter'] == "B452") {
			echo ' selected';
		} else if(!isset($_POST['quarter'])) {
			//set up default selected based on $default_quarter
		}
		echo '>Fall 2014</option>';
		echo '</select>';
		//program
		echo '<h4>Program</h4><select name="program">';
		//print all programs for select
		try {
			$stmt = $dbh->prepare("SELECT * FROM programs ORDER BY program_title");
			$stmt->execute();
		} catch(PDOException $e) {
			echo "Error with SQL for programs: " . $e->getMessage() . "<br>";
		}
		//option for programs
		echo '<option value="-1">All</option>';
		while ($row = $stmt->fetchObject()) {
			echo '<option value="' . $row->admin_unit . '"';
			if(isset($_POST['program']) && $_POST['program'] == $row->admin_unit)
				echo 'selected';
			echo '>'. $row->program_title . '</option>';
		}
		echo '</select>';
		//instructor search area
		echo '<h4>Instructor</h4><input type="text" name="instructor" ';
		if(isset($_POST['instructor'])) {
			echo 'value="' . htmlspecialchars($_POST['instructor']) . '"';
		}
		echo '><br>';
		//keyword search area
		echo '<h4>Keywords</h4><input type="text" name="keyword" ';
		if(isset($_POST['keyword'])) {
			echo 'value="' . htmlspecialchars($_POST['keyword']) . '"';
		}
		echo '><br>';
		//starting after time search area
		echo '<h4>Starting After</h4>';
		if(isset($_POST['after'])) {
			printTimeOptions("after", $_POST['after']);
		} else {
			printTimeOptions("after", -1);
		}
		//ending before time search area
		echo '<h4>Ending Before</h4>';
		if(isset($_POST['before'])) {
			printTimeOptions("before", $_POST['before']);
		} else {
			printTimeOptions("before", -1);
		}
		//credits area
		//ending before time search area
		echo '<h4>Credits</h4>';
		if(isset($_POST['credits'])) {
			printCreditsOptions($_POST['credits']);
		} else {
			printCreditsOptions(-1);
		}
		//location area
		$mode_l = array(null, 'Lakewood', 'South Hill', 'Online', 'Arranged', 'Off Campus');
		echo '<h4>Location</h4>';
		//check which boxes should be checked. if new search, check all. if searched, check previous checked
		for($i = 1; $i < count($mode_l); $i++) {
			echo '<label><input type="checkbox" name="mode[]" value="' . $i . '" ';
			//if no search yet, make all checked:
			if(!isset($_POST['program'])) {
				echo 'checked';
			} else {
				//if after they click search, only check ones they had checked already
				if(isset($_POST['mode']) && in_array($i, $_POST['mode'])) {
					echo 'checked';
				}
			}
			echo '> ' . $mode_l[$i];
			//fencepost backlash
			if($i < (count($mode_l) - 1)) {
				echo '<br>';
			}
			echo '</label>';
		}
		//not full area
		echo '<h4>Availability</h4><label><input type="checkbox" name="notFull" value="1"';
			if(isset($_POST['notFull'])) {
				echo ' checked';
			}
		echo '> Not Full</label>';
		
		//submit area
		echo '<br><input type="submit" value="Search" class="btn">';
		echo '<br><input type="button" value="Reset" onclick="resetForm();" class="btn">';
		echo '</form>';
	 }
	 
	 /**
	  * Prints time selector
	  */
	  function printTimeOptions($name, $pre) {
		$timeO = array('Anytime', '7:00 a.m.', '7:15 a.m.', '7:30 a.m.', '7:45 a.m.', '8:00 a.m.', '8:15 a.m.', '8:30 a.m.', '8:45 a.m.', '9:00 a.m.', '9:15 a.m.', '9:30 a.m.', '9:45 a.m.', '10:00 a.m.', '10:15 a.m.', '10:30 a.m.', '10:45 a.m.', '11:00 a.m.', '11:15 a.m.', '11:30 a.m.', '11:45 a.m.', '12:00 p.m.', '12:15 p.m.', '12:30 p.m.', '12:45 p.m.', '1:00 p.m.', '1:15 p.m.', '1:30 p.m.', '1:45 p.m.', '2:00 p.m.', '2:15 p.m.', '2:30 p.m.', '2:45 p.m.', '3:00 p.m.', '3:15 p.m.', '3:30 p.m.', '3:45 p.m.', '4:00 p.m.', '4:15 p.m.', '4:30 p.m.', '4:45 p.m.', '5:00 p.m.', '5:15 p.m.', '5:30 p.m.', '5:45 p.m.', '6:00 p.m.', '6:15 p.m.', '6:30 p.m.', '6:45 p.m.', '7:00 p.m.', '7:15 p.m.', '7:30 p.m.', '7:45 p.m.', '8:00 p.m.');
		$timeV = array(-1, 700, 715, 730, 745, 800, 815, 830, 845, 900, 915, 930, 945, 1000, 1015, 1030, 1045, 1100, 1115, 1130, 1145, 1200, 1215, 1230, 1245, 1300, 1315, 1330, 1345, 1400, 1415, 1430, 1445, 1500, 1515, 1530, 1545, 1600, 1615, 1630, 1645, 1700, 1715, 1730, 1745, 1800, 1815, 1830, 1845, 1900, 1915, 1930, 1945, 2000);
		echo '<select name="' . $name . '">';
		for($i = 0; $i < count($timeO); $i++) {
			echo '<option value="' . $timeV[$i] . '"';
			if(isset($pre) && $pre == $timeV[$i])
				echo 'selected';
			echo '>' . $timeO[$i] . '</option>';
		}
		echo '</select>';
	  }
	  
	  /**
	   * Prints credits selector
	   */
	function printCreditsOptions($pre) {
		$ops = array('All', 1, 2, 3, 4, 5, 6, 7, 8, 9, 10);
		$vals = array(-1, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100);
		echo '<select name="credits">';
		for($i = 0; $i < count($ops); $i++) {
			echo '<option value="' . $vals[$i] . '"';
			if(isset($pre) && $pre == $vals[$i])
				echo 'selected';
			echo '>' . $ops[$i] . '</option>';
		}
		echo '</select>';
	}
	
   

	/**
	 * Javascript to reset the form
	 */
	function printResetJS() {
		global $default_quarter;
		echo '
			<script language="JavaScript">
			
				//Resets the form to original default values
				function resetForm() {
					location.reload();
					//window.location = "http://www.cptc.edu/schedule/view.php";
			/*		document.getElementsByName("quarter")[0].value = "' . $default_quarter . '";
					document.getElementsByName("program")[0].value = "-1";
					document.getElementsByName("instructor")[0].value = "";
					document.getElementsByName("after")[0].value = "-1";
					document.getElementsByName("before")[0].value = "-1";
					document.getElementsByName("credits")[0].value = "-1";
					var mode = document.getElementsByName("mode[]");
					
					for (var i = 0; i < mode.length; i++) {
						mode[i].checked = true;
					}
					document.getElementsByName("notFull")[0].checked = false;*/
				} 
			</script>
		';
	}
	
	
?>