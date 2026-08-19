import re

path = r"C:\xampp\htdocs\NGO-donation-management-system\assets\css\components.css"
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# Replace .modal-backdrop up to .modal-backdrop.active .modal
pattern = r"\.modal-backdrop\s*\{[^}]+\}\s*\.modal-backdrop\.active\s*\{[^}]+\}\s*\.modal\s*\{[^}]+\}\s*\.modal-backdrop\.active\s*\.modal\s*\{[^}]+\}"

replacement = """.modal-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; background: rgba(20, 25, 35, 0.28); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); z-index: 9999; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: opacity 250ms ease, visibility 250ms ease; padding: 1.5rem; }
.modal-backdrop.active { opacity: 1; visibility: visible; }
.modal { background: var(--surface); border-radius: 20px; width: 100%; max-height: 85vh; display: flex; flex-direction: column; box-shadow: 0 25px 80px rgba(0, 0, 0, 0.22); border: 1px solid rgba(255, 255, 255, 0.25); transform: scale(0.95); transition: transform 250ms cubic-bezier(0.4, 0, 0.2, 1), opacity 250ms ease; opacity: 0; margin: 0 auto; }
.modal-backdrop.active .modal { transform: scale(1); opacity: 1; }"""

content = re.sub(pattern, replacement, content)

# Fix modal footer border radius
footer_pattern = r"(\.modal-footer\s*\{[^}]*?border-bottom-left-radius:\s*)[^;]+(;\s*border-bottom-right-radius:\s*)[^;]+(;\s*\})"
def repl_footer(m):
    return m.group(1) + "20px" + m.group(2) + "20px" + m.group(3)

content = re.sub(footer_pattern, repl_footer, content)

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated components.css")
