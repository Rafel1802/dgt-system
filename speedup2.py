import os

filepath = 'resources/views/dashboard/index.blade.php'
if os.path.exists(filepath):
    with open(filepath, 'r') as f:
        original = f.read()

    original = original.replace('duration-300', 'duration-150')
    original = original.replace('duration-200', 'duration-100')
    original = original.replace('duration-150', 'duration-75')

    with open(filepath, 'w') as f:
        f.write(original)

print("Done replacing in dashboard.")
