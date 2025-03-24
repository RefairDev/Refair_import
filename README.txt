=== Refair import Plugin ===
Tags: refair, Excel files import
Requires at least: 6.7.2
Tested up to: 6.7.2
Stable tag: 0.8
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin is used as a complementary part of the REFAIR plugin. It provide capability to import excels files describing despotis and materials associated as Wordpress REfait plugin custom post types as Deposit and materials.

== Description ==

1. Import from dedicated backoffice main menu entry.

2. Handle several errors on excel file structure and data inputs.

== Installation ==

1. Upload `invimport` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Set your API google key in invimport settings

== Changelog ==

= 0.6 - 24/03/2025 =
* Initial version

== Upgrade Notice ==

-

== Developement ==

* Prerequisite:
	Node.js: 20.17.0
	npm: 10.8.2
	composer: 2.4.2

* Initialization
	1. exec: `npm install`
	2. Customize **gulpfile.js** from **gulpfile.sample.js** with correct paths
	3. plugin rebuild command line:
		* for local server, exec command: 
			`gulp`
		* for archive, exec command:
	    	`gulp dist`
	4. plugin generated is either in working directory either in dist folder
