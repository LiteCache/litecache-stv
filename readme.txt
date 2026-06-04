=== LiteCache Suspicious Traffic Viewer ===
Contributors: rushme2026
Tags: suspicious traffic, masked traffic, bot traffic, crawler, scraper
Requires at least:   6.1
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv3
License URI:     https://www.gnu.org/licenses/gpl-3.0.html


LiteCache Suspicious Traffic Viewer - Identifies suspicious, masked, and not-human-like traffic patterns.

== Description ==

LiteCache - Suspicious Traffic Viewer (STV) was created for a specific problem: traffic that reaches WordPress is often counted, but not understood.

Many traffic plugins are built as visitor statistics tools. They try to count everything, produce dashboards, and present traffic as if every request were equally meaningful. STV follows a different approach.

STV is not mainly about making requests visible. If a request reaches PHP, many plugins can record it. The real question is what that request actually looks like.

This is where STV differs from generic traffic plugins.

STV focuses on classification. It separates clearly human-like requests from traffic that is not clearly human-like: suspicious requests, masked traffic, bot-like behavior, crawler patterns, abnormal status codes, missing or unusual user agents, and requests that only become meaningful when they repeat over time.

That classification focus is the actual value of STV.

AI crawler traffic was one of the original reasons behind STV, but STV is not limited to AI crawlers. AI bots are only one part of a broader problem. Many automated systems do not announce themselves honestly, and many suspicious requests are not obvious from one single header.

Traditional traffic tools usually try to show everything. STV deliberately does not. It ignores clearly human-like 200 requests and focuses on traffic that deserves a second look.

Known bots are often the easy part because they are easier to detect. The more relevant layer is often traffic that does not openly identify itself as automation, but also does not behave like a normal human browser request.

In many environments, the real problem is not that suspicious traffic is never logged. The real problem is that it is buried inside ordinary looking traffic and never becomes visible as a pattern. STV was built to separate that signal from the noise.

This also means STV avoids fake certainty. If a request is well masked, it is often impossible to prove what it really is from a single log entry alone. In those cases, "suspicious" is the honest label.

STV is not a realtime logger. It works with imported log data and is designed for review, inspection, and pattern recognition rather than live streaming.

Another important difference is that STV is database-free while logging. The logger itself does not write every captured request directly into the database. Request data is first collected in a log file and then imported later. This avoids additional database load during capture and keeps the logging process lightweight.

The real value of STV appears when suspicious traffic is not judged too quickly. A single request may look harmless. Repeated patterns over time often reveal much more.

In short:
STV is not a generic traffic viewer.
It is a focused classification and investigation tool for suspicious, masked, not-human-like, and abnormal requests that traditional traffic views often count, bury, or fail to classify clearly.

= What STV identifies differently =

Many traffic plugins collect requests. STV focuses on what those requests indicate.

Instead of filling the interface with normal human pageviews, STV focuses on request types that deserve attention.

Examples include:

- suspicious or masked requests
- known bots and AI crawlers
- scrapers and crawler-like access patterns
- requests with missing, empty, or unusual user agents
- non-200 requests and abnormal status codes
- requests that do not look clearly human-like
- requests that look normal at first glance but become suspicious over time
- traffic patterns that are easy to miss in generic visitor statistics

This makes STV especially useful when the goal is not to count visitors, but to understand what is really hitting the site.

= Human-like vs not-human-like =

STV does not try to prove that every request is human or non-human with absolute certainty. That would be false precision.

Instead, STV uses a more practical distinction:

- clearly human-like traffic can be ignored for the purpose of suspicious traffic review
- known bots and known crawlers can be identified more directly
- unclear, masked, abnormal, or not-human-like requests can be surfaced for review

This distinction is where STV separates itself from generic traffic plugins. A normal traffic plugin may show the request. STV tries to make the request meaningful.


= What STV captures =

STV is selective by design. It does not try to turn the interface into a complete visitor counter. Instead, it focuses on the request layer that is most relevant for suspicious traffic analysis.

In practical terms, STV captures and classifies requests that are not just ordinary human-like 200 pageviews.

STV focuses on:

- known bots, known crawlers, and known AI crawlers
- suspicious or masked requests
- requests that are not clearly human-like
- requests with missing, empty, malformed, or unusual user agents
- requests with crawler-like, scraper-like, tool-like, or automated access patterns
- requests with abnormal or non-200 status codes
- repeated request patterns that only become meaningful over time
- requests that reach PHP before WordPress handles the page
- requests that generic traffic dashboards may record, but usually do not classify clearly

With the planned Cache Shadow Capture feature, STV is intended to recover additional cached pageview evidence through a lightweight browser beacon when the main document itself did not execute PHP.

This still does not mean that STV claims perfect visibility. The intentional non-focus of STV is narrow and clear:

- clearly human-like requests
- HTTP 200 status
- no suspicious, masked, abnormal, bot-like, or not-human-like indicators

In other words: a normal human-like 200 request is not the traffic STV is built to investigate. Everything outside that clean category is where STV becomes useful.

= Cache awareness and optional cache compatibility rules =

There is an important technical reality that many traffic tools do not explain clearly:

If a page is served entirely from page cache or CDN cache before PHP is executed, a PHP-based WordPress plugin cannot see that main document request.

This is not an STV limitation only. It is a structural limitation of every WordPress traffic plugin that depends on PHP execution.

STV does not hide this blind spot. It is designed around technical honesty: it shows and classifies what can be captured at the PHP/origin layer and does not pretend to see requests that never reach that layer.

At the same time, page cache can create a second problem for suspicious traffic analysis: once a bot or crawler has warmed the cache for many URLs, later requests for those URLs may be served before PHP runs. This can reduce STV visibility exactly where suspicious traffic review may matter.

For this reason, STV includes an optional cache compatibility section. If enabled by the site owner, STV can add its own marked `.htaccess` rule block to improve PHP/origin visibility for selected suspicious, bot-like, legacy-browser, or not-human-like request patterns. STV only manages its own block and can remove it again.

LiteSpeed Cache offers the strongest compatibility because selected request patterns can be marked with no-cache behavior at the server/cache layer. This allows STV to keep visibility for these requests while leaving normal cache behavior intact.

WP Rocket compatibility is more limited. STV can add early `.htaccess` rules for selected request patterns, but WP Rocket does not provide the same full request-level no-cache control as LiteSpeed. Depending on the server setup and WP Rocket rules, cached delivery may therefore not be fully bypassable in every case.

Other page cache plugins are not directly supported by STV cache compatibility rules. If another cache plugin is used, the page cache should be purged regularly or configured manually so suspicious or bot-like requests are not always served from an existing cached main document.

STV does not break page cache for normal pageviews just to count traffic. The optional cache compatibility rules are intended to reduce cache-induced blind spots for selected request patterns that are more relevant for suspicious traffic review.

= Upcoming: Cache Shadow Capture =

A future STV feature is planned to reduce the cache visibility gap further.

The planned Cache Shadow Capture feature will use a lightweight browser-side POST beacon for cached pageviews. The idea is simple: if a cached main document is served without PHP execution, JavaScript can still send a small non-cacheable follow-up request to an STV endpoint. STV can then record a shadow capture for the original pageview.

This will not claim perfect 100% visibility. Extremely aggressive CDN setups, custom edge rules, blocked beacon endpoints, or clients without JavaScript can still prevent capture.

But for normal page cache and many CDN cache setups, Cache Shadow Capture is intended to recover additional pageview evidence without forcing the main document out of cache.

Planned principles:

- no cache breaking for the main document
- POST-based follow-up capture
- deduplication against normal origin captures
- separate shadow capture source type
- honest labeling of recovered cached pageview evidence
- no claim of impossible full coverage

Cache Shadow Capture is not part of the initial 1.0.0 release. It is planned as a later feature so the initial plugin remains focused, review-friendly, and technically lean.

= Main characteristics =

- Identifies suspicious, masked, and not-human-like traffic patterns
- Focuses on classification instead of generic visitor statistics
- Captures the traffic layer outside clean human-like 200 requests
- Not limited to AI crawlers - also useful for bots, scrapers, and disguised requests
- Intentionally ignores clearly human-like 200 requests
- Tracks non-200 requests because they often reveal relevant anomalies
- Focuses on the less obvious suspicious layer, not only on known bots
- Database-free logging without additional database writes during capture
- Daily import workflow instead of realtime logging
- Request list with search, filters, pagination, and sorting
- Highlights traffic class, status, method, IP, hits, and user agent
- Designed for investigation, not for cosmetic dashboards
- Cache-aware by design and honest about PHP/origin visibility limits
- Optional cache compatibility rules for LiteSpeed Cache and limited WP Rocket support
- Own marked `.htaccess` rule block when cache compatibility is explicitly enabled
- Roadmap includes Cache Shadow Capture for additional cached-pageview evidence

= What STV is not =

- Not a realtime logger
- Not a generic visitor statistics plugin
- Not a full web application firewall
- Not a complete replacement for server logs
- Not a replacement for CDN or edge logs
- Not a tool that claims impossible 100% visibility
- Not a tool that simply equates visibility with value
- Not able to see main document requests served entirely from page cache or CDN cache before PHP is executed, unless cache compatibility rules or manual cache configuration make those requests reach PHP

= Why this matters =

AI crawlers, bots, scrapers, and other automated systems are getting better at looking normal.

In many environments, obvious bots are only the visible part. The larger problem is often suspicious or masked traffic that blends into ordinary looking requests and only becomes meaningful when patterns are observed over time.

Traditional logs and traffic plugins may still record some of that traffic. But recording something is not the same as classifying it, filtering it, or making its pattern understandable.

Traditional visitor dashboards can also create a false sense of visibility. They may show a lot of traffic while still burying the requests that actually matter for suspicious traffic analysis.

The suspicious label is therefore not a marketing trick. It is often the most honest category available.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/litecache-stv/` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Open LiteCache - Suspicious Traffic Viewer (STV) in the WordPress admin area.
4. Import the current log manually or enable the daily cron import.
5. Optional: review the Cache Compatibility settings if LiteSpeed Cache, WP Rocket, or another page cache is used.

== Frequently Asked Questions ==

= Is STV a realtime traffic viewer? =

No. STV is designed for imported log review, not for live streaming.

= Does STV make invisible traffic visible? =

Not in that simplistic sense. If a request reaches PHP, many plugins can record it. STV's main value is not merely recording requests, but separating clearly human-like traffic from suspicious, masked, abnormal, or not-human-like traffic patterns.

= Which requests does STV focus on? =

STV focuses on requests that are not just clean human-like 200 pageviews. This includes known bots, AI crawlers, suspicious or masked requests, not-human-like requests, missing or unusual user agents, scraper-like patterns, and non-200 responses.

The intentionally ignored category is narrow: clearly human-like requests with HTTP 200 status and no suspicious indicators. STV is not built to count those ordinary pageviews. It is built to separate them from the traffic layer that deserves investigation.

= Can STV see all requests? =

No. No PHP-based WordPress plugin can see a main document request that is served entirely from page cache or CDN cache before PHP is executed. STV is explicit about this limitation instead of pretending it does not exist.

= Can STV currently see cached pageviews? =

STV can only capture requests that reach PHP. Fully cached main document requests that never reach PHP are outside the direct PHP capture layer.

The optional Cache Compatibility settings can reduce this blind spot for selected suspicious, bot-like, legacy-browser, or not-human-like request patterns, especially with LiteSpeed Cache. WP Rocket support is more limited because it does not provide the same request-level no-cache control.

A future Cache Shadow Capture feature is planned to recover additional browser-side cached pageview evidence through a lightweight POST beacon, without forcing the main document itself out of cache.

= Why does STV mention page cache and CDN cache? =

Because cache changes what WordPress traffic plugins can see. If WordPress or PHP is not executed for a request, a WordPress plugin cannot directly capture that request. STV treats this as an important technical reality, not as something to hide behind a marketing claim.

= How does STV work with LiteSpeed Cache and WP Rocket? =

LiteSpeed Cache offers the strongest compatibility. STV can mark selected request patterns as no-cache candidates so they can still reach PHP and be logged, while normal cache behavior remains available for ordinary pageviews.

WP Rocket compatibility is more limited. STV can add early `.htaccess` rules for selected request patterns, but WP Rocket does not provide the same full request-level no-cache control as LiteSpeed. Some cache behavior may remain outside STV's control.

= What if I use another cache plugin? =

Other cache plugins are not directly supported by STV cache compatibility rules. To preserve STV logging visibility, the page cache should be purged regularly or configured manually so suspicious, bot-like, legacy-browser, or not-human-like requests are not always served from an existing cached main document.

= What is Cache Shadow Capture? =

Cache Shadow Capture is a planned future feature. It is intended to use a small browser-side POST beacon to report cached pageviews that did not trigger normal PHP execution. STV would then deduplicate that shadow capture against normal origin captures to avoid double logging.

= Does STV break page cache to improve tracking? =

No. STV is designed to stay cache-friendly. It does not force normal pageviews out of cache just to count them.

If the optional Cache Compatibility settings are enabled, STV may add its own marked `.htaccess` rule block for selected suspicious, bot-like, legacy-browser, or not-human-like request patterns. This is intended to reduce cache-induced blind spots without disabling page cache globally.

= Why does STV use the label "suspicious" instead of claiming exact detection? =

Because well disguised traffic often cannot be proven with certainty from a single request alone. "suspicious" is often the most honest and useful label.

= Does STV log directly into the database? =

No. STV is database-free while logging. Request data is captured first and imported later, which avoids additional database load during the logging phase.

= Is STV only about AI crawlers? =

No. AI crawler related traffic was one of the original reasons behind STV, but the plugin is useful for suspicious traffic in general, including bots, scrapers, masked requests, and abnormal non-200 traffic.

= What makes STV different from a normal traffic plugin? =

Most traffic plugins try to show all visitors. STV does the opposite. It intentionally ignores clearly human-like 200 requests and focuses on suspicious, masked, not-human-like, and non-200 traffic that is often overlooked or poorly classified in conventional traffic views.

The difference is not just visibility. The difference is classification.

STV is built for investigation and pattern recognition, not for cosmetic visitor dashboards.

= Why not focus only on known bots? =

Because known bots are often only the visible part of the problem. In many cases, the larger and more relevant layer is suspicious traffic that does not openly identify itself as automation and only becomes noticeable through repeated patterns over time.

= Which server environments are supported? =

STV supports Apache and LiteSpeed Web Server environments only. For supported environments, the plugin may generate a plugin-local `.htaccess` file to protect the standalone prepend component and to provide the built-in rewrite probe.

If Cache Compatibility is explicitly enabled by the site owner, STV may also add a marked rule block to the WordPress root `.htaccess`. STV only manages its own marked block and can remove it again.

= Is LiteCache a third-party brand used by this plugin? =

No. LiteCache is the plugin author's own registered word mark. This plugin is an official LiteCache plugin.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release of LiteCache - Suspicious Traffic Viewer (STV).
