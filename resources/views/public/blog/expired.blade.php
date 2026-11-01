<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Link Expired • DGT Media Studio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#0b0f17] text-slate-100 font-sans min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-slate-900/90 border border-slate-800 rounded-3xl p-8 shadow-2xl text-center backdrop-blur-xl relative overflow-hidden">
        <!-- Glow accent -->
        <div class="absolute -top-24 -left-24 w-48 h-48 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -right-24 w-48 h-48 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 space-y-5">
            <!-- Icon -->
            <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center text-3xl shadow-inner">
                ⏳
            </div>

            <div class="space-y-2">
                <span class="inline-block px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider bg-amber-500/15 text-amber-300 border border-amber-500/30">
                    10-Minute Limit Reached
                </span>
                <h1 class="text-2xl font-black text-white tracking-tight">
                    Preview Link Expired
                </h1>
                <p class="text-slate-400 text-sm leading-relaxed">
                    This staging preview link was valid for <strong class="text-slate-200">10 minutes</strong> and has expired for security. Please generate a fresh preview link from the CMS Studio editor.
                </p>
            </div>

            <div class="pt-3 border-t border-slate-800/80 flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="{{ route('websites.posts.index') }}"
                   class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m15 18-6-6 6-6"/></svg>
                    <span>Back to CMS Studio</span>
                </a>
                <button type="button" onclick="window.close()"
                        class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-slate-700 hover:bg-slate-800 text-slate-300 text-xs font-bold transition">
                    Close Tab
                </button>
            </div>
        </div>
    </div>
</body>
</html>
