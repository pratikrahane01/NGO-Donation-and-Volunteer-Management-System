import re
import glob

# 1. Add form-control class to all inputs inside modals
php_files = glob.glob('C:/xampp/htdocs/NGO-donation-management-system/*.php')
for file in php_files:
    with open(file, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()
    
    orig = content
    
    # We only want to modify within <div class="modal">...</div> blocks if possible, 
    # but applying form-control to all form elements generally improves UI.
    
    # Find inputs without class
    content = re.sub(r'<input(?![^>]*class=)(?=[^>]*type="(?:text|email|password|number|date|tel|url)")[^>]*>', lambda m: m.group(0).replace('<input', '<input class="form-control"'), content)
    # Find inputs WITH class but not form-control
    def add_fc(m):
        if 'form-control' not in m.group(1):
            return m.group(0).replace(m.group(1), m.group(1) + ' form-control')
        return m.group(0)
    content = re.sub(r'<input[^>]*class="([^"]*)"(?=[^>]*type="(?:text|email|password|number|date|tel|url)")[^>]*>', add_fc, content)
    
    # Selects
    content = re.sub(r'<select(?![^>]*class=)[^>]*>', lambda m: m.group(0).replace('<select', '<select class="form-control"'), content)
    content = re.sub(r'<select[^>]*class="([^"]*)"[^>]*>', add_fc, content)
    
    # Textareas
    content = re.sub(r'<textarea(?![^>]*class=)[^>]*>', lambda m: m.group(0).replace('<textarea', '<textarea class="form-control"'), content)
    content = re.sub(r'<textarea[^>]*class="([^"]*)"[^>]*>', add_fc, content)
    
    # Remove inline modal-body scroll because CSS handles it
    content = re.sub(r'<div class="modal-body"[^>]*style="[^"]*"[^>]*>', '<div class="modal-body">', content)
    
    if orig != content:
        with open(file, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Added form-control to {file}")

print("Done standardizing inputs.")
