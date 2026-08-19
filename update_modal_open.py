import re

app_js_path = r"C:\xampp\htdocs\NGO-donation-management-system\assets\js\app.js"
with open(app_js_path, 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace("document.body.style.overflow = 'hidden';", "document.body.classList.add('modal-open');\n            document.body.style.overflow = 'hidden';")
content = content.replace("document.body.style.overflow = '';", "document.body.classList.remove('modal-open');\n            document.body.style.overflow = '';")

with open(app_js_path, 'w', encoding='utf-8') as f:
    f.write(content)

global_css_path = r"C:\xampp\htdocs\NGO-donation-management-system\assets\css\global.css"
with open(global_css_path, 'a', encoding='utf-8') as f:
    f.write("\n/* Modal Open State */\nbody.modal-open { overflow: hidden !important; }\n")

print("Updated app.js and global.css")
