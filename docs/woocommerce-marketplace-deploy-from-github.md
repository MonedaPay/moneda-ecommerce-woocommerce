# deployment-woocommerce-marketplace

This guide shows two reliable paths to publish an extension that lives on GitHub to the **WooCommerce.com Marketplace**:

1) **Manual deployment via Vendor Dashboard** (new products and updates)  
2) **Automated deployment for updates** using the Marketplace **programmatic deploy API** from a CI workflow

The steps and links below reflect current Woo/Automattic documentation.

---

## 0) What you’ll need (pre‑submission checklist)

- A **WooCommerce Vendor Account** (access to the **Vendor Dashboard**).
- Your plugin **supports PHP 7.4+** (PHP 8+ strongly recommended).  
- Your plugin **supports the latest two major releases** of **WooCommerce** and **WordPress**.
- Your code follows **security best practices** and **Woo extension best practices**.
- Pass **QIT (Quality Insights Toolkit)** tests: Activation, E2E, API, Security, PHPCompatibility, Malware.
- **HPOS compatibility** (High‑Performance Order Storage) — required for new submissions.
- Prioritize **Block Editor compatibility** (Cart & Checkout blocks are default).

> You’ll provide a **ZIP** of your plugin, **testing instructions** (staging creds, steps, sample data, license keys if applicable), and business details during submission.

**Docs to review:**  
- Getting started & submission overview  
- Pre‑submission checklist (security, versions, QIT, HPOS, blocks)  
- Extension UX/best‑practices guidelines  
- HPOS recipe/compat guide

---

## 1) One‑time: become a vendor & submit your product

1. **Sign up** and open the **Vendor Dashboard**.  
2. Go to **Submissions → Submit Product** and choose: **Extension**, **Theme**, **SaaS**, or **Business Service**.  
3. **Freemium?** Submit **both** the free (wp.org) and paid versions; Woo merges them on one product page.
4. Upload your **ZIP**, complete **Business Details** and **Testing Instructions**, then submit.
5. The process:
   - **Automated QIT tests** run. If a test fails, the submission status becomes **Changes required**; replace the file and re‑run tests.  
   - **Human review**: Business, Code, UX.  
   - **Launch prep** and publishing.

---

## 2) Package a release from GitHub

> Ship a **clean, production build**. Exclude dev files (`node_modules`, tests, tooling, dotfiles).

**Checklist for the ZIP:**
- **Version** bumped in the **main plugin header**.
- **`changelog.txt`** included and formatted; entry matches the new version.
- Folder & ZIP **names** match what the uploader expects (you’ll see the required name in the UI).
- If you build assets (JS/CSS), run the build and include the **compiled** output.
- Provide any **integration keys/sandbox creds** for test flows in your submission notes.

**Example: build and stage an export (bash):**
```bash
# from your Git repo
git switch main && git pull --ff-only
npm ci && npm run build   # or composer install --no-dev
mkdir -p ./dist/my-extension
rsync -av --delete \
  --exclude=node_modules --exclude=.git --exclude=.github \
  --exclude=tests --exclude=.distignore --exclude=.gitignore \
  ./ ./dist/my-extension/
# Ensure changelog.txt is present and version headers match
cd dist && zip -r my-extension-1.2.3.zip my-extension
```

---

## 3) Manual deployment (recommended for first release)

**New product**: Use **Submissions → Submit Product** (Section 1).

**Updates to an existing product**:
1. Vendor Dashboard → **Products → All Products** → pick your product.  
2. Open the **Versions** tab → **Add Version**.  
3. **Upload the ZIP**, **enter the version**, **Submit new version**.  
4. Automated tests run (Activation, E2E, API, Security, PHPCompatibility, Malware).  
5. If all required tests pass, the version **auto‑deploys**; otherwise fix issues and upload again.

**Common upload errors to avoid:**
- Missing or invalid **`changelog.txt`**.  
- **Version mismatch** between plugin header, changelog entry, and the version you typed.  
- **ZIP/folder name** doesn’t match the expected pattern.

---

## 4) Automated deployment from GitHub (updates)

Woo supports **programmatic deploys** for *updates* (not new submissions). Use an **Application Password** from your vendor account and POST your ZIP.

### 4.1 Generate credentials
- Log in to Vendor Dashboard and **Generate Application Password** (scoped to deploy).  
- Note your **Woo username**, **product_id**, and the generated **app password**.

### 4.2 Minimal CI job (GitHub Actions)
```yaml
name: Deploy update to Woo Marketplace

on:
  workflow_dispatch:
  push:
    tags:
      - "v*.*.*"   # release tags like v1.2.3

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0

      # Build your plugin (adjust to your stack)
      - run: |
          npm ci
          npm run build
          mkdir -p dist/my-extension
          rsync -av --delete \
            --exclude=node_modules --exclude=.git --exclude=.github \
            --exclude=tests --exclude=.distignore --exclude=.gitignore \
            ./ ./dist/my-extension/
          cd dist && zip -r my-extension-${GITHUB_REF_NAME#v}.zip my-extension

      # Deploy via Woo deploy API
      - name: Deploy to WooCommerce.com
        env:
          WOO_USERNAME: ${{ secrets.WOO_USERNAME }}
          WOO_APP_PASSWORD: ${{ secrets.WOO_APP_PASSWORD }}
          WOO_PRODUCT_ID: ${{ secrets.WOO_PRODUCT_ID }}
          VERSION: ${{ github.ref_name }}
        run: |
          FILE="dist/my-extension-${VERSION#v}.zip"
          curl -X POST "https://woocommerce.com/wp-json/wc/submission/runner/v1/product/deploy" \
            -F "file=@${FILE}" \
            -F "product_id=${WOO_PRODUCT_ID}" \
            -F "username=${WOO_USERNAME}" \
            -F "password=${WOO_APP_PASSWORD}" \
            -F "version=${VERSION#v}"
```

### 4.3 Check deployment status
```bash
curl -X POST https://woocommerce.com/wp-json/wc/submission/runner/v1/product/deploy/status \
  -F "product_id=$WOO_PRODUCT_ID" \
  -F "username=$WOO_USERNAME" \
  -F "password=$WOO_APP_PASSWORD"
```

> Tip: run QIT locally or from CI before deploying to reduce failures.

---

## 5) Technical quality & compatibility (must‑dos)

- **HPOS**: declare support and use WC **CRUD** APIs for orders. Example in your main plugin file:
```php
add_action( 'before_woocommerce_init', function() {
  if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
  }
});
```
- **Blocks / Cart & Checkout**: ensure compatibility with the block‑based experience; avoid shortcode‑only UIs.  
- **Accessibility**: meet the **WordPress Accessibility Coding Standards**.  
- **Popular extensions**: test alongside Bookings, Subscriptions, Product Add‑Ons, Product Bundles, Min/Max Quantities, Shipment Tracking.  
- **Security**: nonces/permissions/escaping, no remote code, safe file ops, sanitized settings.  
- **Headers & versions**: keep plugin header `Version`, `Requires at least`, `Requires PHP`, and your **changelog** in sync. Use **SemVer** for your product versions.

---

## 6) Recommended update cadence

- Update **at least every 6 months** (risk of removal if stale).  
- Align with **Woo core releases**; patch security issues promptly.  
- Maintain transparent **changelogs** and keep docs/product page current.

---

## 7) Troubleshooting

- **Upload rejected** → read the error in the submission/tests table; fix and **Replace** the ZIP to re‑run.  
- **Version mismatch** → ensure header, changelog, and form version match exactly.  
- **Naming error** → adjust ZIP & top‑level folder to the name shown in the uploader UI.  
- **QIT failures** → open the result URL, fix issues, re‑run locally/CI, then upload.  
- **HPOS issues** → confirm you’re using WC CRUD and declared compatibility.

---

## 8) Useful links (official)

- **Submit your product / Pre‑submission checklist**: https://woocommerce.com/document/submitting-your-product-to-the-woo-marketplace/  
- **Getting started / Marketplace overview**: https://woocommerce.com/document/marketplace-overview/  
- **Manage products & upload new versions**: https://woocommerce.com/document/marketplace-manage-products/  
- **Product update guidelines & programmatic deploy API**: https://woocommerce.com/document/product-update-guidelines/  
- **HPOS docs**: https://woocommerce.com/document/high-performance-order-storage/  
- **HPOS developer recipe guide**: https://developer.woocommerce.com/docs/features/high-performance-order-storage/recipe-book/  
- **Extension best practices & UX guidelines**: https://developer.woocommerce.com/docs/extensions/best-practices-extensions/extension-development-best-practices/  

Happy shipping! 🚀
