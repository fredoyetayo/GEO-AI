=== GEO AI (AI SEO) ===
Contributors: geoaiteam
Tags: seo, ai, google ai overviews, perplexity, chatgpt, schema, meta tags
Requires at least: 6.2
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 1.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Modern SEO plugin optimized for AI answer engines (Google AI Overviews, Perplexity, ChatGPT) with essential classic SEO features.

== Description ==

GEO AI is a next-generation WordPress SEO plugin designed to help your content get cited in AI-powered search results while maintaining all the essential SEO features you need.

= Key Features =

**AI Answerability Audit**
* Powered by Google Gemini API
* Transparent scoring across 4 dimensions: Answerability, Structure, Trust, Technical
* Actionable fix suggestions with one-click quick fixes
* Auto-run audits on post publish/update (optional)

**Answer Card Block**
* Gutenberg block for TL;DR summaries and key facts
* Optimized for AI answer engines
* Clean, accessible markup

**Schema Assistant**
* Guided JSON-LD for Article, FAQ, HowTo, Organization, WebSite
* Conflict detection with other SEO plugins
* Validation hints

**Classic SEO Essentials**
* Title & Meta templates with variables
* OpenGraph & Twitter Cards
* XML Sitemaps (posts, pages, CPTs, taxonomies, images)
* Breadcrumbs (block, shortcode, function)
* Robots meta & canonical management
* 301/302 Redirects
* 404 Monitor

**AI Crawler Controls**
* Manage robots.txt suggestions for PerplexityBot, GPTBot, etc.
* HTTP header recommendations
* Honest caveats about crawler compliance

**Compatibility Mode**
* Smart coexistence with Yoast/Rank Math/SEOPress
* Automatic conflict detection
* Import settings from other SEO plugins

= Privacy & Security =

* API keys encrypted with libsodium when available
* Nonces and capability checks on all operations
* Clear privacy notices about AI API usage
* No external data transmission without user consent

= Performance First =

* Minimal database queries
* Action Scheduler for background tasks
* Lean frontend markup
* No heavy analytics dashboards

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/geo-ai/`
2. Activate the plugin through the 'Plugins' menu
3. Go to Settings → GEO AI to configure
4. Add your Google Gemini API key (get one from Google AI Studio)
5. Configure your title templates and social defaults
6. Start auditing your content!

== Frequently Asked Questions ==

= Do I need an API key? =

Yes, you need a Google Gemini API key for the AI audit feature. Get one for free at https://makersuite.google.com/app/apikey

= Does this guarantee my content will appear in AI results? =

No. GEO AI provides standards-based optimization guidance, but inclusion in AI answers depends on many factors beyond any plugin's control.

= Is this compatible with other SEO plugins? =

Yes! Enable "Coexist Mode" in Settings → General to prevent duplicate meta tags when using Yoast, Rank Math, or SEOPress alongside GEO AI.

= Does it work with Gutenberg? =

Yes, GEO AI is built for the block editor with a custom sidebar panel and Answer Card block.

= What about Classic Editor? =

The meta box works in Classic Editor, but the Answer Card block and sidebar audit panel require Gutenberg.

== Screenshots ==

1. Settings Dashboard with 10 tabs
2. Editor Sidebar with AI Audit scores
3. Answer Card Block in action
4. Snippet Preview & Social Cards
5. XML Sitemap Settings
6. Crawler Controls & robots.txt Preview

== Changelog ==

= 1.4.1 =
* Performance: 5-minute caching for dashboard data (95% fewer queries)
* Performance: SQL aggregation instead of PHP loops
* Performance: Optimized word count calculation in SQL
* Performance: Disabled Chart.js animations (reduced CPU/memory)
* Performance: Auto cache clearing on post save/delete
* Improved: Memory footprint reduced by ~80%
* Improved: Page load time improved by ~70%
* Fixed: Dashboard no longer loads all post content into memory

= 1.4.0 =
* Added: Internal Linking Suggestions with smart relevance scoring
* Added: Content Insights with word/phrase frequency analysis
* Added: Primary Category selector for better URL structure
* Added: Link statistics (internal/external/total counts)
* Added: One-click copy to clipboard for suggested links
* Added: Reading time and speaking time estimates
* Added: Lexical diversity metrics
* Added: Visual word cloud with frequency badges
* Added: Common phrases detection (2-3 word phrases)
* Improved: Permalink integration with primary category
* Improved: Breadcrumb navigation with primary category

= 1.3.1 =
* Added: Beautiful dashboard redesign with Chart.js visualizations
* Added: Bar chart for score distribution analysis
* Added: Doughnut chart for content quality overview
* Added: Top Performers and Needs Attention sections
* Added: Recent Activity feed with timestamps
* Added: Custom GEO icon (globe with AI neural network)
* Improved: Modern hero section with gradient background
* Improved: Animated stat cards with hover effects
* Improved: Progress bars for visual metrics
* Improved: Responsive design for all screen sizes

= 1.3.0 =
* Added: Focus Keyword Analysis with 6-point scoring system
* Added: Readability Analysis with Flesch Reading Ease calculator
* Added: SEO Dashboard with site-wide health monitoring
* Added: Keyword density and distribution tracking
* Added: Sentence/paragraph length analysis
* Added: Passive voice and transition words detection
* Added: Meta boxes in post editor for real-time analysis
* Added: Issue detection for missing meta, duplicate titles, thin content
* Improved: Professional dashboard UI with color-coded scores
* Improved: Automatic analysis on post save

= 1.2.1 =
* Fixed: Critical error when saving API keys (moved encryption to proper callback)
* Fixed: Site crashes due to encryption errors (added comprehensive error handling)
* Fixed: WordPress settings API compliance issues
* Improved: Encryption validation with detailed error logging
* Improved: API key storage with change detection

= 1.2.0 =
* Added: WordPress media library integration for OG image selection
* Added: Real-time image preview with insights (dimensions, file size, format)
* Added: Contextual help tooltips throughout settings
* Added: SEO best practices info boxes
* Added: Character counters with visual status indicators for titles/descriptions
* Added: Collapsible variables reference box
* Improved: Enhanced UI/UX for better user experience
* Improved: More intuitive layouts for non-technical users

= 1.1.0 =
* Added: Full-featured 301/302 redirect manager with wildcard support
* Added: 404 error logging and viewer
* Added: Bulk SEO Editor for editing titles/descriptions across posts
* Added: CSV export/import for SEO meta data
* Added: Clear audit cache tool
* Improved: Better admin UI with JavaScript-powered interactions
* Improved: Responsive design for mobile admin panels

= 1.0.0 =
* Initial release
* AI Answerability Audit via Gemini
* Answer Card Gutenberg block
* Schema Assistant with conflict detection
* Complete SEO essentials (titles, meta, OG, sitemaps, breadcrumbs)
* Compatibility mode with major SEO plugins
* WP-CLI commands
* 404 Monitor & Redirects

== Upgrade Notice ==

= 1.0.0 =
Initial release of GEO AI.

== Development ==

Development happens on GitHub: https://github.com/geoai/geo-ai

Contributions welcome!
