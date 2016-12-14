<?php
Function TimeConverter($Time){ // convert unix to hours, minutes, and seconds
	$init = $Time; 
$hours = floor($init / 3600);
$minutes = floor(($init / 60) % 60);
$seconds = $init % 60;
$HMS = $hours .":". $minutes .":". $seconds ." ";

return $HMS;
} 

$mysql_hostname = "localhost";
$mysql_user     = "";
$mysql_password = "";
$mysql_database = "";
$bd             = mysql_connect($mysql_hostname, $mysql_user, $mysql_password) or die("Oops some thing went wrong");
mysql_select_db($mysql_database, $bd) or die("Oops some thing went wrong");// now connected to database
$result = mysql_query("SELECT * FROM Machine_Monitor ORDER BY XBee_Index"); // selecting data through mysql_query() and sorting it by date and time
$result2 = mysql_query("SELECT * FROM Xbee");
$TodayDate = strtotime(date("Y/m/d"));

$XBeeArray = array();
$DataArray = array();
$MachineData = array(array());
$DayTimeArray = array();
$TransferArray = array();
$ShiftStart = array(21600, 55800); // shift start times equivlent to 6:00 am and 3:30 pm
$ShiftEnd = array(-30600, 1800);     // shift end times equivlent to 3:30 pm and 12:30 am 

$day = date('w');
$week_start = date('m-d-Y', strtotime('-'.$day.' days')); 
$week_end = date('m-d-Y', strtotime('+'.(6-$day).' days'));
$Dayinunix = 86400;
$DayTimeArray[0] = $Dayinunix;
$i = 0;

//Grab the data from Xbee and put it into an array.
while($data2 = mysql_fetch_array($result2)){ 
	$XBeeNum = $data2['XBee_Index'];
	$name    = $data2['Name'];
	
	$Xbee = $XBeeNum .": ". $name;

	array_push($XBeeArray, $Xbee);
	
}
	
	$passedweek = ($day * 86400)+(time() - strtotime("today"));
	$CutOffTime = time() - $passedweek;
	
	
//Grab the data from Machine Monitor and put it into an array.	
while($data = mysql_fetch_array($result)){
	
	$time  = $data['Time'];
	$date  = $data['Date'];
	$value = $data['Value'];
	$index = $data['XBee_Index'];
	
	$TempTime = $time . $date; // merge time and date

	$UnixTime = strtotime($TempTime); // convert time and date to unix
	
		
	$TimeValue = $UnixTime .",". $index .",". $value ." "; //merge unixtime, index, and value
	if ($UnixTime > $CutOffTime){ // only allow entrys that are within the cutoff time to pass 
	array_push($DataArray, $TimeValue); // add the data to an array
	}
}
foreach($DataArray as $key => $value){ //Sort data into $MachineData.
	
	$ExplodeValue = explode(',', $value); // explode the data 
	$Index = $ExplodeValue[1]; // find the index number for the machine
	
	$MachineData[$Index][$key] = $value; // add value to a 2D array. machinedata is arranged so that the first dimention is the machine and the second is the data

}


for ($shift = 0; $shift < 2; $shift++) { //loops through shifts 
	$i = 0; // reset $i for second shift
	
	for ($Day = 0; $Day < 7; $Day++) { // loops through days
		
		if($Day < $day){ // $day is the current day of the week while $Day is the forloop counter
			
			$TransferArray[$shift][$i] = array($Day); // creates an array for each day within the shifts 
				$i++; // adds 1 to i
			  $DayTimeArray[$i] = ($Dayinunix * $i) + $CutOffTime; // builds an array of unix values for the start of each day of the week
			
		foreach($XBeeArray as $place => $xbee){ // loops through machines 
	
			sort($MachineData[$i]); // sorts machinedata to remove null from array
			if(strlen($xbee) > 3){ // if the xbee has a name it may pass 
			array_push($TransferArray[$shift][$Day], array($XBeeArray[$place])); // creates an array within the day array to store the machine name. this is now a 3D array
			$TimeCounter = 0; // resets timecounter to 0
			foreach($MachineData[$place] as $spot => $data){ // loops through data 
				
			$spot2 = $spot + 1; // spot2 will always be one higher that spot
			$SortExplode2 = explode(',', $MachineData[$place][$spot2]); // explode the data from machinedata at spot2 
			$SortExplode = explode(',', $MachineData[$place][$spot]);   // explode the data from machinedata at spot 
			
			if ($SortExplode[0] > $DayTimeArray[$i] + $ShiftStart[$shift] and $SortExplode[0] < (($DayTimeArray[$i] + $Dayinunix) + $ShiftEnd[$shift])){ // if in shift calculated here
				if ($SortExplode[2] == 1 and $SortExplode2[2] == 0){ //checks for proper data structure
			
					
			$Timearray[$place][$spot] = max(($SortExplode2[0] - $SortExplode[0]), 0); // finds the time difference of machine start and stop and inputs it into an array
			$TimeCounter = $TimeCounter + $Timearray[$place][$spot]; // adds up the total up time for each machine 
			//echo $Timearray[$place][$spot], "<br>";	//enable for debuging
		
		}else{ // if the data struture in incorrect 
			$Timearray[$place][$spot] = 0; // put a 0 in timearray to avoid null values
			 }	
	}
			}
			if($TimeCounter > 0){ // if timecounter is greater than 0 it may pass. this keeps machines that are not in the system from getting to the frontend
			array_push($TransferArray[$shift][$Day][$place + 1], array(TimeConverter($TimeCounter))); // create a new array for each machine to hold the uptime and any other data. the array is now 4D 
			}
			
				}
			}
		}
	}
}
echo json_encode($TransferArray); // return the array as JSON 
?>
