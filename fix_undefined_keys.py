import os
import re
import glob

def fix_php_files(directory):
    php_files = glob.glob(os.path.join(directory, '**', '*.php'), recursive=True)
    count_echo = 0
    count_html = 0
    count_ternary = 0

    # Pattern 1: <?php echo $var['key']; ?> -> <?php echo $var['key'] ?? ''; ?>
    # We allow optional whitespace and require the semicolon
    pattern_echo = re.compile(r'<\?php\s+echo\s+(\$[a-zA-Z0-9_]+\[[\'"][a-zA-Z0-9_]+[\'"]\])\s*;\s*\?>')
    
    # Pattern 2: htmlspecialchars($var['key']) -> htmlspecialchars($var['key'] ?? '')
    pattern_html = re.compile(r'htmlspecialchars\(\s*(\$[a-zA-Z0-9_]+\[[\'"][a-zA-Z0-9_]+[\'"]\])\s*\)')

    # Pattern 3: <?= $var['key'] ?> -> <?= $var['key'] ?? '' ?>
    pattern_short_echo = re.compile(r'<\?=\s*(\$[a-zA-Z0-9_]+\[[\'"][a-zA-Z0-9_]+[\'"]\])\s*\?>')

    # Pattern 4: Ternary equality check <?php echo $var['key'] == 'val' ? ... -> <?php echo ($var['key'] ?? '') == 'val' ? ...
    # This is slightly more complex, but we can match $var['key'] followed by == or !=
    pattern_ternary = re.compile(r'(?<!\()(\$[a-zA-Z0-9_]+\[[\'"][a-zA-Z0-9_]+[\'"]\])\s*(==|!=|===|!==)\s*')

    for filepath in php_files:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
            
        original_content = content
        
        # Replace Pattern 1
        content, n1 = pattern_echo.subn(r'<?php echo \1 ?? \'\'; ?>', content)
        count_echo += n1

        # Replace Pattern 2
        content, n2 = pattern_html.subn(r'htmlspecialchars(\1 ?? \'\')', content)
        count_html += n2

        # Replace Pattern 3
        content, n3 = pattern_short_echo.subn(r'<?= \1 ?? \'\' ?>', content)
        count_echo += n3

        # Replace Pattern 4 (Only doing it for equality checks in views where it's safe)
        content, n4 = pattern_ternary.subn(r'(\1 ?? \'\') \2 ', content)
        count_ternary += n4

        if content != original_content:
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(content)

    print(f"Fixed {count_echo} direct echos.")
    print(f"Fixed {count_html} htmlspecialchars calls.")
    print(f"Fixed {count_ternary} ternary comparisons.")

if __name__ == "__main__":
    fix_php_files(r"C:\xampp\htdocs\NGO-donation-management-system")
