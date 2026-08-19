import re

# 1. Fix app.js
app_path = r"C:\xampp\htdocs\NGO-donation-management-system\assets\js\app.js"
with open(app_path, 'r', encoding='utf-8') as f:
    app_js = f.read()

app_js = app_js.replace(
    "const actionUrl = form.action || window.location.href;",
    "const actionUrl = form.getAttribute('action') || window.location.href;"
)

with open(app_path, 'w', encoding='utf-8') as f:
    f.write(app_js)

# 2. Fix ngo_events.php role_id
php_path = r"C:\xampp\htdocs\NGO-donation-management-system\ngo_events.php"
with open(php_path, 'r', encoding='utf-8') as f:
    php = f.read()

php = php.replace(
    "SELECT id, full_name FROM users WHERE role_id = 4 AND status = 'active'",
    "SELECT id, full_name FROM users WHERE role_id = 5 AND status = 'active'"
)

with open(php_path, 'w', encoding='utf-8') as f:
    f.write(php)

print("Fixed AJAX form bug and Event Coordinator dropdown.")
