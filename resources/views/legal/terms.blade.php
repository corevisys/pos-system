<x-public-layout page-key="terms">
    <div class="py-12 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
        <!-- Page Header -->
        <div class="mb-8 text-center sm:text-left border-b border-slate-200 pb-6">
            <h1 class="text-3xl font-black tracking-tight text-slate-900 mb-2">Terms of Service & Licensing</h1>
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Effective Date: {{ date('F j, Y') }} · Version 2.0</p>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-3xl p-8 sm:p-12 shadow-sm">
            <div class="prose prose-slate max-w-none prose-headings:font-black prose-headings:tracking-tight prose-h2:text-xl prose-h3:text-base prose-p:text-slate-600 prose-p:leading-relaxed prose-li:text-slate-600">
                
                <p class="text-lg font-medium text-slate-700 leading-relaxed mb-6">
                    These Terms of Service ("Terms") structure the legal agreement between you ("Licensee", "Merchant", "User") and <strong>CorevisysPOS Intel</strong> ("Company", "we", "our") governing your installation, access, and operation of our Point of Sale software, multi-warehouse inventory systems, and administrative interfaces (collectively, the "System").
                </p>

                <h2>1. Software License & Operational Rights</h2>
                <p>Subject to adherence to these Terms and active subscription standing, CorevisysPOS grants you a non-exclusive, non-transferable license to deploy and operate the software for your retail, wholesale, and multi-branch commercial operations. Unauthorized reverse engineering, distribution, or unauthorized multi-tenant resale of the core source code is prohibited.</p>

                <h2>2. User Responsibilities & Account Security</h2>
                <ul>
                    <li>You are responsible for maintaining the confidentiality of administrative credentials, API secrets, and terminal pin codes.</li>
                    <li>You agree to implement suitable internal access controls, assigning cashier roles to operational staff while reserving administrative permissions for owners.</li>
                    <li>All financial transactions, customer EMI installment plans, and inventory adjustments logged under your merchant account are the sole operational responsibility of your business.</li>
                </ul>

                <h2>3. Database Safeguards & Regular Backups</h2>
                <p>While CorevisysPOS incorporates high-availability server architecture and automated database backup routines, merchants are advised to routinely perform manual off-site database exports via the administrative Backup Settings module.</p>

                <h2>4. Service Availability & Support SLA</h2>
                <p>We strive to maintain a 99.9% platform availability rate. Regular system maintenance and feature upgrades will be scheduled with advance notice. Technical assistance, hardware barcode integration support, and database migration services are provided in accordance with your support tier.</p>

                <h2>5. Limitation of Liability</h2>
                <p>To the maximum extent permitted by applicable law, CorevisysPOS shall not be liable for indirect, incidental, or consequential damages resulting from network outages, third-party SMS provider latency, or local hardware malfunctions.</p>

                <h2>6. Inquiries & Legal Contact</h2>
                <p>For questions or formal correspondence regarding these Terms of Service, contact our legal desk at:</p>
                <p>
                    <strong>CorevisysPOS Legal Department</strong><br>
                    Email: <a href="mailto:legal@corevisys.com" class="text-blue-600 font-bold hover:underline">legal@corevisys.com</a><br>
                    Website: <a href="{{ url('/') }}" class="text-blue-600 font-bold hover:underline">{{ url('/') }}</a>
                </p>
            </div>
        </div>
    </div>
</x-public-layout>
