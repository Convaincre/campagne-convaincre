=== Campagne Convaincre ===
Contributors: convaincre
Tags: campaign, website, forms, newsletter, events
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build a political campaign website and connect your public forms to Convaincre.

== Description ==

Campagne Convaincre helps political organizations and campaign teams build and manage a website in WordPress without manually designing each page. The website mode is optional: you can keep your existing theme and use the form blocks and shortcodes instead. The current administration interface is in French.

**A customizable campaign website**

* Eight responsive designs, customizable colors, typography and homepage layout.
* Team, platform, territory, news, events and follow pages; sections can be renamed, reordered and hidden.
* Site identity, logo, favicon, election dates, social links and supporting organizations' logos.
* Guided setup, private previews, drafts and section-by-section publishing.
* Optional local page-view counts without visitor identifiers.

**Optional Convaincre connection**

The external Convaincre platform manages form definitions and responses (contact, newsletter, petition, support, volunteering, event registration and questionnaire), as well as public events. An account or authorized access to a Convaincre workspace is required for these features. Installing this plugin does not grant access to that service. Access conditions are described at https://convainc.re/demander-un-acces/; the platform is currently in beta. The local website builder works without a connection.

Homepage action buttons can link to a selected public form or display it directly. Forms are defined in Convaincre and their presentation uses the available configuration. Website pages remain managed locally in WordPress.

== Installation ==

1. Install and activate Campagne Convaincre from the Plugins screen. PHP 8.1 or later is required; OpenSSL is required for connected features.
2. Open Campagne Convaincre, then Mon site internet. Enter your identity, choose the appearance and sections, and prepare legal and privacy information.
3. Preview and explicitly publish the website. Website mode replaces the homepage and rendering of managed pages without deleting the existing WordPress theme or content.
4. To enable connected features, enter the HTTPS URL of your Convaincre service and the credentials provided by that service, or use its connection code. Test the connection.
5. Create forms in Convaincre and select which forms to publish, or insert their blocks or shortcodes in existing WordPress pages.

To return to the previous WordPress website, disable website mode in the administration screen. Back up the website before migrating or uninstalling.

== External services ==

The plugin connects only to the Convaincre instance configured by the administrator, after the administrator explicitly enables the connection. It includes no social network SDK, remotely hosted fonts or independent external telemetry. Social links open the selected external websites when visitors follow them.

* Service: Convaincre, https://convainc.re/la-plateforme/
* Access requests: https://convainc.re/demander-un-acces/
* Privacy policy: https://convainc.re/politique-de-confidentialite/
* Legal information: https://convainc.re/mentions-legales/
* Data and trust: https://convainc.re/donnees-et-confiance/

Server-to-server HTTPS requests are signed. Manifest synchronization (`/api/v1/public/connect/manifest/`) sends authentication metadata, the website origin, the plugin version and technical request metadata. It retrieves form definitions and public event information during manual refreshes, requests requiring a fresh manifest and scheduled tasks. Depending on the administrative action, the service may receive a form definition (`/api/v1/public/connect/forms/`).

When a visitor voluntarily submits a form, the plugin sends the entered data, answers, consents and metadata needed to identify and deliver that submission to `/api/v1/public/connect/submissions/`. Fields depend on the form configuration. If delivery fails, the encrypted local queue retains submissions until delivery or deletion; retries are scheduled or initiated manually. The service may send signed synchronization messages to `/wp-json/convaincre/v1/sync/`; the plugin stores technical acknowledgments and does not automatically publish their content.

The organization operating the website must inform visitors about applicable purposes, recipients, retention periods, rights and legal bases. The suggested privacy and legal text must be completed and does not guarantee compliance. Political forms may involve particularly sensitive personal data. Review the service instance's contractual terms and the parties' responsibilities before use.

== Frequently Asked Questions ==

= Do I need a Convaincre account to build a website? =

No. The local website builder works without a connection. Synchronized forms and events require authorized access to the Convaincre service.

= Can I change the design after publishing? =

Yes. Open Mon site internet, then Apparence. Preview the design and publish that section. Other sections remain unchanged.

= How do I display a form on an existing page? =

Use a block or the shortcode provided by Convaincre. Shortcodes include `[convaincre_form id="identifier"]`, `[convaincre_petition id="identifier"]`, `[convaincre_soutien id="identifier"]`, `[convaincre_benevolat id="identifier"]`, `[convaincre_newsletter id="identifier"]`, `[convaincre_evenement id="identifier"]`, `[convaincre_questionnaire id="identifier"]` and `[convaincre_contact id="identifier"]`. The event calendar can be displayed with `[convaincre_agenda]`.

= What data is stored in WordPress? =

Website content and settings, connection credentials (the secret is encrypted), cached definitions, encrypted pending submissions, synchronization acknowledgments and submission rate-limit counters. Abuse prevention uses an HMAC-derived IP address value in a temporary local counter. Optional local statistics contain no visitor identifiers and retain daily counters for up to 90 days. The plugin does not provide a local CRM interface.

= Can I uninstall the plugin? =

Back up the website and check pending submissions first. Deactivation restores the previous homepage configuration and moves managed pages to draft. Uninstallation removes connection settings, the manifest cache, the local queue and counters; website content is retained to avoid editorial data loss. Do not delete the old plugin using WordPress during a slug migration, because both versions share the same data.

== Attribution ==

The original plugin code and assets are distributed under GPLv2 or later. Social network SVG icons are from Font Awesome Free 6.7.2, Copyright 2024 Fonticons, Inc., under CC BY 4.0: https://creativecommons.org/licenses/by/4.0/. Source: https://github.com/FortAwesome/Font-Awesome/tree/6.7.2/svgs/brands. Trademarks remain the property of their respective owners.

== Changelog ==

= 1.0.5 =
* Match the translation text domain to each distribution slug.

= 1.0.4 =
* Construct API exceptions with sanitized messages and typed metadata before throwing.
* Validate administrative navigation and news publication input.

= 1.0.3 =
* Filter managed site and form markup with a dedicated HTML allowlist.
* Separate exception metadata from escaped messages.
* Prepare statistics and uninstall table identifiers and validate statistics inputs.

= 1.0.2 =
* Translate the directory readme into English.
* Add translator comments and positional placeholders.
* Prepare queue table identifiers using the WordPress database API.
* Use WordPress URL parsing, tag removal and stylesheet APIs.
* Preserve existing form rendering and submission behavior.

= 1.0.1 =
* Remove redundant plugin URI.

= 1.0.0 =
* Initial public distribution candidate for Campagne Convaincre.
* Eight designs, private preview and section-by-section publishing.
* Configurable homepage actions, forms, social links, supporting organizations and election dates.
* External service documentation and GPLv2-or-later license.

== Upgrade Notice ==

= 1.0.0 =
Back up content and the database before migrating from Convaincre Connect. The version using the historical slug supports in-place updates; migrate to the public slug separately.
