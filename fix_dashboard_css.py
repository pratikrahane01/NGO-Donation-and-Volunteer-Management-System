import re

path = r"C:\xampp\htdocs\NGO-donation-management-system\assets\css\dashboard.css"
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# Replace .sidebar { ... position: fixed; } with top: 0; left: 0; bottom: 0;
sidebar_pattern = r'(\.sidebar\s*\{[^}]*?position:\s*fixed;)([^}]*?\})'
def repl(m):
    # Check if top: 0 is already there
    if 'top: 0;' in m.group(1) or 'top: 0;' in m.group(2):
        return m.group(0)
    return m.group(1) + '\n    top: 0;\n    left: 0;\n    bottom: 0;' + m.group(2)

content = re.sub(sidebar_pattern, repl, content)

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated dashboard.css")
