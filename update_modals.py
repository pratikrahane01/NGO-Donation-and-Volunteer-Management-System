import os
import glob
import re

php_files = glob.glob('C:/xampp/htdocs/NGO-donation-management-system/*.php')

for file in php_files:
    with open(file, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()
    
    original_content = content
    
    # 1. Strip style="max-width..." from <div class="modal">
    content = re.sub(r'<div class="modal"\s+style="max-width:\s*\d+px;?">', '<div class="modal">', content)
    
    # 2. Update Cancel button to secondary, Save to primary
    # Example: <button type="button" class="btn btn-secondary" data-modal-close="true">Cancel</button>
    content = re.sub(r'<button\s+[^>]*?data-modal-close="true"[^>]*?>Cancel</button>', r'<button type="button" class="btn btn-secondary" data-modal-close="true">Cancel</button>', content)
    content = re.sub(r'<button\s+type="submit"\s+class="btn[^"]*?"[^>]*?>(Save.*?|Update.*?|Create.*?)</button>', r'<button type="submit" class="btn btn-primary">\1</button>', content)
    
    if content != original_content:
        with open(file, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Updated {os.path.basename(file)}")

print("Global modal updates complete.")
