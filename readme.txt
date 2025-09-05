=== BuzzBoost Submissions for Contact Form 7 (Admin-Only) ===
Contributors: buzzboostdigital
Tags: contact form 7, submissions, csv, admin, export
Requires at least: 5.6
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Save Contact Form 7 submissions privately in wp-admin with CSV export. Admin-only. No front-end output or REST.

== Description ==
BuzzBoost Submissions for Contact Form 7 stores each submission as a private post (custom post type) that only administrators can view. Includes dynamic CSV export and optional GitHub-based auto-updates for client fleets.

* Admin-only (capability-gated)
* Non-public, not queryable, no REST endpoints
* Dynamic CSV export (columns match your CF7 fields)
* File uploads listed by filename and stored privately as meta paths
* Works with custom field names (first-name/last-name/etc.)

== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/` or use “Upload Plugin”.
2. Activate the plugin.
3. Ensure **Contact Form 7** is active.
4. Submit a test CF7 form → see **Form Submissions** in the admin menu.
5. Export via **Form Submissions → Export CSV**.

== Frequently Asked Questions ==
= Who can view submissions? =
Administrators (`manage_options`) by default. Adjust the capability checks if needed.

= Does it expose any front-end URLs? =
No. The custom post type is non-public, not queryable, and excluded from REST.

= How are files handled? =
The filename is shown in the submission content; the absolute path is saved as meta (private to wp-admin).

= How do updates work? =
Optionally include the Plugin Update Checker library and point it to your GitHub repo. Bump the version and tag releases; sites will see updates in Dashboard → Updates.

== Screenshots ==
1. Admin list of submissions with Name/Email/Phone columns.
2. CSV export screen with filters.

== Changelog ==
= 1.4.1 =
* Add full-name helper combining [first-name] + [last-name].
* Harden CSV export (buffer cleanup, dynamic columns).
* Admin-only gate refined.

= 1.4.0 =
* GitHub updater support (PUC).
* Permission fix (default post caps + admin gate).
* First stable for client use.

== Upgrade Notice ==
= 1.4.1 =
Improved CSV and Name column handling. Safe to update.
