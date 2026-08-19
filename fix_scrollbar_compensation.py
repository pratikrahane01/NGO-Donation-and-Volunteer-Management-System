import os
import re

app_js_path = r"C:\xampp\htdocs\NGO-donation-management-system\assets\js\app.js"
with open(app_js_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Add scrollbar compensation to open()
open_replacement = """    open(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            
            // Prevent layout shift by compensating for missing scrollbar
            const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
            if (scrollbarWidth > 0 && document.body.style.overflow !== 'hidden') {
                document.body.style.paddingRight = `${scrollbarWidth}px`;
                // Also pad sticky header if necessary
                const header = document.querySelector('.header');
                if (header) header.style.paddingRight = `calc(1.5rem + ${scrollbarWidth}px)`;
            }
            
            document.body.style.overflow = 'hidden';
            setTimeout(() => this.trapFocus(modal), 100);
        }
    },"""

# Add scrollbar compensation removal to close() and other places where overflow is restored
def replace_overflow_restore(match):
    return """document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                    const header = document.querySelector('.header');
                    if (header) header.style.paddingRight = '';"""

content = re.sub(r'open\([^)]+\)\s*\{[^}]*?trapFocus[^}]*?\}', open_replacement, content, count=1)
content = re.sub(r"document\.body\.style\.overflow\s*=\s*'';", replace_overflow_restore, content)

with open(app_js_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated app.js for scrollbar compensation.")
