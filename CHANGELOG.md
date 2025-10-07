# Changelog

All notable changes to GEO AI will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2024-01-20

### Added

#### Redirects & 404 Management
- Full-featured 301/302 redirect manager with add/remove UI
- Wildcard support for redirect patterns
- 404 error logging with database table
- Recent 404 errors viewer (last 50 entries)
- Configurable retention period and max log entries
- Automatic old log cleanup

#### Bulk SEO Editor
- Admin table interface for editing titles/descriptions across posts
- Post type filter with dropdown
- Pagination for large datasets (20 posts per page)
- Inline editing with "Save All Changes" action
- Direct links to edit individual posts
- Real-time character count feedback

#### CSV Import/Export
- Export SEO meta data (titles, descriptions) as CSV by post type
- Import CSV with validation and error handling
- Includes post ID, title, URL for easy reference
- Skip invalid entries with detailed feedback
- Clear audit cache tool to remove all cached audit data

#### UI Enhancements
- JavaScript-powered add/remove redirects without page reload
- Better table styling for redirects and bulk editor
- Responsive design for mobile admin panels
- Success/error notices for all operations

---

## [1.0.0] - 2024-01-15

### Added

#### AI Features
- Google Gemini-powered content audit with 4-dimensional scoring
- Answer Card Gutenberg block for TL;DR summaries and key facts
- Editor sidebar panel with one-click audit and quick fixes
- Mock data fallback when API key is not configured

#### SEO Essentials
- Title and meta description templates with variable support
- Snippet preview with character count indicators
- OpenGraph and Twitter Card meta tags
- XML sitemaps for posts, pages, custom post types, and taxonomies
- Image URLs in sitemaps
- Breadcrumbs with Schema.org BreadcrumbList markup
- Breadcrumb shortcode and PHP function
- Per-post robots meta controls
- Canonical URL management

#### Schema
- JSON-LD output for Article, Organization, WebSite types
- SearchAction schema for site search
- Conflict detection to prevent duplication
- Extensible via filters

#### Compatibility
- Auto-detection of Yoast SEO, Rank Math, SEOPress, All in One SEO
- Coexist mode to suppress overlapping outputs
- Standalone mode for full control

#### Redirects & Monitoring
- 301/302 redirect manager with wildcard support
- 404 monitor with configurable retention
- Rate-limited logging

#### AI Crawler Controls
- robots.txt suggestions for PerplexityBot, GPTBot, CCBot, anthropic-ai
- HTTP header recommendations
- Clear caveats about crawler compliance

#### Security & Performance
- API key encryption with libsodium (fallback to XOR obfuscation)
- Nonce verification on all admin actions
- Permission checks on REST endpoints
- Action Scheduler integration for background tasks
- Minimal database queries

#### Developer Features
- WP-CLI commands (`wp geoai audit`)
- REST API endpoints at `/geoai/v1/`
- Extensible via actions and filters
- PHPCS-compliant code
- i18n ready

#### Admin Interface
- 10-tab settings page (General, Titles & Meta, Social, Schema, Sitemaps, Crawlers, Redirects, Bulk Editor, Tools, Advanced)
- Import/Export settings as JSON
- Character counters for titles and descriptions
- Social preview placeholders
- Robots.txt preview generator

### Developer Notes
- Requires WordPress 6.2+ and PHP 8.1+
- Built with @wordpress/scripts
- Uses modern React hooks in editor components
- Follows WordPress coding standards

### Known Limitations (V1)
- Bulk editor UI not yet implemented (placeholder)
- No server config file writes (.htaccess/Nginx)
- Redirect UI is basic (full UI planned for v1.1)
- 404 monitor admin view coming in v1.1
- No migration wizard UI yet (manual import only)

---

## [Unreleased]

### Planned for 1.1
- Bulk SEO editor with inline editing
- CSV import/export for meta fields
- Enhanced redirect manager UI
- 404 monitor admin dashboard
- FAQ and HowTo schema wizards

### Planned for 1.2
- Product schema support
- LocalBusiness schema
- Google Search Console integration
- Content quality suggestions

### Planned for 2.0
- Multi-language support (Polylang/WPML)
- Advanced analytics dashboard
- Keyword research integration
- Link building assistant

---

[1.0.0]: https://github.com/geoai/geo-ai/releases/tag/v1.0.0
[Unreleased]: https://github.com/geoai/geo-ai/compare/v1.0.0...HEAD
