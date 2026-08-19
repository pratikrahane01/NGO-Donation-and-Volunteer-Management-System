import re

path = r"C:\xampp\htdocs\NGO-donation-management-system\assets\css\dashboard.css"
with open(path, 'r', encoding='utf-8') as f:
    css = f.read()

# Update .search-bar input padding
css = re.sub(
    r'(padding:\s*0\s+40px\s+0\s+)44px;',
    r'\g<1>56px;',
    css
)

# Update .search-bar i.search-icon
# from:
# left: 16px;
# top: 50%;
# transform: translateY(-50%);
# color: var(--text-tertiary);
# font-size: 1.1rem;

replacement = """    left: 20px;
    top: 50%;
    transform: translateY(-50%);
    color: #94A3B8;
    font-size: 18px;"""
    
css = re.sub(
    r'left:\s*16px;\s*top:\s*50%;\s*transform:\s*translateY\(-50%\);\s*color:\s*var\(--text-tertiary\);\s*font-size:\s*1\.1rem;',
    replacement,
    css
)

# Also ensure placeholder is lighter gray
if "::placeholder" in css:
    css = re.sub(
        r'(\.search-bar input::placeholder\s*\{\s*color:)[^;]+;',
        r'\1 #94A3B8;',
        css
    )

with open(path, 'w', encoding='utf-8') as f:
    f.write(css)

print("Updated search bar alignment.")
