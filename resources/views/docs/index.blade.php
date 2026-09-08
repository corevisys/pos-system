<x-app-layout title="Help Documentation">
    <div class="w-full">
        <!-- Page header -->
        <div class="sm:flex sm:justify-between sm:items-center mb-6">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-lg font-black tracking-tight text-slate-800 dark:text-slate-100">Help Documentation ✨</h1>
                <p class="text-[9px] text-slate-400 font-medium -mt-0.5 whitespace-nowrap">Comprehensive guides, setup instructions, and operational workflows.</p>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-4">
            
            <!-- Sidebar Navigation -->
            <div class="lg:w-1/4">
                <div class="bg-white dark:bg-dark-card border border-slate-100 dark:border-dark-border rounded-2xl p-2 sticky top-20 max-h-[calc(100vh-120px)] overflow-y-auto">
                    <div class="px-2 pb-2 mb-2 border-b border-slate-50 dark:border-dark-border">
                        <h2 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest leading-tight">Modules & Guides</h2>
                    </div>
                    <ul class="space-y-0.5">
                        @foreach ($docs as $doc)
                            <li>
                                <a href="{{ route('docs.index', ['file' => $doc['filename']]) }}" 
                                   class="block px-3 py-2 rounded-xl text-[12px] font-bold transition-all duration-200 {{ $activeDocFilename === $doc['filename'] ? 'bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-400' : 'text-slate-500 hover:bg-slate-50 hover:text-primary-600 dark:text-slate-400 dark:hover:bg-slate-800' }}">
                                    {{ $doc['title'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <!-- Content Area -->
            <div class="lg:w-3/4">
                <div class="bg-white dark:bg-dark-card border border-slate-100 dark:border-dark-border rounded-2xl p-6 min-h-screen relative overflow-hidden">
                    <div class="prose prose-sm prose-slate dark:prose-invert max-w-none prose-headings:font-black prose-h1:text-lg prose-h2:text-base prose-h3:text-sm prose-p:text-[13px] prose-li:text-[13px] prose-a:text-primary-600 hover:prose-a:text-primary-500 prose-img:rounded-xl prose-img:shadow-sm">
                        @if($activeDoc)
                            {!! $activeDoc['content'] !!}
                        @else
                            <div class="text-center py-20 text-slate-500">
                                <svg class="mx-auto h-12 w-12 text-slate-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                                <h3>No Documentation Found</h3>
                                <p>Ensure that valid Markdown files are placed in the <code>/docs/</code> root folder.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
