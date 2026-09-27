@extends('layouts.public')

@section('title', 'Privacy Policy — KIUQ SYSTEM')
@section('meta_description', 'Privacy Policy for KIUQ SYSTEM by KIUQ.COM, detailing Google OAuth user data compliance, data storage, and user privacy rights.')

@section('content')
<div class="py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="mb-12 text-center md:text-left">
            <span class="badge-pill mb-3">Legal &amp; Compliance</span>
            <h1 class="text-3xl sm:text-5xl font-extrabold text-white tracking-tight mb-4">
                Application Privacy Policy
            </h1>
            <p class="text-sm text-slate-400">
                Effective Date: September 25, 2026 &bull; Published by <strong class="text-white">KIUQ.COM</strong> for <strong class="text-cyan-300">KIUQ SYSTEM</strong>
            </p>
        </div>

        <!-- Document Card -->
        <div class="glass-panel p-8 sm:p-12 legal-content">
            
            <section class="mb-8">
                <h2>1. Introduction</h2>
                <p>
                    This Privacy Policy applies to the <strong>KIUQ SYSTEM</strong> platform ("Service", "Application", or "System"), developed and operated by <strong>KIUQ.COM</strong> ("we", "us", or "our"). We respect your privacy and are committed to protecting the personal data of our users, authorized team members, and enterprise partners.
                </p>
                <p>
                    This document explains our practices regarding the collection, use, disclosure, storage, and protection of information obtained through your use of KIUQ SYSTEM, including data accessed via Google OAuth 2.0 Single Sign-On (SSO).
                </p>
            </section>

            <section class="mb-8">
                <h2>2. Information We Collect</h2>
                <p>We collect information strictly necessary to provide, secure, and maintain our digital workflow services:</p>
                
                <h3>A. Account and Profile Information</h3>
                <ul>
                    <li>Full Name and Display Name</li>
                    <li>Official Email Address (e.g., authorized corporate Google accounts)</li>
                    <li>Profile Avatar or Photo</li>
                    <li>Assigned Roles and Permissions (Super Admin, QC, Team Lead, Member)</li>
                </ul>

                <h3>B. Google OAuth 2.0 User Data</h3>
                <p>
                    When you authenticate using <strong>Google Sign-In</strong>, KIUQ SYSTEM requests access only to standard, non-sensitive identity scopes:
                </p>
                <ul>
                    <li><code>openid</code>: To securely authenticate your identity using OpenID Connect standards.</li>
                    <li><code>https://www.googleapis.com/auth/userinfo.email</code>: To identify your authorized account and associate actions with your email.</li>
                    <li><code>https://www.googleapis.com/auth/userinfo.profile</code>: To retrieve your basic public name and profile picture for display on workspace Kanban boards and comment threads.</li>
                </ul>
                <p class="text-sm text-cyan-200/90 bg-cyan-950/40 p-4 rounded-xl border border-cyan-500/20">
                    <strong>Notice Regarding Sensitive Scopes:</strong> KIUQ SYSTEM does <em>not</em> request or access restricted Google APIs, such as Google Drive files, Gmail messages, contacts, calendar entries, or device location.
                </p>

                <h3>C. System Usage &amp; Security Logs</h3>
                <ul>
                    <li>IP address, device user-agent, and browser specifications (used solely for security auditing, rate limiting, and brute-force protection).</li>
                    <li>Audit timestamps for card creations, comments, file attachments, and status updates.</li>
                </ul>
            </section>

            <section class="mb-8">
                <h2>3. How We Use Your Information</h2>
                <p>Information collected by KIUQ SYSTEM is used solely for the following legitimate business purposes:</p>
                <ul>
                    <li>Authenticating your identity and authorizing access to specific boards, workflows, and tools.</li>
                    <li>Displaying team member identity on collaborative tasks, cards, and activity logs.</li>
                    <li>Sending critical account-related security alerts or platform notifications.</li>
                    <li>Protecting against malicious activity, unauthorized login attempts, and denial-of-service attacks.</li>
                    <li>Troubleshooting system performance and ensuring high service availability.</li>
                </ul>
            </section>

            <section class="mb-8">
                <h2>4. Google API Services User Data Policy Compliance</h2>
                <p>
                    KIUQ SYSTEM's use and transfer to any other app of information received from Google APIs adheres to the 
                    <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener noreferrer" class="text-cyan-400 hover:underline font-semibold">Google API Services User Data Policy</a>, including the Limited Use requirements:
                </p>
                <ul>
                    <li><strong>No Sale of Personal Data:</strong> We will never sell, rent, monetize, or trade Google user data to any third party.</li>
                    <li><strong>No Advertising:</strong> We do not use Google user data to serve advertisements, personalized marketing, or retargeting campaigns.</li>
                    <li><strong>No Unauthorized Third-Party Transfer:</strong> We do not share Google user data with external third parties, except as required by law or to secure infrastructure providers who are bound by confidentiality agreements.</li>
                    <li><strong>Human Review Restrictions:</strong> No human will read your raw Google authentication data unless necessary to investigate security breaches, troubleshoot technical incidents with your explicit consent, or comply with applicable legal obligations.</li>
                </ul>
            </section>

            <section class="mb-8">
                <h2>5. Data Storage, Retention, and Security</h2>
                <p>
                    We implement modern industry-standard security measures to safeguard user data:
                </p>
                <ul>
                    <li><strong>Encryption in Transit:</strong> All communications between your client device and our servers are encrypted using TLS 1.3 encryption (HTTPS).</li>
                    <li><strong>Secure Session Management:</strong> Authentication tokens are securely hashed and stored with rigorous access protection and automatic expiration.</li>
                    <li><strong>Access Controls:</strong> System database access is strictly restricted to authorized system administrators under role-based access management.</li>
                    <li><strong>Data Retention:</strong> We retain account data only as long as you maintain an active profile within the KIUQ SYSTEM. Upon account deactivation or termination, personal identifiers are purged or anonymized in accordance with our data retention schedule.</li>
                </ul>
            </section>

            <section class="mb-8">
                <h2>6. User Rights and Data Deletion</h2>
                <p>Depending on your jurisdiction, you have the right to:</p>
                <ul>
                    <li>Access the personal information we hold about you.</li>
                    <li>Request correction of inaccurate or incomplete information.</li>
                    <li>Request the deletion or purging of your account and associated personal data.</li>
                    <li>Revoke Google OAuth permissions at any time via your 
                        <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener noreferrer" class="text-cyan-400 hover:underline">Google Account Permissions Settings</a>.
                    </li>
                </ul>
                <p>
                    To request account deletion or data removal, please contact our privacy compliance team at <a href="mailto:privacy@kiuq.com" class="text-cyan-400 hover:underline">privacy@kiuq.com</a> or <a href="mailto:support@kiuq.com" class="text-cyan-400 hover:underline">support@kiuq.com</a>. We will process verified requests within 30 calendar days.
                </p>
            </section>

            <section class="mb-8">
                <h2>7. Contact Information</h2>
                <p>If you have any questions, concerns, or inquiries regarding this Privacy Policy or our data handling practices, please contact us at:</p>
                <div class="mt-4 p-5 rounded-xl bg-slate-900/60 border border-slate-800 text-sm">
                    <p class="font-bold text-white mb-1">KIUQ.COM &bull; KIUQ SYSTEM Privacy Team</p>
                    <p class="text-slate-300">Email: <a href="mailto:privacy@kiuq.com" class="text-cyan-400 hover:underline">privacy@kiuq.com</a> / <a href="mailto:support@kiuq.com" class="text-cyan-400 hover:underline">support@kiuq.com</a></p>
                    <p class="text-slate-300">Website: <a href="https://kiuq.com" class="text-cyan-400 hover:underline">https://kiuq.com</a></p>
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
