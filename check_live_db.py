import subprocess

PHP = "/opt/alt/php84/usr/bin/php"
tinker_cmd = (
    PHP + ' artisan tinker --execute="'
    "\\$users = \\App\\Models\\User::role(['ebay-team', 'ebay-supervisor', 'super-admin', 'boss', 'admin-crm'])->pluck('id')->toArray(); "
    "\\$since = \\Carbon\\Carbon::now()->startOfMonth(); "
    "\\$until = \\Carbon\\Carbon::now()->endOfMonth(); "
    "\\$records = \\App\\Models\\EbayCustomerRecord::whereBetween('created_at', [\\$since, \\$until])->get(); "
    "echo \\$records->map(function(\\$r) use (\\$users) { "
    "  \\$inEbay = in_array(\\$r->created_by, \\$users) || \\$r->created_by === null || \\$r->created_by === 0 || \\$r->handlerHistory()->whereIn('user_id', \\$users)->exists(); "
    "  return \\$r->id . ' - ' . \\$r->username . ' - created_at: ' . \\$r->created_at . ' - created_by: ' . \\$r->created_by . ' - in_ebay: ' . (\\$inEbay ? 'YES' : 'NO'); "
    "})->implode(PHP_EOL);"
    '"'
)

ssh_cmd = [
    "ssh", "-i", "/Users/phanithlim/.ssh/hostinger_mvillage", "-o", "StrictHostKeyChecking=no", "-p", "65002", "u355625773@157.173.215.124",
    f"cd domains/lightcyan-weasel-711536.hostingersite.com/public_html && {tinker_cmd}"
]

print("Running command on server...")
subprocess.run(ssh_cmd)
