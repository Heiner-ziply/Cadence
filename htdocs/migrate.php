
<?php 
$pagetitle="CalDAV migration";
$headline = '<h1>Cadence</h1>' ;
include "Hydrogen/pgTemplate.php";
require_once 'Hydrogen/libDebug.php';
require_once 'Hydrogen/clsSQLBuilder.php';
require_once 'Hydrogen/clsDatasource.php';
require_once 'clsDB.php';
require_once 'clsCalDAV.php';
require_once 'caldav-client.php';
require_once 'Hydrogen/clsHTMLTable.php';
?>
<div class="w3-main w3-container w3-padding-16" style="margin-left:250px">
<?php
/*

This page has two use cases:

1. GET (?ID= ): Present the user with an explanation and an "OK" button.
2. POST (?ID=):  Move reminders with null Calendar ID to the specified CalDAV calendar ID, and show confirmation

*/



//unset these to remove the elements from the page, but include elemLogoHeadline to push the main section below the nav bar. Find a cleaner way of doing this later.
//unset ($logo_image);
//unset ($headline);
include 'Hydrogen/elemLogoHeadline.php'; 


if (isset($_SESSION['username'])) {
 
    if (isset($_GET['ID'])) {
        $toID=(int) $_GET['ID'];
		$sql = "SELECT c.name,a.alias FROM " . DB::$caldav_cal_table ;
		$sql .= " c inner join " . DB::$caldav_acct_table . " a on a.id=c.remote_acct_id ";
		$sql .= " where c.id=" . $toID . " and a.owner='" . $_SESSION['username'] . "'";
        $dds->setSQL($sql);
        if ($r=$dds->getNextRow()) {
            $calendarName=$r[0] . "(". $r[1]. ")";
            echo "</P>Click 'OK' below to copy all non-CalDAV reminders to the calendar '" . $calendarName . "'</P>";
            echo '<form action="migrate.php" method="POST">        
            <input name="ID" type="hidden" value="' . $toID . '">
            <input name="btnSubmit" type="Submit" value="OK">
            </form>';
        } else {
            echo "<p>You do not own a calendar with that ID.</P>";
        }
    } elseif (isset($_POST['ID'])) {
        $toID=(int) $_POST['ID'];
        //echo "<p>Placeholder code for migrating to calendar ID " . $toID . ".</P>";
        
        $sql = "SELECT c.id FROM " . DB::$caldav_cal_table ;
		$sql .= " c inner join " . DB::$caldav_acct_table . " a on a.id=c.remote_acct_id ";
		$sql .= " where c.id=" . $toID . " and c.owner='" . $_SESSION['username'] . "'";
        $dds->setSQL($sql);
        if ($r=$dds->getNextRow()) {
            $sql="SELECT id FROM ". DB::$reminder_table ." where calendar_id is null and owner='" . $_SESSION['username'] . "'";
            $dds->setSQL($sql);
            $array=$dds->getDataset();
            $rcount=count($array);
            for ($i = 0; $i < count($array); $i++) {
                //update the record
                $sql="UPDATE ". DB::$reminder_table . " SET calendar_id=" . $toID . " where id=" . $array[$i][0] . " and calendar_id is null and owner='" . $_SESSION['username'] . "'";
                $dds->setSQL($sql);
                $sql="UPDATE ". DB::$reminder_table . " SET uid='" . CalDAV::uid() . "' where id=" . $array[$i][0] . " and uid is null and owner='" . $_SESSION['username'] . "'";
                $dds->setSQL($sql);
                //push the change
                CalDAV::PushReminderUpdate($array[$i][0],true);
                
            }
            echo "<p>Migrated " . $rcount . " reminders to calendar ID " . $toID . ".</P>";

        } else {
            echo "<p>No calendar with that ID in your account.</P>";
        }
    } else {
        echo "<p>No calendar ID specified in request.</P>";
    }
    
} else {
	echo '<p>Only registered users may set up a CalDAV account.</p>';	
}	
?>

<!-- END MAIN -->
<p></p>
<p></p>
</div>
<?php include "Hydrogen/elemNavbar.php"; ?>
<?php include "Hydrogen/elemFooter.php"; ?>
</body></html>
