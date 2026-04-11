=== Juda B2B Exporter ===
Contributors: judab2b
Tags: woocommerce, b2b, export, marketplace, africa
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 2.0.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Export your WooCommerce or WordPress products to the Juda B2B marketplace — a verified supplier directory serving Africa.

== Description ==

Juda B2B Exporter connects your WordPress or WooCommerce store to the [Juda B2B marketplace](https://judab2b.com). Business owners can select their products in the WordPress admin and push them directly to their Juda product listings — no manual re-entry needed.

**What it does:**

* One-click OAuth connection — sign in to judab2b.com and return automatically, no API keys to copy-paste
* Guided 4-step setup wizard: connect, map categories, export, done
* Lists your WooCommerce or WordPress products in a dedicated export UI
* Maps your WordPress categories to Juda categories, with a fallback default category
* Pushes products to Juda via the Juda Import API
* Detects duplicates by WP post ID — re-exporting updates the existing Juda listing instead of creating a duplicate
* Writes back the Juda product ID and URL slug as post meta after a successful export
* Supports Yoast SEO: reads meta title and description from Yoast if available
* Dashboard shows total, synced, and unsynced product counts at a glance
* Disconnect and reconnect your Juda account at any time

**Admin interface:**

* **Setup Wizard** (Dashboard): guided connect → category mapping → export flow
* **Export Products** page: select products, export in batches, see per-item status
* **Settings** page: manage post types and reconnect your account

**WP-CLI support:**

`wp juda export --category="Building Materials" --dry-run`

== External Services ==

This plugin sends data to the Juda B2B Marketplace API.

**Service:** Juda B2B Marketplace — https://www.judab2b.com
**Endpoints used:**
* `https://www.judab2b.com/api/plugin/authorize` — OAuth authorization (during account connection only)
* `https://www.judab2b.com/api/plugin/token` — OAuth token exchange (during account connection only)
* `https://www.judab2b.com/api/import/products` — product export
* `https://www.judab2b.com/api/import/categories` — category list

**When data is sent:** Only when the site administrator triggers an action — connecting an account or running an export manually via the admin UI or WP-CLI. No data is sent automatically or in the background.

**What is sent:** During connection, an OAuth authorization code is exchanged for an API key and Business ID. During export, product data from your WordPress site is sent — title, description, price, images, category, and B2B fields (minimum order quantity, lead time, incoterms, etc.) — along with your API key for authentication.

* [Juda Terms of Service](https://judab2b.com/terms)
* [Juda Privacy Policy](https://judab2b.com/privacy)

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via the WordPress Plugins screen.
2. Activate the plugin. You will be redirected to the Setup Wizard automatically.
3. Click **Connect to Juda**. You will be taken to judab2b.com to sign in, then returned here automatically.
4. Click **Load Juda categories** and map each WordPress category to a Juda category.
5. Select your products and click **Export selected** to publish them to Juda.

== Frequently Asked Questions ==

= Do I need a Juda account? =

Yes. You need a registered and verified business account on [judab2b.com](https://judab2b.com). The plugin connects to your account via a secure one-click OAuth flow — no API keys to copy-paste.

= Does it work without WooCommerce? =

Yes. The plugin lists any WordPress post type. WooCommerce product fields (price, gallery images) are read when WooCommerce is active, but the plugin works with standard WordPress posts as well.

= Will re-exporting a product create a duplicate? =

No. The plugin stores the WordPress post ID as an external reference. Re-exporting the same product will update the existing Juda listing.

= Is my account credential stored securely? =

Yes. The API key received during OAuth is stored in the WordPress options table using the standard `update_option()` function. It is never exposed in frontend HTML or JavaScript. You can disconnect your account at any time from the Setup Wizard.

= Can I disconnect my Juda account? =

Yes. Go to **Juda Export → Setup Wizard**, click **Disconnect**, and confirm. Your exported products remain on Juda; only the link between this WordPress site and your Juda account is removed.

== Changelog ==

= 2.0.2 =
* OAuth one-click account connection — replaces manual API key and Business ID entry.
* Guided 4-step setup wizard (connect → map categories → export → done).
* Dashboard product sync counters: total, synced to Juda, and not yet exported.
* Disconnect and reconnect your Juda account from the wizard.
* Activation now redirects directly to the Setup Wizard.
* WP-CLI: added `--post-type` and `--status` flags.
* WP-CLI: output now includes the live Juda product URL after each successful export.
* New post meta fields supported: `_juda_price_from`, `_juda_gallery`, `_juda_meta_title`, `_juda_meta_description`, `_juda_seo_keywords`.
* businessId is now derived server-side from the API key — no longer required in the export payload.

= 2.0.0 =
* Initial public release.
* WooCommerce and custom post type support.
* Category mapping UI.
* WP-CLI export command.
* Yoast SEO integration.
