import os

filepath = 'resources/views/layouts/app.blade.php'
with open(filepath, 'r') as f:
    original = f.read()

# Halve the durations
original = original.replace('duration-300', 'duration-150')
original = original.replace('duration-200', 'duration-100')
original = original.replace('duration-150', 'duration-75')

with open(filepath, 'w') as f:
    f.write(original)

print("Done replacing durations.")
