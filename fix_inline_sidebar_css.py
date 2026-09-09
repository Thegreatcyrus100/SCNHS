"""
fix_inline_sidebar_css.py
Removes all sidebar/layout-related CSS from inline <style> blocks by stripping
individual property rules and entire selector blocks that belong to dashboard_shared.css
"""
import os, re

admin_dir = r'c:\xampp\htdocs\Final\admin'

skip_files = {
    'logout.php', 'login_process.php', 'generate_barcode.php', 'generate_bulk_barcodes.php',
    'export_section.php', 'create_superadmins.php', 'generate_password.php',
    'certificate.php', 'print.php', 'register.php', 'accounts.php',
    'superadmin_logout.php',
}

# Selectors that belong to dashboard_shared.css and should NOT be in inline styles
SHARED_SELECTORS = [
    '.sidebar', '.sidebar-header', '.sidebar-title', '.sidebar-footer',
    '.nav-links', '.nav-item', '.nav-logout',
    '.hamburger', '.main-content', '.main-description',
    '.content-grid', '.description-grid',
    '.panel', '.panel-header', '.panel-title',
    '.form-group', '.form-control',
    '.btn', '.btn-primary', '.btn-danger', '.btn-success', '.btn-print', '.btn-risk',
    '.alert', '.alert-success', '.alert-error',
    '.news-list', '.news-item', '.news-header', '.news-title', '.news-meta',
    '.news-content', '.news-description', '.news-actions',
    '@keyframes fadeIn',
]

def remove_selector_block(css, selector):
    """Remove a CSS selector block (handles nested braces for @media)."""
    # Escape special regex chars in selector
    esc = re.escape(selector)
    
    # Match: selector { ... } - simple block
    pattern = re.compile(
        r'\s*' + esc + r'\s*\{[^{}]*\}',
        re.DOTALL
    )
    css = pattern.sub('', css)
    
    # Match: selector { ... { ... } ... } - one level of nesting (@media)
    pattern2 = re.compile(
        r'\s*' + esc + r'[^{]*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}',
        re.DOTALL
    )
    css = pattern2.sub('', css)
    
    return css

def clean_style_block(style_content):
    result = style_content
    for sel in SHARED_SELECTORS:
        result = remove_selector_block(result, sel)
    # Fix leftover artifacts
    result = result.replace('justify-description:', 'justify-content:')
    result = result.replace('fit-description', 'fit-content')
    # Collapse excessive blank lines
    result = re.sub(r'\n{3,}', '\n\n', result)
    return result

updated = []
skipped = []

for filename in os.listdir(admin_dir):
    if not filename.endswith('.php') or filename in skip_files:
        continue

    filepath = os.path.join(admin_dir, filename)
    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
        original = f.read()

    content = original

    # Fix class="main-description" -> class="main-content"
    content = content.replace('class="main-description"', 'class="main-content"')

    # Strip sidebar CSS from inline <style> blocks
    def replace_style(m):
        inner = m.group(1)
        cleaned = clean_style_block(inner)
        return '<style>' + cleaned + '</style>'

    content = re.sub(r'<style>(.*?)</style>', replace_style, content, flags=re.DOTALL)

    if content != original:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        updated.append(filename)
    else:
        skipped.append(filename)

print('Updated (' + str(len(updated)) + '):')
for f in updated:
    print('  + ' + f)
print('\nClean/Skipped (' + str(len(skipped)) + '):')
for f in skipped:
    print('  - ' + f)
