#!/bin/bash
set -e

export WP_CLI_ALLOW_ROOT=1

if [ "$#" -gt 0 ]; then
    exec "$@"
fi

echo "=========================================================="
echo " Starting WordPress Setup & Sample Data Provisioning...   "
echo "=========================================================="

cd /var/www/html

# Wait for MySQL / MariaDB connection to be healthy
echo "Waiting for database connection..."
until wp db query "SELECT 1;" --path=/var/www/html > /dev/null 2>&1; do
    echo "Database is not ready yet. Retrying in 3 seconds..."
    sleep 3
done
echo "[SUCCESS] Database connected successfully!"

# Check if WordPress is already installed
if ! wp core is-installed --path=/var/www/html > /dev/null 2>&1; then
    echo "Installing WordPress core..."
    wp core install \
        --path=/var/www/html \
        --url="http://localhost:8080" \
        --title="AI Visibility Test Site" \
        --admin_user="admin" \
        --admin_password="password123" \
        --admin_email="admin@example.com" \
        --skip-plugins

    echo "[SUCCESS] WordPress core installed!"
else
    echo "[INFO] WordPress is already installed."
fi

# Configure Permalinks (Essential for VPS/Shared hosting REST API & pretty links)
echo "Setting permalink structure to /%postname%/..."
wp option update permalink_structure '/%postname%/' --path=/var/www/html

# Install & Activate standard theme if needed
echo "Ensuring theme is active..."
wp theme activate twentytwentyfour --path=/var/www/html || wp theme activate twentytwentythree --path=/var/www/html || echo "[INFO] Default theme active."

# Create Categories
echo "Creating categories..."
CAT_AI=$(wp term create category "Artificial Intelligence" --slug=ai --porcelain --path=/var/www/html || wp term get category ai --field=term_id --path=/var/www/html)
CAT_SEO=$(wp term create category "SEO & Optimization" --slug=seo --porcelain --path=/var/www/html || wp term get category seo --field=term_id --path=/var/www/html)
CAT_TECH=$(wp term create category "Technology & Web" --slug=technology --porcelain --path=/var/www/html || wp term get category technology --field=term_id --path=/var/www/html)

# Create Pages
echo "Seeding sample pages..."

# Front Page
if ! wp post list --post_type=page --name=home --format=ids --path=/var/www/html | grep -q .; then
    HOME_ID=$(wp post create --post_type=page --post_title="Home - AI Visibility Test Platform" --post_name="home" --post_status="publish" \
        --post_content="<!-- wp:heading {\"level\":1} --><h1>Welcome to AI Visibility Test Platform</h1><!-- /wp:heading --><!-- wp:paragraph --><p>This website is configured to demonstrate how modern Search Engines, LLMs (ChatGPT, Perplexity, Claude, Gemini), and AI Web Crawlers inspect, index, and evaluate website visibility and structured data.</p><!-- /wp:heading --><!-- wp:heading {\"level\":2} --><h2>Key Test Features</h2><!-- /wp:heading --><!-- wp:list --><ul><li>Comprehensive AI Crawler Audit</li><li>Schema & Structured Data Verification</li><li>Robots.txt & Meta Tags Inspection</li><li>LLM Answer Engine Readiness Score</li></ul><!-- /wp:list -->" --porcelain --path=/var/www/html)
    wp option update show_on_front page --path=/var/www/html
    wp option update page_on_front "$HOME_ID" --path=/var/www/html
fi

# About Page
if ! wp post list --post_type=page --name=about-us --format=ids --path=/var/www/html | grep -q .; then
    wp post create --post_type=page --post_title="About Us" --post_name="about-us" --post_status="publish" \
        --post_content="<!-- wp:heading {\"level\":1} --><h1>About NexVis Technologies</h1><!-- /wp:heading --><!-- wp:paragraph --><p>NexVis Technologies builds next-generation WordPress plugins designed to help web publishers, brands, and agencies stay visible across AI discovery engines.</p><!-- /wp:paragraph --><!-- wp:heading {\"level\":2} --><h2>Our Mission</h2><!-- /wp:heading --><!-- wp:paragraph --><p>To empower site owners with deep analytics, AI audit scores, and automated optimization tools for the AI-driven search era.</p><!-- /wp:paragraph -->" --path=/var/www/html
fi

# Services Page
if ! wp post list --post_type=page --name=services --format=ids --path=/var/www/html | grep -q .; then
    wp post create --post_type=page --post_title="Our Services & Audit Tools" --post_name="services" --post_status="publish" \
        --post_content="<!-- wp:heading {\"level\":1} --><h1>AI Visibility & GEO Audit Services</h1><!-- /wp:heading --><!-- wp:paragraph --><p>Explore our suite of auditing features, content readability metrics, and schema generation tools tailored for AI web bots.</p><!-- /wp:paragraph -->" --path=/var/www/html
fi

# Seed Blog Posts
echo "Seeding sample blog posts..."

# Post 1
if ! wp post list --name=optimizing-wordpress-for-ai-search-engines --format=ids --path=/var/www/html | grep -q .; then
    wp post create --post_title="Optimizing Your WordPress Website for AI Search Engines" \
        --post_name="optimizing-wordpress-for-ai-search-engines" \
        --post_status="publish" \
        --post_category="$CAT_AI,$CAT_SEO" \
        --post_content="<!-- wp:heading {\"level\":1} --><h1>Optimizing Your WordPress Website for AI Search Engines</h1><!-- /wp:heading --><!-- wp:paragraph --><p>As artificial intelligence transforms search behavior, websites must adapt to how Large Language Models (LLMs) like GPT-4, Claude 3.5, and Gemini parse web content. Traditional SEO focuses on keywords and backlinks, but AI Visibility requires structured data, semantic clarity, and clean entity relationships.</p><!-- /wp:paragraph --><!-- wp:heading {\"level\":2} --><h2>Understanding AI Web Crawlers</h2><!-- /wp:heading --><!-- wp:paragraph --><p>AI bots such as GPTBot, ClaudeBot, PerplexityBot, and Bytespider crawl the web to index authoritative content. Ensuring your robots.txt and server HTTP headers properly allow or target these bots is vital for maintaining digital presence.</p><!-- /wp:paragraph --><!-- wp:heading {\"level\":2} --><h2>Key Elements of AI Visibility</h2><!-- /wp:heading --><!-- wp:list --><ul><li><strong>Structured JSON-LD Schema:</strong> Provides unambiguous context about Organization, Article, Product, and Author entities.</li><li><strong>Semantic Heading Hierarchy:</strong> Logical H1, H2, and H3 structures aid LLM content parsing.</li><li><strong>High Citation Density:</strong> Clear claims backed by verifiable data points improve answer engine citations.</li></ul><!-- /wp:list -->" --path=/var/www/html
fi

# Post 2
if ! wp post list --name=understanding-how-llms-index-web-content --format=ids --path=/var/www/html | grep -q .; then
    wp post create --post_title="Understanding How LLMs and Answer Engines Index Web Content" \
        --post_name="understanding-how-llms-index-web-content" \
        --post_status="publish" \
        --post_category="$CAT_AI" \
        --post_content="<!-- wp:heading {\"level\":1} --><h1>Understanding How LLMs and Answer Engines Index Web Content</h1><!-- /wp:heading --><!-- wp:paragraph --><p>Answer engines like Perplexity AI and SearchGPT extract direct answers from top-ranked web pages. This article breaks down the technical ingestion pipeline of modern web crawlers.</p><!-- /wp:paragraph --><!-- wp:heading {\"level\":2} --><h2>RAG Systems and Citation Extraction</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Retrieval-Augmented Generation (RAG) splits text into semantic chunks and converts them into vector embeddings. Clean HTML markup without intrusive scripts or broken layout trees enables higher embedding quality.</p><!-- /wp:paragraph -->" --path=/var/www/html
fi

# Post 3
if ! wp post list --name=why-schema-org-matters-for-generative-engine-optimization --format=ids --path=/var/www/html | grep -q .; then
    wp post create --post_title="Why Schema.org Matters for Generative Engine Optimization (GEO)" \
        --post_name="why-schema-org-matters-for-generative-engine-optimization" \
        --post_status="publish" \
        --post_category="$CAT_SEO,$CAT_TECH" \
        --post_content="<!-- wp:heading {\"level\":1} --><h1>Why Schema.org Matters for Generative Engine Optimization (GEO)</h1><!-- /wp:heading --><!-- wp:paragraph --><p>Generative Engine Optimization (GEO) relies heavily on explicit metadata. Rich JSON-LD markup helps search bots identify who published the article, what entities are discussed, and how content maps to real-world knowledge graphs.</p><!-- /wp:paragraph -->" --path=/var/www/html
fi

# Activate AI Visibility Scanner Plugin
echo "Activating AI Visibility Scanner plugin..."
if wp plugin list --path=/var/www/html | grep -q "ai-visibility-scanner"; then
    wp plugin activate ai-visibility-scanner --path=/var/www/html
    echo "[SUCCESS] AI Visibility Scanner activated!"
else
    echo "[WARNING] ai-visibility-scanner plugin directory not detected yet."
fi

# Activate Mirror Readiness Scanner Plugin
echo "Activating Mirror Readiness Scanner plugin..."
if wp plugin list --path=/var/www/html | grep -q "mirror-readiness-scanner"; then
    wp plugin activate mirror-readiness-scanner --path=/var/www/html
    echo "[SUCCESS] Mirror Readiness Scanner activated!"
else
    echo "[WARNING] mirror-readiness-scanner plugin directory not detected yet."
fi

# Flush rewrite rules
wp rewrite flush --path=/var/www/html

echo "=========================================================="
echo " [COMPLETE] WordPress Test Site Setup Successfully Done!  "
echo " Site URL:  http://localhost:8080                         "
echo " Admin URL: http://localhost:8080/wp-admin                 "
echo " User:      admin | Pass: password123                     "
echo "=========================================================="
