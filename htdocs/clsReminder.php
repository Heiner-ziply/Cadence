<?php

class Reminders {
	
	public static function RemindersSQL($where="1=1", $mine=true) {
		$sql= "SELECT * FROM " . DB::$reminder_table . " WHERE  ". $where ;
		if ($mine) $sql .= " AND owner ='" . $_SESSION['username'] . "'";
		return $sql;
	}
	public static function Delete($id, $push=false, $owner="") {
		global $dds;
		require_once "common.php";
		require_once "Hydrogen/libDebug.php";
		require_once "Hydrogen/clsDateTimeExt.php";
		require_once "clsCalDAV.php";
		if ($owner!="") $sqlowner=$owner; 
		if (isset($_SESSION['username'])) $sqlowner=$_SESSION['username'];

		//check first if there are any matching records. Ignore if none, because this may happen with page refresh or bookmark
		$sql = "SELECT calendar_id FROM " . DB::$reminder_table;
		$sql = $sql . " WHERE id=" . $id . " AND owner='" . $sqlowner . "' ";
		$result = $dds->setSQL($sql);
		if ($result_row = $dds->getNextRow()) {
			//Delete from CalDAV first (need to send eTag which is stored in DB)
			if ($result_row[0]> 0) CalDAV::DeleteReminderFromCalendar($id, $result_row[0]);
			//Delete from Database
			$sql="delete FROM " . DB::$reminder_table;
			$sql = $sql . " WHERE id=" . $id . " AND owner='" . $sqlowner . "' ";
			$result = $dds->setSQL($sql);
		}
	}
	public static function MarkComplete($sequence, $push=false, $owner="") {
		$mark_complete=$sequence;
		global $dds;
		require_once "common.php";
		require_once "Hydrogen/libDebug.php";
		require_once "Hydrogen/clsDateTimeExt.php";
		require_once "clsCalDAV.php";
		if ($owner!="") $sqlowner=$owner; 
		if (isset($_SESSION['username'])) $sqlowner=$_SESSION['username'];
		
		
		//check first if there are any matching records. Ignore if none, because this may happen with page refresh or bookmark
		$sql = "SELECT count(*) FROM " . DB::$reminder_table;
		$sql = $sql . " WHERE sequence=" . $mark_complete . " AND owner='" . $sqlowner . "' ";
		$result = $dds->setSQL($sql);
		$result_row = $dds->getNextRow();
		if ($result_row[0] > 0) {
			$sql = "SELECT id, recur_float, recur_scale, recur_units, start_date, grace_scale, grace_units, passive_scale, passive_units FROM " . DB::$reminder_table;
			$sql = $sql . " WHERE sequence=" . $mark_complete . " AND owner='" . $sqlowner . "' ";
			$result = $dds->setSQL($sql);
			$result_row = $dds->getNextRow("labeled");	
			
			$sql =  "UPDATE " . DB::$reminder_table . " SET complete_date='" . date("Y-m-d H:i:s") . "', ";
			
			//check for recurrence
			if (!is_null($result_row['recur_units'])) {
				debug("recurring task completed");
				$recurscale = decode_scale($result_row['recur_scale']);
				$gracescale = decode_scale($result_row['grace_scale']);
				$passivescale = decode_scale($result_row['passive_scale']);
				
				if ($result_row['recur_float']==0) {
					$initdate = strtotime($result_row['start_date']);
					debug("recurrence set as fixed, with base date: " .$result_row['start_date']);
				} else {
					$initdate=time();
					debug("recurrence set as floating");
				}	
				debug("recurrence will follow date:" . date("Y-m-d H:i:s",$initdate));
				//Calculate the next correct start time	
				debug("next start: " . "+" . $result_row['recur_units'] . " " . $recurscale);
				$starttime = strtotime("+" . $result_row['recur_units'] . " " . $recurscale, $initdate );
				//Format it for MySQL
				$startdate = date("Y-m-d H:i:s",$starttime);
				debug("next recurrence date:" . $startdate);
				debug("next recurrence due: " . "+" . $result_row['grace_units'] . " " . $gracescale);
				$duetime = strtotime("+" . $result_row['grace_units'] . " " . $gracescale,$starttime);
				$duedate = date("Y-m-d H:i:s",$duetime);
				debug("next recurrence due date:" . $duedate);
				debug("next recurrence active: " . "+" . $result_row['passive_units'] . " " . $passivescale);
				$activetime = strtotime("+" . $result_row['passive_units'] . " " . $passivescale,$starttime) ;
				$activedate = date("Y-m-d H:i:s",$activetime);
				debug("next recurrence active date:" . $activedate);
				
				$sql = $sql . " start_date='" . $startdate . "', ";				
				$sql = $sql . " due_date='" . $duedate . "', ";	
				$sql = $sql . " active_date='" . $activedate . "', ";	
				debug($sql);
				
			} else {
				//non-recurring reminder will be expired
				$sql = $sql . " end_date='" . date("Y-m-d H:i:s",strtotime("-1 second")) . "', ";
			}
			$timestamp = (string) time();
			$sql = $sql . " sequence=" . $result_row['id'] . '000' .  $timestamp . ", ";
			$sql = $sql . " last_modified='" . DateTimeExt::zdate() . "' ";
			$sql = $sql . " WHERE id=" . $result_row['id'] . " AND owner='" . $sqlowner . "' ";
			//echo "<P>SQL:" . $sql . "</P>";
			$result = $dds->setSQL($sql);
			if ($push) CalDAV::PushReminderUpdate($result_row['id'],true);
		} //if count > 0		
	
	
	}
	
}

class Reminder {	
	protected $ICS;
	protected $reminder;
	public function __construct($ID,$owner="") {
		$sqlowner=$owner;
		if (isset($_SESSION['username'])) $sqlowner=$_SESSION['username'];
		require_once ("Hydrogen/clsDataSource.php");
		global $dds;
		$sql= "SELECT * FROM " . DB::$reminder_table . " WHERE id= $ID AND owner ='" . $sqlowner . "'";
		$result = $dds->setSQL($sql);
		$result_row = $dds->getNextRow("labeled");
		if($result_row)  $this->reminder = $result_row;  
	
	}
  
  
	public function serialize ($verbose=false) {
		require_once ("clsDB.php");
		require_once ("clsCalDAV.php");
		require_once ("Hydrogen/clsDateTimeExt.php");
		$this->ICS="";

		$this->write_line("BEGIN:VCALENDAR");
			$this->write_line("VERSION:2.0");
			$this->write_line("CALSCALE:GREGORIAN");

			if (is_null($this->reminder['prodid'])) $this->reminder['prodid']= "-//monstro.us//Cadence//2.0.0";
			
			$this->write_line("PRODID:".$this->reminder['prodid']);
			$this->write_line("BEGIN:VTODO");
			

			$this->write_line("X-CADENCE-SEQUENCE:".$this->reminder['sequence']);
			/* This is a hassle to be dealt with later
			BEGIN:VTIMEZONE
				TZID:America/Los_Angeles
				BEGIN:STANDARD
					DTSTART:20071104T020000
					RRULE:FREQ=YEARLY;BYMONTH=11;BYDAY=1SU
					TZNAME:PST
					TZOFFSETFROM:-0700
					TZOFFSETTO:-0800
				END:STANDARD
				BEGIN:DAYLIGHT
					DTSTART:20070311T020000
					RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=2SU
					TZNAME:PDT
					TZOFFSETFROM:-0800
					TZOFFSETTO:-0700
				END:DAYLIGHT
			END:VTIMEZONE
			*/			
				
				
			$datestamp=$this->reminder['created'];
			if (!is_null($this->reminder['last_modified'])) {
				$datestamp=$this->reminder['last_modified'];
			}
			$datestamp=DateTimeExt::CalDAVZFormatFromMySQLDateTime($datestamp);
			$this->write_line("DTSTAMP:$datestamp"); 
			
			$tempstatus="STATUS:NEEDS-ACTION";
			if (!is_null($this->reminder['due_date'])) {
				$this->write_line("DUE:" . DateTimeExt::CalDAVZFormatFromMySQLDateTime($this->reminder['due_date'])); 
			}
			if (!is_null($this->reminder['end_date'])) {
				$this->write_line("DTEND:" . DateTimeExt::CalDAVZFormatFromMySQLDateTime($this->reminder['end_date'])); 
				$temp=new DateTime($this->reminder['end_date']);
				if (time() > $temp->getTimestamp()) {
					$this->write_line("COMPLETED:" . $temp->format(DateTimeExt::$CalDAVZFormat));
					$tempstatus="STATUS:COMPLETED";
				}
			}
			
			$this->write_line($tempstatus);
			
			$this->write_line("DTSTART:" . DateTimeExt::CalDAVZFormatFromMySQLDateTime($this->reminder['start_date'])); 
						
			$fields= DB::calDAV_mapped_columns();
			//CREATED:20190617T203340Z
			//LAST-MODIFIED:20190617T203340Z
			//SUMMARY:New Task
			//DESCRIPTION:This is a test
			//UID:b27c8f51-1aa3-4ae7-b9c7-b1b725463bad
			//URL:www.monstro.us
			//LOCATION:here
			//PRIORITY:1
			$length = count($fields);
			for ($i = 0; $i < $length; $i++) {
				//PRODID was already done above so we will skip it here
				if (!is_null($this->reminder[$fields[$i]['col_name']]) and  $fields[$i]['calDAV_name']!="PRODID"   ) {
					//write_line("SUMMARY:" . $this->reminder['summary']);
					$outdata=$this->reminder[$fields[$i]['col_name']];
					if ($fields[$i]['calDAV_name']=="CREATED") $outdata=DateTimeExt::CalDAVZFormatFromMySQLDateTime($outdata);
					if ($fields[$i]['calDAV_name']=="LAST-MODIFIED") $outdata=DateTimeExt::CalDAVZFormatFromMySQLDateTime($outdata);
					$this->write_line ($fields[$i]['calDAV_name'] . ":" . $outdata);
				}
			}
			
			//Technically, this may be more than one line to be written, 
			// but the result is what we want
			if ($verbose) $this->write_line(rtrim($this->reminder['caldav_hidden']),"\n");
			
			//This section is especially for the 2Do Android app which for some reason
			//ignores the standard "DTSTART" iCal line
			$temp=new DateTime($this->reminder['start_date']);
			$SDATEL = $temp->getTimestamp();
			$temp->setTimezone(new DateTimeZone("UTC"));
			$tempstr= $temp->format('Y-m-d H:i:s');
			$temp=new DateTime($tempstr);
			$SDATE=$temp->getTimestamp();
			$X2DoMeta="<2Do Meta>%7B%22uid%22%3A%22" . $this->reminder['uid'] . "%22%2C%22parentuid%22%3A%22%22%2C%22outlookuid%22%3A";
			$X2DoMeta .= "%22%22%2C%22tags%22%3A%22%22%2C%22locations%22%3A%22%22%2C%22actionValue%2";
			$X2DoMeta .= "2%3A%22%22%2C%22actionType%22%3A-1%2C%22RUID%22%3A%22%22%2C%22RecurrenceFr";
			$X2DoMeta .= "om%22%3A1%2C%22RecurrenceType%22%3A0%2C%22RecurrenceValue%22%3A0%2C%22Recu";
			$X2DoMeta .= "rrenceEndType%22%3A0%2C%22RecurrenceEndRepetitions%22%3A0%2C%22RecurrenceE";
			$X2DoMeta .= "ndRepetitionsOrig%22%3A0%2C%22RecurrenceEndDate%22%3A0%2C%22RecurrenceEndD";
			$X2DoMeta .= "ateLocal%22%3A0%2C%22StartDayDelay%22%3A0%2C%22TaskType%22%3A0%2C%22isExpa";
			$X2DoMeta .= "ndedToShowChildProjects%22%3A0%2C%22IsStarred%22%3A0%2C%22DisplayOrder%22%";
			$X2DoMeta .= "3A0%2C%22TaskDuration%22%3A0%2C%22duetime%22%3A999999%2C%22StartDate%22%3A";
			$X2DoMeta .= $SDATE . "%2C%22StartDateLocal%22%3A" . $SDATEL . "%7D</2Do Meta>";
			$X2DoMeta .= "";
			$this->write_line("X-2DOAPP-METADATA;SHARE-SCOPE=GLOBAL:" . $X2DoMeta);
			
			
			$this->write_line("END:VTODO");		
		$this->write_line("END:VCALENDAR");
		return $this->ICS;
	
	}
	
	private function write_line ($content) {
		$output=str_replace("&rsquo;","'",$content);
		$output=str_replace("&quot;",'"',$output);
		$this->ICS .= $output . "\n";
	}
	
}



?>