# GEO AI (AI SEO) - WordPress Plugin

[![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Modern WordPress SEO plugin optimized for **AI answer engines** (Google AI Overviews, Perplexity, ChatGPT) with all the essential classic SEO features.

---

## 🚀 Features

### AI Answerability
- **Google Gemini-powered audits** with transparent scoring
- **4-dimensional analysis**: Answerability (40%), Structure (20%), Trust (25%), Technical (15%)
- **One-click quick fixes** (e.g., insert Answer Card, add citations)
- **Auto-run on save** (optional)

### Author UX
- **Gutenberg Answer Card block**: TL;DR + Key Facts in a beautiful, accessible layout
- **Editor sidebar panel**: Run audits, view scores, apply fixes without leaving the editor

### Schema Assistant
- **Guided JSON-LD**: Article, FAQ, HowTo, Product, LocalBusiness, Organization, WebSite + SearchAction
- **Conflict detection**: Warns when other plugins are outputting schema
- **Validation hints**: Common error checking

### SEO Essentials
- ✅ **Titles & Meta**: Templates with variables (`%%title%%`, `%%sitename%%`, etc.)
- ✅ **Social Cards**: OpenGraph & Twitter with preview
- ✅ **XML Sitemaps**: Posts, pages, CPTs, taxonomies, images
- ✅ **Breadcrumbs**: Block, shortcode, PHP function with BreadcrumbList schema
- ✅ **Robots & Indexing**: Per-post robots meta, canonical URLs
- ✅ **Redirects**: 301/302 manager with wildcard support
- ✅ **404 Monitor**: Opt-in logging with retention controls

### AI Crawler Controls
- **robots.txt suggestions** for PerplexityBot, GPTBot, CCBot, anthropic-ai
- **HTTP header snippets** (for server admins)
- **Honest caveats**: We're transparent that blocks may not be respected

### Compatibility
- **Coexist mode**: Auto-detects Yoast/Rank Math/SEOPress and suppresses overlapping outputs
- **Import wizard**: Pull settings from other SEO plugins
- **Export/Import**: JSON backup of all settings

---

## 📋 Requirements

- **WordPress**: 6.2+
- **PHP**: 8.1+
- **Gutenberg**: Block editor (for Answer Card and sidebar)
- **Google Gemini API Key**: Free tier available at [Google AI Studio](https://makersuite.google.com/app/apikey)

---

## 🛠️ Installation

### From WordPress Admin
1. Download the latest release
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the `.zip` file and activate
4. Navigate to **Settings → GEO AI** to configure

### Manual Installation
1. Clone or download this repository
2. Run `npm install && npm run build`
3. Upload the entire folder to `/wp-content/plugins/`
4. Activate via **Plugins** menu
5. Configure at **Settings → GEO AI**

---

## 🏗️ Development Setup

### Prerequisites
- Node.js 18+
- npm or yarn
- WordPress development environment

### Build Instructions

```bash
# Install dependencies
npm install

# Development build with watch mode
npm start

# Production build (optimized)
npm run build

# Lint JavaScript
npm run lint:js

# Lint CSS
npm run lint:css

# Format code
npm run format
```

### Project Structure

```
geo-ai/
├── geo-ai.php              # Main plugin file
├── includes/               # PHP classes
│   ├── class-geoai-admin.php
│   ├── class-geoai-rest.php
│   ├── class-geoai-analyzer.php
│   ├── class-geoai-compat.php
│   ├── class-geoai-schema.php
│   ├── class-geoai-sitemaps.php
│   ├── class-geoai-meta.php
│   ├── class-geoai-social.php
│   ├── class-geoai-breadcrumbs.php
│   ├── class-geoai-redirects.php
│   ├── class-geoai-404.php
│   ├── class-geoai-cli.php
│   └── traits/
│       └── trait-encryption.php
├── blocks/                 # Gutenberg blocks
│   └── answer-card/
│       ├── block.json
│       ├── index.js
│       ├── edit.js
│       ├── save.js
│       ├── style.css
│       └── editor.css
├── src/                    # JavaScript source
│   └── editor.js          # Editor sidebar plugin
├── assets/                 # Compiled assets
│   ├── admin.css
│   ├── admin.js
│   ├── editor.css
│   └── editor.js          # Built from src/
├── vendor/                 # Third-party libraries
│   └── action-scheduler/  # Bundled
├── package.json
└── readme.txt
```

---

## 🎯 Usage

### Running Your First Audit

1. **Add API Key**: Settings → GEO AI → General → Enter your Gemini API key
2. **Edit a Post**: Open any post in Gutenberg
3. **Open GEO AI Panel**: Click the GEO AI icon in the sidebar (or ⋮ → GEO AI)
4. **Run Audit**: Click "Run AI Audit"
5. **Review Scores**: See your 4-dimensional breakdown and issues
6. **Apply Fixes**: Click "Apply Quick Fix" on suggested actions

### Inserting an Answer Card

**Via Quick Fix:**
1. Run audit → Click "Apply Quick Fix" on "missing_tldr" issue

**Manually:**
1. In editor, click **+** → Search "Answer Card"
2. Fill in TL;DR (max 200 words)
3. Add Key Facts with the "Add Key Fact" button

### Setting Up Sitemaps

1. **Settings → GEO AI → Sitemaps**
2. Enable sitemaps
3. Choose post types & taxonomies to include
4. Toggle image inclusion
5. Your sitemap is live at `https://yoursite.com/sitemap.xml`

### Managing AI Crawlers

1. **Settings → GEO AI → Crawlers & Robots**
2. Check which bots to block (PerplexityBot, GPTBot, etc.)
3. Copy the generated robots.txt rules
4. Add them to your site's `robots.txt` file

**Note**: GEO AI does not write server files. Rules are previews only.

### WP-CLI Commands

```bash
# Audit a single post
wp geoai audit 123

# Audit all posts
wp geoai audit all

# Audit posts with min score filter
wp geoai audit all --min-score=80
```

---

## 🔐 Security & Privacy

### Encryption
- API keys encrypted with **libsodium** when available
- Fallback XOR obfuscation on older systems
- Keys never transmitted to frontend

### Data Flow
- **Audit requests**: Post content → Gemini API → Audit scores (stored in postmeta)
- **No tracking**: We don't collect usage data
- **User consent**: API usage is opt-in via manual key entry

### Permissions
- **Audit endpoint**: Requires `edit_post` capability
- **Settings page**: Requires `manage_options`
- All forms protected with nonces

---

## 🤝 Compatibility

### Works With
- ✅ Yoast SEO (coexist mode)
- ✅ Rank Math (coexist mode)
- ✅ SEOPress (coexist mode)
- ✅ All in One SEO (coexist mode)
- ✅ Classic Editor (meta box only)
- ✅ Multisite

### Tested With
- WordPress 6.2, 6.3, 6.4
- PHP 8.1, 8.2, 8.3
- Block themes (Twenty Twenty-Four)
- Classic themes

---

## 📊 Settings Tabs

1. **General**: API key, auto-run, compatibility mode
2. **Titles & Meta**: Templates with variables
3. **Social**: OpenGraph/Twitter defaults
4. **Schema**: Enable/disable types, sitewide defaults
5. **Sitemaps**: CPTs/taxonomies, image inclusion, ping
6. **Crawlers & Robots**: AI bot controls, robots.txt preview
7. **Redirects & 404**: Redirect manager, 404 logging
8. **Bulk Editor**: Inline edit titles/meta (coming soon)
9. **Tools**: Import/Export, migration, cache clearing
10. **Advanced**: Debug mode, role capabilities

---

## 🐛 Troubleshooting

### Audit returns mock data
**Cause**: No API key or invalid key  
**Fix**: Add a valid Gemini API key in Settings → General

### Meta tags duplicated
**Cause**: Running alongside another SEO plugin in standalone mode  
**Fix**: Enable "Coexist Mode" in Settings → General

### Sitemap 404
**Cause**: Rewrite rules not flushed  
**Fix**: Go to Settings → Permalinks → Click "Save Changes"

### Editor sidebar not showing
**Cause**: Block editor not active  
**Fix**: Ensure Gutenberg is enabled (not Classic Editor)

---

## 🗺️ Roadmap

### V1.1 (Planned)
- [ ] Bulk SEO editor UI
- [ ] CSV import/export for meta
- [ ] Enhanced redirect manager UI
- [ ] 404 monitor admin interface

### V1.2 (Planned)
- [ ] FAQ & HowTo schema wizards
- [ ] Product schema support
- [ ] LocalBusiness schema
- [ ] Google Search Console integration

### V2.0 (Future)
- [ ] Multi-language support (Polylang/WPML)
- [ ] Content quality suggestions
- [ ] Keyword research integration
- [ ] Link building assistant

---

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Follow WordPress Coding Standards (PHPCS)
4. Write tests if adding new features
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Coding Standards
- **PHP**: WordPress PHPCS
- **JavaScript**: ESLint (WordPress preset)
- **CSS**: Stylelint (WordPress preset)

---

## 📝 License

This plugin is licensed under the **GPL v2 or later**.

```
GEO AI (AI SEO)
Copyright (C) 2025 GEO AI Team

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

---

## 📧 Support

- **Documentation**: [Coming Soon]
- **Issues**: [GitHub Issues](https://github.com/geoai/geo-ai/issues)
- **Discussions**: [GitHub Discussions](https://github.com/geoai/geo-ai/discussions)

---

## 🙏 Acknowledgments

- Built with [@wordpress/scripts](https://www.npmjs.com/package/@wordpress/scripts)
- Powered by [Google Gemini API](https://ai.google.dev/)
- Background tasks via [Action Scheduler](https://actionscheduler.org/)
- Inspired by Yoast, Rank Math, and the WordPress community

---

**Made with ❤️ for the WordPress community**
