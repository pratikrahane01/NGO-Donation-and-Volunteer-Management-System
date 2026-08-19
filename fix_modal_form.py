import re

path_comp = r"C:\xampp\htdocs\NGO-donation-management-system\assets\css\components.css"
with open(path_comp, 'r', encoding='utf-8') as f:
    comp_content = f.read()

# Add overflow: hidden to .modal
comp_content = re.sub(
    r'(\.modal\s*\{[^}]*?)(transform:[^;]+;)', 
    r'\1overflow: hidden; \2', 
    comp_content
)

# Remove duplicate .modal form definitions
comp_content = re.sub(r'\.modal form\s*\{[^}]*?\}\s*', '', comp_content)

# Add it exactly once
css_addition = "\n.modal form { display: flex; flex-direction: column; flex: 1; overflow: hidden; margin: 0; min-height: 0; }\n"
comp_content = re.sub(r'(\.modal\s*\{[^}]*?\})', r'\1' + css_addition, comp_content)

with open(path_comp, 'w', encoding='utf-8') as f:
    f.write(comp_content)
