# Cadence
Reminder management app (xAMP)

Focusing on recurrence functionality. Release 1.x is an xAMP standalone solution. Release 2.x is designed to solve the recurrence problem in a heterogenous CalDAV client environment, in which various clients often ignore or undo each other's changes.

Built on Hydrogen framework (clone the Hydrogen repository inside this project's htdocs folder and then add it to your .gitignore)

Testing Release 2.x with Hydrogen v1.x


## Contents
* the **htdocs** folder contains the PHP and image files to go in your web root
* the **sql** folder contains the SQL commands to create your database tables and (optionally) create demo data
* the **tests** folder contains 1 or more Python scripts used for automated browser-based regression testing

## Project values
* Functionality 
* Platform independence (use web interface from any and all devices with browsers)
* CalDAV client independence (use with any and all CalDAV clients)
* CalDAV server independence (use with any and all CalDAV servers)
* Open Source (have it your way)
* Portability (take your data with you)
* Privacy (self-hosting optional; data deletion optional)

# Credits
* @subpackage caldav-client
* @author Andrew McMillan <debian@mcmillan.net.nz>
* @copyright Andrew McMillan
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2

