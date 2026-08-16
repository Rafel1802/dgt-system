import re

with open('resources/views/websites/index.blade.php', 'r', encoding='utf-8') as f:
    content = f.read()

start = content.find('function websitesApp() {')
end = content.find('</script>', start)
js = content[start:end]

# Replace {{ ... }} with "null"
js = re.sub(r'\{\{.*?\}\}', 'null', js, flags=re.DOTALL)
# Replace {!! ... !!} with "null"
js = re.sub(r'\{!!.*?!!\}', 'null', js, flags=re.DOTALL)
# Replace @json(...) with "null" (just a lazy hack for nested parentheses)
js = re.sub(r'@json\(.*?\),', 'null,', js, flags=re.DOTALL)

with open('test_syntax.js', 'w', encoding='utf-8') as f:
    f.write(js)
