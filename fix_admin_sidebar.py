"""
fix_admin_sidebar.py
- For pages that DON'T have dashboard_shared.css: add the link tag
- For ALL pages with <style> blocks: remove the old sidebar/layout inline CSS
  so dashboard_shared.css rules take effect properly
"""
import os
import re

admin_dir = r'c:\xampp\htdocs\Final\admin'

skip_files = {
    'logout.php', 'login_process.php', 'generate_barcode.php', 'generate_bulk_barcodes.php',
    'export_section.php', 'create_superadmins.php', 'generate_password.php',
    'certificate.php', 'print.php', 'register.php', 'accounts.php',
    'login.html', 'diag.py', 'inject_sidebar.py', 'fix_sidebar.py',
    'update_admin_sidebars.py', 'BARCODE_SETUP_GUIDE.md',
}

# CSS properties that belong to the shared file and should be removed from inline styles
SIDEBAR_SELECTORS = [
    r'\.sidebar\b', r'\.sidebar-header\b', r'\.sidebar-title\b',
    r'\.nav-links\b', r'\.nav-item\b', r'\.sidebar-footer\b',
    r'\.nav-logout\b', r'\.main-content\b', r'\.main-description\b',
    r'\.hamburger\b', r'@media\s*\(max-width:\s*1024px\)',
    r'@media\s*\(max-width:\s*768px\)',
    r'@media\s*\(max-width:\s*480px\)',
]

def remove_css_block(css_text, selector_pattern):
    """Remove a CSS rule block matching the selector."""
    # Match selector { ... } including nested braces
    pattern = re.compile(
        selector_pattern + r'\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)?\}',
        re.DOTALL
    )
    return pattern.sub('', css_text)

def strip_inline_sidebar_css(style_content):
    """Remove sidebar and layout rules from an inline <style> block."""
    result = style_content
    for sel in SIDEBAR_SELECTORS:
        result = remove_css_block(result, sel)
    return result

shared_css_link = '    <link rel="stylesheet" href="../static/dashboard_shared.css">\n'

updated = []
skipped = []

for filename in os.listdir(admin_dir):
    if not filename.endswith('.php') or filename in skip_files:
        continue

    filepath = os.path.join(admin_dir, filename)
    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
        original = f.read()

    content = original

    # 1. Add dashboard_shared.css if missing
    if 'dashboard_shared.css' not in content:
        # Insert before </head>
        content = content.replace(
            '</head>',
            shared_css_link + '</head>',
            1
        )

    # 2. Strip sidebar/layout CSS from inline <style> blocks
    def replace_style(m):
        inner = m.group(1)
        cleaned = strip_inline_sidebar_css(inner)
        # Collapse excessive blank lines
        cleaned = re.sub(r'\n{3,}', '\n\n', cleaned)
        return '<style>' + cleaned + '</style>'

    content = re.sub(r'<style>(.*?)</style>', replace_style, content, flags=re.DOTALL)

    # 3. Fix .main-description → .main-content (leftover from bad copy-paste)
    content = content.replace('class="main-description"', 'class="main-content"')
    content = content.replace('.main-description{', '.main-content{')
    content = content.replace('.description-grid{', '.content-grid{')
    content = content.replace('class="description-grid"', 'class="content-grid"')

    # 4. Fix justify-description → justify-content (bad regex replacement artifact)
    content = content.replace('justify-description:', 'justify-content:')
    content = content.replace('fit-description', 'fit-content')

    if content != original:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        updated.append(filename)
    else:
        skipped.append(filename)

print('Updated (' + str(len(updated)) + '):')
for f in updated:
    print('  + ' + f)
print('\nSkipped (' + str(len(skipped)) + '):')
for f in skipped:
    print('  - ' + f)
