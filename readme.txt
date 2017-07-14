=== Apocalypse Meow ===
Contributors: blobfolio
Donate link: https://blobfolio.com/plugin/apocalypse-meow/
Tags: security, login, password requirements, cats, generator, wp-content, PHP, malware, exploit, brute-force, system admin, session management, opsec
Requires at least: 4.4
Tested up to: 4.8
Stable tag: trunk
License: WTFPL
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A simple, light-weight collection of tools to harden WordPress security and help mitigate common types of attacks.

== Description ==

Apocalypse Meow's main focus is addressing WordPress security issues related to user accounts and logins. This includes things like:

 * Brute-force login-in protection;
 * Customizable password strength requirements;
 * XML-RPC and WP-REST access controls;
 * Account access alerts;
 * Searchable access logs (including failed login attempts and temporary bans);
 * User enumeration prevention;
 * Miscellaneous Core and template options to make targetted hacks more difficult;

Security is an admittedly technical subject, but Apocalypse Meow strives to help educate "normal" users about the nature of common web attacks, mitigation techniques, etc. Every option contains detailed explanations and links to external resources with additional information.

Knowledge is power!

For the *less* normal among us — system administrators, developers, and other IT professionals — there is also a [Premium Version](https://blobfolio.com/plugin/apocalypse-meow/), packed with administrative tools, data visualizations and export functionality, and complete [WP-CLI](https://wp-cli.org/) integration for those with nerdier workflows.

== Requirements ==

Due to the advanced nature of some of the plugin features, there are a few additional server requirements beyond what WordPress itself requires:

 * WordPress 4.4+.
 * PHP 5.6 or later.
 * PHP extensions: bcmath, date, filter, json, pcre.
 * `CREATE` and `DROP` MySQL grants.
 * Single-site Installs (i.e. Multi-Site is not supported).

Please note: it is **not safe** to run WordPress atop a version of PHP that has reached its [End of Life](http://php.net/supported-versions.php). As of right now, that means your server should only be running **PHP 5.6 or newer**.

Future releases of this plugin might, out of necessity, drop support for old, unmaintained versions of PHP. To ensure you continue to receive plugin updates, bug fixes, and new features, just make sure PHP is kept up-to-date. :)

== Premium Version ==

Apocalypse Meow's proactive security hardening and attack mitigation features are completely **free**, and always will be.

The [Premium Version](https://blobfolio.com/plugin/apocalypse-meow/) is intended for IT professionals like system administrators and developers, who require more control over the data and workflow.

This version comes with a bunch of advanced tools, offering the ability to:

 * Reset passwords site-wide (with or without email notifications);
 * Detect and revoke old passwords hashed with MD5;
 * Rename the dangerous default "admin" and "administrator" usernames;
 * View and revoke individual user sessions;
 * Export login data in CSV format;
 * Backup and restore plugin settings;
 * Access to hooks and filters to interact with the brute-force login operations;
 * Run operations and view data through [WP-CLI](https://wp-cli.org/);

To learn more, visit [blobfolio.com](https://blobfolio.com/plugin/apocalypse-meow/).

== Frequently Asked Questions ==

= Is this plugin compatible with WPMU? =

No, sorry. This plugin may only be installed on single-site WordPress instances.

= How does the Community Pool Blocklist Work? =

The Community Pool is a new opt-in feature that combines attack data from your site with other sites running in pool mode to produce a global blocklist.

In other words, an attack against one becomes an attack against all!

The blocklist data is conservatively filtered using a tiered and weighted ranking system based on activity shared within the past 24 hours. For an IP address to be eligible for community banning, it must be independently reported from multiple sources and have a significant amount of total failures.

Your site's whitelist is always respected. Failures from whitelisted IPs will never be sent to the pool, and if the pool declares a ban for an IP you have whitelisted, your site will not ban it.

For more information, check out the Community Pool settings page.

= How do I unban a user? =

The Login Activity page will show any active bans in the top/right corner. You can click the button corresponding to the victim to remove the ban.

If you accidentally banned yourself and cannot access the backend, you have a few options:

 * Wait until the defined time has elapsed;
 * Login from a different IP address (tip: use your cellphone (via data, not Wifi));
 * Ask a friend to login and pardon you;
 * Temporarily de-activate the plugin by renaming the `apocalypse-meow` plugin folder via FTP;

Remember: you can (and should) whitelist any IP addresses that you commonly log in from. This is done through the Settings pgae.

= Can I see the passwords people tried when logging in? =

Of course not!  Haha.  Apocalypse Meow is here to solve security problems, not create them.  Only usernames and IP addresses are stored.

= Will the brute-force log-in prevention work if my server is behind a proxy? =

As of version 1.5.0, it is now possible to specify an alternative `$_SERVER` variable Apocalypse Meow should use to determine the visitor's "true" IP.  It is important to note, however, that depending on how that environmental variable is populated, the value might be forgeable.  Nonetheless, this should be better than nothing!

= Multi-Server Setup =

Apocalypse Meow tracks login history in the database. If your WordPress site is spread across multiple load-balanced servers, they must share access to a master database, or else tracking will only occur on a per-node basis.

== Log Monitoring ==

Some robots are so dumb they'll continue trying to submit credentials even after the login form is replaced, wasting system resources and clogging up the log-in history table.  One way to mitigate this is to use a server-side log-monitoring program like [Fail2Ban](http://www.fail2ban.org/) or [OSSEC](https://ossec.github.io/) to ban users via the firewall.

Apocalypse Meow produces a 403 error when a banned user requests the login form. Your log-monitoring rule should therefore look for repeated 403 responses to `wp-login.php`.  Additionally, some robots are unable to follow redirects; if your login form requires SSL, you should also ban repeated 301/302 responses to catch those fools.

If you have enabled user enumeration protection with the `die()` option, requests for `?author=X` will produce a 400 response code which can be similarly tracked.

== Installation ==

Nothing fancy!  You can use the built-in installer on the Plugins page or extract and upload the `apocalypse-meow` folder to your plugins directory via FTP.

To install this plugin as [Must-Use](https://codex.wordpress.org/Must_Use_Plugins), download, extract, and upload the `blob-mimes` folder to your mu-plugins directory via FTP. See the [MU Caveats](https://codex.wordpress.org/Must_Use_Plugins#Caveats) for more information about getting WordPress to load an MU plugin that is in a subfolder.

Please note: MU Plugins are removed from the usual update-checking process, so you will need to handle future updates manually.

== Screenshots ==

1. View and search the login history and manage banned users.
2. All settings include detailed explanations, suggestions, and links to additional resources. Not only will your site be vastly more secure, you'll learn a lot!
3. The Community Pool: the login blocklist can ultimately be extended to include community-reported attack data, vastly increasing the effectiveness of the brute-force login mitigation.
4. Pro: simple but sexy statistics.
5. Pro: a ton of additional security and management tools for system administrators, including an ability to view and revoke individual user sessions.
6. Pro: a full suite of WP-CLI tools, hookable functions and filters to interact with or extend the login protection features, read-only configurations, and detailed documentation covering it all!

== Changelog ==

= 21.0.3 =
* [Change] Update domain database.
* [Fix] Localization issue.
* [Misc] Shrink library size.

= 21.0.2 =
* [Misc] Clarify Community Pool ban logic.
* [New] Ahora en Español.

= 21.0.1 =
* [Fix] Fix URL protocols for sites that serve mixed content.

= 21.0.0 =
* [New] This is a major new release, re-coded from the ground up for better performance and security, and packed with tons of new features. Enjoy!
* [New] Ability to track user enumeration attempts.
* [New] Community Blocklist integration.
* [New] Premium Version featuring tons of additional tools, CLI access, and more!
* [Change] The code has been re-licened under [WTFPL](http://www.wtfpl.net).
* [Misc] Apocalypse Meow now requires PHP 5.6 or newer.

= 20.2.0 =
* [Misc] Add an admin notice for users running out-of-support versions of PHP.
* [Misc] This will be the last release supporting PHP 5.4+. Future releases will require PHP 5.6+.

= 20.1.8 =
* [New] Ability to control access to WP-REST requests.
* [Fix] Extend user enumeration protection to API requests.

= 20.1.7 =
* [Fix] pass-by-reference notice.

= 20.1.6 =
* [Fix] IPv6 whitelist bug.

= 20.1.5 =
* [New] Option to mitigate phishing attempts with `rel=noopener`.
* [New] Additional common password checks.
* [Misc] Admin area improvements.

= 20.1.4 =
* [Change] Show number of remaining attempts after login failure.
* [Fix] Failed login attempts not always expiring.

= 20.1.3 =
* [Fix] Layering bug that could make the Settings > Save button unclickable.

= 20.1.2 =
* [Fix] Address PHP notice.

= 20.1.1 =
* [New] The plugin has been completely rewritten from the ground up to provide a cleaner interface, faster performance, and additional features.

= 2.2.0 =
* [New] Option to disable XML-RPC.
* [New] Option to remove adjacent post meta tags.
* [New] Support plugin configuration via `wp-config.php`.

= 2.1.2 =
* [Change] Common password list has been expanded to around 500 entries.

= 2.1.1 =
* [Change] Tweak nonce error display.

= 2.1.0 =
* [New] Option to add Nonce field to the login form.
* [New] Email alerts after login from new location.

= 2.0.1 =
* [Fix] More robust username retrieval.

= 2.0.0 =
* [Fix] Forgot password reset enforces password strength rules.
* [New] Don't allow Top 25 Most Common passwords ever.
* [Change] Move database cleanup to WP Cron.
* [Misc] Code clean-up.

= 1.7.0 =
* [New] Ability to clear unclaimed pardons.

= 1.6.0 =
* [New] In honor of Heartbleed, there is now a tool for resetting all user passwords en masse.

= 1.5.0 =
* [New] Allow alternate `$_SERVER` variables for proxy installations (thanks `jjfalling`).
* [Misc] Code clean-up.

= 1.4.5 =
* [New] Warn administrators on settings page of potential proxy/intranet-type issues.
* [Fix] Only show `.htaccess` options on Apache servers.
* [Change] Use `wp_die()` for Apocalypse screen.
* [Change] Database maintenance on by default.

= 1.4.4 =
* [Misc] File clean-up.

= 1.4.3 =
* [Fix] Ensure variables are declared at activation.

= 1.4.2 =
* [Fix] Replace deprecated `$wpdb->escape()` with `esc_sql()`.

= 1.4.1 =
* [Fix] Replaced a couple functions that are deprecated as of PHP 5.5.0.

= 1.4.0 =
* [New] Log-in jail page to view currently banned IPs.
* [New] Ability to temporarily pardon a banned IP.
* [Fix] Log-in history now displayed in viewer's timezone.

= 1.3.6 =
* [Fix] Call-time pass-by-reference warning/error in PHP 5.3+.

= 1.3.5 =
* [Change] Fail window unit converted minutes.
* [Misc] More efficient logging of Apocalypse triggers.
* [Misc] Simplified Apocalypse page options.
* [Fix] Database upgrade procedure skipped.

= 1.3.4 =
* [Change] Lowered data retention minimum to 10 days.
* [New] Option to manually clear data.
* [Fix] Uninstallation now removes all plugin data/settings.
* [New] Option to disable theme/plugin editor.
* [Change] Prevent installation on WPMU blogs.
* [Fix] Use `$_SERVER` instead of `getenv()` as it is more compatible across server environments.
* [Fix] Minor bug fixes.

= 1.3.3 =
* [New] Log-in statistics.
* [Change] Storing UA string with log-in attempt is now optional (default disabled).
* [Misc] Log-in protection settings now hidden if log-in protection is disabled.
* [Misc] Database maintenance settings now hidden if maintenance is disabled.

= 1.3.2 =
* [Misc] Use existing WP CSS for log-in history table.
* [Change] Set 403 status header when displaying Apocalypse screen.

= 1.3.1 =
* [Misc] Compatibility with WP 3.5.
* [Misc] All queries now run through $wpdb.

= 1.3.0 =
* [New] Ability to rename the default WordPress user to something less .predictable.
* [Fix] Minor bug fixes.

= 1.2.0 =
* [New] Ability to disable the direct execution of PHP scripts in wp-content/.
* [Change] Re-organized the settings page.

= 1.1.0 =
* [New] Customizeable page title and content for the Apocalypse page;
* [New] Apocalypse page display logging.
* [Fix] Improved timestamp handling.
* [Change] Un-embedded kitten graphic for improved support with older browsers.

= 1.0.0 =
* [New] Apocalypse Meow is born!

== Upgrade Notice ==

= 21.0.3 =
This is a small maintenance release which fixes a localization issue, improves domain name handling, and comes with a reduced disk footprint.

= 21.0.2 =
The Community Pool blocklist logic is now better explained and the plugin has been translated into Español.

= 21.0.1 =
This releases contains a small bug fix affecting sites that serve content over both HTTP and HTTPS.

= 21.0.0 =
This is a major new release, re-coded from the ground up for better performance and security, and packed with tons of new features. Enjoy!

= 20.2.0 =
Add an admin notice for users running out-of-support versions of PHP. Note: this will be the last release compatible with versions of PHP older than 5.6.

= 20.1.8 =
Add ability to restrict or disable WP-REST access and extend user enumeration protection to cover WP-REST requests.

= 20.1.7 =
Fix pass-by-reference notice.

= 20.1.6 =
Fix IPv6 whitelist bug.

= 20.1.5 =
Option to add rel=noopener to target=_blank links, additional common password checks, and miscellaneous admin area improvements.

= 20.1.4 =
Show number of remaining attempts after login failure, fix failed logins not always expiring.

= 20.1.3 =
Fix layering bug that can make the Settings > Save button unclickable.

= 20.1.2 =
Minor fix to remove PHP notice.

= 20.1.1 =
The plugin has been completely rewritten from the ground up to provide a cleaner interface, faster performance, and additional features. Please backup your database before upgrading (it should be fine, but safety first!).

= 2.2.0 =
Options to disable XML-RPC and remove adjacent post meta tags. You can also now set many plugin options via wp-config.php. Refer to the FAQ for a complete list.

= 2.1.2 =
The common password list has been expanded to around 500 entries.

= 2.1.1 =
Minor update, tweak login nonce error display.

= 2.1.0 =
Ability to add a nonce field to the login form and receive email alerts after a login is made from a new location.

= 2.0.1 =
Minor update, more robust username retrieval.

= 2.0.0 =
Fixed a forgot password reset bug, added test for common passwords, and conducted some code clean up.

= 1.7.0 =
Added ability to clear unclaimed pardons.

= 1.6.0 =
Includes a tool for resetting all user passwords. Sites which were vulnerable to Heartbleed are encouraged to take advantage of this.

= 1.5.0 =
Improvements for proxy installations.

= 1.4.5 =
Various UX improvements, mostly in the form of clearer warnings and explanations.

= 1.4.4 =
Minor update, file clean up.

= 1.4.3 =
Minor update, fixes a small bug.

= 1.4.2 =
Minor update, replace deprecated $wpdb->escape() with esc_sql().

= 1.4.1 =
Minor update, replacing a couple functions that are deprecated as of PHP 5.5.0.

= 1.4.0 =
New features added.

= 1.3.6 =
Bug fix affecting PHP 5.3+.

= 1.3.5 =
Minor improvements and bug fixes.

= 1.3.4 =
New features and bug fixes.

= 1.3.3 =
Added a stats page and more settings.

= 1.3.2 =
Minor tweaks and optimizations.

= 1.3.1 =
This release contains a bit of code clean up.

= 1.3.0 =
An additional security feature and minor bug fixes.

= 1.2.0 =
This release adds a new feature and cleans up the settings page, which is getting kinda long.

= 1.1.0 =
This release provides more accurate timestamp handling and new features.
