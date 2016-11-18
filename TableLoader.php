<?php
Function TimeConverter($Time){
	$init = $Time;
$hours = floor($init / 3600);
$minutes = floor(($init / 60) % 60);
$seconds = $init % 60;
$HMS = $hours .":". $minutes .":". $seconds ." ";

return $HMS;
} 

$mysql_hostname = "localhost";
$mysql_user     = "monitor";
$mysql_password = "password";
$mysql_database = "AHI_DB";
$bd             = mysql_connect($mysql_hostname, $mysql_user, $mysql_password) or die("Oops some thing went wrong");
mysql_select_db($mysql_database, $bd) or die("Oops some thing went wrong");// we are now connected to database
$result = mysql_query("SELECT * FROM Machine_Monitor ORDER BY XBee_Index"); // selecting data through mysql_query() and sorting it by date and time
$result2 = mysql_query("SELECT * FROM Xbee");
$TodayDate = strtotime(date("Y/m/d"));

$XBeeArray = array();
$DataArray = array();
$MachineData = array(array());
$DayTimeArray = array();
$TransferArray = array();

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
	
	$TempTime = $time . $date;

	$UnixTime = strtotime($TempTime);
	
		
	$TimeValue = $UnixTime .",". $index .",". $value ." ";
	if ($UnixTime > $CutOffTime){
	array_push($DataArray, $TimeValue);
	}
}
foreach($DataArray as $key => $value){ //Sort data into $MachineData.
	
	$ExplodeValue = explode(',', $value);
	$Index = $ExplodeValue[1];
	
	$MachineData[$Index][$key] = $value;

}


for ($shift = 0; $shift < 2; $shift++) { //loops through shifts 
	$i = 0; // reset $i for second shift
	
	for ($Day = 0; $Day < 7; $Day++) { // loops through days
		
		if($Day < $day){ // 
			$TransferArray[$shift][$i] = array($Day); // creates an array for each day within the shifts 
				$i++; // adds 1 to i
			  $DayTimeArray[$i] = ($Dayinunix * $i) + $CutOffTime;
			
		foreach($XBeeArray as $place => $xbee){ // loops through machines 
	
			sort($MachineData[$i]);
			if(strlen($xbee) > 3){
			array_push($TransferArray[$shift][$Day], array($XBeeArray[$place]));
			$TimeCounter = 0;
			foreach($MachineData[$place] as $spot => $data){ // loops through data 
				
			$spot2 = $spot + 1;
			$SortExplode2 = explode(',', $MachineData[$place][$spot2]);
			$SortExplode = explode(',', $MachineData[$place][$spot]); 
			//echo $DayTimeArray[$i];
			if ($SortExplode[0] > $DayTimeArray[$i] and $SortExplode[0] < ($DayTimeArray[$i] + $Dayinunix)){ // if in shift calculated here
				if ($SortExplode[2] == 1 and $SortExplode2[2] == 0){ //checks for proper data structure
			
					
			$Timearray[$place][$spot] = max(($SortExplode2[0] - $SortExplode[0]), 0);
			$TimeCounter = $TimeCounter + $Timearray[$place][$spot];	
			//echo $Timearray[$place][$spot], "<br>";	//enable for debuging
		
		}else{
			$Timearray[$place][$spot] = 0;
			 }	
	}
			}
			if($TimeCounter > 0){
			array_push($TransferArray[$shift][$Day][$place + 1], array(TimeConverter($TimeCounter)));	
			}
			
				}
			}
		}
	}
}
echo json_encode($TransferArray);
?>
