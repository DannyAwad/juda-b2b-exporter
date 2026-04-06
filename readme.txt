=== Juda B2B Exporter ===
Contributors: judab2b
Tags: woocommerce, b2b, export, marketplace, africa
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Export your WooCommerce or WordPress products to the Juda B2B marketplace — a verified supplier directory serving Africa.

== Description ==

Juda B2B Exporter connects your WordPress or WooCommerce store to the [Juda B2B marketplace](https://judab2b.com). Business owners can select their products in the WordPress admin and push them directly to their Juda product listings — no manual re-entry needed.

**What it does:**

* Lists your WooCommerce or WordPress products in a dedicated export UI
* Maps your WordPress categories to Juda categories
* Pushes products to Juda via the Juda Import API
* Detects duplicates by WP post ID — re-exporting updates the existing Juda listing instead of creating a duplicate
* Writes back the Juda product ID and URL slug as post meta after a successful export
* Supports Yoast SEO: reads meta title and description from Yoast if available

**Admin interface:**

* Settings page: API key, Juda business ID, category mapping
* "Test connection" button to verify credentials before exporting
* Export page: select products, export in batches, see per-item status

**WP-CLI support:**

`wp juda export --category="Building Materials" --dry-run`

== External Services ==

This plugin sends data to the Juda B2B Marketplace API.

**Service:** Juda B2B Marketplace — https://www.judab2b.com
**Endpoint used:** `https://www.judab2b.com/api/import/products`

**When data is sent:** Only when the site administrator triggers an export — manually via the admin UI or via WP-CLI. No data is sent automatically or in the background.

**What is sent:** Product data from your WordPress site — title, description, price, images, category, and B2B fields (minimum order quantity, lead time, incoterms, etc.) — along with your Juda API key and Business ID which you configure in the plugin settings.

* [Juda Terms of Service](https://judab2b.com/terms)
* [Juda Privacy Policy](https://judab2b.com/privacy)

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via the WordPress Plugins screen.
2. Activate the plugin.
3. Go to **Juda Export → Settings**.
4. Enter your Juda site URL, API key, and Business ID. (Obtain these from your Juda business account.)
5. Click **Test connection** to verify.
6. Click **Load Juda categories** and map each WordPress category to a Juda category.
7. Go to **Juda Export → Export**, select your products, and click **Export selected**.

== Frequently Asked Questions ==

= Do I need a Juda account? =

Yes. You need a registered and verified business account on [judab2b.com](https://judab2b.com) to get an API key and Business ID.

= Does it work without WooCommerce? =

Yes. The plugin lists any WordPress post type. WooCommerce product fields (price, gallery images) are read when WooCommerce is active, but the plugin works with standard WordPress posts as well.

= Will re-exporting a product create a duplicate? =

No. The plugin stores the WordPress post ID as an external reference. Re-exporting the same product will update the existing Juda listing.

= Is the API key stored securely? =

The API key is stored in the WordPress options table using the standard `update_option()` function. It is never exposed in frontend HTML or JavaScript.

== Changelog ==

= 2.0.0 =
* Initial public release.
* WooCommerce and custom post type support.
* Category mapping UI.
* WP-CLI export command.
* Yoast SEO integration.
