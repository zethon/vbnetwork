vbNetwork Version 1.0.0
=======================

**Requirements/Notes**

* * *

This plugin is being developed on 3.8.4 of vBulletin and has been tested on 3.6.x, 3.7.x and **4.0.x**

PHP's [libcurl package](http://curl.haxx.se/) needs to be installed on your server. _Most servers have this installed._

AdminCP -> Usergroups -> Usergroup Manager -> "Unregistered / Not Logged" -> "Can View Forum"  
**This option must be set to "Yes".** (You can still block Guests from viewing forums in the Forum Persmissions in the AdminCP.)

It is recommended that your User Registration Options allow usernames up to 25 characters in length.

**Installation**

* * *

1\. Upload all files in the UPLOAD directory, preserving the directory structure.

2\. Create the following directories with the following permissions

./network/packets/incoming - chmod 777
./network/packets/outgoing - chmod 777

3\. Go to your AdminCP and install the product's XML file.

**Instructions for Those Joining a Netwrok**

**Steps for Joining a Network**

1\. Go into your Control Panel and under Network Management select "Add New Network"

2\. The network admin should have supplied you with the information on this screen. Fill it out accordingly and click "Save".

3\. Get back to "Network Manager" under "Network Management" and select the network you just added.

4\. The network admin should have supplied you with XML for "Network Nodes" and "Network Forums". Paste the XML into the appropiate field and click "Save".

Your board is now configured to receive packets from the network. **See notes below for configuring the plugin's cron job.**

* * *

**Steps for Adding a Network Forum**

1\. Notify your network admin that you wish to subscribe to a certain network forum.

2\. Once your network admin has confirmed that you have been subscribed to the network forum, use the "Forum Manager" to assign a local forum to the network forum. These options are near the bottom of the forum configuration under "Network Options".

  

**Instructions for Those Running a Netwrok**

**Steps for Creating a Network**

1\. Go into your Control Panel and under Network Management select "Add New Network"

2\. Fill out the information accordingly. Make sure that the "Network Admin" and "SelfID" match.

3\. Under "Network Manager" select the newly created network.

4\. Under "Add a New Messageboard" enter in the information for your messageboard. The "Node Code" should match what you entered under "Add New Network".

* * *

**Steps for Adding a Mesasgeboard to Your Network**

1\. Go into your Control Panel and under Network Manager select the desired network.

2\. Under "Add a New Messageboard" enter the information for the new messageboard and click "Save Board".

3\. Use the "Network Forum Manager" to add the new board to the desired network forums.

4\. Once again select the desired network in the Network Manager.

5\. Under "XML Update" copy the XML from "Network Nodes" and "Network Forums" and send it to the admin of the new messageboard. The admin will use this XML to update the network info on their board.

6\. Using the Network Manager again, under "Network Updates" select the boards on your network to send an update. Every board on your network will need an update with the new messageboard's info, so you should probably select every board.

7\. Click "Send Updates".

* * *

**Steps for Creating a Network Forum**

1\. Under "Network Forum Manger" select "Add Network Forum".

2\. Enter in a unique numeric Network Forum ID.

3\. Your board will automatically be subscribed to this network forum. Use the Forum Manager to assign this network forum to a forum on your board.

* * *

**Steps for Adding an Existing Messagaeboard to a Network Forum**

1\. Under "Network Forum Manger" select "Add Board to Forum" for the appropiate forum.

2\. Enter the node code of the messageboard that wishes to subscribe to the network forum.

3\. Using the Network Manager, under "Network Updates" select the boards on your network to send an update. Every board on your network will need an update, so you should probably select every board.

6\. Click "Send Updates".

* * *

**Final Notes**

When adding boards, removing board, adding network forums, removing network forums, adding messageboards to and removing messageboards from network forums, all the boards on your network will need to be updated with this new information. Use the "Network Updates" in the "Network Manager" to send these updates to the boards on your network.

  
**Cron Configuration**

* * *

Your board will periodically send out updates (mostly new posts/threads). This is done through a cron, either a vBulletin "Scheduled Task" or a standard Unix cron.

In 'Scheduled Task Manager' the installer will create a task that is by default disabled. You can enable to the scheduled task and set it to run as often as you like.

Alternatively, you can run a system cron. The cron must be run from the directory of the deliver.php script and passed the 'console' parameter. An example is below:

	cd /directory/of/your/forum/network; php -f deliver.php console

**Other**

* * *

Support for this plugin can be found at [http://code.google.com/p/vbnetwork/](http://code.google.com/p/vbnetwork/).

If you believe you have found a bug, please log it in the "Issues" of the project. **I do not check vbulletin.org very often, so do not expect bugs posted there to be fixed.**

If you need some help starting up your own network, let me know and I'll do my best to help out.
