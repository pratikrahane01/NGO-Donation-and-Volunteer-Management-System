import os

# 1. Update layout_footer.php
footer_path = r"C:\xampp\htdocs\NGO-donation-management-system\includes\dashboard\layout_footer.php"
with open(footer_path, 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace('<div class="modal" id="globalModalContent">', '<div id="globalModalContent" style="display: contents;">')

with open(footer_path, 'w', encoding='utf-8') as f:
    f.write(content)

# 2. Update app.js
app_js_path = r"C:\xampp\htdocs\NGO-donation-management-system\assets\js\app.js"
with open(app_js_path, 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace(
    'contentContainer.innerHTML = \'<div style="padding: 40px; text-align: center;">',
    'contentContainer.innerHTML = \'<div class="modal" style="max-width: 400px; margin: auto; padding: 40px; text-align: center;">'
)
content = content.replace(
    'contentContainer.innerHTML = \'<div style="padding: 40px; text-align: center; color: var(--danger);">',
    'contentContainer.innerHTML = \'<div class="modal" style="max-width: 400px; margin: auto; padding: 40px; text-align: center; color: var(--danger);">'
)

with open(app_js_path, 'w', encoding='utf-8') as f:
    f.write(content)

# 3. Update all PHP files
project_dir = r"C:\xampp\htdocs\NGO-donation-management-system"
php_files = [f for f in os.listdir(project_dir) if f.endswith(".php")]

for file in php_files:
    path = os.path.join(project_dir, file)
    with open(path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    if '<div class="modal-content"' in content:
        content = content.replace('<div class="modal-content"', '<div class="modal"')
        with open(path, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Updated {file}")

print("Fix applied successfully.")
