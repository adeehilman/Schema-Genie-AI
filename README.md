# Schema Genie AI 🧞‍♂️

AI-powered JSON-LD structured data generator for WordPress. Automatically creates rich schema markup using Azure OpenAI.

![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue?logo=wordpress)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple?logo=php)
![License](https://img.shields.io/badge/License-GPLv2-green)
![Version](https://img.shields.io/badge/Version-1.0.0-orange)

---

## ✨ Features

- **AI-Powered Generation** — Analyzes your content and generates accurate JSON-LD schema markup automatically
- **Multiple Schema Types** — Supports Article, FAQPage, HowTo, LegalService, NewsArticle, and more
- **One-Click Generate** — Generate schema directly from the post editor sidebar
- **Bulk Generation** — Generate schemas for all published posts at once
- **Rank Math Integration** — Seamlessly merges into Rank Math's `@graph` output
- **Standalone Mode** — Works perfectly without any SEO plugin
- **Encrypted API Key** — AES-256-CBC encryption for secure API key storage
- **Rate Limiting** — Built-in rate limiter to prevent API overuse
- **Auto-Generate** — Optionally generate schema on post publish (async via WP-Cron)
- **Token Tracking** — Monitor API token usage per post

## 📋 Requirements

| Requirement | Version |
|-------------|---------|
| WordPress   | 5.8+    |
| PHP         | 7.4+    |
| Azure OpenAI API | Active subscription |

## 🚀 Installation

1. Download the latest release ZIP
2. Go to **Plugins → Add New → Upload Plugin** in WordPress admin
3. Upload the ZIP file and activate the plugin
4. Navigate to **Settings → Schema Genie AI**
5. Enter your Azure OpenAI API key and endpoint
6. Test the connection and save

## ⚙️ Configuration

### Azure OpenAI Settings

| Setting | Description | Default |
|---------|-------------|---------|
| API Key | Your Azure OpenAI API key (stored encrypted) | — |
| Azure Endpoint | Your Azure cognitive services endpoint | — |
| API Version | Azure OpenAI API version | `2025-01-01-preview` |
| Model / Deployment | Azure deployment name | `gpt-4o` |

### Generation Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Temperature | AI creativity (lower = more deterministic) | `0.1` |
| Max Tokens | Maximum response tokens | `2000` |
| Timeout | Request timeout in seconds | `45` |
| Content Limit | Characters of content sent to AI | `4000` |
| Auto-generate | Generate on publish (first time only) | Off |

## 📖 Usage

### Single Post
1. Open any post/page in the editor
2. Find the **"Schema Genie AI"** meta box in the sidebar
3. Click **"Generate Schema"**
4. Preview the generated JSON-LD output

### Bulk Generation
1. Go to **Settings → Schema Genie AI**
2. Scroll to **"Bulk Schema Generation"**
3. Click **"Generate All Missing Schemas"**
4. Wait for the progress bar to complete

## 🏗️ Schema Types Generated

| Type | When Generated |
|------|---------------|
| `WebPage` | Always (base template) |
| `FAQPage` | When Q&A content is detected |
| `NewsArticle` | Always |
| `LegalService` | When legal service content is detected |
| `HowTo` | When step-by-step instructions are detected |
| `Organization` | Always (base template) |
| `Person` | Always (author entity) |

## 📁 File Structure

```
schema-genie-ai/
├── schema-genie-ai.php          # Main plugin file
├── readme.txt                   # WordPress plugin readme
├── README.md                    # This file
└── includes/
    ├── class-ai-client.php      # Azure OpenAI API client
    ├── class-meta-box.php       # Post editor meta box UI
    ├── class-schema-generator.php  # Core schema generation logic
    ├── class-schema-injector.php   # JSON-LD output injection
    ├── class-schema-template.php   # Master template builder
    └── class-settings.php       # Settings page & AJAX handlers
```

## 📄 License

This project is licensed under the **GPLv2 or later** — see the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.

## 📝 Changelog

### 1.0.0
- Initial release
