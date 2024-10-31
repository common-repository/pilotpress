=== PilotPress ===
Contributors: ONTRAPORT
Tags: ONTRAPORT, OfficeAutoPilot, Sendpepper, Moonray, ONTRAPORT, Pilotpress, Oap, Membership Site, Customer Center, Affiliate Center, Integration
Requires at least: 3.6
Tested up to: 6.4
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

PilotPress allows you to have a website, membership site, customer center, and a partner center integrated together with ONTRAPORT.

== Description ==


PilotPress is a WordPress plug-in that brings the power of [ONTRAPORT](http://ontraport.com) to the WordPress platform, specifically:

*   Create an Affiliate Network with customized login and reporting
*   A Customer Center for handling purchases and subscriptions
*   Insert ONTRAPORT Forms (such as SmartForms and OrderForms)
*   Create a Membership Site with Access Levels and Protected Content
        Create Website Subscriber users for Contacts
        Create Signup Forms that create Contacts / Website Subscribers
        Show / Hide Pages based on Membership Level
*   Track Page Views within ONTRAPORT
*   Merge Fields for logged-in users

Please note that an [ONTRAPORT](http://ontraport.com) paid account is required for this plugin to function. Data from your WordPress installation will be sent through our API hosted on api.ontraport.com.

== Installation ==

1. Upload the PilotPress plugin to the "/wp-content/plugins/" directory
2. Activate the plugin through the "Plugins" menu in WordPress
3. Configure PilotPress to your ONTRAPORT account through "Settings" and "PilotPress" menu in WordPress using your Application ID and API key. 
You can retrieve these in the Sites Menu of your ONTRAPORT account by clicking on "WordPress" and then click on "Existing WordPress Site" on the left. On this page input your WordPress URL  and click save to retrieve the Application ID and API Key to use in WordPress. 
4. You can now activate your customer center and affiliate center in the PilotPress settings in WordPress and modify your pages to display to your members based on their membership level.
NOTE: PilotPress depends on having the php-xml module installed.

== Frequently Asked Questions ==

= Where can I get further help? =

You can reach support Monday thru Thursday from 6:00am – Midnight PST, and Friday from 6:00am - 9:00pm. Saturday and Sunday we’re available from 9:00am – 9:00pm PST.

Please email us at support@ONTRAPORT.com, call us at 805.568.1424, or login and submit a ticket (in the help section, link in the lower right).

= How will I charge for my membership site? =

Luckily we integrate with a number of gateways ([click our list of gateways](https://support.ontraport.com/hc/en-us/articles/217881648-Integrating-a-Payment-Gateway#list-of-available-payment-gateways)) Once you get a gateway, you can put it on an order form and do full payment processing.

= What kind of content should I be creating for my membership site? =

Content that relates to your business, and content that you can successfully sell. Be sure to consider what marketing strategies you can implement to ensure a successful product launch!

= What if I don't have a Login page? =

If you have Pilotpress installed, you can create a new page and simply enter "[login_page]" (excluding quotes) and that page will then have a login box for users to use.

== Changelog ==
= 2.0.34 = 
* Update to handle deprecated wp class 

= 2.0.33 =
* Updated src link for lock icon

= 2.0.32 =
* Clean up some deprecations with newer php

= 2.0.31 =
* Moar security fixes

= 2.0.30 =
* Fix security access control

= 2.0.29 =
* Fix for lostpassword on newer versions

= 2.0.28 =
* Future proofed up to php version 8.1

= 2.0.27 =
* Update our tracking script to handle google

= 2.0.26 =
* Fixed a PHP error involving undefined user roles arrays

= 2.0.25 =
* Fix some php warnings
* Fix older use of method_exists, swapped to function_exists


= 2.0.24 =
* Campaigns renamed to Automations

= 2.0.23 =
* Fix Php Notice

= 2.0.22 =
* Fix issue with redirect for Smart Plugin Manager

= 2.0.21 =
* Fix some more PHP warnings

= 2.0.20 =
* Fix some more PHP warnings

= 2.0.19 =
* Fix some PHP warnings

= 2.0.18 =
* Re-fixed issue causing logout when multiple sessions of same user logged in.

= 2.0.17 =
* Revert from 2.0.16

= 2.0.16 =
* Fixed issue causing logout when multiple sessions of same user logged in.

= 2.0.15 =
* Fixed issue preventing page visits from tracking on insecure pages.

= 2.0.14 =
* Fixed issue preventing login form from showing when visiting a protected page and setting the on error page to the login.
* Reverted fix for "Post Preview" pages not properly hiding content.

= 2.0.13 =
* Fixed issue causing "Post Preview" pages to not properly hide content if the first post on the page is unprotected.
* Updated API calls referencing older endpoints to point to api.ontraport.com

= 2.0.12 =
* Fixed issue causing Customer Center and Partner Center to not be viewable.
* Reverted fix for "Post Preview" pages not properly hiding content.

= 2.0.11 =
* Added more validation & sanitization of input data
* Brought in external .css and .js files
* Fixed issue causing "Post Preview" pages to not properly hide content if the first post on the page is unprotected.

= 2.0.10 =
* Fixed Preview Selected Membership level with Gutenberg editor
* Fixed issue preventing video/form insertion
* Reduced unnecessary API calls
* Fixed potential mis-syncs of users who use both their email and username to log in to the same account
* Updated 'Tested To' version to 5.2.2
* Squashed misc warnings/notices

= 2.0.9 =
* Fixed Uncaught Fatal

= 2.0.8 =
* Fixed MISC Pilotpress PHP warnings

= 2.0.7 =
* Fixed issue with not displaying remember me checkbox when inserted on Ontrapages Plugin

= 2.0.6 =
* Fixed issue causing inserted forms to insert incorrectly
* Fixed issue which caused the "Invalid Email" error message to malfunction on the default WordPress login form

= 2.0.5 =
* Fixed issue causing comments section to display while content is protected by login page

= 2.0.4 =
* Fixed issue causing users to not be able to utilize the theme editor with PilotPress enabled
* Fixed issue that caused users to redirect to the home page when logging in after viewing a protected category
* Added option to use a more discrete nickname for WP users created by PilotPress
* Fixed issue that caused PURL merge shortcodes to not merge correctly sometimes
* Fixed issue that caused users trying to view a protected category page to hit a page stating that there was nothing found
* Fixed issue causing the show_if has_tag shortcode to not process if more than 1 tag was listed
* Fixed issue that caused users to temporarily lose their access levels if they exited their browsing session on Mac

= 2.0.3 =
* Added better logic that will help reduce the amount of erroneous API calls PP makes when trying to reconnect to ONTRAPORT

= 2.0.2 =
* Fixed issue that caused Promotional Tool shortcodes to not show up correctly
* Fixed issue that caused custom login boxes to incorrectly display error messages

= 2.0.1 =
* Added new shortcode: [pilotpress_sync_contact] to allow a manual resync when the shortcode is processed
* Added above shortcode to the TinyMCE plugin drop-down menu
* Fixed WYSIWYG editor conflict between PilotPress and OptimizePress
* Login redirect fixed for non-root domain installations of WordPress
* Updated the way forms are listed under the add media button pop-up
* Fixed incorrect error reporting issue when developer's debug mode is active
* Plugin conflict between Search and Filter Pro, and PilotPress resolved
* Fixed issue where a failed login on a custom login page would strip styling

= 2.0.0 =
* Moved stored data out of sessions into transients
* Added ability to sync users with ONTRAPORT, works with existing WordPress users
* Added option to preview a post or page as a specific membership level
* Added support for ONTRAform insertion
* Added drop menu in tiny MCE editor to insert shortcodes
* Added new shortcode does_not_have_tag
* Added option to add new users to campaigns
* Added "Forgot Password?" link to bottom of login page by default
* Added a button to clear all stored PilotPress transients under advanced settings
* Added HTML5 video encoding support
* Added "PilotPress Levels" column support for custom post types
* Added ability to log page visits to a contacts contact log after successful log in
* Added dropdown to the WYSIWYG editor to show our shortcodes
* Added new hooks for:
    * Pre & Post ONTRAport member authentication
    * Pre & Post user logout
    * User login failed
    * User lost password
    * Content hiding
* Fixed redirection after user login
* Fixed redirection when both centers are active, added priority setting on settings page
* Fixed issue where PilotPress not load tiny MCE plugins correctly
* Fixed bug where any extra space in API key or App ID would invalidate PilotPress
* Fixed bug where security plugins could cause the WYSIWYG editor not to load properly

= 1.9.9 =
* Added first name and last name to new user sign up

= 1.9.8 =
* Fixed some deprecated methods
* Updated Documentation

= 1.9.7 =
* Fixed bug with filtering posts in the admin dashboard not filtering properly on criteria
* Fixed show_if has_any shortcode not hiding/showing content correctly
* Fixed warnings showing up in Posts if no membership levels were selected for category protection

= 1.9.6 =
* Added message when username already exists when you try to update it.

= 1.9.5 =
* Add support to all PilotPress to redirect Customers and Partners to an ONTRApage of their choosing

= 1.9.4 =
* Bug fix to address issue where pages continue to redirect or exit after their category protection is removed

= 1.9.3 =
* Bug fixes to address login issues and possible conflicts with other plugins

= 1.9.2 =
* Bug fixes to address php notices being generated when protecting a category with no permissions set

= 1.9.1 =
* Bug fixes to address issue of empty array throwing warning in category protection

= 1.9 =
* Adding category protection to PilotPress

= 1.8.6 =
* Convert Tracking script to use the one served by our CDN... improves speed

= 1.8.5 =
* Fixed various notices and added custom filters to override routeable pages returned by PilotPress

= 1.8.4 =
* Adds PilotPress functionality to ONTRApages custom post types
* Fixed issue with nested shortcodes not populating pilotpress_field shortcodes

= 1.8.3 =
* Fixed issue with the password reset email sending to the user every time they logged in.

= 1.8.2 =
* Reduced the api calls needed for PilotPress every page load for logged out users.

= 1.8.1 =
* Added new PilotPress text widget to allow merge codes to merge easily and without any extra modifications to your theme.

= 1.8.0 =
* Decreased page load times and login times dramatically by defferring several database calls to only when you need them instead of every page load.
* Fixed issue with ONTRAPORT merge codes not merging properly when the name had html entitites.
* Fixed issue with the Customer Center not properly displaying after closing the browser and you logged in through the Remember Me option.

= 1.7.7 =
* Fixed session not allowing customer center to show properly if browser was closed and re-opened.

= 1.7.6 =
* Fixed tracking.js not using the 3.0 tracking domain when necessary.

= 1.7.5 =
* Fixed issue from last release that did not properly show membership levels meta box when creating a new page/post

= 1.7.4 =
* Fixed issue where admin user could not connect to the Pilotpress API

= 1.7.3 =
* Fixed issue with logging in where the "remember me" option would not keep users logged in, if/when was browser closed and the session was destroyed
* Fixed issue with custom login form going to 404 page if WordPress was hosted on a subdomain

= 1.7.2 =
* Fixed bug where if your home page is set to a static page and is protected by a membership level it would still show the content.
* Fixes issue where WordPress trimmed the trailing and leading spaces when logging in however PilotPress did not.

= 1.7.1 =
* Fixed bug where featured images would not show up properly when creating a new post/page

= 1.7.0 =
* Added new admin option to add new WordPress registered users to ONTRAPORT with the option to add Tags or Sequences
* Added the ability to style the Customer Center with three new options (Primary Color , Secondary Color and Header Image)
* Updated Customer Center to be on one page instead of using tabs
* Added the ability to link to Invoices in the Customer Center
* Merged the Plugin Session Slap into PilotPress to keep sessions alive
* Added ability to sort table headers in Customer Center
* Fixed bug with the show_if shortcode not working properly if a membership level or tag has an HTML Entity;
* Fixed improper use of do_action('wp_login')

= 1.6.0j =
* Updated action hook for saving posts from save_post to pre_post_update
* Decreased payload for migrated accounts
* Moved jQuery-ui to only enqueue on admin pages

= 1.6.0i =
* Updated login form editor to fix styling where form was not working in some themes.
* Updated status bug when membership status was disabled on ONTRAPORT

= 1.6.0h =
* Fixed merge fields to work in WordPress version 3.9 and later.
* Fixed a bug where TinyMCE would strip out the Flowplayer code when switching from the visual editor to the text editor and back.
* Added the ability to customize the log-in page shortcode.
* Added security upgrade to send passwords hashed to ONTRAPORT for user authentication.
* Fixed a bug with the "not_contact" shortcode where it would not function if a user was not logged in.
* Improved page load speeds by moving the ONTRAPORT tracking script into the footer.
* Fixed a bug where a user not logged in trying to use the affiliate_center would redirect to wp_login.php instead of the PilotPress login page.
* Added two additional shortcode attributes to the show_if shortcode - 'not_cookied_contact' and 'is_cookied_contact'

= 1.6.0g =
* Fixed an issue where inserting multiple videos into one page could break flowplayer functionality.

= 1.6.0f =
* Fixed a bug introduced in 1.6.0e where logging out would result in an HTTP 500 error.

= 1.6.0e =
* Fixed a bug where the show_if shortcode would display content to users who were not logged in.
* Fixed a bug where PilotPress could enter an infinite redirect loop.
* Fixed a bug where some PilotPress features would break when the plugin files lived in a non-default directory.

= 1.6.0d =
* Fixed a bug introduced in 1.6.0c that broke the merge fields drop down in the page editor.

= 1.6.0c =
* Fixed a bug where the show_if shortcode would always display the hidden content if the contact has no membership levels.
* Support for ONTRAPORT accounts.

= 1.6.0b =
* Membership levels are now updated in real time and do not require the user to logout and login.

= 1.6.0 =
* Important security updates.
* Merge fields can now be nested within pilotpress_show_if blocks.
* Merge fields are now updated in real time and do not require the user to logout and login.
* The following shortcodes can now be used: pilotpress_show_if, pilotpress_login_page, pilotpress_field. These shortcodes should be used to avoid conflicts with other plugins. The existing shortcodes will continue to function.
* Fixed the "From Computer" and "From URL" tab on the "Add OfficeAutoPilot Media" page.

= 1.5.9b =
* Newly created users synced from OAP have the nickname field in WordPress properly set
* Updating the password in the customer center will no longer require a logout
* Updating the username in the customer center is less buggy
* Content placed on the [login_page] shortcode page will properly display on a failed username or password
* Fixed the home page not working with membership level content hiding

= 1.5.9 =
* Fixed an error which would cause the plugin to not activate when the PHP Setting short_open_tag is set to off

= 1.5.9 =
* Fixed a possible issue where editing a users password would fail with PilotPress enabled

= 1.5.8 =
* Fixed a potential bug which would cause logins to fail under certain scenarios

= 1.5.7 =
* Invalid login inputs on custom login pages now stay at the custom login page, forgotten password function does not cause accounts to go out of sync

== Upgrade Notice ==
= 2.0.34 = 
* Update to handle deprecated wp class 

= 2.0.33 =
* Updated src link for lock icon

= 2.0.32 =
* Clean up some deprecations with newer php

= 2.0.31 =
* Moar security fixes

= 2.0.30 =
* Fix security access control

= 2.0.29 =
* Fix for lostpassword on newer versions

= 2.0.28 =
* Future proofed up to php version 8.1

= 2.0.27 =
* Update our tracking script to handle google

= 2.0.26 =
* Fixed a PHP error involving undefined user roles arrays

= 2.0.25 =
* Fix some php warnings
* Fix older use of method_exists, swapped to function_exists

= 2.0.24 =
* Renamed Campaigns to Automations

= 2.0.23 =
* Fix Php Notice

= 2.0.22 =
* Fix issue with redirect for Smart Plugin Manager

= 2.0.21 =
* Fix some more PHP warnings

= 2.0.20 =
* Fix some more PHP warnings

= 2.0.19 =
* Fix some PHP warnings

= 2.0.18 =
* Re-fixed issue causing logout when multiple sessions of same user logged in.

= 2.0.17 =
* Revert from 2.0.16.

= 2.0.16 =
* Fixed issue causing logout when multiple sessions of same user logged in.

= 2.0.15 =
* Fixed issue preventing page visits from tracking on insecure pages.

= 2.0.14 =
* Fixed issue preventing login form from showing when visiting a protected page and setting the on error page to the login.
* Reverted fix for "Post Preview" pages not properly hiding content.

= 2.0.13 =
* Fixed issue causing "Post Preview" pages to not properly hide content if the first post on the page is unprotected.
* Updated API calls referencing older endpoints to point to api.ontraport.com

= 2.0.12 =
* Fixed issue causing Customer Center and Partner Center to not be viewable.
* Reverted fix for "Post Preview" pages not properly hiding content.

= 2.0.11 =
* Added more validation & sanitization of input data
* Brought in external .css and .js files.
* Fixed issue causing "Post Preview" pages to not properly hide content if the first post on the page is unprotected.

= 2.0.10 =
* Fixed Preview Selected Membership level with Gutenberg editor
* Fixed issue preventing video/form insertion
* Reduced unnecessary API calls
* Fixed potential mis-syncs of users who use both their email and username to log in to the same account
* Updated 'Tested To' version to 5.2.2

= 2.0.9 =
* Fixed Uncaught Fatal

= 2.0.8 =
* Fixed MISC Pilotpress PHP warnings

= 2.0.7 =
* Fixed issue with not displaying remember me checkbox when inserted on Ontrapages Plugin

= 2.0.6 =
* Fixed issue causing inserted forms to insert incorrectly
* Fixed issue which caused the "Invalid Email" error message to malfunction on the default WordPress login form

= 2.0.5 =
* Fixed issue causing comments section to display while content is protected by login page

= 2.0.4 =
* Fixed issue causing users to not be able to utilize the theme editor with PilotPress enabled
* Fixed issue that caused users to redirect to the home page when logging in after viewing a protected category
* Added option to use a more discrete nickname for WP users created by PilotPress
* Fixed issue that caused PURL merge shortcodes to not merge correctly sometimes
* Fixed issue that caused users trying to view a protected category page to hit a page stating that there was nothing found
* Fixed issue causing the show_if has_tag shortcode to not process if more than 1 tag was listed
* Fixed issue that caused users to temporarily lose their access levels if they exited their browsing session on Mac

= 2.0.3 =
* Added better logic that will help reduce the amount of erroneous API calls PP makes when trying to reconnect to ONTRAPORT

= 2.0.2 =
* Fixed issue that caused Promotional Tool shortcodes to not show up correctly
* Fixed issue that caused custom login boxes to incorrectly display error messages

= 2.0.1 =
* Added new shortcode: [pilotpress_sync_contact] to allow a manual resync when the shortcode is processed
* Added above shortcode to the TinyMCE plugin drop-down menu
* Fixed WYSIWYG editor conflict between PilotPress and OptimizePress
* Login redirect fixed for non-root domain installations of WordPress
* Updated the way forms are listed under the add media button pop-up
* Fixed incorrect error reporting issue when developer's debug mode is active
* Plugin conflict between Search and Filter Pro, and PilotPress resolved
* Fixed issue where a failed login on a custom login page would strip styling

= 2.0.0 =
* Moved stored data out of sessions into transients
* Added ability to sync users with ONTRAPORT, works with existing WordPress users
* Added option to preview a post or page as a specific membership level
* Added support for ONTRAform insertion
* Added drop menu in tiny MCE editor to insert shortcodes
* Added new shortcode does_not_have_tag
* Added option to add new users to campaigns
* Added "Forgot Password?" link to bottom of login page by default
* Added a button to clear all stored PilotPress transients under advanced settings
* Added HTML5 video encoding support
* Added "PilotPress Levels" column support for custom post types
* Added ability to log page visits to a contact's contact log after successful log in
* Added dropdown to the WYSIWYG editor to show our shortcodes
* Added new hooks for:
    * Pre & Post ONTRAport member authentication
    * Pre & Post user logout
    * User login failed
    * User lost password
    * Content hiding
* Fixed redirection after user login
* Fixed redirection when both centers are active, added priority setting on settings page
* Fixed issue where PilotPress did not load the tiny MCE plugins correctly
* Fixed bug where any extra space in API key or App ID would invalidate PilotPress
* Fixed bug where security plugins could cause the WYSIWYG editor not to load properly

= 1.9.9 =
* Added first name and last name to new user sign up

= 1.9.8 =
* Fixed some deprecated methods
* Updated Documentation

= 1.9.7 =
* Fixed bug with filtering posts in the admin dashboard not filtering properly on criteria
* Fixed show_if has_any shortcode not hiding/showing content correctly
* Fixed warnings showing up in Posts if no membership levels were selected for category protection

= 1.9.6 =
* Added message when username already exists when you try to update it.

= 1.9.5 =
* Add support to all PilotPress to redirect Customers and Partners to an ONTRApage of their choosing

= 1.9.4 =
* Bug fix to address issue where pages continue to redirect or exit after their category protection is removed

= 1.9.3 =
* Bug fixes to address login issues and possible conflicts with other plugins

= 1.9.2 =
* Bug fixes to address php notices being generated when protecting a category with no permissions set

= 1.9.1 =
* Bug fixes to address issue of empty array throwing warning in category protection

= 1.9 =
* Adding category protection to PilotPress

= 1.8.6 =
* Convert Tracking script to use the one served by our CDN... improves speed

= 1.8.5 =
* Fixed various notices and added custom filters to override routeable pages returned by PilotPress

= 1.8.4 =
* Adds PilotPress functionality to ONTRApages custom post types
* Fixed issue with nested shortcodes not populating pilotpress_field shortcodes

= 1.8.3 =
* Fixed issue with the password reset email sending to the user every time they logged in.

= 1.8.2 =
* Reduced the api calls needed for PilotPress every page load for logged out users.

= 1.8.1 =
* Added new PilotPress text widget to allow merge codes to merge easily and without any extra modifications to your theme.

= 1.8.0 =
* Decreased page load times and login times dramatically by defferring several database calls to only when you need them instead of every page load.
* Fixed issue with ONTRAPORT merge codes not merging properly when the name had html entitites.
* Fixed issue with the Customer Center not properly displaying after closing the browser and you logged in through the Remember Me option.

= 1.7.7 =
* Fixed session not allowing customer center to show properly if browser was closed and re-opened.

= 1.7.6 =
* Fixed tracking.js not using the 3.0 tracking domain when necessary.

= 1.7.5 =
* Fixed issue from last release that did not properly show membership levels meta box when creating a new page/post

= 1.7.4 =
* Fixed issue where admin user could not connect to the Pilotpress API

= 1.7.3 =
* Fixed issue with logging in where the "remember me" option would not keep users logged in, if/when was browser closed
* Fixed issue with custom login form going to 404 page if WordPress was hosted on a subdomain

= 1.7.2 =
* Fixed bug where if your home page is set to a static page and is protected by a membership level it would still show the content.
* Fixes issue where WordPress trimmed the trailing and leading spaces when logging in however PilotPress did not.

= 1.7.1 =
* Fixed bug where featured images would not show up properly when creating a new post/page

= 1.7.0 =
* Added new admin option to add new WordPress registered users to ONTRAPORT with the option to add Tags or Sequences
* Added the ability to style the Customer Center with three new options (Primary Color , Secondary Color and Header Image)
* Updated Customer Center to be on one page instead of using tabs
* Added the ability to link to Invoices in the Customer Center
* Merged the Plugin Session Slap into PilotPress to keep sessions alive
* Added ability to sort tables in Customer Center
* Fixed bug with the show_if shortcode not working properly if a membership level or tag has an HTML Entity;
* Fixed improper use of do_action('wp_login')

= 1.6.0j =
* Updated action hook for saving posts from save_post to pre_post_update
* Decreased payload for migrated accounts
* Moved jQuery-ui to only enqueue on admin pages

= 1.6.0i =
* Updated login form editor to fix styling where form was not working in some themes.
* Updated status bug when membership status was disabled on ONTRAPORT

= 1.6.0h =
* Fixed merge fields to work in WordPress version 3.9 and later.
* Fixed a bug where TinyMCE would strip out the Flowplayer code when switching from the visual editor to the text editor and back.
* Added the ability to customize the log-in page shortcode.
* Added security upgrade to send passwords hashed to ONTRAPORT for user authentication.
* Fixed a bug with the "not_contact" shortcode where it would not function if a user was not logged in.
* Improved page load speeds by moving the ONTRAPORT tracking script into the footer.
* Fixed a bug where a user not logged in trying to use the affiliate_center would redirect to wp_login.php instead of the PilotPress login page.
* Added two additional shortcode attributes to the show_if shortcode - 'not_cookied_contact' and 'is_cookied_contact'

= 1.6.0g =
* Fixed an issue where inserting multiple videos into one page could break flowplayer functionality.

= 1.6.0f =
* Fixed a bug introduced in 1.6.0e where logging out would result in an HTTP 500 error.

= 1.6.0e =
* Fixed a bug where the show_if shortcode would display content to users who were not logged in.
* Fixed a bug where PilotPress could enter an infinite redirect loop.
* Fixed a bug where some PilotPress features would break when the plugin files lived in a non-default directory.

= 1.6.0d =
* Fixed a bug introduced in 1.6.0c that broke the merge fields drop down in the page editor.

= 1.6.0c =
* Fixed a bug where the show_if shortcode would always display the hidden content if the contact has no membership levels.
* Support for ONTRAPORT accounts.

= 1.6.0b =
* Membership levels are now updated in real time and do not require the user to logout and login.

= 1.6.0 =
* Important security updates.
* Merge fields can now be nested within pilotpress_show_if blocks.
* Merge fields are now updated in real time and do not require the user to logout and login.
* The following shortcodes can now be used: pilotpress_show_if, pilotpress_login_page, pilotpress_field. These shortcodes should be used to avoid conflicts with other plugins. The existing shortcodes will continue to function.
* Fixed the "From Computer" and "From URL" tab on the "Add OfficeAutoPilot Media" page.

= 1.5.9b =
* Newly created users synced from OAP have the nickname field in WordPress properly set
* Updating the password in the customer center will no longer require a logout
* Updating the username in the customer center is less buggy
* Content placed on the [login_page] shortcode page will properly display on a failed username or password
* Fixed the home page not working with membership level content hiding

= 1.5.9 =
* Fixed a possible issue where editing a users password would fail with PilotPress enabled

= 1.5.7 =
* Invalid login inputs on custom login pages now stay at the custom login page, forgotten password function does not cause accounts to go out of sync

== Using your WordPress site as a Membership Site (aka Protecting and revealing content) ==

Below are instructions on how to protect content in your membership site. There are two ways to do this:

1. First, you can drip content out over time (in a week-by-week type format).

2. Second, you can choose to reveal more content based on your customers' membership levels like Silver, Gold, and Platinum etc.  You will first need to have either added the Pilotpress plugin to your existing WordPress site or have started a new WordPress site within ONTRAPORT. Watch the 'Protect and Release Content' video to learn how to do both.

__Setting the Minimum Membership Level to view a page or post on your WordPress site__

Once you have created your membership levels (which you did during the WordPress site creation or when you added the plugin to your existing site), you will notice that those membership levels also appeared in the right-hand margin of your WordPress posts / pages under "PilotPress Options." These options are used to limit access to the specified page or post. This is where you will set a minimum membership level to view the page or post. Check off the levels that you want, check the 'Show in Navigation' box if you want this page title to appear in your navigation menu, and finally set your 'On Error' redirect. The 'On Error' redirect is that page that your users will be sent to in the case that they don't have the proper membership levels.

For time release posts or pages, you will set the minimum membership needed to view the post off to the right, and then create a sequence right within ONTRAPORT that members will be automatically subscribed to when they sign up for your site.  As they move along on the sequence, say from week 1, to week 2, their membership level will change accordingly and they will be able to view the next week's content.

__Setting up your system to release new content__

1. Go into the Admin menu, then into "WordPress Integration".   (Make sure you've created membership levels for your website. If you haven't, do that first).

2. Next, go to the Admin menu once again and click on "Product and Order Forms" to set up a new product if you haven't done so already.

3. The product, in this case, is the membership to your membership site. If you were selling a silver, gold, and platinum membership to your site, each of those would be its own separate product, and, each should have its own order form. If on the other hand, you're selling only one level of membership, you would only need to create one product. Note that if certain membership levels are free, or membership to the entire site is free, then you would simply set up a smartform for people to fill out to become members rather than an order form. You should also note that when you're creating your membership level products, you must indicate that the product is a digital product, set the product as a subscription, and set a subscription price and time.

4. Then, when you create your order form, you will set it so the order form itself subscribes your contacts to the sequence that will first send them a welcome email containing their login info, and which will also control their membership levels (if there are any). For example, moving them along from week to week, or month to month, etc.

5. For now, title the sequence something like "membership progression sequence" if you're planning to have this be a week-by-week content, or simply "membership login info", if you're going with the silver, gold, platinum format or simply a one membership level format. Set this sequence to be a 'step' sequence.  You will go back and actually flesh out the steps of the sequence afterward in the autopilot tab.

6. Create an Order Form that has an Active Response Rule which makes new members to your site

- Now, you will make a new active response rule that is located on an order form and it will actually turn this new contact into a member of your membership site, once they submit the order form. If you're unclear of what an active response rule is, please watch the video on active response rules.

- - First, give the active response rule a title. An example would be, "Make a new Member to my Membership Site.

- - Next, under "What Should Happen", select "Change Field to Value".

- - Then, set [field] to "membership level" and set [value] to the desired membership level. (Example "week 1" for time release content).

7. Create the membership Sequence to welcome client AND to deliver their login info:

- Now it's time to go into the autopilot tab and flesh out your "Membership Progression Sequence", so it will deliver your new customers' login info and move them along from week 1, to week 2, etc.

- - First, you will locate the step sequence you started (when you created your order form).

- - Next, you will set up an email step that goes out on a 15 minute delay (the system needs time to generate the membership site username and password) and thanks the new customer for joining and sends them their username and password.

- Merge the the login info into the very first email

- - Insert their username as a merge field and insert their password as a merge field as well.  Important Note: The 'User Name' and 'password merge field' is site- specific, so, find the website in question, then the select the "password" merge field underneath that specific site.  Also in this email, you would welcome them and remember to send them the link to the login page. Another Important Note Regarding the Password: The password merge field is unique and can only be sent once. In order to retrieve a password after this, a contact will have to retrieve their password from their login page, using the "forgot your password" link, or you can manually reset a contact's password in their contact record under "website subscribers".

- Add Rule Steps to the "Membership Progression Sequence"
If you are creating sequential content, as opposed to just the silver, gold, platinum type format, you will need to create rule steps that change your customers' membership levels forward, from say, week to week, as the weeks go by.

- - To do this, set a rule step to fire 7 days after that first email step. For example, add a rule to the sequence which moves them on to week 2, by changing their 'membership level' from week one, to week two.

- - For this rule, you would set the "What Should Happen" section to "Change Field to Value" and then select the "Membership Level" field as the one to have changed. Then, set it to change to the next specific level of membership, in this case, we'd set it to 'Week 2'. It would be the same moving forward to week 3, etc.

- - Next , you could create an email step to fire immediately after the rule step to notify your contact that they are able to login and access week 2's content, you may repeat this as many time as you wish. And, you don't have to do it all at once. You might add the first three weeks to start, then add a week or twos worth of additional content at a time later on. The process is the same either way.

- Note: Be sure to save each step as you go and then save your sequence when you're all through.

== Showing/Hiding Sections of Content on a Page ==

That's right, you can not only show or hide pages based on membership levels, but you can show or hide a portion of a page based on a few different criteria. PilotPress comes with the ability to reveal content using Shortcodes. These nifty little tags can be placed around blocks of text, forms, etc to allow you to personalize the content of the page based on membership level, tags or even if the contact is currently being tracked by ONTRAPORT. We realize that this is a bit cumbersome...and, given our commitment to giving you an awesome membership site with "no code to mess with, no-how", will have this feature usable right within the User Interface in a future PilotPress update, but it does work for now.

The Shortcodes currently available for use in PilotPress are as follows:

__Show Content Based on Membership Level__

[show_if has_one="Many,levels"] content [/show_if]

> Displays content only for members who are logged in and have ANY of the membership levels listed (separated by commas)

[show_if has_all="Many,levels"] content [/show_if]

> Displays content only for members who are logged in and have ALL of the membership levels listed

[show_if not_one="Many,levels"] content [/show_if]

> Displays content only for members who are logged in and do NOT have at least ONE of the membership levels listed

[show_if not_any="Many,levels"] content [/show_if]

> Displays content for members who are logged in and are missing any of the memberships listed.

__Show Content Based on Tags__

[show_if has_tag="Tag"] content [/show_if]

> Displays content only for members who are logged in and have the tag indicated by "Tag"

__Show Content Based if Contact is Identified__

[show_if is_contact] content [/show_if]

> Displays content if visitor is an identified contact in your database

[show_if not_contact] content [/show_if]

> Displays content if visitor is __not__ an identified contact in your database

[show_if is_cookied_contact] content [/show_if]

> Displays content if visitor has been identified as a contact through a previous login , or ONTRAPORT defined action (email click , form fillout , etc)

[show_if not_cookied_contact] content [/show_if]

> Displays content if visitor has __not__  been identified as a contact through a previous login , or ONTRAPORT defined action (email click , form fillout , etc)

[pilotpress_sync_contact]

> Allows you to re-sync a logged-in user with ONTRAPORT without having the user log in and out again. Runs only when the page first loads.

== Creating the Sign-Up Page ==

This is where your prospects/clients purchase their membership. It's the page that any sales emails, sales letters, and sales videos would point to.

1. First, go back to your WordPress account and click on "Pages", then click on "Add New".

2. Give it an appropriate title and write out your content in the text prompt. You might have a sales letter or a sales video.

3. Once you have added your sales content to the page, you'll insert the order form your prospects will fill out in order to convert themselves into customers.

4. You will achieve this by clicking the "ONTRAPORT" or "Add Media" button. (If you haven't already created the order form or forms for your membership site, you need to now, before moving on).

5. Next, you will simply select the order form you created earlier and paste it on the page. If you're offering multiple levels, like, silver, gold, and platinum, you may want to create separate pages for each that contains the respective order forms. This will give the pages a cleaner look (just link to the order page for each membership level from the main page).

6. Finish editing the page, proof it, and click on "Publish" (in the right-hand margin).

7. Next, click on "View Page" up at the top center to preview your page.
Once filled out, this form will take the customer to the "Thank-you" page which you set when you created the form. This most likely would be a page that thanks them, and tells them to go check their email for their login info and provides a link to the sign-in page.

8. When you're all through creating your signup page, click "Publish".  Now you will see that this page is available on your home menu.

== Adding Forms within WordPress ==

The WordPress integration allows you to add ONTRAPORT Smartforms, Order Forms, Affiliate Signup Forms, and Upsell forms to your WordPress pages.

To achieve this, you will first need to have added the PilotPress plugin to your existing WordPress site, or have started a new WordPress site within ONTRAPORT.

1. Log into your WordPress account.

2. When adding a new post or page, you will notice that there is a new icon for "Add ONTRAPORT Form".

3. Click on the icon.

4. Once clicked, this will bring up all the Smartforms, Order Forms, Affiliate Signup Forms, and Upsell Forms that you have created in ONTRAPORT.

5. When you select one, it will grab the code and insert the form right onto your page in the location where the cursor is. If you know some HTML, you can edit the way the form looks here using HTML mode. Otherwise, you'll need to edit the form from within the form editor in ONTRAPORT
