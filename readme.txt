=== AumChat ===
Contributors: aumcreate
Tags: live chat, ai chat, customer support, chat widget, chatbot
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds the AumChat widget to your site. Connect once, then answer visitors from your AumChat workspace.

== Description ==

AumChat is a hosted customer chat service. This plugin is the installer for it: it connects your site to an AumChat account and prints the widget on your pages. That is all it does.

Everything a chat widget usually buries in a settings screen — how it looks, who it greets, what the automatic replies say, business hours, your team — lives in the AumChat workspace and applies to every site you connect, including ones that do not run WordPress. So this plugin has no settings screen to speak of. It has a Connect button and a status line.

= What the status line is for =

AumChat only serves the widget on the domain saved for that site in your workspace. That check is what stops someone from copying your snippet onto another site and using your account.

It also means a site key that is correct but saved with the wrong domain shows **nothing at all** on your pages, with no error anywhere. That is a miserable thing to debug, so this plugin asks AumChat about your key when you connect and tells you plainly: connected and live, or "this key belongs to example.com but this site is shop.example.com".

= What you need =

A free AumChat account at [chat.aumcreate.com](https://chat.aumcreate.com/). The free plan includes one website, AI replies each month, and a product catalogue; plans and limits are listed at [aumcreate.com/aumchat](https://aumcreate.com/aumchat). There is no separate licence to enter here and nothing in this plugin is locked.

= Controlling where the widget appears =

By default the widget is on every public page, and never in the admin, in feeds, or on AMP pages. The settings screen can narrow that down:

* every page, every page **except** a list you write, or **only** the pages on that list;
* hidden from signed-in users with roles you choose, which is how most people stop their own widget from following them around while they work;
* hidden on the WooCommerce cart and checkout, when WooCommerce is active.

These rules are decided inside WordPress. Nothing about them is sent anywhere; they only choose whether the loader script is printed on the page.

When a rule is not enough, one filter decides per request:

`add_filter( 'aumchat_show_widget', function ( $show ) {
    return is_checkout() ? false : $show;
} );`

== External services ==

This plugin connects your site to **AumChat**, a chat service operated by AumCreate at `https://chat.aumcreate.com`. The plugin is useless without it, so connecting a site is what grants consent for the exchanges below.

**1. The chat widget, in your visitors' browsers.**

Once a site is connected, every public page loads `https://chat.aumcreate.com/loader.js` and, when a visitor opens the chat, a frame from `https://chat.aumcreate.com/`. This happens in the visitor's browser, not on your server. AumChat receives:

* the site key, and the address and title of the page the visitor is on;
* the address that referred them, their browser's language and user agent;
* the country their connection comes from, as reported by the hosting provider (IP addresses are not stored);
* a random visitor identifier the widget stores in the browser's local storage, so a conversation continues as the visitor moves between pages;
* anything the visitor types or attaches in the chat, and an email address if they choose to leave one.

Conversations are kept for the retention period the site owner sets in the workspace (12 months after a conversation closes by default, and they can be deleted sooner). Visitors who never open the chat still cause the loader script to be fetched.

**2. One check from your server, in the admin only.**

When you press Connect, save a key by hand, or press "Check again", this plugin calls `https://chat.aumcreate.com/api/plugin/site`. It sends the site key and this site's domain, and nothing else. The answer is the site's name, its saved domain, and when the widget was last seen. No visitor data is involved and this request never happens on a front-end page view.

**3. Connecting an account.**

The Connect button sends you to `https://chat.aumcreate.com/wp-connect` with this site's domain and the address of this settings page, so the workspace can send the site key back. You sign in on AumChat's own pages; this plugin never sees your password.

Terms of Service: [https://chat.aumcreate.com/legal#terms](https://chat.aumcreate.com/legal#terms)
Privacy Policy: [https://chat.aumcreate.com/legal](https://chat.aumcreate.com/legal)

== Installation ==

1. Install and activate the plugin.
2. Go to **Settings → AumChat**.
3. Press **Connect to AumChat** and pick the site you want. If you would rather not leave WordPress, paste the site key from your workspace instead.
4. Open your site in a browser. The widget is in the corner.

== Frequently Asked Questions ==

= Do I need an account? =

Yes, a free one at chat.aumcreate.com. The widget is served by that account, so there is nothing to run on your own server.

= I connected it but I see no widget. =

Check the status line on Settings → AumChat. The usual cause is that the site key belongs to a different domain than this WordPress site, and AumChat will not serve the widget in that case. The status line says so explicitly, and you fix it by changing the domain in the workspace.

If the status line says the site is live, look for a page-caching plugin holding an old copy of your pages, and clear that cache.

= Can I keep it off the checkout, or hide it from myself? =

Yes. Settings → AumChat has a list of pages to skip (or the only pages to show it on), a tick box per user role, and a switch for the WooCommerce cart and checkout.

= Where do I change the colour, the greeting, or the replies? =

In the AumChat workspace. Those settings belong to the site, not to WordPress, so they apply everywhere that site is installed.

= Does it slow my site down? =

The loader is one small asynchronous script. It does not block rendering, and the chat interface itself is only fetched when a visitor opens the chat.

= Does the plugin phone home? =

Only in the admin, and only when you connect or press "Check again". Front-end page views make no request from your server to AumChat. See "External services" above.

= What happens when I delete the plugin? =

The stored site key and the cached status are removed and the widget stops appearing. Your conversations stay in your AumChat account.

== Screenshots ==

1. Settings → AumChat before connecting: one button.
2. A connected site: the status line confirms AumChat is seeing the widget, and the rules below decide where it appears.
3. The status line catching the common mistake — a key saved for a different domain, which otherwise shows nothing at all.

== Changelog ==

= 1.0.0 =
* First release.
