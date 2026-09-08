<x-public-layout page-key="privacy">
    <div class="py-12 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
        <!-- Page Header -->
        <div class="mb-8 text-center sm:text-left border-b border-slate-200 pb-6">
            <h1 class="text-3xl font-black tracking-tight text-slate-900 mb-2">Privacy Policy & Data Protection</h1>
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Effective Date: {{ date('F j, Y') }} · Version 2.0</p>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-3xl p-8 sm:p-12 shadow-sm">
            <div class="prose prose-slate max-w-none prose-headings:font-black prose-headings:tracking-tight prose-h2:text-xl prose-h3:text-base prose-p:text-slate-600 prose-p:leading-relaxed prose-li:text-slate-600">
                
                <p class="text-lg font-medium text-slate-700 leading-relaxed mb-6">
                    At <strong>CorevisysPOS Intel</strong>, we respect your privacy and are committed to protecting the confidential business records, transactional ledgers, customer directories, and personal information you entrust to us. This Privacy Policy explains how our cloud Point of Sale (POS) and inventory platform collects, manages, and safeguards your operational data.
                </p>

                <h2>1. Information We Collect</h2>
                <p>When you register for or operate the CorevisysPOS platform, we collect operational data necessary to perform enterprise billing and inventory tracking:</p>
                <ul>
                    <li><strong>Account & Merchant Credentials:</strong> Store name, registered business email, primary mobile number, store location, and administrator login credentials.</li>
                    <li><strong>Transactional & Business Records:</strong> Sales invoices, purchase orders, customer names, contact numbers, serialized inventory numbers, supplier ledgers, and expense accounts generated within your store.</li>
                    <li><strong>System & Device Telemetry:</strong> Browser client type, IP address, device fingerprints, and access timestamps recorded for security auditing and login anomaly detection.</li>
                </ul>

                <h2>2. How We Utilize Merchant Information</h2>
                <p>All data processed through CorevisysPOS is used strictly to deliver and maintain retail software operations, specifically to:</p>
                <ul>
                    <li>Process real-time point-of-sale transactions, invoice printing, and EMI installment schedules.</li>
                    <li>Synchronize stock counts across multiple warehouse locations and prevent inventory shrinkage.</li>
                    <li>Deliver customer SMS notifications and automated payment reminders via configured SMS gateways.</li>
                    <li>Generate executive accounting summaries, Profit & Loss reports, and tax compliance computations.</li>
                    <li>Maintain automated encrypted database backups for disaster recovery.</li>
                </ul>

                <h2>3. Role-Based Access Control (RBAC) & Internal Security</h2>
                <p>CorevisysPOS enforces a strict Role-Based Access Control matrix. Master account owners possess administrative rights to designate custom user roles (e.g., Cashier, Store Manager, Warehouse Staff) with granular permissions. Sensitive reports, profit margins, and financial records remain restricted to authorized credentials.</p>

                <h2>4. Data Ownership & Portability</h2>
                <p>You retain 100% ownership of your business data. CorevisysPOS will never sell, rent, or commercialize your customer lists, sales revenue, or pricing structures to any third party. Merchants may export their complete data catalog anytime using built-in CSV, Excel, or PDF report generators.</p>

                <h2>5. Contact & Privacy Inquiries</h2>
                <p>If you have questions regarding this Privacy Policy or wish to request data management support, contact our data protection team at:</p>
                <p>
                    <strong>CorevisysPOS Support</strong><br>
                    Email: <a href="mailto:support@corevisys.com" class="text-blue-600 font-bold hover:underline">support@corevisys.com</a><br>
                    Website: <a href="{{ url('/') }}" class="text-blue-600 font-bold hover:underline">{{ url('/') }}</a>
                </p>
            </div>
        </div>
    </div>
</x-public-layout>
