import re
import os

# 1. Update dashboard.css
dash_path = r"C:\xampp\htdocs\NGO-donation-management-system\assets\css\dashboard.css"
with open(dash_path, 'r', encoding='utf-8') as f:
    css = f.read()

# Update topbar height
css = re.sub(r'--topbar-height:\s*70px;', r'--topbar-height: 80px;', css)

# Replace .topbar section
topbar_replacement = """/* Topbar */
.topbar {
    height: var(--topbar-height);
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    box-shadow: 0 4px 24px rgba(0,0,0,0.02);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    position: sticky;
    top: 0;
    z-index: 999;
}
"""
css = re.sub(r'/\*\s*Topbar\s*\*/.*?\.topbar-left,\s*\.topbar-right', topbar_replacement + '\n.topbar-left, .topbar-right', css, flags=re.DOTALL)

# Replace search bar section
search_bar_replacement = """/* Search Bar */
.search-form-container {
    margin: 0;
    padding: 0;
    border: none;
    background: transparent;
}
.search-bar {
    position: relative;
    width: 400px;
    max-width: 420px;
    min-width: 340px;
    display: flex;
    align-items: center;
}
.search-bar input {
    width: 100%;
    height: 48px;
    padding: 0 40px 0 44px;
    border-radius: 16px;
    border: 1px solid rgba(0,0,0,0.08);
    background: rgba(255,255,255,0.9);
    font-family: var(--font-body);
    font-size: 0.95rem;
    color: var(--text-dark);
    transition: all var(--transition-fast);
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}
.search-bar input::placeholder {
    color: var(--text-tertiary);
}
.search-bar input:focus {
    outline: none;
    border-color: var(--success);
    background: white;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25), 0 4px 12px rgba(0,0,0,0.05);
}
.search-bar i.search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-tertiary);
    font-size: 1.1rem;
    pointer-events: none;
    transition: color var(--transition-fast);
}
.search-bar input:focus ~ i.search-icon {
    color: var(--success);
}
.shortcut-hint {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0,0,0,0.04);
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 6px;
    padding: 2px 8px;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
    pointer-events: none;
    font-family: monospace;
}
"""
css = re.sub(r'/\*\s*Search Bar\s*\*/.*?/\*\s*Topbar Actions\s*\*/', search_bar_replacement + '\n/* Topbar Actions */', css, flags=re.DOTALL)

# Replace action btn section
action_btn_replacement = """/* Topbar Actions */
.action-btn {
    position: relative;
    background: white;
    border: 1px solid rgba(0,0,0,0.05);
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-body);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.action-btn:hover {
    color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.08);
}
.action-btn.user-avatar {
    font-weight: 700;
    font-size: 1.1rem;
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}
.badge {
    position: absolute;
    top: -2px;
    right: -2px;
"""
css = re.sub(r'/\*\s*Topbar Actions\s*\*/.*?.badge\s*\{\s*position:\s*absolute;\s*top:\s*-2px;', action_btn_replacement, css, flags=re.DOTALL)

with open(dash_path, 'w', encoding='utf-8') as f:
    f.write(css)

# 2. Update topbar.php
topbar_path = r"C:\xampp\htdocs\NGO-donation-management-system\includes\dashboard\topbar.php"
with open(topbar_path, 'r', encoding='utf-8') as f:
    topbar_html = f.read()

# Replace search bar HTML
search_html = """<form action="admin_search.php" method="GET" class="search-form-container">
            <div class="search-bar">
                <input type="text" name="q" id="global-search-input" placeholder="Search campaigns, volunteers, donors...">
                <i class="fas fa-search search-icon"></i>
                <span class="shortcut-hint">/</span>
            </div>
        </form>"""
topbar_html = re.sub(r'<form action="admin_search\.php" method="GET" class="search-bar"[^>]*>.*?<\/form>', search_html, topbar_html, flags=re.DOTALL)

with open(topbar_path, 'w', encoding='utf-8') as f:
    f.write(topbar_html)


# 3. Update app.js (Keyboard listener for "/")
app_js_path = r"C:\xampp\htdocs\NGO-donation-management-system\assets\js\app.js"
with open(app_js_path, 'r', encoding='utf-8') as f:
    app_js = f.read()

if "global-search-input" not in app_js:
    shortcut_js = """
// Global Keyboard Shortcuts
document.addEventListener('keydown', (e) => {
    // Focus search on '/'
    if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        const searchInput = document.getElementById('global-search-input');
        if (searchInput) {
            e.preventDefault();
            searchInput.focus();
        }
    }
});
"""
    app_js += shortcut_js
    with open(app_js_path, 'w', encoding='utf-8') as f:
        f.write(app_js)

# 4. Update responsive.css
resp_path = r"C:\xampp\htdocs\NGO-donation-management-system\assets\css\responsive.css"
with open(resp_path, 'r', encoding='utf-8') as f:
    resp_css = f.read()

# Tablet
tablet_rules = """
    .search-bar { width: 260px; min-width: 200px; }
    .shortcut-hint { display: none; }
"""
if ".search-bar { width: 260px;" not in resp_css:
    resp_css = re.sub(r'(@media\s*\(max-width:\s*992px\)\s*\{)', r'\1' + tablet_rules, resp_css)

# Mobile
mobile_rules = """
    .search-form-container { display: none; }
"""
resp_css = re.sub(r'\.search-bar\s*\{\s*display:\s*none;\s*/\*\s*Hide on small mobile, show icon only\s*\*/\s*\}', mobile_rules, resp_css)

with open(resp_path, 'w', encoding='utf-8') as f:
    f.write(resp_css)

print("Update script completed successfully.")
