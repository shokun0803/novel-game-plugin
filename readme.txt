=== Novel Game Maker ===
Contributors: shokun0803
Tags: game, novel, visual novel, adventure, story
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and publish branching visual novel / sound novel games on your site, with scenes, choices, flags and multiple endings.

== Description ==

Novel Game Maker lets you build classic branching visual novel games directly inside the block editor and play them on the front end in an immersive full-screen player.

**Features**

* Scene-based game builder using a custom post type — each scene has a background, up to three characters with per-line expression changes, dialogue lines and choices.
* Branching stories: choices can be shown or hidden based on flags, and can set flags of their own, enabling evidence-gathering, deduction phases and multiple endings.
* Conditional dialogue: lines can change or disappear depending on the flags the player has collected.
* Front-end player with typewriter text, cross-fade scene transitions, a title screen, auto-save (browser localStorage) and a resume dialog.
* Multiple games per site, playable via shortcodes, blocks or the game archive page.
* Export / import games as JSON for backup and migration.
* Optional ad banner slot above the player. If — and only if — the site owner enters their own publisher ID, the player can display ads from Google AdSense or Adsterra.
* Fully translatable (Japanese translation bundled).

**Optional sample game**

An optional sample mystery game ("Shadow Detective", 25 scenes, 4 endings) demonstrates every feature. To keep this plugin lightweight, the sample's image files are **not** bundled. You can install the sample from the plugin dashboard, and the plugin will offer to download the image set — the download only happens after you explicitly confirm it (see "External services" below). The plugin is fully functional without the sample game and without the images.

== External services ==

This plugin connects to external services only in the following cases. No data is sent anywhere automatically, and no external requests are made on the public front end of your site unless you configure ads.

**1. GitHub (sample image download, opt-in)**

If you choose to install the optional sample game images, the plugin connects to the GitHub API (`api.github.com`) to look up the latest release of this plugin's own repository, and downloads the sample image archive from GitHub release assets (`github.com/shokun0803/novel-game-plugin`). This happens only when an administrator explicitly clicks the download button in the admin area; the request contains no personal data beyond what any HTTP request exposes (your server's IP address and a user-agent string identifying the plugin version). The downloaded images are stored locally in your uploads directory and are never loaded remotely afterwards.

* Service: GitHub, operated by GitHub, Inc.
* Terms of service: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service
* Privacy policy: https://docs.github.com/en/site-policy/privacy-policies/github-privacy-statement

**2. Google AdSense / Adsterra (front-end ads, opt-in)**

If the site owner enters their own publisher ID in the plugin's ad settings and enables ads for a game, the front-end player loads the corresponding ad network script in visitors' browsers. This is entirely optional and disabled by default; if no publisher ID is configured, no ad network is ever contacted.

* Google AdSense — terms: https://www.google.com/adsense/new/localized-terms — privacy: https://policies.google.com/privacy
* Adsterra — terms: https://adsterra.com/terms-of-use/ — privacy: https://adsterra.com/privacy-policy/

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/novel-game-plugin`, or install through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen.
3. Go to "Novel Game" in the admin menu to create your first game, or install the optional sample game from the dashboard.

== Frequently Asked Questions ==

= Does the plugin create any content by itself? =

No. Games and scenes are only created when you create them, or when you explicitly install the optional sample game from the plugin dashboard.

= Where does the sample game's artwork come from? =

The sample images are original artwork distributed with this project under the GPL. They are hosted as release assets in the plugin's GitHub repository and downloaded only after you confirm the download in the admin area.

= Does the plugin show ads? =

Only if you configure your own ad network publisher ID and enable ads for a game. Nothing is shown and no ad network is contacted by default.

= Where is player progress stored? =

In the player's browser (localStorage). No play data is sent to the server or to any third party.

== Screenshots ==

1. Front-end player with dialogue box and character expressions.
2. Choice branching in the player.
3. Scene editor in the admin area.

== Changelog ==

= 1.6.0 =
* Redesigned front-end player (dialogue panel, choices, title and ending screens, typewriter text, cross-fade transitions).
* Rewrote the optional sample game as a 25-scene mystery with a three-stage deduction phase and four endings.
* Fixed conditional dialogue data not being applied for generated sample scenes.
* Fixed choice `setFlags` being dropped during sample generation.
* Added an ad-banner preview mode for layout testing.

= 1.5.0 =
* Release automation, title readability improvements.

== Upgrade Notice ==

= 1.6.0 =
Front-end player redesign and reworked sample game. Clear your browser cache after updating.
