import re

# 1. Update components.css
path_comp = r"C:\xampp\htdocs\NGO-donation-management-system\assets\css\components.css"
with open(path_comp, 'r', encoding='utf-8') as f:
    comp_content = f.read()

# Update .modal-backdrop
comp_content = re.sub(
    r'\.modal-backdrop\s*\{[^}]*?background:[^;]*;[^}]*?transition:[^;]*;[^}]*?\}',
    r'.modal-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.35); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); z-index: 9999; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: opacity 220ms ease, visibility 220ms ease; padding: 1.5rem; }',
    comp_content
)

# Update .modal
comp_content = re.sub(
    r'\.modal\s*\{[^}]*?max-height:\s*85vh;[^}]*?transform:[^}]*?transition:[^}]*?\}',
    r'.modal { background: var(--surface); border-radius: 20px; width: min(92vw, 780px); max-height: 85vh; display: flex; flex-direction: column; box-shadow: 0 25px 80px rgba(0, 0, 0, 0.20); border: 1px solid rgba(255, 255, 255, 0.25); transform: scale(0.96); transition: transform 220ms cubic-bezier(0.4, 0, 0.2, 1), opacity 220ms ease; opacity: 0; margin: 0 auto; }',
    comp_content
)

# Update .modal-header, .modal-title, .modal-close
comp_content = re.sub(r'\.modal-header\s*\{[^}]*?\}', r'.modal-header { padding: 24px 32px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }', comp_content)
comp_content = re.sub(r'\.modal-title\s*\{[^}]*?\}', r'.modal-title { font-size: 32px; font-weight: 700; margin: 0; color: var(--text-primary); line-height: 1.2; }', comp_content)
comp_content = re.sub(r'\.modal-close\s*\{[^}]*?\}', r'.modal-close { background: transparent; border: none; font-size: 1.25rem; color: var(--text-tertiary); cursor: pointer; transition: all var(--transition-fast); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }', comp_content)
comp_content = re.sub(r'\.modal-close:hover\s*\{[^}]*?\}', r'.modal-close:hover { color: var(--danger); background: rgba(0,0,0,0.05); }', comp_content)

# Update .modal-body, .modal-footer
comp_content = re.sub(r'\.modal-body\s*\{[^}]*?\}', r'.modal-body { padding: 32px; overflow-y: auto; flex: 1; }', comp_content)
comp_content = re.sub(r'\.modal-footer\s*\{[^}]*?\}', r'.modal-footer { padding: 24px 32px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 16px; background: var(--surface-hover); border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; flex-shrink: 0; position: sticky; bottom: 0; }', comp_content)

with open(path_comp, 'w', encoding='utf-8') as f:
    f.write(comp_content)


# 2. Update forms.css
path_forms = r"C:\xampp\htdocs\NGO-donation-management-system\assets\css\forms.css"
with open(path_forms, 'r', encoding='utf-8') as f:
    forms_content = f.read()

# form group spacing
forms_content = re.sub(r'\.form-group\s*\{[^}]*?margin-bottom:[^;]*;([^}]*?)\}', r'.form-group { margin-bottom: 16px; \1}', forms_content)

# form control updates (inputs)
forms_content = re.sub(r'\.form-control\s*\{[^}]*?padding:[^;]*;[^}]*?border-radius:[^;]*;([^}]*?)\}', r'.form-control { width: 100%; padding: 0.75rem 1.25rem; height: 50px; font-size: var(--body-size); font-family: var(--font-family); color: var(--text-primary); background-color: var(--surface); border: 1px solid var(--border); border-radius: 12px; \1}', forms_content)
forms_content = re.sub(r'textarea\.form-control\s*\{[^}]*?\}', r'textarea.form-control { resize: vertical; min-height: 120px; height: auto; }', forms_content)

# focus ring
forms_content = re.sub(r'\.form-control:focus\s*\{[^}]*?box-shadow:[^;]*;([^}]*?)\}', r'.form-control:focus { border-color: var(--success); box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25), var(--shadow-sm); \1}', forms_content)

# Add .form-section if not exists
if '.form-section' not in forms_content:
    forms_content += "\n/* Form Sections */\n.form-section { margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border); }\n.form-section:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }\n.form-section-title { font-size: var(--h6-size); font-weight: 600; color: var(--text-primary); margin-bottom: 16px; }\n"

with open(path_forms, 'w', encoding='utf-8') as f:
    f.write(forms_content)

print("Updated components.css and forms.css")
