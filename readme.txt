=== Schema Genie AI ===
Contributors: schema-genie
Tags: schema, json-ld, seo, structured-data, ai, schema-markup
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.4
License: GPLv2 or later

AI-powered JSON-LD structured data generator for WordPress. Automatically creates rich schema markup using Azure OpenAI.

== Description ==

Schema Genie AI is an intelligent JSON-LD schema markup generator for WordPress. Powered by Azure OpenAI, it automatically analyzes your content and generates comprehensive structured data including Organization, WebPage, Person, LegalService, FAQPage, HowTo, and NewsArticle schemas.

**Features:**
* AI-powered schema generation based on article content
* Supports multiple schema types (Article, FAQ, HowTo, LegalService, and more)
* One-click generation from the post editor sidebar
* Bulk schema generation for all published posts
* Seamless integration with Rank Math SEO
* Standalone mode when no SEO plugin is active
* Encrypted API key storage
* Rate limiting to prevent API overuse
* Auto-generate on publish (optional)

== Installation ==

1. Upload the plugin ZIP via Plugins > Add New > Upload Plugin
2. Activate the plugin
3. Go to Settings > Schema Genie AI
4. Enter your Azure OpenAI API key and endpoint
5. Open any post and click "Generate Schema" in the sidebar

== Changelog ==

= 1.1.4 =
* Removed openingHours, location @id, and image @id from Organization schema
* FAQPage schema now generated aggressively on every article

= 1.0.0 =
* Initial release
