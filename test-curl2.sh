#!/bin/bash
curl -s -i -X POST http://localhost/dgt-system/public/websites/follow-ups \
     -H "Accept: application/json" \
     -H "X-Requested-With: XMLHttpRequest" \
     -d "website_id=1" \
     -d "type=blog_post" \
     -d "url=https://usaforklift.org/why-the-right-forklift-capacity-make:" \
     -d "created_at=18/08/2026"
