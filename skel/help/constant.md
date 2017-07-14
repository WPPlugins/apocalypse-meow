## Constants ##

Most Apocalypse Meow settings can be hardcoded as PHP constants in `wp-config.php`. This allows system administrators to set up the plugin without logging in, and also prevents configuration changes from being made by WordPress users (constant-defined options are readonly).

All of this is covered in much greater detail in the plugin's `wp-admin` settings page. There is even a WP-Config tab that will generate PHP constants using your chosen settings.

But if you prefer to receive information list-style instead, here you go!

```
<?php
define('MEOW_WHATEVER', 'my value');
```

### Login/Brute-Force

(string) **MEOW_LOGIN_KEY**
The array key in `$_SERVER` corresponding to the visitor IP. Default `REMOTE_ADDR`. This shouldn't be changed unless you're behind a proxy or something.

(int) **MEOW_LOGIN_FAIL_LIMIT**
The number of failures a single IP is allowed to have within the window.

(int) **MEOW_LOGIN_SUBNET_FAIL_LIMIT**
The number of failures a single subnet is allowed to have within the window.

(int) **MEOW_LOGIN_FAIL_WINDOW**
The window of time, in seconds, that failed login attempts are counted against someone.

(bool) **MEOW_LOGIN_RESET_ON_SUCCESS**
When `TRUE`, a person's past failures are immediately forgotten when they successfully log in.

(bool) **MEOW_LOGIN_NONCE**
Add a Nonce to the login form to help prevent automated robot submissions. Do not use this feature if you have a custom login form.

(bool) **MEOW_LOGIN_ALERT_ON_NEW**
Email a WordPress user when their account is accessed from a new IP address.

(bool) **MEOW_LOGIN_ALERT_BY_SUBNET**
Same as above, except the alert condition is based on subnet rather than IP. Use this if your site supports IPv6 or else every login will probably trigger an alert.

### Data Retention

(bool) **MEOW_PRUNE_ACTIVE**
Enable automatic login record cleaning. This is useful if the amount of data being stored begins to pose storage or performance problems.

(int) **MEOW_PRUNE_LIMIT**
The maximum age, in days. Records older than this will be deleted automatically. Because various features rely on this data, it is recommended not to go lower than 90 days.

### Password Requirements

(string) **MEOW_PASSWORD_ALPHA**
This should be one of the following:
 * `"required"`: passwords must contain a letter
 * `"required-both"`: passwords must contain an upper- and lowercase letter
 * `"optional"`: letters are optional

(string) **MEOW_PASSWORD_NUMERIC**
This should be one of the following:
 * `"required"`: passwords must contain a number
 * `"optional"`: numbers are optional

(string) **MEOW_PASSWORD_SYMBOL**
This should be one of the following:
 * `"required"`: passwords must contain something non-alphanumeric
 * `"optional"`: symbols are optional

(int) **MEOW_PASSWORD_LENGTH**
The minimum password length. For security reasons, Apocalypse Meow sets its own minimum at 10, but your own minimum can be higher.

### Core/Template Overrides

(bool) **MEOW_CORE_ENUMERATION**
Prevent user enumeration attacks.

(bool) **MEOW_CORE_ENUMERATION_DIE**
Produce an error page rather than redirecting to the home page when user enumeration is attempted.

(bool) **MEOW_CORE_FILE_EDIT**
Disable WordPress' file editor. But while you're messing with `wp-config.php`, you should just use the official WP constant instead: **DISALLOW_FILE_EDIT**.

(string) **MEOW_CORE_WP_REST**
Restrict access to the WP-REST API by using one of the following:
 * `"all"`: the default behavior
 * `"users"`: only logged-in WP users can access it
 * `"none"`: nobody can access it

(bool) **MEOW_CORE_XMLRPC**
Disable the XML-RPC interface.

(bool) **MEOW_TEMPLATE_ADJACENT_POSTS**
Remove the `<meta>` tags corresponding to the previous and next post.

(bool) **MEOW_TEMPLATE_GENERATOR_TAG**
Remove the WordPress version `<meta>` tag.

(bool) **MEOW_TEMPLATE_NOOPENER**
Add `rel="noopener"` to offsite links to prevent phishing attempts.

(bool) **MEOW_TEMPLATE_README**
Remove the `readme.html` file if it exists.
