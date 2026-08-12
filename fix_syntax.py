import re

with open('resources/views/websites/index.blade.php', 'r') as f:
    content = f.read()

# We have duplicated closing braces:
#    });
#                }
#            }
#        }
#    });

# Let's fix the realtime listener by replacing the broken block with the correct one.
# First, let's find the broken block:
bad_block = """                if (typeof window.Turbo.refresh === 'function') {
                    window.Turbo.refresh();
                } else {
                    window.Turbo.visit(window.location.href, { action: "replace" });
                }
            }
        }
    });
                }
            }
        }
    });"""

good_block = """                if (typeof window.Turbo.refresh === 'function') {
                    window.Turbo.refresh();
                } else {
                    window.Turbo.visit(window.location.href, { action: "replace" });
                }
            }
        }
    });"""

if bad_block in content:
    content = content.replace(bad_block, good_block)
else:
    # If exact string didn't match, let's use a regex to clean up any extra closing braces before function websitesApp()
    content = re.sub(r'\}\);\s*\}\s*\}\s*\}\s*\}\);\s*function websitesApp\(\)', r'});\n\nfunction websitesApp()', content)

with open('resources/views/websites/index.blade.php', 'w') as f:
    f.write(content)

print("Fixed syntax error")
