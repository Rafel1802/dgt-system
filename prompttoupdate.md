Prompt 1: To Update the External System ([https://kiuq.kiuq.net/](https://kiuq.kiuq.net/))
This prompt instructs the external site to prepare and structured-deliver all raw data grouped by specific website profiles so your Laravel system can build the aggregated "All Websites" dashboard.

“Act as a Senior Backend Developer. We need to upgrade the data delivery system on kiuq.kiuq.net to support a comprehensive "All Websites" management hub on our main Laravel system. 

Please provide the PHP/MySQL database schemas, queries, or secure REST API endpoints that package data explicitly structured by website domain/profile.

Data Requirements grouped by 'Website':
1. Website Profiles: Pull all registered brand sites as seen across the system (e.g., electricforklift, Machinery, Mini Excavator, rollercompactor.org, skidsteerloaders.org, wheel loder).
2. Followup Module Cross-Reference (Based on image_067782.jpg, image_067042.jpg, image_067004.jpg):
   - For EACH website, attach its specific 'eBay Click Followup' records (statuses, dates).
   - For EACH website, attach its 'Website Optimization' logs.
   - For EACH website, attach its 'Blog Followup' upload schedules and compliance.
   - For EACH website, attach its 'Plugin Update' weekly completion logs.
3. Operations & Quality Check (Based on image_066fe1.jpg and image_067b05.jpg):
   - Link tasks from the 'Member Board' that are tied to specific websites, along with their statuses (To Do, In Progress, Postpone, Waiting Review, Needs Fix, Completed) and assigned members.
   - Map out the 'Quality Check' board metrics per website, detailing counts for 'New Issue', 'Fixed', and 'Approved'.

Technical Requirements:
- Build a clean JSON API endpoint (e.g., GET /api/v1/websites-summary) that outputs an array of websites containing their respective tasks, followups, and quality check counters.
- Ensure proper indexing on `website_id` or `website_domain` fields to handle fast querying over day, week, month, and custom date ranges.
- Protect this endpoint with secure token-based authentication.”


Prompt 2: To Update Your Central System (Laravel Architecture)
This prompt instructs your local system to build a high-end, visual "All Websites" sub-menu dashboard with interactive metric blocks, filtering, and reporting utilities.

“Act as an Expert Laravel & Frontend Developer. We are updating our central management application to include a premium, highly professional "All Websites" sub-menu under our operations/content navigation tree. This dashboard must aggregate all incoming analytical data from https://kiuq.kiuq.net/ to give the Boss and Supervisors a holistic look at website health and team progress.

Please write the complete code architecture (Routes, Controller, Service Layer, Blade layout, and Eloquent/API mappings) using Tailwind CSS for a stunning UI.

Key Implementation Checklist:

1. Navigation Update & Routing:
   - Create a clean sub-menu item titled "All Websites" in the sidebar navigation layout.
   - Map it to a dedicated controller method: `WebsitesDashboardController@index`.

2. The "All Websites" Executive Grid Layout (Tailwind CSS):
   - Design a clean, modern card matrix layout where each card represents a distinct website (e.g., electricforklift, Machinery, etc.).
   - Header Cards / Global Metrics: Beautiful stats counters displaying global summaries across all sites: Total Open Issues, Overall Completion Rate %, Active Blog Schedules, and Pending Plugin Updates.
   - Individual Website Cards: Each site's card must cleanly display:
     * A Status Matrix (A compact row visualizing To Do -> Completed progress from image_066fe1.jpg).
     * Followup Indicators: Visual checkmarks or glowing status badges for the 4 pillars: eBay Click, Optimization, Blogs, and Plugins.
     * Quality Check Badges: Crisp counter bubbles for 'New Issue' (Red), 'Fixed' (Blue/Orange), and 'Approved' (Green) based on image_067b05.jpg.

3. Live Wireframe Filters (Day, Week, Month, Custom Range, Members):
   - Integrate interactive frontend filter controls:
     * Date Scopes: Quick-toggle buttons for "Today", "This Week", "This Month", and a crisp "Custom Range" calendar picker.
     * Team Member Dropdown: Dropdown to filter the entire "All Websites" view down to tasks managed by a specific member (e.g., "Dara") or view collectively.
   - Ensure the controller handles these parameters elegantly using conditional Laravel query scopes or HTTP collection filtering.

4. Customized Range Export Engine:
   - Provide a Laravel download service/response to export a comprehensive spreadsheet report based on the active website/date filters selected by the boss.
   - The CSV/Excel sheet layout must structure data logically: Website Name | Module | Task/Action Name | Assigned Member | Status | Due Date | Completion/Sync Timestamp.

Deliver exceptional, clean Laravel architecture utilizing modern blade formatting guidelines.”


