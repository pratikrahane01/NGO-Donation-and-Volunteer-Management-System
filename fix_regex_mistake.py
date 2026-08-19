import os
import glob

def fix_php_files(directory):
    php_files = glob.glob(os.path.join(directory, '**', '*.php'), recursive=True)
    count = 0

    for filepath in php_files:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
            
        original_content = content
        
        content = content.replace("== =", "===")
        content = content.replace("!= =", "!==")
        
        if content != original_content:
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(content)
            count += 1

    print(f"Fixed operators in {count} files.")

if __name__ == "__main__":
    fix_php_files(r"C:\xampp\htdocs\NGO-donation-management-system")
