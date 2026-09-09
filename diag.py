import os

admin_dir = r'c:\xampp\htdocs\Final\admin'

for filename in os.listdir(admin_dir):
    if not filename.endswith('.php'):
        continue
    filepath = os.path.join(admin_dir, filename)
    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()
    
    has_shared_css = 'dashboard_shared.css' in content
    has_inline_style = '<style>' in content.lower()
    has_sidebar_js = 'sidebar.js' in content
    has_sidebar_el = 'id="sidebar"' in content or "id='sidebar'" in content

    if has_sidebar_el or 'hamburger' in content:
        flags = []
        if not has_shared_css:
            flags.append('NO_SHARED_CSS')
        if has_inline_style:
            flags.append('HAS_INLINE_STYLE')
        if not has_sidebar_js:
            flags.append('NO_SIDEBAR_JS')
        status = ', '.join(flags) if flags else 'OK'
        print(filename + ': ' + status)
