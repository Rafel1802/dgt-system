@extends('layouts.public')

@section('title', 'Terms of Service — KIUQ SYSTEM')
@section('meta_description', 'Terms of Service for KIUQ SYSTEM by KIUQ.COM governing platform usage, account responsibilities, and intellectual property.')

@section('content')
<div class="py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="mb-12 text-center md:text-left">
            <span class="badge-pill mb-3">Legal &amp; Governance</span>
            <h1 class="text-3xl sm:text-5xl font-extrabold text-white tracking-tight mb-4">
                Application Terms of Service
            </h1>
            <p class="text-sm text-slate-400">
                Effective Date: September 25, 2026 &bull; Published by <strong class="text-white">KIUQ.COM</strong> for <strong class="text-cyan-300">KIUQ SYSTEM</strong>
            </p>
        </div>

        <!-- Document Card -->
        <div class="glass-panel p-8 sm:p-12 legal-content">
            
            <section class="mb-8">
                <h2>1. Acceptance of Terms</h2>
                <p>
                    These Terms of Service ("Terms") govern your access to and use of <strong>KIUQ SYSTEM</strong>, an enterprise collaboration, digital workflow, and Kanban management platform operated by <strong>KIUQ.COM</strong> ("we", "us", or "our").
                </p>
                <p>
                    By accessing, signing into, or using KIUQ SYSTEM, you ("User" or "Authorized Member") agree to be bound by these Terms and our accompanying <a href="{{ route('privacy-policy') }}" class="text-cyan-400 hover:underline">Privacy Policy</a>. If you do not agree to these Terms, you may not access or use the Service.
                </p>
            </section>

            <section class="mb-8">
                <h2>2. Authorized Accounts &amp; Access Controls</h2>
                <ul>
                    <li><strong>Account Security:</strong> You are responsible for safeguarding your login credentials. Authorized users accessing the platform via Google OAuth Single Sign-On must maintain the security of their associated Google Workspace account.</li>
                    <li><strong>Account Authenticity:</strong> You agree to provide accurate, current, and complete information during registration and profile creation. Impersonating another person or entity is strictly prohibited.</li>
                    <li><strong>Enterprise Role Levels:</strong> Access to specific workspaces, boards, and administrative functions is granted according to designated permission tiers (Super Admin, QC, Team Lead, Member). You may not attempt to circumvent role boundaries or exploit system vulnerabilities.</li>
                </ul>
            </section>

            <section class="mb-8">
                <h2>3. Acceptable Use Policy</h2>
                <p>When using KIUQ SYSTEM, you agree not to:</p>
                <ul>
                    <li>Violate any applicable local, national, or international law or regulation.</li>
                    <li>Upload, distribute, or link to malicious software, trojans, viruses, or harmful code.</li>
                    <li>Attempt to gain unauthorized access to any system component, database, or server.</li>
                    <li>Perform automated data scraping, aggressive crawling, or denial-of-service attacks against our infrastructure.</li>
                    <li>Store or transmit unlawful, harassing, defamatory, or infringing content.</li>
                </ul>
            </section>

            <section class="mb-8">
                <h2>4. Intellectual Property Rights</h2>
                <p>
                    All rights, titles, and interests in and to <strong>KIUQ SYSTEM</strong>, including its software architecture, user interface designs, logos, graphics, visual assets, trademarks, and documentation, are the exclusive property of <strong>KIUQ.COM</strong>.
                </p>
                <p>
                    Users retain ownership of the project content, text, and files they legitimately upload to the platform. By utilizing the platform, you grant KIUQ.COM a limited, non-exclusive license to host, display, and process your content solely to deliver the platform services.
                </p>
            </section>

            <section class="mb-8">
                <h2>5. Third-Party Integrations &amp; Google Services</h2>
                <p>
                    KIUQ SYSTEM provides authentication capabilities using <strong>Google OAuth 2.0</strong>. Your use of Google services in conjunction with KIUQ SYSTEM is subject to Google's applicable Terms of Service and Privacy Policies. KIUQ.COM is not responsible for the availability, uptime, or policies of third-party authentication providers.
                </p>
            </section>

            <section class="mb-8">
                <h2>6. Disclaimer of Warranties</h2>
                <p>
                    KIUQ SYSTEM is provided on an "as is" and "as available" basis without warranties of any kind, either express or implied, including but not limited to implied warranties of merchantability, fitness for a particular purpose, and non-infringement.
                </p>
            </section>

            <section class="mb-8">
                <h2>7. Limitation of Liability</h2>
                <p>
                    To the maximum extent permitted by applicable law, in no event shall KIUQ.COM, its officers, directors, or employees be liable for any indirect, incidental, special, consequential, or punitive damages, including loss of profits, data, goodwill, or business interruption arising out of or in connection with your use of KIUQ SYSTEM.
                </p>
            </section>

            <section class="mb-8">
                <h2>8. Modifications to Terms</h2>
                <p>
                    We reserve the right to revise or modify these Terms at any time. Notice of substantial revisions will be provided through our platform or by updating the "Effective Date" at the top of this document. Continued use of KIUQ SYSTEM following any updates constitutes acceptance of the modified Terms.
                </p>
            </section>

            <section class="mb-8">
                <h2>9. Governing Law &amp; Contact</h2>
                <p>
                    These Terms shall be governed and interpreted in accordance with applicable laws. If you have any inquiries regarding these Terms of Service, please contact us:
                </p>
                <div class="mt-4 p-5 rounded-xl bg-slate-900/60 border border-slate-800 text-sm">
                    <p class="font-bold text-white mb-1">KIUQ.COM &bull; KIUQ SYSTEM Legal Department</p>
                    <p class="text-slate-300">Email: <a href="mailto:support@kiuq.com" class="text-cyan-400 hover:underline">support@kiuq.com</a> / <a href="mailto:legal@kiuq.com" class="text-cyan-400 hover:underline">legal@kiuq.com</a></p>
                    <p class="text-slate-300">Official Brand: <a href="https://kiuq.com" class="text-cyan-400 hover:underline">KIUQ.COM</a></p>
                </div>
            </section>

        </div>

        <!-- Back to Home Button -->
        <div class="mt-8 text-center">
            <a href="{{ route('home') }}" class="btn-secondary-glass">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Return to Home Page</span>
            </a>
        </div>

    </div>
</div>
@endsection
