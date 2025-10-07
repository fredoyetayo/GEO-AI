# Quick Start Guide

Get GEO AI up and running in 5 minutes.

## 🏃 Fast Setup

### 1. Run Setup Script

```bash
chmod +x setup.sh
./setup.sh
```

This will:
- Install npm dependencies
- Download Action Scheduler
- Build JavaScript assets

### 2. Install in WordPress

**Option A: Use wp-env (Recommended)**

```bash
npx wp-env start
```

Your WordPress site will be at:
- **Frontend**: http://localhost:8888
- **Admin**: http://localhost:8888/wp-admin
- **Credentials**: admin / password

**Option B: Manual Install**

```bash
# Copy to WordPress plugins directory
cp -r . /path/to/wordpress/wp-content/plugins/geo-ai/

# Or create symlink
ln -s $(pwd) /path/to/wordpress/wp-content/plugins/geo-ai
```

### 3. Activate Plugin

1. Go to WordPress Admin → Plugins
2. Find "GEO AI (AI SEO)"
3. Click "Activate"

### 4. Configure

1. Go to **Settings → GEO AI**
2. Click **General** tab
3. Add your Gemini API key ([Get one here](https://makersuite.google.com/app/apikey))
4. Click **Save Changes**

## ✨ First Audit

1. **Edit a post** in WordPress
2. **Open GEO AI sidebar** (click the icon or ⋮ → GEO AI)
3. **Click "Run AI Audit"**
4. **Review scores** and issues
5. **Apply quick fixes** if available

## 📝 Add Answer Card

1. In post editor, click **+** (Add Block)
2. Search for **"Answer Card"**
3. Fill in:
   - **TL;DR**: Concise summary (max 200 words)
   - **Key Facts**: Click "Add Key Fact" to add bullet points
4. **Publish** or **Update**

## 🗺️ Enable Sitemaps

1. **Settings → GEO AI → Sitemaps**
2. Check **"Generate XML sitemaps"**
3. Select post types to include
4. Check **"Add image URLs"** if desired
5. **Save Changes**

Your sitemap: `https://yoursite.com/sitemap.xml`

## 🤖 Block AI Crawlers (Optional)

1. **Settings → GEO AI → Crawlers & Robots**
2. Check bots to block (e.g., GPTBot, PerplexityBot)
3. **Copy** the generated robots.txt rules
4. **Add to** your site's robots.txt file

⚠️ **Note**: GEO AI doesn't write server files. You must manually add rules.

## 🔧 Development Mode

```bash
# Watch mode (auto-rebuild on changes)
npm start

# Production build
npm run build

# Lint code
npm run lint:js
npm run lint:css
```

## 🆘 Troubleshooting

### Audit returns mock data
→ Add valid Gemini API key in Settings

### Meta tags duplicated
→ Enable "Coexist Mode" in General settings

### Sitemap 404
→ Go to Settings → Permalinks → Save Changes

### Editor sidebar not showing
→ Ensure Gutenberg is active (not Classic Editor)

## 📚 Next Steps

- **Read full docs**: [README.md](./README.md)
- **Installation details**: [INSTALL.md](./INSTALL.md)
- **Configure title templates**: Settings → Titles & Meta
- **Set up social cards**: Settings → Social
- **Enable schema types**: Settings → Schema

---

**Need help?** Check the [README](./README.md) or open an issue on GitHub.

**Happy optimizing! 🚀**
