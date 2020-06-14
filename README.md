Ciniki - Multitenant Cloud Application Platform
===============================================

This module stores weather data and has the ability to provide that data to maps and share via aprs.

The scripts/beacon.php must be running to send beacons over APRS or to Weather Underground.
This is required so as not to slow down the reception and storing of weather data
from other modules such as 43392. The wuSubmit can get hung and timeout, which stalls 
the database updates. The hooks/weatherDataReceived.php file needs to return quickly
so it does not stall calling functions.

License
-------
Ciniki is free software, and is released under the terms of the MIT License. See LICENSE.md.
