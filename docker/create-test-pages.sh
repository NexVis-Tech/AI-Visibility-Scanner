#!/bin/bash
set -e
export WP_CLI_ALLOW_ROOT=1

cd /var/www/html

echo "=========================================================="
echo " Creating Elementor & Gutenberg Test Pages...              "
echo "=========================================================="

# 1. Create Elementor Landing Page
ELEMENTOR_CONTENT='<div class="elementor elementor-test-page">
  <div class="elementor-element elementor-element-sec1 elementor-section">
    <div class="elementor-container">
      <div class="elementor-column elementor-col-100">
        <div class="elementor-widget-wrap">
          <div class="elementor-widget elementor-widget-heading">
            <h1>Elementor Test Landing Page - Next-Gen AI Services</h1>
          </div>
          <!-- INTENTIONAL ISSUE 1: Skipped H2 and H3 heading levels (H1 directly to H4) -->
          <div class="elementor-widget elementor-widget-heading">
            <h4>Our Key Enterprise Features (Skipped H2/H3)</h4>
          </div>
          <!-- INTENTIONAL ISSUE 2: Image missing alt text attribute -->
          <div class="elementor-widget elementor-widget-image">
            <img src="https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=800" class="elementor-animation-" />
          </div>
          <!-- INTENTIONAL ISSUE 3: Thin content, vague claims, no structured schema -->
          <div class="elementor-widget elementor-widget-text-editor">
            <p>We offer amazing services that boost your website performance. Click here to learn more about how we help companies succeed in modern digital landscape.</p>
            <h4>Unstructured Feature List</h4>
            <p>Fast speed, 24/7 support, guaranteed satisfaction.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>'

ELEMENTOR_DATA='[{"id":"sec1","elType":"section","isInner":false,"settings":{},"elements":[{"id":"col1","elType":"column","isInner":false,"settings":{"_column_size":100},"elements":[{"id":"w_title","elType":"widget","isInner":false,"widgetType":"heading","settings":{"title":"Elementor Test Landing Page - Next-Gen AI Services","header_size":"h1"}},{"id":"w_flawed_heading","elType":"widget","isInner":false,"widgetType":"heading","settings":{"title":"Our Key Enterprise Features (Skipped H2/H3)","header_size":"h4"}},{"id":"w_img","elType":"widget","isInner":false,"widgetType":"image","settings":{"image":{"url":"https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=800"},"caption":"","alt":""}},{"id":"w_text","elType":"widget","isInner":false,"widgetType":"text-editor","settings":{"editor":"<p>We offer amazing services that boost your website performance. Click here to learn more about how we help companies succeed in modern digital landscape.</p><h4>Unstructured Feature List</h4><p>Fast speed, 24/7 support, guaranteed satisfaction.</p>"}}]}]}]'

# Check if Elementor page exists or create it
EXISTING_ELEM=$(wp post list --post_type=page --name=elementor-ai-landing-page --format=ids --path=/var/www/html)
if [ -n "$EXISTING_ELEM" ]; then
    wp post delete "$EXISTING_ELEM" --force --path=/var/www/html
fi

ELEM_ID=$(wp post create \
    --post_type=page \
    --post_title="Elementor AI Test Landing Page" \
    --post_name="elementor-ai-landing-page" \
    --post_status="publish" \
    --post_content="$ELEMENTOR_CONTENT" \
    --porcelain \
    --path=/var/www/html)

wp post meta update "$ELEM_ID" _elementor_edit_mode "builder" --path=/var/www/html
wp post meta update "$ELEM_ID" _elementor_template_type "wp-page" --path=/var/www/html
wp post meta update "$ELEM_ID" _elementor_version "4.2.0" --path=/var/www/html
wp post meta update "$ELEM_ID" _elementor_data "$ELEMENTOR_DATA" --path=/var/www/html

echo "[SUCCESS] Elementor Landing Page created! (ID: $ELEM_ID, URL: http://localhost:8080/elementor-ai-landing-page/)"


# 2. Create Gutenberg Blocks Page
GUTENBERG_CONTENT='<!-- wp:heading {"level":1} -->
<h1>Gutenberg AI Test Page - Technical LLM Benchmark Guide</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Large Language Models (LLMs) rely on clear HTML document hierarchy, explicit Schema.org JSON-LD tags, and high citation density to evaluate domain authority.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":5} -->
<h5>Intentional Heading Hierarchy Deficit (Skipped H2, H3, H4 to H5)</h5>
<!-- /wp:heading -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=800" alt=""/><figcaption>AI Neural Network Visualization without ALT text</figcaption></figure>
<!-- /wp:image -->

<!-- wp:paragraph -->
<p>Many websites make vague claims without citations, such as "our AI system is 100x faster than traditional methods." Without data sources, entity schema, or structured metadata, AI answer engines like Perplexity AI and ChatGPT degrade content quality scores.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":5} -->
<h5>Duplicated Subheading Title (Intentional SEO & AI Readability Flaw)</h5>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Thin content with zero author bio, zero organizational schema, and missing open graph tags reduces generative search indexability.</p>
<!-- /wp:paragraph -->'

EXISTING_GUTEN=$(wp post list --post_type=page --name=gutenberg-ai-test-page --format=ids --path=/var/www/html)
if [ -n "$EXISTING_GUTEN" ]; then
    wp post delete "$EXISTING_GUTEN" --force --path=/var/www/html
fi

GUTEN_ID=$(wp post create \
    --post_type=page \
    --post_title="Gutenberg AI Test Page" \
    --post_name="gutenberg-ai-test-page" \
    --post_status="publish" \
    --post_content="$GUTENBERG_CONTENT" \
    --porcelain \
    --path=/var/www/html)

echo "[SUCCESS] Gutenberg Blocks Page created! (ID: $GUTEN_ID, URL: http://localhost:8080/gutenberg-ai-test-page/)"

wp rewrite flush --path=/var/www/html

echo "=========================================================="
echo " All Test Pages Successfully Provisioned!                  "
echo "=========================================================="
