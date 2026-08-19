---
name: Auto Deploy
description: Automatically run the upload_and_clear.py script when the user asks to deploy
---

# Deploy Workflow

When the user asks you to "deploy", "run deploy", or "push to production":
1. Automatically run `python3 upload_and_clear.py` in the terminal using the `run_command` tool.
2. Wait for the command to finish.
3. Confirm with the user that the deployment has been completed successfully.
