<?php
	/************************
	 * descriptions.php
	 * This file is used to set information about a given course.
	 * Use this file to: 
	 *	* <title> Overwrite the title of a course, instead of using what's saved in SMS.
	 *	* <description> Add a description to course.
	 *	* <ibest>1</ibest> Specify a course as an I-BEST course. (1 will list course as I-BEST
	 *	* <force>5</force> Force a course to another admin_unit (use if course showing up under incorrect program, or -1 to hide course from search)
	 *		-replace 5 with new admin_unit number
	 *		-common numbers are: General Ed = 5, Continuing Ed = 68, Hidden = -1, Default = -2 (use to remove a default admin_unit, and use admin_unit from SMS)
	 *		-see programs table in database for full list of numbers
	 *
	 * Courses only need to be modified here once and the information will be saved from quarter to quarter, but
	 *	easiest practice would be to keep a running XML file in case any changes need to be made. Simply upload
	 *	the XML file and enter the password to update course information. See example.xml for an example of
	 *	the proper XML to use.
	 *
	 * Note: Any changes will not be reflected until update.php runs after the file has been uploaded. Either
	 *	wait for the file to run automatically, or simply access update.php through your browser.
	 *
	 * Note: The ampersand character is a special character for XML. If you wish to use it in your XML, "&amp;" (minus
	 *	the quotes) instead. For example, to use "ENG&101", write it as "ENG&amp;101"
	 *
	 * Note: Every course requires a <number>Example 101</number> tag to specify which course, but the rest of the tags are optional.
	 *	You can use one or all to specify the data you need.
	 *
	 *************************/

	//error reporting for dev stage
	ini_set('display_errors', 'On');
	error_reporting(E_ALL);
	
	//flags
	$upload = false;
	$pass = false;
	
	//checks for correct password
	if(isset($_POST['pass']) && $_POST['pass'] == "test") {
		$pass = true;
	}
	
	//attempts to gets uploaded file
	if(strtolower($_SERVER['REQUEST_METHOD'])=='post') {
		$contents= file_get_contents($_FILES['file']['tmp_name']);
		$upload = true;
	}
	
	//if file not uploaded or password incorrect, print form and password error is needed
	if(!$upload || !$pass) {
		//print password error is password was attempted
		if(isSet($_POST['pass']) && !$pass) {
			echo "Password is incorrect.<br>";
		}
		echo 'Select the XML file with the course descriptions you wish to add or update. Then, enter the password and click "Submit"<br>';
		echo '<form action="descriptions.php" method="post" enctype="multipart/form-data">
			<label for="file">Filename:</label><input type="file" name="file" id="file"> <br>
			<abel for="pass">Password:</tabel><input type="password" name="pass" id="pass"> <br>
			<input type="submit" name="submit" value="Submit">
			</form>';
		//exit script
		die();
	} else {
	//here we have a matching password and an uploaded file, so parse the XML now
	
		//connect to the database
		try {
			$dbh = new PDO('mysql:host=localhost;dbname=cptcclasses;charset=utf8', 'classes', 'CKnpF62v5qA3L9aZ');
		} catch(PDOException $e) {
			echo "Access denied: " . $e->getMessage();
		}
		
		//parse the string of XML
		parseXML($contents, $dbh);	
		
		//close the database connection
		$dbh = null;
	}
	
	//     *************************
	//     ******* FUNCTIONS *******
	//     *************************

	/**
	 * Given an <item> of valid XML (from parseXML()) will check database if course currently in.
	 * If course number in place, will update description. If not, will add course number and description to database
	 */
	function parseItem($item, $dbh) {
		$cnumber = trim($item->number);
		
		//get course description if provided.
		$cdescription;
		$ctitle = "";
		if($item->description != null) {
			$cdescription = trim($item->description);
		}
		
		//get course title if provided.
		$ctitle = "";
		if($item->ctitle != null) {
			$ctitle = trim($item->title);
		}
		
		//check if course is I-BEST.
		$ibest1 = "";
		if($item->ibest != null && trim($item->ibest) == "1") {
			$ibest1 = 1;
		}

		//check if course is I-BEST.
		$ibest2 = "";
		if($item->ibest2 != null && trim($item->ibest2) == "1") {
			$ibest2 = 1;
		}

		//check if course is I-BEST.
		$ibest3 = "";
		if($item->ibest3 != null && trim($item->ibest3) == "1") {
			$ibest3 = 1;
		}
		
		//check if need to force to new admin_unit
		$force = 0;
		if($item->force != null) {
			$force = intval(trim($item->force));
		}
		if($force == 0) {
			$force = -2;
		}
		
		//see if course exists.
		$exists = checkForCourse($cnumber, $dbh);
		
		if($exists) {
			updateDescription($cnumber, $cdescription, $ctitle, $ibest1, $ibest2, $ibest3, $force, $dbh);
		} else {
			insertDescription($cnumber, $cdescription, $ctitle, $ibest1, $ibest2, $ibest3, $force, $dbh);
		}
		
	}
	
	/**
	 * Given a string of valid XML, will parse item by item sending each item to parseItem() as an individual item
	 */
	function parseXML($xml, $dbh) {
		$xml = simplexml_load_string($xml);
		
		foreach($xml->children() as $child) {
			parseItem($child, $dbh);
		}
	}
	
	/**
	 * Checks if course number is already in database. If so, returns true, if not, returns false
	 * Search is case insensitive
	 */
	function checkForCourse($num, $dbh) {
		try {
			//grab the program info now that we have source number
			$sql = "SELECT cnumber FROM course_description WHERE cnumber = '" . $num . "'";
			$sth = $dbh->prepare($sql);
			$sth->execute();
			$res = $sth->fetch(PDO::FETCH_ASSOC);
			if($res['cnumber'] == null) {
				return false;
			} else {
				return true;
			}
		} catch(PDOException $e) {
			echo $e->getMessage();
		}
	}
	
	/**
	 * Updates a course description given the course number
	 */
	function updateDescription($num, $desc, $ctitle, $ibest, $ibest2, $ibest3, $force, $dbh) {
		// query
		$sql = "UPDATE course_description SET cnumber=?, cdescription=?, ctitle=?, ibest=?, ibest2=?, ibest3=?, force_admin=? WHERE cnumber=?";
		$q = $dbh->prepare($sql);
		$q->execute(array($num, $desc, $ctitle, $ibest, $ibest2, $ibest3, $force, $num));
		echo "Updated $num<br>";
	}
	
	/**
	 * Inserts and new course description with a course number
	 */
	function insertDescription($num, $desc, $ctitle, $ibest, $ibest2, $ibest3, $force, $dbh) {
		$sql = "INSERT INTO course_description (cnumber, cdescription, ctitle, ibest, ibest2, ibest3, force_admin) VALUES (:cnumber,:cdescription, :ctitle, :ibest, :ibest2, :ibest3, :force_admin)";
		$q = $dbh->prepare($sql);
		$q->execute(array(':cnumber'=>$num, ':cdescription'=>$desc, ':ctitle'=>$ctitle, ':ibest'=>$ibest, ':ibest2'=>$ibest2, ':ibest3'=>$ibest3, ':force_admin'=>$force));
		echo "Added $num<br>";
	}
?>