Kayako Wordpress Integration
=======================

This library is maintained by Kayako.

Overview
=======================

Kayako Wordpress integration for Kayako version 4. This module can be used on a Wordpress based website for basic functionality of ticket creation and status checking.
Now WordPress users won't have to log in separately, they can convert their wordpress comments directly to kayako ticket and many more.
User only needs to log in to the website and he will be able to create/view/update tickets corresponding to the email id by which he has logged in.

Features
=======================

* Kayako Ticket Submission form for front-end users.
* Logged in user in WordPress site can view his/her tickets on WordPress dashboard.
* You can edit your ticket properties like update ticketstatus, ticketpriority etc.
* Post a reply to a ticket.
* Comments can directly converted to kayako tickets.
* LiveChat Tag Generator for Wordpress Templates.

Supported versions
=======================

Kayako: v4.51.1891 and above

Installation Steps
=======================

1. Download and extract Wordpress integration.
2. Place the 'kayako' folder in Wordpress installation under wp-content/plugins/ folder.
3. Open the Wordpress Admin Panel. Click on the Plugins -> Installed Plugins. You will find the Kayako for Wordpress plugin. Please activate the plugin.
4. Click on Kayako Settings from submenu. Fill the kayako helpdesk API details accordingly. 
	You can also set the labels for kayako ticket submission form which would further used to display on frontend using short code in Wordpress Posts.
5. Put the LiveChat Tag generator code in LiveChat tag settings.
6. This integration allows to convert your comment directly from the comment section of wordpress by clicking on link 'Convert this comment to kayako ticket'.
7. After adding shortcode[kayako_helpdesk], Kayako Ticket Submission form, View tickets will be displayed at frontend of wordpress.
